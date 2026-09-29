<?php
/**
 * Tests SHOP-07 : remboursements et reclamations.
 *
 * Criteres du plan : un remboursement retire l'acces et ajuste le solde
 * artiste ; l'historique d'origine reste intact et consultable ; aucun
 * remboursement n'est possible sans motif saisi.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\shop-remboursements.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';
if (!preg_match('/local|test|dev/', (string) EnvLoader::get('DB_DATABASE', ''))) {
    fwrite(STDERR, "Refus : base non locale.\n");
    exit(1);
}

$ok = 0;
$ko = 0;
function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    $condition ? $ok++ : $ko++;
    echo $condition ? "  OK  {$libelle}\n" : "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
}
function attendre(callable $condition, float $secondes = 10.0): bool
{
    $fin = microtime(true) + $secondes;
    do {
        if ($condition()) {
            return true;
        }
        usleep(200000);
    } while (microtime(true) < $fin);
    return $condition();
}

$db = TchadokDatabase::getInstance()->getConnection();
$php = PHP_BINARY;
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');

$processus = [];
foreach (['airtel' => 9101, 'moov' => 9102, 'visa' => 9103, 'gimac' => 9104] as $dossier => $port) {
    $c = curl_init("http://127.0.0.1:{$port}/sante");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
    if (curl_exec($c) === false) {
        $processus[] = proc_open([$php, '-S', "127.0.0.1:{$port}", "mock-gateways/{$dossier}/index.php"], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t, $racine);
    }
}
$processus[] = proc_open([$php, 'mock-gateways/distributeur.php'], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t, $racine);
$fichierReglages = $racine . '/mock-gateways/storage/reglages.json';
@mkdir(dirname($fichierReglages), 0775, true);
$reglagesAvant = is_file($fichierReglages) ? file_get_contents($fichierReglages) : null;
file_put_contents($fichierReglages, json_encode(['facteur_delai' => 0.2]));

$marque = 'zzremb' . bin2hex(random_bytes(3));
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, 'x', 'Client', 'Remboursement', 1, 1)")
   ->execute([$marque, $marque . '@test.local']);
$acheteur = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Artiste', 'Remb', 1)")
   ->execute([$marque . 'a', $marque . 'a@test.local']);
$userArtiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)")->execute([$userArtiste, 'ZZREMB ' . $marque]);
$artiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, price, status) VALUES (?, 'ZZREMB titre', 'x.mp3', 200, 0, 1000, 'approved')")->execute([$artiste]);
$titre = (int) $db->lastInsertId();
$versements = [];

$commandes = [];
$acheter = static function (string $passerelle = 'airtel_money', string $numero = '66000001') use ($db, $acheteur, $titre, $artiste, &$commandes): array {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $acheteur]);
    $id = (int) $db->lastInsertId();
    Commandes::ajouterArticle($id, 'track', $titre, 1000.0, $artiste, 'ZZREMB titre');
    $commandes[] = $id;
    $ref = (string) $db->query("SELECT reference FROM orders WHERE id = {$id}")->fetchColumn();
    Paiements::initier($ref, $acheteur, $passerelle, $numero);
    attendre(fn() => $db->query("SELECT status FROM orders WHERE id = {$id}")->fetchColumn() === 'paid');
    return [$id, $ref];
};
$etat = static fn (int $id): string => (string) $db->query("SELECT status FROM orders WHERE id = {$id}")->fetchColumn();
$dossierDe = static fn (int $id): array => $db->query("SELECT * FROM refunds WHERE order_id = {$id} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$courriels = static fn (): string => (string) @file_get_contents($racine . '/storage/logs/mail.log');

try {
    echo "\n=== A. Reclamation du client ===\n";
    [$o1, $ref1] = $acheter();
    verif('Achat paye, acces ouvert', $etat($o1) === 'paid' && Commandes::aLeDroit($acheteur, 'track', $titre));
    verif('Motif inconnu refuse', !Remboursements::demander($acheteur, $ref1, 'nimporte', '')['succes']);
    verif('Commande d\'autrui refusee', !Remboursements::demander($userArtiste, $ref1, 'autre', '')['succes']);
    $r = Remboursements::demander($acheteur, $ref1, 'ne_fonctionne_pas', 'Le son grésille.');
    verif('Reclamation enregistree, delai annonce', $r['succes'] && str_contains($r['message'], Remboursements::DELAI_REPONSE), $r['message']);
    verif('... une seule a la fois', !Remboursements::demander($acheteur, $ref1, 'autre', '')['succes']);

    $d = $dossierDe($o1);
    verif('Refus sans motif : impossible', !Remboursements::refuser((int) $d['id'], 'non', null)['succes']);
    $r = Remboursements::refuser((int) $d['id'], 'Fichier verifie, lecture correcte', null);
    verif('Refus motive', $r['succes'] && $dossierDe($o1)['status'] === 'refusee');
    verif('... le client est prevenu, avec le motif', str_contains($courriels(), 'Fichier verifie, lecture correcte'));
    verif('... et l\'acces reste ouvert', Commandes::aLeDroit($acheteur, 'track', $titre));
    verif('Nouvelle reclamation possible apres un refus', Remboursements::demander($acheteur, $ref1, 'autre', 'Toujours un souci')['succes']);

    $contourne = false;
    try {
        $db->exec('UPDATE refunds SET status = \'refusee\', decision_reason = NULL WHERE id = ' . (int) $dossierDe($o1)['id']);
        $contourne = true;
    } catch (Throwable $e) {
    }
    verif('Une decision sans motif est refusee par la base', !$contourne);

    echo "\n=== B. Remboursement par l'operateur ===\n";
    $lignesAvant = $db->query("SELECT id, unit_price, commission, artist_net FROM order_items WHERE order_id = {$o1}")->fetchAll(PDO::FETCH_ASSOC);
    $r = Remboursements::rembourser($o1, 'ok', null);
    verif('Remboursement sans motif : refuse', !$r['succes']);
    $r = Remboursements::rembourser($o1, 'Defaut audio confirme par le support', null);
    verif('Remboursement effectue par l\'operateur', $r['succes'] && $r['statut'] === 'effectue', json_encode($r));
    verif('La reclamation en attente est devenue ce remboursement', (int) $db->query("SELECT COUNT(*) FROM refunds WHERE order_id = {$o1} AND status = 'effectue'")->fetchColumn() === 1);
    verif('Commande remboursee', $etat($o1) === 'refunded');
    verif('Acces retire', !Commandes::aLeDroit($acheteur, 'track', $titre));
    verif('Lignes d\'origine intactes', $db->query("SELECT id, unit_price, commission, artist_net FROM order_items WHERE order_id = {$o1}")->fetchAll(PDO::FETCH_ASSOC) === $lignesAvant);
    $annulation = $db->query("SELECT ri.* FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id WHERE r.order_id = {$o1}")->fetch(PDO::FETCH_ASSOC);
    verif('Ecriture d\'annulation : montant, commission et part artiste reprises',
        (float) $annulation['amount'] === 1000.0 && (float) $annulation['artist_net_reversed'] === (float) $lignesAvant[0]['artist_net']
        && (float) $annulation['commission_reversed'] === (float) $lignesAvant[0]['commission'] && (int) $annulation['artist_id'] === $artiste, json_encode($annulation));
    verif('... pas encore versee a l\'artiste : reprise sur ses revenus a venir', (int) $annulation['after_payout'] === 0);
    verif('Trace au journal d\'audit', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'remboursement.execute' AND target_id = " . $db->quote($ref1))->fetchColumn() === 1);
    verif('Client prevenu', str_contains($courriels(), $ref1 . ' est remboursee'));
    $refOp = (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE order_id = {$o1} AND status = 'succeeded'")->fetchColumn();
    verif('L\'operateur confirme ensuite (callback refund.succeeded recu)',
        attendre(fn() => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE order_id = {$o1} AND event_type = 'refund.succeeded'")->fetchColumn() === 1));
    verif('Second remboursement de la meme commande : refuse', !Remboursements::rembourser($o1, 'Encore une fois', null)['succes']);
    $facture = (string) $db->query("SELECT invoice_number FROM orders WHERE id = {$o1}")->fetchColumn();
    verif('La facture d\'origine reste attachee a la commande', str_starts_with($facture, 'FAC-'));
    Rapprochement::executer('airtel_money', date('Y-m-d'));
    verif('Rapprochement : rembourse des deux cotes, aucun ecart', (int) $db->query('SELECT COUNT(*) FROM reconciliation_discrepancies WHERE resolved_at IS NULL AND gateway_ref = ' . $db->quote($refOp))->fetchColumn() === 0);

    echo "\n=== C. Racheter apres un remboursement ===\n";
    [$o2, $ref2] = $acheter('moov_money', '65000001');
    verif('Le titre rembourse peut etre rachete, et l\'acces revient', $etat($o2) === 'paid' && Commandes::aLeDroit($acheteur, 'track', $titre));

    echo "\n=== D. Remboursement manuel, part deja versee ===\n";
    $db->prepare("INSERT INTO payouts (artist_id, period_start, period_end, gross, net, method, destination, status, paid_at) VALUES (?, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 1 DAY, 850, 850, 'airtel_money', '66000000', 'paid', NOW())")
       ->execute([$artiste]);
    $versements[] = (int) $db->lastInsertId();
    $db->exec("UPDATE payment_intents SET gateway_ref = 'MOV-FFFFFFFFFFFFFFFF' WHERE order_id = {$o2} AND status = 'succeeded'");
    $db->exec("UPDATE orders SET gateway_ref = 'MOV-FFFFFFFFFFFFFFFF' WHERE id = {$o2}");
    $r = Remboursements::rembourser($o2, 'Double achat signale par le client', null);
    verif('Operateur incapable de rembourser : dossier « manuel », suivi', $r['succes'] && $r['statut'] === 'manuel', json_encode($r));
    verif('... l\'acces est retire quand meme', !Commandes::aLeDroit($acheteur, 'track', $titre));
    $apres = (int) $db->query("SELECT ri.after_payout FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id WHERE r.order_id = {$o2}")->fetchColumn();
    verif('Periode deja versee a l\'artiste : reprise marquee pour le prochain versement', $apres === 1);
    $idManuel = (int) $dossierDe($o2)['id'];
    verif('Cloture manuelle sans explication : refusee', !Remboursements::cloturerManuel($idManuel, 'fait', null));
    verif('Cloture manuelle motivee', Remboursements::cloturerManuel($idManuel, 'Renvoye par Moov depuis le compte marchand', null)
        && $dossierDe($o2)['status'] === 'effectue');

    echo "\n=== E. Ecrans ===\n";
    $cookies = (string) tempnam(sys_get_temp_dir(), 'remb');
    $http = static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps];
    };
    [$code] = $http($base . '/admin/remboursements.php');
    verif('Ecran fermé sans session', $code !== 200);
    $db->exec("DELETE FROM login_attempts WHERE identifier = 'admin@tchadok.td'");
    [, $page] = $http($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => 'admin@tchadok.td', 'password' => 'tchadok2026']);
    [$o3, $ref3] = $acheter();
    Remboursements::demander($acheteur, $ref3, 'achat_par_erreur', 'Oups');
    [$code, $page] = $http($base . '/admin/remboursements.php');
    verif('Le super-administrateur voit la reclamation', $code === 200 && str_contains($page, $ref3) && str_contains($page, 'Achat par erreur'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    [$code, $page] = $http($base . '/admin/remboursements.php', ['csrf_token' => $m[1] ?? '', 'action' => 'rembourser', 'commande' => $o3, 'motif' => '']);
    verif('... ne peut rembourser sans motif', str_contains($page, 'Motif obligatoire') && $etat($o3) === 'paid');
    [$code] = $http($base . '/admin/remboursements.php', ['csrf_token' => $m[1] ?? '', 'action' => 'rembourser', 'commande' => $o3, 'motif' => 'Achat par erreur, geste commercial']);
    verif('... et rembourse avec un motif', $etat($o3) === 'refunded');
    [$code, $page] = $http($base . '/admin-dashboard.php');
    verif('Entree « Remboursements » dans la console', str_contains($page, '/admin/remboursements.php'));
    @unlink($cookies);
} finally {
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    if ($commandes) {
        $liste = implode(',', array_map('intval', $commandes));
        $db->exec("DELETE ri FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id WHERE r.order_id IN ({$liste})");
        $db->exec("DELETE FROM refunds WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
        $db->exec("DELETE FROM payment_intents WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    foreach ($versements as $v) {
        $db->exec("DELETE FROM payouts WHERE id = {$v}");
    }
    $db->exec("DELETE FROM tracks WHERE id = {$titre}");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    $db->exec("DELETE FROM users WHERE id IN ({$acheteur}, {$userArtiste})");
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
