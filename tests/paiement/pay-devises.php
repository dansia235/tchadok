<?php
/**
 * Tests : cours du dollar automatique (decision du 28/09/2026).
 *
 *   - le cours USD -> XAF vient d'une API gratuite (open.er-api.com, puis
 *     frankfurter.app via la parite fixe EUR/XAF) ;
 *   - sans connexion, reponse aberrante ou cours trop ancien : 600 FCFA ;
 *   - un echec n'est pas retente a chaque page (pas de page lente hors ligne) ;
 *   - chaque cours obtenu entre dans l'historique immuable, avec sa source ;
 *   - la tentative de paiement fige le taux ET sa provenance.
 *
 * Aucun appel reseau reel n'est necessaire : les sources sont simulees. Un
 * dernier controle, informatif, interroge les vraies API si Internet repond.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\pay-devises.php
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

$db = TchadokDatabase::getInstance()->getConnection();
$processus = [];
$c = curl_init('http://127.0.0.1:9103/sante');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
if (curl_exec($c) === false) {
    $processus[] = proc_open([PHP_BINARY, '-S', '127.0.0.1:9103', 'mock-gateways/visa/index.php'], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t, $racine);
    usleep(600000);
}
$premierId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM exchange_rates')->fetchColumn();
$memo = Devises::fichierMemo('USD');
$memoAvant = is_file($memo) ? file_get_contents($memo) : null;
$appels = 0;
$source = static function (?float $cours) use (&$appels): callable {
    return static function (string $devise) use ($cours, &$appels): ?array {
        $appels++;
        return $cours === null ? null : ['taux' => $cours, 'source' => 'simulation'];
    };
};
$repartir = static function () use ($memo): void {
    @unlink($memo);
    Devises::oublier();
};

try {
    echo "\n=== A. Lecture des reponses des API ===\n";
    verif('open.er-api.com : cours XAF lu', Devises::lireErApi('{"result":"success","base_code":"USD","rates":{"USD":1,"XAF":567.1234}}') === 567.1234);
    verif('... reponse en erreur ignoree', Devises::lireErApi('{"result":"error","error-type":"unsupported-code"}') === null);
    verif('... reponse illisible ignoree', Devises::lireErApi('<html>proxy</html>') === null);
    verif('frankfurter.app : USD -> EUR x parite fixe 655,957', abs((float) Devises::lireFrankfurter('{"amount":1.0,"base":"USD","rates":{"EUR":0.9}}') - 590.3613) < 0.0001);
    verif('... reponse sans EUR ignoree', Devises::lireFrankfurter('{"rates":{}}') === null);

    echo "\n=== B. Cours du jour ===\n";
    // Tout cours simule s'ecrit dans l'historique : la transaction, annulee a
    // la fin de C, garantit qu'aucun ne survit au test.
    $db->beginTransaction();
    $repartir();
    Devises::utiliserSource($source(575.25));
    verif('Connexion disponible : le cours de l\'API s\'applique', Devises::taux('USD') === 575.25, (string) Devises::taux('USD'));
    verif('... provenance « api »', Devises::origine('USD') === 'api');
    $ligne = $db->query("SELECT * FROM exchange_rates WHERE id > {$premierId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('... inscrit a l\'historique, avec sa source', $ligne && $ligne['source'] === 'api' && (float) $ligne['xaf_per_unit'] === 575.25 && str_contains((string) $ligne['reason'], 'simulation'), json_encode($ligne));
    verif('... conversion au cours du jour, arrondie au cent superieur', Devises::convertir(1500, 'USD') === 2.61, (string) Devises::convertir(1500, 'USD'));
    $appels = 0;
    Devises::oublier();
    Devises::taux('USD');
    verif('Cours recent : pas de nouvel appel a l\'API', $appels === 0, (string) $appels);

    echo "\n=== C. Sans connexion : 600 FCFA ===\n";
    $repartir();
    Devises::utiliserSource($source(null));
    verif('Pas de connexion : taux de secours 600 FCFA', Devises::taux('USD') === 600.0, (string) Devises::taux('USD'));
    verif('... provenance « secours »', Devises::origine('USD') === 'secours');
    verif('... 1 500 FCFA = 2,50 $', Devises::convertir(1500, 'USD') === 2.5);
    $lignes = (int) $db->query("SELECT COUNT(*) FROM exchange_rates WHERE id > {$premierId}")->fetchColumn();
    $appels = 0;
    for ($i = 0; $i < 5; $i++) {
        Devises::oublier();
        Devises::taux('USD');
    }
    verif('Echec memorise : pas de nouvel essai a chaque page (15 min)', $appels === 0, (string) $appels);
    verif('... et le secours n\'est pas inscrit comme un cours', (int) $db->query("SELECT COUNT(*) FROM exchange_rates WHERE id > {$premierId}")->fetchColumn() === $lignes);

    $repartir();
    Devises::utiliserSource($source(6.5));
    verif('Cours aberrant (6,5) : ignore, secours 600', Devises::taux('USD') === 600.0 && Devises::origine('USD') === 'secours');
    $repartir();
    Devises::utiliserSource($source(60000.0));
    verif('Cours aberrant (60 000) : ignore, secours 600', Devises::taux('USD') === 600.0);

    // Cours obtenu, puis la connexion tombe au moment de le rafraichir.
    $repartir();
    Devises::utiliserSource($source(575.25));
    Devises::taux('USD');
    Devises::utiliserSource($source(null), 0);
    $appels = 0;
    verif('Hors ligne mais cours du jour deja obtenu (< 24 h) : il reste valable',
        Devises::taux('USD') === 575.25 && Devises::origine('USD') === 'api' && $appels === 1, (string) $appels);
    Devises::utiliserSource($source(null), 0, 0);
    verif('Hors ligne et cours perime (> 24 h) : 600', Devises::taux('USD') === 600.0 && Devises::origine('USD') === 'secours');
    $db->rollBack();
    verif('(cours simules annules : aucun ne reste dans l\'historique)', (int) $db->query("SELECT COUNT(*) FROM exchange_rates WHERE id > {$premierId}")->fetchColumn() === 0);

    echo "\n=== D. Le paiement fige le taux et sa provenance ===\n";
    $repartir();
    Devises::utiliserSource($source(null));
    $marque = 'zzdev' . bin2hex(random_bytes(3));
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Test', 'Devises', 1)")->execute([$marque, $marque . '@test.local']);
    $acheteur = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $acheteur]);
    $commande = (int) $db->lastInsertId();
    Commandes::ajouterArticle($commande, 'subscription', 1, 1500.0, null, 'ZZ devises');
    $ref = (string) $db->query("SELECT reference FROM orders WHERE id = {$commande}")->fetchColumn();
    $r = Paiements::initier($ref, $acheteur, 'visa', null, 'USD');
    $tentative = $db->query('SELECT amount, exchange_rate, rate_source FROM payment_intents WHERE id = ' . (int) ($r['tentative']['id'] ?? 0))->fetch(PDO::FETCH_ASSOC);
    verif('Tentative hors ligne : 2,50 $, taux 600, provenance « secours »', $tentative && (float) $tentative['amount'] === 2.5
        && (float) $tentative['exchange_rate'] === 600.0 && $tentative['rate_source'] === 'secours', json_encode($tentative));
    $db->exec("DELETE FROM payment_intents WHERE order_id = {$commande}");
    $db->exec("DELETE FROM order_items WHERE order_id = {$commande}");
    $db->exec("DELETE FROM orders WHERE id = {$commande}");
    $db->exec("DELETE FROM users WHERE id = {$acheteur}");

    echo "\n=== E. Mode manuel et ligne de commande ===\n";
    $repartir();
    Devises::utiliserSource(null);
    $sortie = [];
    exec(sprintf('"%s" "%s/scripts/devises.php" liste 2>&1', PHP_BINARY, $racine), $sortie, $code);
    verif('scripts/devises.php liste : mode, provenance et secours affiches', $code === 0 && str_contains(implode("\n", $sortie), 'secours 600'), implode(' | ', $sortie));

    echo "\n=== F. Les vraies API (informatif) ===\n";
    $repartir();
    $reel = Devises::interrogerApi('USD');
    if ($reel === null) {
        echo "  --  Internet ou API injoignable : c'est le cas que couvre le secours a 600.\n";
    } else {
        verif('Cours reel plausible (' . $reel['source'] . ' : ' . $reel['taux'] . ')', $reel['taux'] > 300 && $reel['taux'] < 1200);
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Devises::utiliserSource(null);
    $memoAvant === null ? @unlink($memo) : file_put_contents($memo, $memoAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
