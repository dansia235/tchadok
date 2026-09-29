<?php
/**
 * Tests SHOP-06 : portefeuille prepaye.
 *
 * Criteres du plan : deux achats simultanes sur un solde insuffisant n'en
 * passent qu'un ; le solde recalcule depuis le journal correspond toujours a
 * wallet_balance ; un rechargement echoue ne credite rien.
 *
 * La concurrence est provoquee par deux PROCESSUS PHP distincts : deux
 * requetes d'une meme session web seraient serialisees par le verrou de
 * session de PHP, et masqueraient l'absence de verrou en base.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\shop-portefeuille.php
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

$marque = 'zzwal' . bin2hex(random_bytes(3));
$motDePasse = 'Essai-Portefeuille-2026!';
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Client', 'Portefeuille', 1, 1)")
   ->execute([$marque, $marque . '@test.local', password_hash($motDePasse, PASSWORD_BCRYPT)]);
$membre = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Artiste', 'Wal', 1)")->execute([$marque . 'a', $marque . 'a@test.local']);
$userArtiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)")->execute([$userArtiste, 'ZZWAL ' . $marque]);
$artiste = (int) $db->lastInsertId();
$titres = [];
foreach (['A', 'B', 'C', 'D'] as $lettre) {
    $db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, price, status) VALUES (?, ?, 'x.mp3', 200, 0, 1000, 'approved')")->execute([$artiste, 'ZZWAL ' . $lettre]);
    $titres[$lettre] = (int) $db->lastInsertId();
}

$commandes = [];
$commande = static function (int $titre) use ($db, $membre, $artiste, &$commandes): array {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'awaiting_payment', 'XAF')")->execute([Commandes::reference(), $membre]);
    $id = (int) $db->lastInsertId();
    // Sans artiste rattache : la ligne resterait liee a l'artiste d'essai, que
    // le nettoyage ne pourrait alors plus retirer.
    Commandes::ajouterArticle($id, 'track', $titre, 1000.0, null, 'ZZWAL');
    $commandes[] = $id;
    return [$id, (string) $db->query("SELECT reference FROM orders WHERE id = {$id}")->fetchColumn()];
};
$recharge = static function (int $montant, string $numero) use ($db, $membre, &$commandes): array {
    $r = Portefeuille::creerRecharge($membre, $montant);
    $id = (int) $db->query('SELECT id FROM orders WHERE reference = ' . $db->quote((string) $r['reference']))->fetchColumn();
    $commandes[] = $id;
    Paiements::initier((string) $r['reference'], $membre, 'airtel_money', $numero);
    return [$id, (string) $r['reference']];
};
$solde = static fn (): float => Portefeuille::solde($membre);
$etat = static fn (int $id): string => (string) $db->query("SELECT status FROM orders WHERE id = {$id}")->fetchColumn();

try {
    echo "\n=== A. Rechargement ===\n";
    verif('Montant hors paliers refuse', !Portefeuille::creerRecharge($membre, 1234)['succes']);
    [$r1] = $recharge(2500, '66000001');
    verif('Rechargement de 2 500 FCFA paye par Airtel : solde credite', attendre(fn() => $solde() === 2500.0), (string) $solde());
    $ligne = $db->query("SELECT commission, artist_net, artist_id FROM order_items WHERE order_id = {$r1}")->fetch(PDO::FETCH_ASSOC);
    verif('... sans commission ni part artiste (ce n\'est pas une vente)', (float) $ligne['commission'] === 0.0 && $ligne['artist_id'] === null);
    [$r2] = $recharge(1000, '66000007');
    verif('Callback de recharge recu trois fois : credite une seule fois',
        attendre(fn() => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE order_id = {$r2} AND direction = 'callback'")->fetchColumn() === 3, 12)
        && $solde() === 3500.0, (string) $solde());
    [$r3] = $recharge(5000, '66000003');
    attendre(fn() => $etat($r3) === 'failed');
    verif('Rechargement echoue : rien n\'est credite', $etat($r3) === 'failed' && $solde() === 3500.0, $etat($r3) . ' ' . $solde());

    echo "\n=== B. Achat par le portefeuille ===\n";
    [$o1, $ref1] = $commande($titres['A']);
    $r = Portefeuille::payer($ref1, $membre);
    verif('Achat regle par le solde', $r['succes'] && $etat($o1) === 'paid', json_encode($r));
    verif('... solde debite de 1 000', $solde() === 2500.0);
    verif('... acces ouvert, facture attribuee, moyen « portefeuille »', Commandes::aLeDroit($membre, 'track', $titres['A'])
        && str_starts_with((string) $db->query("SELECT invoice_number FROM orders WHERE id = {$o1}")->fetchColumn(), 'FAC-')
        && $db->query("SELECT payment_method FROM orders WHERE id = {$o1}")->fetchColumn() === 'wallet');
    verif('Payer deux fois la meme commande : refuse', !Portefeuille::payer($ref1, $membre)['succes'] && $solde() === 2500.0);
    verif('Commande d\'autrui : refusee', !Portefeuille::payer($ref1, $userArtiste)['succes']);
    verif('Une recharge ne se paie pas par le portefeuille', !Portefeuille::payer((string) $db->query("SELECT reference FROM orders WHERE id = {$r3}")->fetchColumn(), $membre)['succes']);

    echo "\n=== C. Deux achats simultanes sur un solde insuffisant ===\n";
    Portefeuille::ajuster($membre, -1000, 'Mise en place du test de concurrence', null);
    verif('Solde ramene a 1 500 FCFA', $solde() === 1500.0);
    [$o2, $ref2] = $commande($titres['B']);
    [$o3, $ref3] = $commande($titres['C']);
    $script = tempnam(sys_get_temp_dir(), 'wal') . '.php';
    file_put_contents($script, '<?php require ' . var_export($racine . '/includes/functions.php', true) . '; require ' . var_export($racine . '/includes/paiement/chargement.php', true) . ';'
        . ' usleep((int) $argv[3]); echo json_encode(Portefeuille::payer($argv[1], (int) $argv[2]));');
    $depart = (int) ((ceil(microtime(true)) + 1 - microtime(true)) * 1000000);
    $p1 = proc_open([$php, $script, $ref2, (string) $membre, (string) $depart], [['pipe', 'r'], ['pipe', 'w'], ['file', 'NUL', 'w']], $tp1, $racine);
    $p2 = proc_open([$php, $script, $ref3, (string) $membre, (string) $depart], [['pipe', 'r'], ['pipe', 'w'], ['file', 'NUL', 'w']], $tp2, $racine);
    $sortie1 = (string) stream_get_contents($tp1[1]);
    $sortie2 = (string) stream_get_contents($tp2[1]);
    proc_close($p1);
    proc_close($p2);
    @unlink($script);
    // La sortie peut commencer par des avertissements de session (CLI) : on
    // ne garde que le JSON final.
    $res1 = json_decode(substr($sortie1, (int) strpos($sortie1, '{"succes')), true);
    $res2 = json_decode(substr($sortie2, (int) strpos($sortie2, '{"succes')), true);
    $reussis = (int) !empty($res1['succes']) + (int) !empty($res2['succes']);
    verif('Un seul des deux achats passe', $reussis === 1, $sortie1 . ' | ' . $sortie2);
    verif('... le solde reste positif (500 FCFA)', $solde() === 500.0, (string) $solde());
    verif('... et l\'autre recoit « solde insuffisant »', str_contains(($res1['erreur'] ?? '') . ($res2['erreur'] ?? ''), 'insuffisant'));

    echo "\n=== D. Remboursement d'un achat paye par le portefeuille ===\n";
    $r = Remboursements::rembourser($o1, 'Titre defectueux confirme', null);
    verif('Recredit immediat du portefeuille', $r['succes'] && $r['statut'] === 'effectue' && $solde() === 1500.0, json_encode($r) . ' ' . $solde());
    verif('... acces retire', !Commandes::aLeDroit($membre, 'track', $titres['A']));
    verif('Une recharge ne se rembourse pas en ligne', !Remboursements::rembourser($r1, 'Rembourser la recharge', null)['succes']);

    echo "\n=== E. Integrite du journal ===\n";
    verif('Solde du journal = solde affiche', Portefeuille::verifier($membre) === []);
    $mouvements = $db->query("SELECT type, amount FROM wallet_transactions WHERE user_id = {$membre} ORDER BY id")->fetchAll(PDO::FETCH_NUM);
    verif('Historique complet et dans l\'ordre',
        array_column($mouvements, 0) === ['topup', 'topup', 'purchase', 'adjustment', 'purchase', 'refund'], json_encode($mouvements));
    $modifie = false;
    try {
        $db->exec("UPDATE wallet_transactions SET amount = 99999 WHERE user_id = {$membre}");
        $modifie = true;
    } catch (Throwable $e) {
    }
    verif('Le journal ne se modifie pas (base)', !$modifie);
    verif('Un debit au-dela du solde est refuse', !Portefeuille::ajuster($membre, -99999, 'Debit impossible', null) && $solde() === 1500.0);
    verif('Un ajustement sans motif est refuse', !Portefeuille::ajuster($membre, 100, 'non', null));
    exec(sprintf('"%s" "%s/scripts/portefeuille.php" verifier 2>&1', $php, $racine), $sortie, $code);
    verif('scripts/portefeuille.php verifier : tout concorde', $code === 0, implode(' ', $sortie));

    echo "\n=== F. Pages ===\n";
    $cookies = (string) tempnam(sys_get_temp_dir(), 'walc');
    $http = static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        $brut = (string) curl_exec($h);
        preg_match('/^Location:\s*(\S+)/mi', $brut, $l);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $brut, $l[1] ?? ''];
    };
    [, $page] = $http($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $marque . '@test.local', 'password' => $motDePasse]);
    [$code, $page] = $http($base . '/wallet.php');
    verif('Page portefeuille : solde reel et paliers', $code === 200 && str_contains($page, '1 500 FCFA') && str_contains($page, 'value="10000"'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    [$code, , $vers] = $http($base . '/wallet.php', ['csrf_token' => $m[1] ?? '', 'action' => 'recharger', 'montant' => 5000]);
    verif('Recharger 5 000 FCFA mene au paiement', $code === 302 && str_contains($vers, 'paiement.php?commande='), $vers);
    preg_match('/commande=(TCHK-[0-9A-F-]+)/', urldecode($vers), $mr);
    $commandes[] = (int) $db->query('SELECT id FROM orders WHERE reference = ' . $db->quote($mr[1] ?? ''))->fetchColumn();
    [, $page] = $http($vers);
    verif('... sans proposer de la regler par le portefeuille', !str_contains($page, 'Payer avec mon solde'));
    [$o4, $ref4] = $commande($titres['D']);
    [, $page] = $http($base . '/paiement.php?commande=' . $ref4);
    verif('Une commande ordinaire propose le portefeuille', str_contains($page, 'Payer avec mon solde') && str_contains($page, 'Solde : 1 500 FCFA'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    [$code] = $http($base . '/paiement.php?commande=' . $ref4, ['csrf_token' => $m[1] ?? '', 'action' => 'portefeuille']);
    verif('... et la regle en un clic', $code === 302 && $etat($o4) === 'paid' && $solde() === 500.0);
    @unlink($cookies);
} finally {
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    // Le journal du portefeuille est immuable : le compte d'essai et ses
    // commandes restent, inertes et identifiables (zzwal...). Le reste part.
    $db->exec("DELETE FROM entitlements WHERE user_id = {$membre}");
    // Les commandes conservees referencent le titre et l'artiste : s'ils ne
    // peuvent pas partir, ils sont retires du catalogue (jamais laisses actifs).
    foreach (["DELETE FROM tracks WHERE artist_id = {$artiste}" => "UPDATE tracks SET deleted_at = NOW() WHERE artist_id = {$artiste}",
              "DELETE FROM artists WHERE id = {$artiste}" => "UPDATE artists SET is_active = 0, deleted_at = NOW() WHERE id = {$artiste}",
              "DELETE FROM users WHERE id = {$userArtiste}" => "UPDATE users SET is_active = 0 WHERE id = {$userArtiste}"] as $suppression => $retrait) {
        try {
            $db->exec($suppression);
        } catch (Throwable $e) {
            $db->exec($retrait);
        }
    }
    echo "\n  (donnees d'essai conservees : compte {$marque}, journal du portefeuille immuable)\n";
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
