<?php
/**
 * Tests PAY-10 : rapprochement avec les releves des operateurs.
 *
 * Critere du plan : la reconciliation s'execute en local sur les donnees des
 * simulateurs ; un ecart injecte volontairement est detecte et liste ; aucun
 * ecart ne peut etre clos sans motif.
 *
 * Le test fabrique chacun des cinq types d'ecart, puis clot les siens. En
 * local, les paiements des autres tests (dont les commandes sont effacees a
 * la fin) apparaissent aussi comme « argent recu sans tentative » : c'est
 * attendu, et ce test ne touche pas a ces ecarts-la.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\pay-rapprochement.php
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
$aujourdhui = date('Y-m-d');

// Simulateurs, console et distributeur
$processus = [];
foreach (['airtel' => 9101, 'moov' => 9102, 'visa' => 9103, 'gimac' => 9104, 'console' => 9100] as $dossier => $port) {
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
usleep(800000);

$console = static function (string $chemin, array $champs): int {
    $c = curl_init('http://127.0.0.1:9100' . $chemin);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($champs)]);
    curl_exec($c);
    return (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
};
$c = curl_init('http://127.0.0.1:9100/');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true]);
curl_exec($c);
$jeton = trim((string) @file_get_contents($racine . '/mock-gateways/storage/console-jeton'));

// Donnees d'essai
$marque = 'zzrap' . bin2hex(random_bytes(3));
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Test', 'Rapprochement', 1)")
   ->execute([$marque, $marque . '@test.local']);
$acheteur = (int) $db->lastInsertId();
$commandes = [];
$nouvelle = static function () use ($db, $acheteur, &$commandes): array {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $acheteur]);
    $id = (int) $db->lastInsertId();
    Commandes::ajouterArticle($id, 'subscription', 1, 1500.0, null, 'ZZ rapprochement');
    $commandes[] = $id;
    return [$id, (string) $db->query("SELECT reference FROM orders WHERE id = {$id}")->fetchColumn()];
};
$etat = static fn (int $id): string => (string) $db->query("SELECT status FROM orders WHERE id = {$id}")->fetchColumn();
$ref = static fn (int $intent): string => (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$intent}")->fetchColumn();
$ecartsDe = static function (string $reference) use ($db): array {
    $s = $db->prepare('SELECT type FROM reconciliation_discrepancies WHERE gateway_ref = ? AND resolved_at IS NULL ORDER BY type');
    $s->execute([$reference]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
};
$refsTest = [];

try {
    echo "\n=== A. Preparation des cas ===\n";
    // a) paiement normal
    [$oA, $refA] = $nouvelle();
    $tA = (int) Paiements::initier($refA, $acheteur, 'airtel_money', '66000001')['tentative']['id'];
    verif('Cas normal : paye', attendre(fn() => $etat($oA) === 'paid'));
    $refsTest[] = $refOkA = $ref($tA);

    // b) montant altere dans le callback : revue chez Tchadok, encaisse chez l'operateur
    [$oB, $refB] = $nouvelle();
    $tB = (int) Paiements::initier($refB, $acheteur, 'airtel_money', '66000009')['tentative']['id'];
    verif('Cas revue : tentative en revue', attendre(fn() => $db->query("SELECT status FROM payment_intents WHERE id = {$tB}")->fetchColumn() === 'review'));
    $refsTest[] = $refRevue = $ref($tB);

    // c) argent recu sans tentative Tchadok
    $corps = json_encode(['reference' => 'ZZ-HORS-TCHADOK-1', 'amount' => 700, 'currency' => 'XAF', 'flow' => 'push', 'msisdn' => '66000001',
        'callback_url' => rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE'), '/') . '/api/payments/callback.php?passerelle=airtel_money']);
    $ts = (string) time();
    $c = curl_init('http://127.0.0.1:9101/v1/payments');
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        'Content-Type: application/json', 'X-Merchant-Id: ' . EnvLoader::get('AIRTEL_MERCHANT_ID'), 'X-Timestamp: ' . $ts,
        'X-Signature: ' . hash_hmac('sha256', $ts . '.POST./v1/payments.' . $corps, (string) EnvLoader::get('AIRTEL_API_KEY'))]]);
    $refsTest[] = $refHors = (string) (json_decode((string) curl_exec($c), true)['id'] ?? '');
    usleep(1500000);

    // d) paiement enregistre, absent du releve
    [$oD, $refD] = $nouvelle();
    $refsTest[] = $refFantome = 'AIR-' . strtoupper(bin2hex(random_bytes(8)));
    $db->prepare("INSERT INTO payment_intents (order_id, attempt, gateway, amount, currency, amount_xaf, status, gateway_ref, completed_at, expires_at)
                  VALUES (?, 1, 'airtel_money', 1500, 'XAF', 1500, 'succeeded', ?, NOW(), NOW())")->execute([$oD, $refFantome]);
    Commandes::marquerPayee($oD, $refFantome, 'airtel_money');

    // e) montant different
    [$oE, $refE] = $nouvelle();
    $tE = (int) Paiements::initier($refE, $acheteur, 'airtel_money', '66000001')['tentative']['id'];
    attendre(fn() => $etat($oE) === 'paid');
    $db->exec("UPDATE payment_intents SET amount = 1400 WHERE id = {$tE}");
    $refsTest[] = $refMontant = $ref($tE);

    // f) une commande encaissee deux fois chez le meme operateur
    [$oF, $refF] = $nouvelle();
    $tF1 = (int) Paiements::initier($refF, $acheteur, 'airtel_money', '66000005')['tentative']['id'];
    $db->exec("UPDATE payment_intents SET expires_at = NOW() - INTERVAL 1 SECOND WHERE id = {$tF1}");
    Paiements::expirerEchues();
    $tF2 = (int) Paiements::initier($refF, $acheteur, 'airtel_money', '66000001')['tentative']['id'];
    attendre(fn() => $etat($oF) === 'paid');
    $refsTest[] = $refDouble1 = $ref($tF1);
    $refsTest[] = $refDouble2 = $ref($tF2);
    $console('/action', ['passerelle' => 'airtel_money', 'id' => $refDouble1, 'action' => 'valider', 'jeton' => $jeton]);
    verif('Cas doublon : la premiere tentative, expiree, est finalement encaissee -> revue',
        attendre(fn() => $db->query("SELECT error_code FROM payment_intents WHERE id = {$tF1}")->fetchColumn() === 'double_encaissement'));

    echo "\n=== B. Rapprochement du jour ===\n";
    $bilan = Rapprochement::executer('airtel_money', $aujourdhui);
    verif('Execution sur le releve du simulateur', $bilan['statut'] === 'ecarts' && $bilan['operateur'] > 0, json_encode($bilan));
    verif('Paiement normal : aucun ecart', $ecartsDe($refOkA) === []);
    verif('Argent recu sans tentative Tchadok', $ecartsDe($refHors) === ['operateur_sans_commande'], json_encode($ecartsDe($refHors)));
    verif('Paiement enregistre, absent du releve', $ecartsDe($refFantome) === ['commande_sans_encaissement'], json_encode($ecartsDe($refFantome)));
    verif('Montant different', $ecartsDe($refMontant) === ['ecart_montant'], json_encode($ecartsDe($refMontant)));
    verif('Montant altere dans le callback : encaisse mais non livre', $ecartsDe($refRevue) === ['statut_divergent'], json_encode($ecartsDe($refRevue)));
    $double = array_merge($ecartsDe($refDouble1), $ecartsDe($refDouble2));
    verif('Commande encaissee deux fois : doublon signale', in_array('doublon', $double, true) && in_array('statut_divergent', $double, true), json_encode($double));
    $detail = (string) $db->query("SELECT detail FROM reconciliation_discrepancies WHERE type = 'doublon' AND resolved_at IS NULL AND (gateway_ref = " . $db->quote($refDouble1) . ' OR gateway_ref = ' . $db->quote($refDouble2) . ') LIMIT 1')->fetchColumn();
    verif('... avec la marche a suivre', str_contains($detail, 'rembourser'), $detail);

    $avant = (int) $db->query('SELECT COUNT(*) FROM reconciliation_discrepancies WHERE resolved_at IS NULL')->fetchColumn();
    Rapprochement::executer('airtel_money', $aujourdhui);
    verif('Relancer le meme jour ne duplique pas les ecarts ouverts',
        (int) $db->query('SELECT COUNT(*) FROM reconciliation_discrepancies WHERE resolved_at IS NULL')->fetchColumn() === $avant);
    verif('Chaque execution est tracee', (int) $db->query("SELECT COUNT(*) FROM reconciliation_runs WHERE gateway = 'airtel_money' AND statement_date = '{$aujourdhui}'")->fetchColumn() >= 2);
    verif('Le releve est trace dans payment_events', (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE event_type = 'releve' AND created_at > NOW() - INTERVAL 5 MINUTE")->fetchColumn() >= 2);

    echo "\n=== C. Cloture ===\n";
    $idEcart = (int) $db->query('SELECT id FROM reconciliation_discrepancies WHERE gateway_ref = ' . $db->quote($refFantome) . ' AND resolved_at IS NULL')->fetchColumn();
    verif('Cloture sans motif refusee', !Rapprochement::cloturer($idEcart, '  ', null) && !Rapprochement::cloturer($idEcart, 'ok', null));
    $contourne = false;
    try {
        $db->exec("UPDATE reconciliation_discrepancies SET resolved_at = NOW() WHERE id = {$idEcart}");
        $contourne = true;
    } catch (Throwable $e) {
    }
    verif('... y compris par une requete directe (contrainte en base)', !$contourne);
    verif('Cloture motivee acceptee', Rapprochement::cloturer($idEcart, 'Test automatise : paiement fictif injecte', null));
    verif('... et tracee au journal d\'audit', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'rapprochement.ecart_clos' AND target_id = '{$idEcart}'")->fetchColumn() === 1);
    verif('Un ecart clos ne se reclot pas', !Rapprochement::cloturer($idEcart, 'Seconde cloture', null));

    echo "\n=== D. Releve indisponible ===\n";
    $console('/reglages', ['facteur_delai' => '0.2', 'taux_echec' => '0', 'indisponibles' => ['moov_money'], 'jeton' => $jeton]);
    $bilan = Rapprochement::executer('moov_money', $aujourdhui);
    verif('Operateur en panne : execution en erreur, signalee', $bilan['statut'] === 'erreur' && $bilan['erreur'] !== null, json_encode($bilan));
    $console('/reglages', ['facteur_delai' => '0.2', 'taux_echec' => '0', 'jeton' => $jeton]);
    verif('... et normale une fois retabli', Rapprochement::executer('moov_money', $aujourdhui)['statut'] !== 'erreur');

    echo "\n=== E. Ligne de commande et ecran ===\n";
    exec(sprintf('"%s" "%s/scripts/rapprochement.php" --date=%s --passerelle=gimac 2>&1', $php, $racine, $aujourdhui), $sortie, $code);
    verif('scripts/rapprochement.php s\'execute', $code === 0 && str_contains(implode("\n", $sortie), 'gimac'), implode(' | ', $sortie));
    $sortie = [];
    exec(sprintf('"%s" "%s/scripts/rapprochement.php" clore 1 2>&1', $php, $racine), $sortie, $code);
    verif('... et refuse une cloture sans motif', $code !== 0 && str_contains(implode(' ', $sortie), 'motif'));
    $c = curl_init(rtrim((string) EnvLoader::get('SITE_URL'), '/') . '/admin/rapprochement.php');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true]);
    curl_exec($c);
    verif('Ecran d\'administration ferme sans session', (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE) !== 200);
    // Super-administrateur de demonstration (database/seeds/demo.sql).
    $base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
    $cookies = tempnam(sys_get_temp_dir(), 'rap');
    $http = static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps];
    };
    $db->exec("DELETE FROM login_attempts WHERE identifier = 'admin@tchadok.td'");
    [, $page] = $http($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => 'admin@tchadok.td', 'password' => 'tchadok2026']);
    [$code, $page] = $http($base . '/admin/rapprochement.php');
    verif('Le super-administrateur voit l\'ecran', $code === 200 && str_contains($page, 'Ecarts ouverts') && str_contains($page, 'name="motif"'), (string) $code);
    [, $tableau] = $http($base . '/admin-dashboard.php');
    verif('... accessible depuis la console d\'administration', str_contains($tableau, '/admin/rapprochement.php'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $idOuvert = (int) $db->query('SELECT id FROM reconciliation_discrepancies WHERE resolved_at IS NULL LIMIT 1')->fetchColumn();
    [$code, $page] = $http($base . '/admin/rapprochement.php', ['csrf_token' => $m[1] ?? '', 'action' => 'clore', 'ecart' => $idOuvert, 'motif' => 'ok']);
    verif('... qui refuse une cloture au motif trop court', $code === 200 && str_contains($page, 'Motif obligatoire')
        && $db->query("SELECT resolved_at FROM reconciliation_discrepancies WHERE id = {$idOuvert}")->fetchColumn() === null);
    @unlink($cookies);

    $ecran = (string) file_get_contents($racine . '/admin/rapprochement.php');
    verif('... consultation reservee a finance.rapport.lire, cloture a finance.remboursement.executer',
        str_contains($ecran, "exiger('finance.rapport.lire')") && str_contains($ecran, "exiger('finance.remboursement.executer')"));
} finally {
    // Clot les ecarts fabriques par ce test, et eux seuls.
    foreach (array_filter($refsTest) as $r) {
        $s = $db->prepare('SELECT id FROM reconciliation_discrepancies WHERE gateway_ref = ? AND resolved_at IS NULL');
        $s->execute([$r]);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
            Rapprochement::cloturer((int) $id, 'Test automatise pay-rapprochement : ecart fabrique', null);
        }
    }
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    if ($commandes) {
        $liste = implode(',', array_map('intval', $commandes));
        // Les articles d'essai sont des abonnements : depuis le LOT 7, un
        // encaissement active un vrai abonnement (et ses droits).
        $db->exec("DELETE FROM subscriptions WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
        $db->exec("DELETE FROM payment_intents WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec("DELETE FROM users WHERE id = {$acheteur}");
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
