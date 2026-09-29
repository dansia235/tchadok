<?php
/**
 * Tests LOT 8 : versements aux artistes (PAYOUT-01 a PAYOUT-05).
 *
 * Criteres du plan :
 *   - le solde se lit sur la part figee a la vente, retention comprise ;
 *     part artiste + commission = prix paye, sur chaque vente ;
 *   - un remboursement apres versement rend le solde negatif, compense
 *     ensuite ; le releve s'additionne exactement au net verse ;
 *   - demander, valider, executer : trois personnes, la base le garantit ;
 *   - un echec se reprend sans jamais payer deux fois (scenarios 11 a 14 du
 *     simulateur, panne de l'operateur) ;
 *   - pas de publication ni de versement sans le contrat en vigueur accepte.
 *
 * Residus assumes : l'artiste de test, ses ajustements et son acceptation du
 * contrat restent en base (journaux immuables) ; l'artiste est desactive et
 * le contrat de test retire en fin de test.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\payout-versements.php
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
function attendre(callable $condition, float $secondes = 12.0): bool
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
foreach (['airtel' => 9101, 'moov' => 9102] as $dossier => $port) {
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
$reglages = static fn (array $r) => file_put_contents($fichierReglages, json_encode($r + ['facteur_delai' => 0.2]));
$reglages([]);

$marque = 'zzvrs' . bin2hex(random_bytes(3));
$motDePasse = 'Vrs-' . bin2hex(random_bytes(6));
$creerUtilisateur = static function (string $suffixe) use ($db, $marque, $motDePasse): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Test', 'Versement', 1, 1)")
       ->execute([$marque . $suffixe, $marque . $suffixe . '@test.local', password_hash($motDePasse, PASSWORD_DEFAULT)]);
    return (int) $db->lastInsertId();
};
$uArtiste = $creerUtilisateur('a');
$uArtisteB = $creerUtilisateur('b');
$uAcheteur = $creerUtilisateur('c');
$finance1 = $creerUtilisateur('f1');
$finance2 = $creerUtilisateur('f2');
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uArtiste, 'ZZVRS ' . $marque]);
$artiste = (int) $db->lastInsertId();
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uArtisteB, 'ZZVRS B ' . $marque]);
$artisteB = (int) $db->lastInsertId();
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, price, status) VALUES (?, 'ZZVRS titre', 'x.mp3', 200, 0, 1000, 'approved')")->execute([$artiste]);
$titre = (int) $db->lastInsertId();
$commandes = [];
$contratTest = null;

// Vente payee il y a $jours jours, part artiste figee par ajouterArticle.
$vendre = static function (float $prix, int $jours) use ($db, $uAcheteur, $titre, $artiste, &$commandes): int {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency, payment_method) VALUES (?, ?, 'cart', 'XAF', 'airtel_money')")
       ->execute([Commandes::reference(), $uAcheteur]);
    $id = (int) $db->lastInsertId();
    Commandes::ajouterArticle($id, 'track', $titre, $prix, $artiste, 'ZZVRS titre');
    $db->exec("UPDATE orders SET status = 'paid', paid_at = NOW() - INTERVAL {$jours} DAY WHERE id = {$id}");
    $commandes[] = $id;
    return $id;
};
$part = static fn (int $orderId): float => (float) $db->query("SELECT SUM(artist_net) FROM order_items WHERE order_id = {$orderId}")->fetchColumn();
$versement = static fn (int $id): array => $db->query("SELECT * FROM payouts WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC) ?: [];
$essais = static fn (int $id): array => $db->query("SELECT * FROM payout_attempts WHERE payout_id = {$id} ORDER BY attempt")->fetchAll(PDO::FETCH_ASSOC);
$compteVerifie = static function (string $numero) use ($artiste, $finance1): void {
    Versements::enregistrerCompte($artiste, 'airtel_money', $numero, 'Test Versement');
    Versements::verifierCompte($artiste, 'Piece d\'identite controlee', $finance1);
};
// Demande, validation par finance1, execution par finance2.
$circuit = static function () use ($artiste, $uArtiste, $finance1, $finance2): array {
    $d = Versements::demander($artiste, $uArtiste);
    $id = (int) ($d['payout_id'] ?? 0);
    if ($id === 0) {
        echo "      (demande refusee : {$d['message']})\n";
    }
    Versements::approuver($id, $finance1);
    return [$id, Versements::executer($id, $finance2), $d];
};

try {
    echo "\n=== A. Solde (PAYOUT-01) ===\n";
    $ancienne = $vendre(20000, 40);
    $recente = $vendre(5000, 5);
    $s = Versements::solde($artiste);
    verif('Part artiste figee a la vente, sur les commandes payees', abs($s['net'] - ($part($ancienne) + $part($recente))) < 0.01, json_encode($s));
    verif('Vente de moins de ' . Versements::retention() . ' jours : en retention', abs($s['en_retention'] - $part($recente)) < 0.01);
    verif('... seule la vente ancienne est disponible', abs($s['disponible'] - $part($ancienne)) < 0.01);
    verif('Brut - commission = part artiste', abs($s['brut'] - $s['commission'] - $s['net']) < 0.01);
    $ecarts = (int) $db->query(
        "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
          WHERE o.status = 'paid' AND ABS(oi.artist_net + oi.commission - oi.unit_price * oi.quantity) > 0.01"
    )->fetchColumn();
    verif('Invariant, sur toute la base : part artiste + commission = prix paye', $ecarts === 0, "{$ecarts} ligne(s)");
    exec(escapeshellarg($php) . ' ' . escapeshellarg($racine . '/scripts/versements.php') . ' controle', $sortie, $code);
    verif('... et le script de controle le confirme', $code === 0, implode(' ', $sortie));

    $db->exec("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = {$recente}");
    verif('Remboursement AVANT versement : la vente quitte le solde', abs(Versements::solde($artiste)['en_retention']) < 0.01);

    echo "\n=== B. Conditions de la demande ===\n";
    $r = Versements::demander($artiste, $uArtiste);
    verif('Sans compte declare : refus explicite', !$r['succes'] && str_contains($r['message'], 'Declarez'), $r['message']);
    verif('Operateur sans versement sortant (Visa) : refuse', !Versements::enregistrerCompte($artiste, 'visa', '66123456', 'Test Versement')['succes']);
    verif('Numero invalide : refuse', !Versements::enregistrerCompte($artiste, 'airtel_money', '12', 'Test Versement')['succes']);
    Versements::enregistrerCompte($artiste, 'airtel_money', '66123456', 'Test Versement');
    $r = Versements::demander($artiste, $uArtiste);
    verif('Compte non verifie : refus', !$r['succes'] && str_contains($r['message'], 'verification'), $r['message']);
    verif('Verification sans explication : refusee', !Versements::verifierCompte($artiste, 'ok', $finance1));
    verif('Verification motivee', Versements::verifierCompte($artiste, 'Piece d\'identite controlee', $finance1));
    Versements::enregistrerCompte($artiste, 'airtel_money', '66123457', 'Test Versement');
    verif('Changement de numero : la verification tombe', Versements::compte($artiste)['verified_at'] === null);
    Versements::verifierCompte($artiste, 'Nouveau numero controle', $finance1);

    $version = 'T' . strtoupper(substr($marque, -6));
    $db->prepare('INSERT INTO distribution_contracts (version, title, body, published_at) VALUES (?, ?, ?, NOW())')
       ->execute([$version, 'Contrat de test', str_repeat('Clause de test. ', 30)]);
    $contratTest = (int) $db->lastInsertId();
    $r = Versements::demander($artiste, $uArtiste);
    verif('Contrat en vigueur non accepte : refus', !$r['succes'] && str_contains($r['message'], 'contrat'), $r['message']);
    $modifie = true;
    try {
        $db->exec("UPDATE distribution_contracts SET body = 'autre texte' WHERE id = {$contratTest}");
    } catch (Throwable $e) {
        $modifie = false;
    }
    verif('Texte d\'un contrat publie : non modifiable (base)', !$modifie);
    verif('Acceptation enregistree', Contrats::accepter($artiste, $contratTest, $uArtiste));
    $preuve = $db->query("SELECT * FROM contract_acceptances WHERE artist_id = {$artiste} AND contract_id = {$contratTest}")->fetch(PDO::FETCH_ASSOC);
    verif('... avec l\'empreinte du texte accepte', $preuve && $preuve['body_sha256'] === hash('sha256', str_repeat('Clause de test. ', 30)));
    verif('... une seule fois', !Contrats::accepter($artiste, $contratTest, $uArtiste));
    $efface = true;
    try {
        $db->exec("DELETE FROM contract_acceptances WHERE id = {$preuve['id']}");
    } catch (Throwable $e) {
        $efface = false;
    }
    verif('Acceptation : ni supprimable ni modifiable (base)', !$efface);

    $vendreB = $db->prepare("INSERT INTO orders (reference, user_id, status, currency, paid_at) VALUES (?, ?, 'cart', 'XAF', NOW() - INTERVAL 40 DAY)");
    $vendreB->execute([Commandes::reference(), $uAcheteur]);
    $oB = (int) $db->lastInsertId();
    $commandes[] = $oB;
    Commandes::ajouterArticle($oB, 'track', $titre, 5000.0, $artisteB, 'ZZVRS B');
    $db->exec("UPDATE orders SET status = 'paid' WHERE id = {$oB}");
    Versements::enregistrerCompte($artisteB, 'moov_money', '65123456', 'Artiste B');
    Versements::verifierCompte($artisteB, 'Piece d\'identite controlee', $finance1);
    Contrats::accepter($artisteB, $contratTest, $uArtisteB);
    $r = Versements::demander($artisteB, $uArtisteB);
    $manque = Versements::seuil() - $part($oB);
    verif('Sous le seuil : refus, avec le montant manquant', !$r['succes'] && str_contains($r['message'], 'il manque ' . number_format($manque, 0, ',', ' ')), $r['message']);

    echo "\n=== C. Ajustements ===\n";
    verif('Ajustement sans motif : refuse', !Versements::ajuster($artiste, 1500, 'ok', $finance1));
    verif('Ajustement nul : refuse', !Versements::ajuster($artiste, 0, 'Avance sur recettes', $finance1));
    $avant = Versements::solde($artiste)['disponible'];
    verif('Avance motivee', Versements::ajuster($artiste, 1500, 'Avance sur recettes (test)', $finance1));
    verif('... ajoutee au disponible', abs(Versements::solde($artiste)['disponible'] - $avant - 1500) < 0.01);
    $modifie = true;
    try {
        $db->exec("UPDATE artist_adjustments SET amount = 99999 WHERE artist_id = {$artiste}");
    } catch (Throwable $e) {
        $modifie = false;
    }
    verif('Ajustement : immuable (base)', !$modifie);

    echo "\n=== D. Separation des pouvoirs (PAYOUT-02) ===\n";
    $d = Versements::demander($artiste, $uArtiste);
    $v1 = (int) $d['payout_id'];
    verif('Demande enregistree', $d['succes'] && $versement($v1)['status'] === 'draft', $d['message']);
    verif('... montant = disponible', abs((float) $versement($v1)['net'] - $part($ancienne) - 1500) < 0.01);
    verif('Seconde demande pendant le traitement : refusee', !Versements::demander($artiste, $uArtiste)['succes']);
    verif('Disponible a zero, montant engage', abs(Versements::solde($artiste)['disponible']) < 0.01 && Versements::solde($artiste)['engage'] > 0);
    verif('Execution avant validation : refusee', !Versements::executer($v1, $finance2)['succes']);
    $r = Versements::approuver($v1, $uArtiste);
    verif('Le demandeur ne valide pas sa propre demande', !$r['succes'] && $versement($v1)['status'] === 'draft', $r['message']);
    verif('Validation par la finance', Versements::approuver($v1, $finance1)['succes']);
    $r = Versements::executer($v1, $finance1);
    verif('Celui qui valide n\'execute pas', !$r['succes'] && $versement($v1)['status'] === 'approved', $r['message']);
    $r = Versements::executer($v1, $uArtiste);
    verif('Le demandeur n\'execute pas', !$r['succes'] && $versement($v1)['status'] === 'approved', $r['message']);
    $contourne = true;
    try {
        $db->exec("UPDATE payouts SET executed_by = approved_by WHERE id = {$v1}");
    } catch (Throwable $e) {
        $contourne = false;
    }
    verif('... et la base refuse meme une ecriture directe', !$contourne);

    echo "\n=== E. Execution et releve (PAYOUT-03) ===\n";
    $r = Versements::executer($v1, $finance2);
    verif('Execution par une troisieme personne : envoyee', $r['succes'], $r['message']);
    verif('Confirmee par l\'operateur (callback)', attendre(fn() => $versement($v1)['status'] === 'paid'), $versement($v1)['status'] ?? '');
    verif('Un seul essai, reussi', count($essais($v1)) === 1 && $essais($v1)[0]['status'] === 'succeeded');
    $s = Versements::solde($artiste);
    verif('Solde apres versement : verse = net, disponible 0', abs($s['verse'] - (float) $versement($v1)['net']) < 0.01 && abs($s['disponible']) < 0.01, json_encode($s));
    $rel = Versements::releve($v1);
    verif('Releve : ventes + ajustements + regularisations = net verse',
        abs($rel['total_ventes'] + $rel['total_ajustements'] + $rel['regularisation'] - (float) $versement($v1)['net']) < 0.01);
    verif('... ventes ligne a ligne, ajustement motive, rien d\'autre', count($rel['ventes']) === 1 && abs($rel['total_ajustements'] - 1500) < 0.01 && abs($rel['regularisation']) < 0.01, json_encode([$rel['total_ventes'], $rel['total_ajustements'], $rel['regularisation']]));
    verif('Artiste prevenu', str_contains((string) @file_get_contents($racine . '/storage/logs/mail.log'), 'a ete envoye sur votre compte mobile money'));

    echo "\n=== F. Remboursement APRES versement ===\n";
    $r = Remboursements::rembourser($ancienne, 'Achat conteste par le client (test)', $finance1);
    verif('Vente deja versee remboursee', $r['succes'], json_encode($r));
    verif('... reprise marquee apres versement', (int) $db->query("SELECT after_payout FROM refund_items WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = {$ancienne})")->fetchColumn() === 1);
    $s = Versements::solde($artiste);
    verif('Solde negatif de la part reprise, rien n\'est reclame', abs($s['disponible'] + $part($ancienne)) < 0.01 && abs($s['reprises'] - $part($ancienne)) < 0.01, json_encode($s));
    $nouvelle = $vendre(30000, 35);
    $s = Versements::solde($artiste);
    verif('... compense sur la vente suivante', abs($s['disponible'] - ($part($nouvelle) - $part($ancienne))) < 0.01, json_encode($s));

    echo "\n=== G. Scenarios de l'operateur (PAYOUT-04) ===\n";
    // Chaque scenario repart d'un disponible suffisant.
    $vendre(30000, 35);
    $compteVerifie('66000011');
    [$v2, $r] = $circuit();
    verif('11 numero invalide : refus immediat, versement en echec', !$r['succes'] && $versement($v2)['status'] === 'failed' && $essais($v2)[0]['failure_code'] === 'invalid_msisdn', json_encode($r));
    verif('... le montant reste engage (pas de nouvelle demande)', !Versements::demander($artiste, $uArtiste)['succes']);
    verif('Refus sans motif : impossible', !Versements::decider($v2, 'rejected', '', $finance1)['succes']);
    verif('Refus motive : le montant redevient disponible', Versements::decider($v2, 'rejected', 'Numero invalide, corriger le compte', $finance1)['succes']
        && Versements::solde($artiste)['disponible'] >= Versements::seuil());

    $compteVerifie('66000012');
    [$v3, $r] = $circuit();
    verif('12 compte inconnu : echec notifie par callback', attendre(fn() => $versement($v3)['status'] === 'failed') && $versement($v3)['failure_reason'] === 'account_not_found', json_encode($versement($v3)));
    Versements::decider($v3, 'rejected', 'Compte inexistant chez l\'operateur', $finance1);

    $vendre(30000, 35);
    $compteVerifie('66000013');
    [$v4, $r] = $circuit();
    verif('13 succes lent : en cours', $r['succes'] && $versement($v4)['status'] === 'processing');
    $r = Versements::executer($v4, $finance2);
    verif('... relancer pendant l\'attente : refuse, pas de second envoi', !$r['succes'] && count($essais($v4)) === 1, $r['message']);
    verif('... puis verse', attendre(fn() => $versement($v4)['status'] === 'paid'));
    verif('Consultation des envois en attente : sans effet sur un versement conclu', Versements::verifierEnCours() >= 0 && $versement($v4)['status'] === 'paid');

    $vendre(30000, 35);
    $compteVerifie('66000014');
    [$v5, $r] = $circuit();
    verif('14 double notification : verse une fois', attendre(fn() => $versement($v5)['status'] === 'paid'));
    $ref5 = (string) $essais($v5)[0]['gateway_ref'];
    $notifs = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE direction = 'callback' AND event_type = 'disbursement.succeeded' AND payload LIKE " . $db->quote('%' . $ref5 . '%'))->fetchColumn();
    verif('... deux notifications recues, un seul essai conclu', attendre(fn() => $notifs() >= 2, 6) && count($essais($v5)) === 1 && $essais($v5)[0]['status'] === 'succeeded', 'notifications : ' . $notifs());
    verif('... et l\'appel direct d\'une notification deja traitee repond « doublon »', Versements::appliquerCallback('airtel_money', $ref5, 'succeeded', null, null) === 'doublon');

    $vendre(30000, 35);
    $compteVerifie('66123458');
    $reglages(['indisponibles' => ['airtel_money']]);
    [$v6, $r] = $circuit();
    verif('Operateur en panne : echec, versement a reprendre', !$r['succes'] && $versement($v6)['status'] === 'failed', json_encode($r));
    $reglages([]);
    $r = Versements::executer($v6, $finance2);
    verif('Reprise apres la panne : nouvel essai', $r['succes'] && count($essais($v6)) === 2, json_encode($r));
    verif('... verse', attendre(fn() => $versement($v6)['status'] === 'paid'));
    $cles = array_column($essais($v6), 'idempotency_key');
    verif('... chaque essai a sa propre cle, un seul reussi', count(array_unique($cles)) === 2
        && count(array_filter($essais($v6), fn($e) => $e['status'] === 'succeeded')) === 1);
    verif('Deja verse : une nouvelle execution est refusee', !Versements::executer($v6, $finance2)['succes']);

    $rel = Versements::releve($v6);
    verif('Releve d\'un versement ulterieur : composantes = net', abs($rel['total_ventes'] + $rel['total_ajustements'] + $rel['regularisation'] - (float) $versement($v6)['net']) < 0.01);
    $s = Versements::solde($artiste);
    verif('Bilan : verse = somme des versements payes', abs($s['verse'] - array_sum(array_map(fn($id) => (float) $versement($id)['net'], [$v1, $v4, $v5, $v6]))) < 0.01, json_encode($s));

    echo "\n=== H. Ecrans ===\n";
    $session = static function () {
        $cookies = (string) tempnam(sys_get_temp_dir(), 'vrs');
        return static function (string $url, ?array $post = null) use ($cookies): array {
            $h = curl_init($url);
            curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
            if ($post !== null) {
                curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
            }
            $corps = (string) curl_exec($h);
            return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_REDIRECT_URL)];
        };
    };
    $connecter = static function (callable $http, string $email, string $mdp) use ($base, $db): void {
        $db->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$email]);
        [, $page] = $http($base . '/login.php');
        preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
        $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $email, 'password' => $mdp]);
    };

    [$code] = $session()($base . '/admin/versements.php');
    verif('Ecran finance ferme sans session', $code !== 200);

    $artisteHttp = $session();
    $connecter($artisteHttp, $marque . 'a@test.local', $motDePasse);
    [$code, $page] = $artisteHttp($base . '/artiste-revenus.php');
    verif('Artiste : page Revenus et versements', $code === 200 && str_contains($page, 'Disponible pour un versement') && str_contains($page, '66123458'), "HTTP {$code}");
    [$code, $page] = $artisteHttp($base . '/releve-versement.php?id=' . $v6);
    verif('Artiste : son releve', $code === 200 && str_contains($page, 'Net verse'));
    [$code, $page] = $artisteHttp($base . '/artist-dashboard.php');
    verif('Tableau de bord artiste : liens revenus et contrat', str_contains($page, '/artiste-revenus.php') && str_contains($page, '/contrat.php'));
    [$code] = $artisteHttp($base . '/admin/versements.php');
    verif('Artiste : pas d\'acces a l\'ecran finance', $code !== 200);
    [$code, , $vers] = $artisteHttp($base . '/artist-add-song.php');
    verif('Contrat accepte : la publication est ouverte', !str_contains($vers, '/contrat.php'), $vers);

    $db->exec("UPDATE distribution_contracts SET retired_at = NOW() WHERE id = {$contratTest}");
    $db->prepare('INSERT INTO distribution_contracts (version, title, body, published_at) VALUES (?, ?, ?, NOW())')
       ->execute([$version . 'b', 'Contrat de test v2', str_repeat('Nouvelle clause. ', 30)]);
    $contratTest2 = (int) $db->lastInsertId();
    foreach (['artist-add-song.php', 'artist-add-album.php', 'upload.php'] as $pageArtiste) {
        [$code, , $vers] = $artisteHttp($base . '/' . $pageArtiste);
        verif("Nouvelle version non acceptee : {$pageArtiste} renvoie au contrat", in_array($code, [301, 302, 303], true) && str_contains($vers, '/contrat.php'), "HTTP {$code} {$vers}");
    }
    [$code, $page] = $artisteHttp($base . '/contrat.php?retour=%2Fartist-add-song.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    verif('Page contrat : texte et bouton d\'acceptation', str_contains($page, 'Nouvelle clause.') && str_contains($page, 'Accepter la version'));
    [$code, , $vers] = $artisteHttp($base . '/contrat.php', ['csrf_token' => $m[1] ?? '', 'lu' => '1', 'retour' => '/artist-add-song.php']);
    verif('Acceptation par la page, retour a la publication', Contrats::aAccepte($artiste, $contratTest2) && str_contains($vers, '/artist-add-song.php'), $vers);
    [$code, , $vers] = $artisteHttp($base . '/contrat.php', ['lu' => '1']);
    verif('Acceptation sans jeton CSRF : refusee', $code === 403 || $code === 419 || $code === 400, "HTTP {$code}");
    $db->exec("UPDATE distribution_contracts SET retired_at = NOW() WHERE id = {$contratTest2}");

    $vendre(30000, 35);
    $compteVerifie('66123459');
    $v7 = (int) Versements::demander($artiste, $uArtiste)['payout_id'];
    $admin = $session();
    $connecter($admin, 'admin@tchadok.td', 'tchadok2026');
    [$code, $page] = $admin($base . '/admin/versements.php');
    verif('Finance : la demande est en file', $code === 200 && str_contains($page, 'ZZVRS ' . $marque) && str_contains($page, 'Demande en attente de validation'), "HTTP {$code}");
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $admin($base . '/admin/versements.php', ['csrf_token' => $m[1] ?? '', 'action' => 'refuser', 'versement' => $v7, 'motif' => '']);
    verif('Finance : refus sans motif impossible', $versement($v7)['status'] === 'draft');
    $admin($base . '/admin/versements.php', ['csrf_token' => $m[1] ?? '', 'action' => 'approuver', 'versement' => $v7]);
    verif('Finance : validation depuis l\'ecran', $versement($v7)['status'] === 'approved');
    [, $page] = $admin($base . '/admin/versements.php', ['csrf_token' => $m[1] ?? '', 'action' => 'executer', 'versement' => $v7]);
    verif('Finance : le meme compte ne peut pas executer', $versement($v7)['status'] === 'approved' && str_contains($page, 'ne peut pas l&#039;executer'));
    [, $page] = $admin($base . '/admin-dashboard.php');
    verif('Entree « Versements » dans la console', str_contains($page, '/admin/versements.php'));
    Versements::decider($v7, 'on_hold', 'Suspendu en fin de test', $finance1);
    Versements::decider($v7, 'rejected', 'Refuse en fin de test', $finance1);
} finally {
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    // Aucun contrat de test ne doit rester en vigueur : il bloquerait les artistes locaux.
    $db->exec("UPDATE distribution_contracts SET retired_at = NOW() WHERE retired_at IS NULL AND title LIKE 'Contrat de test%'");
    $db->exec("DELETE pa FROM payout_attempts pa JOIN payouts p ON p.id = pa.payout_id WHERE p.artist_id IN ({$artiste}, {$artisteB})");
    $db->exec("DELETE FROM payouts WHERE artist_id IN ({$artiste}, {$artisteB})");
    if ($commandes) {
        $liste = implode(',', array_map('intval', $commandes));
        $db->exec("DELETE ri FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id WHERE r.order_id IN ({$liste})");
        $db->exec("DELETE FROM refunds WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM entitlements WHERE user_id = {$uAcheteur}");
        $db->exec("DELETE FROM payment_intents WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec("DELETE FROM artist_payout_accounts WHERE artist_id IN ({$artiste}, {$artisteB})");
    $db->exec("DELETE FROM tracks WHERE id = {$titre}");
    // Les artistes portent des preuves immuables (acceptations, ajustements) :
    // desactives, pas supprimes.
    $db->exec("UPDATE artists SET is_active = 0, deleted_at = NOW() WHERE id IN ({$artiste}, {$artisteB})");
    $db->exec("UPDATE users SET is_active = 0 WHERE id IN ({$uArtiste}, {$uArtisteB})");
    foreach ([$uAcheteur, $finance1, $finance2] as $u) {
        try {
            $db->exec("DELETE FROM users WHERE id = {$u}");
        } catch (Throwable $e) {
            $db->exec("UPDATE users SET is_active = 0 WHERE id = {$u}");
        }
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
