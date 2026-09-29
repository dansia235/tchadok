<?php
/**
 * Tests LOT 5 : passerelles, machine a etats, callbacks, simulateurs.
 *
 * Exerce le chemin REEL : l'application appelle les simulateurs en HTTP, les
 * simulateurs rappellent l'application via Apache (api/payments/callback.php).
 * Rien n'est court-circuite, hormis quelques transitions forcees pour les cas
 * impossibles a provoquer autrement (paiement tardif, double encaissement).
 *
 * Prerequis : Apache et MySQL demarres. Les simulateurs sont lances par le
 * test s'ils ne tournent pas deja, et arretes a la fin dans ce cas.
 *
 * Les delais des simulateurs sont accelere (facteur 0,2) le temps du test,
 * puis retablis.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\pay-passerelles.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';

$baseDb = (string) EnvLoader::get('DB_DATABASE', '');
if (!preg_match('/local|test|dev/', $baseDb)) {
    fwrite(STDERR, "Refus : base '{$baseDb}' non locale.\n");
    exit(1);
}

$ok = 0;
$ko = 0;

function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    if ($condition) {
        $ok++;
        echo "  OK  {$libelle}\n";
    } else {
        $ko++;
        echo "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

$db = TchadokDatabase::getInstance()->getConnection();
$php = PHP_BINARY;
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

// ---------------------------------------------------------------------
// Simulateurs
// ---------------------------------------------------------------------
$ports = ['airtel' => 9101, 'moov' => 9102, 'visa' => 9103, 'gimac' => 9104, 'console' => 9100];
$processus = [];

function repond(int $port): bool
{
    $c = curl_init("http://127.0.0.1:{$port}/sante");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
    $r = curl_exec($c);
    return $r !== false && (str_contains((string) $r, '"ok"') || str_contains((string) $r, 'Console des simulateurs'));
}

$demarres = false;
foreach ($ports as $dossier => $port) {
    if (!repond($port)) {
        $processus[] = proc_open([$php, '-S', "127.0.0.1:{$port}", "mock-gateways/{$dossier}/index.php"], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $tuyaux, $racine);
        $demarres = true;
    }
}
$distributeurLance = false;
$fichierReglages = $racine . '/mock-gateways/storage/reglages.json';
$reglagesAvant = is_file($fichierReglages) ? file_get_contents($fichierReglages) : null;
@mkdir(dirname($fichierReglages), 0775, true);
file_put_contents($fichierReglages, json_encode(['facteur_delai' => 0.2]));

// Un distributeur tourne-t-il deja ? On en lance un dedie si la file ne se vide pas.
$processus[] = proc_open([$php, 'mock-gateways/distributeur.php'], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $tuyaux, $racine);
$distributeurLance = true;
usleep(800000);

/** Callbacks envoyes pour une transaction simulee, le plus recent d'abord. */
function Magasin_journal(string $racine, string $id): array
{
    $lignes = @file($racine . '/mock-gateways/storage/journal-callbacks.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $trouves = [];
    foreach (array_reverse($lignes) as $ligne) {
        $l = json_decode($ligne, true);
        if (is_array($l) && ($l['id'] ?? '') === $id) {
            $trouves[] = $l;
        }
    }
    return $trouves;
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

// ---------------------------------------------------------------------
// Donnees d'essai (identifiants attribues par la base, jamais fixes)
// ---------------------------------------------------------------------
$marque = 'zzpay' . bin2hex(random_bytes(3));
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Test', 'Paiement', 1)")
   ->execute([$marque, $marque . '@test.local']);
$acheteur = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Artiste', 'Paiement', 1)")
   ->execute([$marque . 'a', $marque . 'a@test.local']);
$userArtiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)")->execute([$userArtiste, 'ZZPAY ' . $marque]);
$artiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, price, status) VALUES (?, ?, 'x.mp3', 200, 0, 1500, 'approved')")
   ->execute([$artiste, 'ZZPAY titre']);
$titre = (int) $db->lastInsertId();

$commandes = [];

/** Cree une commande d'un titre a 1 500 FCFA et renvoie [id, reference]. */
$nouvelleCommande = static function () use ($db, $acheteur, $titre, $artiste, &$commandes): array {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")
       ->execute([Commandes::reference(), $acheteur]);
    $id = (int) $db->lastInsertId();
    Commandes::ajouterArticle($id, 'track', $titre, 1500.0, $artiste, 'ZZPAY titre');
    $commandes[] = $id;
    return [$id, (string) $db->query("SELECT reference FROM orders WHERE id = {$id}")->fetchColumn()];
};

$etat = static fn (int $orderId): string => (string) $db->query("SELECT status FROM orders WHERE id = {$orderId}")->fetchColumn();
$etatTentative = static fn (int $intentId): string => (string) $db->query("SELECT status FROM payment_intents WHERE id = {$intentId}")->fetchColumn();
$droits = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM entitlements WHERE user_id = {$acheteur} AND revoked_at IS NULL")->fetchColumn();

/** Paie une commande et renvoie l'identifiant de tentative. */
$payer = static function (string $ref, string $passerelle, ?string $numero) use ($acheteur): array {
    return Paiements::initier($ref, $acheteur, $passerelle, $numero);
};

/** Requete signee vers un simulateur, comme le ferait l'application. */
$requeteSimulateur = static function (string $prefixe, int $port, string $methode, string $chemin, string $corps, ?int $horodatage = null, ?string $cle = null, array $sup = []): array {
    $ts = (string) ($horodatage ?? time());
    $signature = hash_hmac('sha256', $ts . '.' . $methode . '.' . $chemin . '.' . $corps, $cle ?? (string) EnvLoader::get($prefixe . '_API_KEY'));
    $c = curl_init("http://127.0.0.1:{$port}{$chemin}");
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => $corps !== '' ? $corps : null,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'X-Merchant-Id: ' . EnvLoader::get($prefixe . '_MERCHANT_ID'),
            'X-Timestamp: ' . $ts, 'X-Signature: ' . $signature,
        ], $sup),
    ]);
    $r = curl_exec($c);
    return [(int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), json_decode((string) $r, true)];
};

/** Callback signe envoye a l'application. */
$urlCallback = rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE'), '/') . '/api/payments/callback.php?passerelle=';
$callback = static function (string $passerelle, string $prefixe, array $contenu, ?string $secret = null, ?int $horodatage = null) use ($urlCallback): array {
    $corps = json_encode($contenu, JSON_UNESCAPED_SLASHES);
    $ts = (string) ($horodatage ?? time());
    $sig = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $corps, $secret ?? (string) EnvLoader::get($prefixe . '_WEBHOOK_SECRET'));
    $c = curl_init($urlCallback . $passerelle);
    curl_setopt_array($c, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Signature: ' . $sig],
    ]);
    $r = curl_exec($c);
    return [(int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), json_decode((string) $r, true)];
};

try {
    echo "\n=== A. Configuration et abstraction ===\n";
    verif('Les quatre passerelles sont configurees', count(FabriquePasserelles::disponibles()) === 4,
        implode(',', array_keys(FabriquePasserelles::disponibles())));
    verif('KONOOM a disparu du code et de la configuration',
        !preg_match('/konoom/i', $source('.env.local.example') . $source('.env.production.example') . $source('includes/production-secret-policy.php')));
    verif('Mode simulateur en local', !FabriquePasserelles::modeReel());
    verif('Visa : parcours hosted, pas de numero', FabriquePasserelles::obtenir('visa')->parcours() === 'hosted'
        && FabriquePasserelles::obtenir('visa')->normaliserNumero('66000001') === null);
    verif('Airtel : numero tchadien normalise', FabriquePasserelles::obtenir('airtel_money')->normaliserNumero('+235 66 00 00 01') === '66000001');
    verif('Airtel : numero etranger refuse', FabriquePasserelles::obtenir('airtel_money')->normaliserNumero('+237699000000') === null);
    verif('GIMAC : numero camerounais accepte (interoperabilite CEMAC)', FabriquePasserelles::obtenir('gimac')->normaliserNumero('+237 62000001') === '23762000001');
    verif('GIMAC : indicatif hors CEMAC refuse', FabriquePasserelles::obtenir('gimac')->normaliserNumero('+33612345678') === null);
    verif('Callbacks locaux : boucle locale seulement', FabriquePasserelles::adressesCallback('visa') === ['127.0.0.1', '::1']);
    verif('Logos presents', array_sum(array_map(fn($p) => (int) is_file($racine . '/' . $p->logo()), FabriquePasserelles::disponibles())) === 4);

    echo "\n=== B. Simulateur : authentification du marchand ===\n";
    [$code] = $requeteSimulateur('AIRTEL', 9101, 'GET', '/v1/payments/AIR-0000000000000000', '', null, 'mauvaise-cle');
    verif('Signature avec une mauvaise cle : 401', $code === 401, (string) $code);
    [$code, $rep] = $requeteSimulateur('MOOV', 9102, 'GET', '/v1/payments/MOV-0000000000000000', '', time() - 600);
    verif('Requete horodatee il y a 10 minutes : 401', $code === 401 && ($rep['error']['code'] ?? '') === 'timestamp_out_of_range', (string) $code);
    [$code] = $requeteSimulateur('GIMAC', 9104, 'GET', '/v1/payments/GIM-0000000000000000', '');
    verif('Requete correctement signee : acceptee (404 transaction inconnue)', $code === 404, (string) $code);

    $corpsInit = json_encode(['reference' => 'ZZ-IDEM-1', 'amount' => 100, 'currency' => 'XAF', 'flow' => 'push', 'msisdn' => '66000005', 'callback_url' => 'http://127.0.0.1/x']);
    $cleIdem = bin2hex(random_bytes(16));
    [$c1, $r1] = $requeteSimulateur('AIRTEL', 9101, 'POST', '/v1/payments', $corpsInit, null, null, ['Idempotency-Key: ' . $cleIdem]);
    [$c2, $r2] = $requeteSimulateur('AIRTEL', 9101, 'POST', '/v1/payments', $corpsInit, null, null, ['Idempotency-Key: ' . $cleIdem]);
    verif('Initiation rejouee avec la meme cle : meme transaction, aucun second debit',
        $c1 === 201 && $c2 === 200 && ($r1['id'] ?? 'a') === ($r2['id'] ?? 'b'), "{$c1}/{$c2}");

    echo "\n=== C. Airtel Money : les dix scenarios ===\n";
    // 1. Succes
    [$o1, $ref1] = $nouvelleCommande();
    $r = $payer($ref1, 'airtel_money', '66000001');
    $t1 = (int) ($r['tentative']['id'] ?? 0);
    verif('Initiation acceptee, tentative en attente', $r['succes'] && $r['tentative']['statut'] === 'pending', json_encode($r));
    verif('Commande en attente de paiement', $etat($o1) === 'awaiting_payment');
    verif('Scenario 1 : commande payee apres le callback', attendre(fn() => $etat($o1) === 'paid'), $etat($o1));
    $facture = (string) $db->query("SELECT invoice_number FROM orders WHERE id = {$o1}")->fetchColumn();
    verif('... facture numerotee', preg_match('/^FAC-\d{4}-\d{6}$/', $facture) === 1, $facture);
    verif('... droit d\'acces au titre accorde', Commandes::aLeDroit($acheteur, 'track', $titre));
    verif('... tentative reussie', $etatTentative($t1) === 'succeeded');
    $evenements = $db->query("SELECT direction, event_type, http_status, signature_valid FROM payment_events WHERE intent_id = {$t1} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    verif('... trace complete : requete, reponse, callback signe',
        array_column($evenements, 'direction') === ['request', 'response', 'callback'] && (int) $evenements[2]['signature_valid'] === 1,
        json_encode($evenements));

    verif('Payer une commande deja payee est refuse', $payer($ref1, 'airtel_money', '66000001')['succes'] === false);

    // 3 puis nouvel essai
    [$o3, $ref3] = $nouvelleCommande();
    $r = $payer($ref3, 'airtel_money', '66000003');
    $t3 = (int) $r['tentative']['id'];
    verif('Scenario 3 (solde insuffisant) : tentative echouee', attendre(fn() => $etatTentative($t3) === 'failed'), $etatTentative($t3));
    verif('... commande echouee', $etat($o3) === 'failed');
    verif('... message clair', Paiements::suivre($t3, $acheteur)['message'] === 'Solde insuffisant.');
    $r = $payer($ref3, 'moov_money', '65000001');
    verif('... nouvel essai avec Moov Money : tentative n° 2', $r['succes'] && (int) $db->query("SELECT attempt FROM payment_intents WHERE id = " . (int) $r['tentative']['id'])->fetchColumn() === 2);
    verif('... et commande payee', attendre(fn() => $etat($o3) === 'paid'), $etat($o3));

    // 4
    [$o4, $ref4] = $nouvelleCommande();
    $t4 = (int) $payer($ref4, 'airtel_money', '66000004')['tentative']['id'];
    verif('Scenario 4 (code errone) : echec invalid_pin', attendre(fn() => $etatTentative($t4) === 'failed')
        && $db->query("SELECT error_code FROM payment_intents WHERE id = {$t4}")->fetchColumn() === 'invalid_pin');

    // 5 : aucun callback, puis expiration apres verification
    [$o5, $ref5] = $nouvelleCommande();
    $t5 = (int) $payer($ref5, 'airtel_money', '66000005')['tentative']['id'];
    $refus = $payer($ref5, 'gimac', '62000001');
    verif('Scenario 5 : second moyen refuse tant que la demande push est ouverte', $refus['succes'] === false && str_contains((string) $refus['erreur'], 'deja en attente'));
    $meme = $payer($ref5, 'airtel_money', '66000005');
    verif('... le meme clic rend la meme tentative, sans nouvelle demande', (int) ($meme['tentative']['id'] ?? 0) === $t5);
    usleep(1500000);
    verif('... toujours en attente sans callback', $etatTentative($t5) === 'pending');
    $db->exec("UPDATE payment_intents SET expires_at = NOW() - INTERVAL 1 SECOND WHERE id = {$t5}");
    $bilan = Paiements::expirerEchues();
    verif('... expiree apres consultation de l\'operateur', $etatTentative($t5) === 'expired' && $bilan['verifiees'] >= 1, json_encode($bilan));
    verif('... commande expiree, et retentable', $etat($o5) === 'expired' && $payer($ref5, 'gimac', '62000001')['succes']);
    verif('... puis payee par GIMAC', attendre(fn() => $etat($o5) === 'paid'), $etat($o5));

    // 6
    [$o6, $ref6] = $nouvelleCommande();
    $t6 = (int) $payer($ref6, 'airtel_money', '66000006')['tentative']['id'];
    verif('Scenario 6 : annule par l\'abonne', attendre(fn() => $etatTentative($t6) === 'cancelled') && $etat($o6) === 'cancelled');

    // 7 : trois callbacks
    $droitsAvant = $droits();
    [$o7, $ref7] = $nouvelleCommande();
    $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
    $t7 = (int) $payer($ref7, 'airtel_money', '66000007')['tentative']['id'];
    verif('Scenario 7 : trois callbacks recus', attendre(fn() => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE intent_id = {$t7} AND direction = 'callback'")->fetchColumn() === 3, 12));
    verif('... une seule facture, un seul droit', $etat($o7) === 'paid' && $droits() === 1, 'droits : ' . $droits());
    $journal = array_map(fn($l) => json_decode($l, true), file($racine . '/mock-gateways/storage/journal-callbacks.jsonl', FILE_IGNORE_NEW_LINES));
    $reponses = array_column(array_filter($journal, fn($l) => ($l['id'] ?? '') === $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$t7}")->fetchColumn()), 'http');
    verif('... l\'application a repondu 200 aux trois (l\'operateur cesse de reessayer)', $reponses === [200, 200, 200], json_encode($reponses));

    // 8 : signature invalide, recuperation par consultation
    [$o8, $ref8] = $nouvelleCommande();
    $t8 = (int) $payer($ref8, 'airtel_money', '66000008')['tentative']['id'];
    verif('Scenario 8 : callback mal signe rejete et trace',
        attendre(fn() => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE intent_id = {$t8} AND direction = 'callback' AND signature_valid = 0")->fetchColumn() === 1));
    verif('... la commande n\'est pas payee pour autant', $etat($o8) === 'awaiting_payment');
    verif('... la consultation de l\'operateur retablit la verite', Paiements::verifierStatut($t8) === 'succeeded' && $etat($o8) === 'paid');

    // 9 : montant altere
    [$o9, $ref9] = $nouvelleCommande();
    $t9 = (int) $payer($ref9, 'airtel_money', '66000009')['tentative']['id'];
    verif('Scenario 9 : montant divergent -> revue', attendre(fn() => $etatTentative($t9) === 'review'), $etatTentative($t9));
    verif('... commande en revue, sans facture ni droit', $etat($o9) === 'review'
        && $db->query("SELECT invoice_number FROM orders WHERE id = {$o9}")->fetchColumn() === null);
    verif('... montant annonce conserve pour la revue', (int) $db->query("SELECT amount_reported FROM payment_intents WHERE id = {$t9}")->fetchColumn() === 1501);
    verif('... et la commande ne peut plus etre repayee automatiquement', $payer($ref9, 'airtel_money', '66000001')['succes'] === false);

    // 10 : panne a l'initiation
    [$o10, $ref10] = $nouvelleCommande();
    $r = $payer($ref10, 'airtel_money', '66000010');
    verif('Scenario 10 : erreur 500 de l\'operateur -> refus explicite', $r['succes'] === false && str_contains((string) $r['erreur'], 'ne repond pas'), json_encode($r));
    verif('... tentative echouee, commande retentable', $etat($o10) === 'failed' && $payer($ref10, 'airtel_money', '66123456')['succes']);

    // 2 : lent
    [$o2, $ref2] = $nouvelleCommande();
    $payer($ref2, 'airtel_money', '66000002');
    verif('Scenario 2 : succes lent', attendre(fn() => $etat($o2) === 'paid', 12), $etat($o2));
    verif('Numero hors scenarios (66123456) : succes par defaut', attendre(fn() => $etat($o10) === 'paid', 8), $etat($o10));

    echo "\n=== D. Callbacks : robustesse cote application ===\n";
    [$code] = $callback('airtel_money', 'AIRTEL', ['event' => 'payment.succeeded', 'payment' => ['id' => 'AIR-1']], 'mauvais-secret');
    verif('Signature invalide : 401', $code === 401, (string) $code);
    [$code] = $callback('airtel_money', 'AIRTEL', ['event' => 'payment.succeeded', 'payment' => ['id' => 'AIR-1']], null, time() - 900);
    verif('Callback vieux de 15 minutes : 401', $code === 401, (string) $code);
    [$code, $rep] = $callback('airtel_money', 'AIRTEL', ['event' => 'payment.succeeded', 'payment' => ['id' => 'AIR-FFFFFFFFFFFFFFFF', 'reference' => 'TCHK-2026-00000000-1', 'amount' => 10, 'currency' => 'XAF', 'status' => 'succeeded']]);
    verif('Tentative inconnue : 200 sans effet', $code === 200 && ($rep['resultat'] ?? '') === 'tentative_inconnue', json_encode($rep));
    [$code] = $callback('konoom', 'AIRTEL', ['event' => 'x', 'payment' => ['id' => 'x']]);
    verif('Passerelle inconnue : 404', $code === 404, (string) $code);
    $c = curl_init($urlCallback . 'visa');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true]);
    curl_exec($c);
    verif('GET sur le point de callback : 405', (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE) === 405);
    verif('Callbacks rejetes traces dans payment_events',
        (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE direction = 'callback' AND signature_valid = 0 AND created_at > NOW() - INTERVAL 5 MINUTE")->fetchColumn() >= 3);

    // Deux callbacks simultanes sur la meme tentative
    [$oS, $refS] = $nouvelleCommande();
    $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
    $tS = (int) $payer($refS, 'airtel_money', '66000005')['tentative']['id'];
    $refOp = (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$tS}")->fetchColumn();
    $corpsS = json_encode(['event' => 'payment.succeeded', 'event_id' => 'evt_s', 'payment' => ['id' => $refOp, 'reference' => $refS . '-1', 'amount' => 1500, 'currency' => 'XAF', 'status' => 'succeeded']], JSON_UNESCAPED_SLASHES);
    $multi = curl_multi_init();
    $poignees = [];
    for ($i = 0; $i < 4; $i++) {
        $ts = (string) time();
        $h = curl_init($urlCallback . 'airtel_money');
        curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpsS, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Signature: t=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $corpsS, (string) EnvLoader::get('AIRTEL_WEBHOOK_SECRET'))]]);
        curl_multi_add_handle($multi, $h);
        $poignees[] = $h;
    }
    do {
        curl_multi_exec($multi, $actifs);
        curl_multi_select($multi, 0.2);
    } while ($actifs > 0);
    $codes = array_map(fn($h) => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $poignees);
    verif('Quatre callbacks simultanes : tous 200', $codes === [200, 200, 200, 200], json_encode($codes));
    verif('... une seule facture et un seul droit', $etat($oS) === 'paid' && $droits() === 1, 'droits : ' . $droits());

    // Paiement tardif apres expiration
    [$oT, $refT] = $nouvelleCommande();
    $tT = (int) $payer($refT, 'airtel_money', '66000005')['tentative']['id'];
    $db->exec("UPDATE payment_intents SET expires_at = NOW() - INTERVAL 1 SECOND WHERE id = {$tT}");
    Paiements::expirerEchues();
    $refOpT = (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$tT}")->fetchColumn();
    [$code, $rep] = $callback('airtel_money', 'AIRTEL', ['event' => 'payment.succeeded', 'payment' => ['id' => $refOpT, 'reference' => $refT . '-1', 'amount' => 1500, 'currency' => 'XAF', 'status' => 'succeeded']]);
    verif('Confirmation tardive apres expiration : l\'argent est pris, la commande est payee', $code === 200 && $etat($oT) === 'paid', $etat($oT) . ' ' . json_encode($rep));

    // Echec annonce apres une reussite (ordre inverse) : ignore
    [$code, $rep] = $callback('airtel_money', 'AIRTEL', ['event' => 'payment.failed', 'payment' => ['id' => $refOpT, 'reference' => $refT . '-1', 'amount' => 1500, 'currency' => 'XAF', 'status' => 'failed']]);
    verif('Echec recu apres la reussite : aucun retour arriere', $code === 200 && $etatTentative($tT) === 'succeeded' && $etat($oT) === 'paid');

    // Double encaissement
    $db->exec("UPDATE payment_intents SET status = 'pending', completed_at = NULL WHERE id = {$tT}");
    $db->prepare("INSERT INTO payment_intents (order_id, attempt, gateway, amount, currency, status, gateway_ref, expires_at) VALUES (?, 2, 'moov_money', 1500, 'XAF', 'pending', ?, NOW() + INTERVAL 1 HOUR)")
       ->execute([$oT, 'MOV-DOUBLE' . strtoupper(bin2hex(random_bytes(5)))]);
    $tDouble = (int) $db->lastInsertId();
    $db->exec("UPDATE payment_intents SET status = 'succeeded' WHERE id = {$tT}");
    $issue = Paiements::appliquer($tDouble, 'succeeded', 1500, 'XAF', null, null, 'test');
    verif('Second encaissement d\'une commande payee : mis en revue, pas absorbe', $issue === 'revue' && $etatTentative($tDouble) === 'review'
        && $db->query("SELECT error_code FROM payment_intents WHERE id = {$tDouble}")->fetchColumn() === 'double_encaissement');

    echo "\n=== E. VISA : page hebergee, 3-D Secure, contestation ===\n";
    $soumettre = static function (string $url, array $champs): array {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($champs), CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false]);
        $r = (string) curl_exec($c);
        preg_match('/^Location:\s*(\S+)/mi', $r, $m);
        return [(int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), $m[1] ?? ''];
    };

    [$oV, $refV] = $nouvelleCommande();
    $r = $payer($refV, 'visa', null);
    $tV = (int) $r['tentative']['id'];
    $page = (string) $r['tentative']['redirection'];
    verif('Initiation VISA : redirection vers la page de l\'acquereur', str_starts_with($page, 'http://127.0.0.1:9103/hosted/VIS-'), $page);
    $html = (string) file_get_contents($page);
    verif('... la page de l\'acquereur porte le formulaire de carte', str_contains($html, 'name="card"'));
    [$code, $vers] = $soumettre($page . '/submit', ['card' => '4111 1111 1111 1111', 'exp' => '12/30', 'cvc' => '123', 'name' => 'TEST']);
    verif('... carte acceptee : retour chez Tchadok', $code === 303 && str_contains($vers, '/paiement.php?commande=' . $refV), $vers);
    verif('... le retour seul ne paie pas : c\'est le callback qui le fait', attendre(fn() => $etat($oV) === 'paid'), $etat($oV));

    [$oW, $refW] = $nouvelleCommande();
    $pageW = (string) $payer($refW, 'visa', null)['tentative']['redirection'];
    [$code, $vers] = $soumettre($pageW . '/submit', ['card' => '4000000000003220', 'exp' => '12/30', 'cvc' => '123', 'name' => 'TEST']);
    verif('Carte 3-D Secure : defi demande', $code === 303 && str_ends_with($vers, '/3ds'), $vers);
    [$code] = $soumettre($pageW . '/3ds', ['code' => '123456']);
    verif('... code 123456 : paiement confirme', $code === 303 && attendre(fn() => $etat($oW) === 'paid'), $etat($oW));

    [$oX, $refX] = $nouvelleCommande();
    $rX = $payer($refX, 'visa', null);
    $soumettre($rX['tentative']['redirection'] . '/submit', ['card' => '4000000000000002', 'exp' => '12/30', 'cvc' => '123', 'name' => 'TEST']);
    verif('Carte refusee par l\'emetteur : echec card_declined', attendre(fn() => $etatTentative((int) $rX['tentative']['id']) === 'failed')
        && Paiements::suivre((int) $rX['tentative']['id'], $acheteur)['message'] === 'Carte refusee par votre banque.');

    $rX2 = $payer($refX, 'visa', null);
    verif('Nouvel essai VISA apres un refus : nouvelle page', $rX2['succes'] && $rX2['tentative']['redirection'] !== $rX['tentative']['redirection']);
    $rX3 = $payer($refX, 'visa', null);
    verif('Retour sur VISA : la meme session est rouverte, sans en creer une autre',
        (int) ($rX3['tentative']['id'] ?? 0) === (int) $rX2['tentative']['id']);
    $rX4 = $payer($refX, 'airtel_money', '66000001');
    verif('Page VISA abandonnee au profit d\'Airtel Money : l\'ancienne tentative est remplacee',
        $rX4['succes'] && $etatTentative((int) $rX2['tentative']['id']) === 'cancelled'
        && $db->query('SELECT error_code FROM payment_intents WHERE id = ' . (int) $rX2['tentative']['id'])->fetchColumn() === 'remplacee');
    verif('... et la commande est payee par Airtel Money', attendre(fn() => $etat($oX) === 'paid'), $etat($oX));

    [$oD, $refD] = $nouvelleCommande();
    $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
    $rD = $payer($refD, 'visa', null);
    $soumettre($rD['tentative']['redirection'] . '/submit', ['card' => '4000000000000259', 'exp' => '12/30', 'cvc' => '123', 'name' => 'TEST']);
    verif('Carte a contestation : d\'abord payee', attendre(fn() => $etat($oD) === 'paid') && $droits() === 1);
    verif('... puis contestee : commande disputed', attendre(fn() => $etat($oD) === 'disputed', 12), $etat($oD));
    verif('... et droit d\'acces retire (revoque, pas supprime)', $droits() === 0
        && (int) $db->query("SELECT COUNT(*) FROM entitlements WHERE user_id = {$acheteur} AND revoked_at IS NOT NULL")->fetchColumn() === 1);

    [$oR, $refR] = $nouvelleCommande();
    $payer($refR, 'airtel_money', '66000001');
    verif('Rachat du titre apres la contestation : l\'acces est retabli (et non bloque par le droit revoque)',
        attendre(fn() => $etat($oR) === 'paid') && Commandes::aLeDroit($acheteur, 'track', $titre)
        && (int) $db->query("SELECT downloads_used FROM entitlements WHERE user_id = {$acheteur} AND item_id = {$titre}")->fetchColumn() === 0);

    $cartes = ['4111111111111111', '4000000000003220', '4000000000000002', '4000000000000259', '4111 1111'];
    $fuite = 0;
    foreach ($cartes as $numero) {
        $fuite += (int) $db->query('SELECT COUNT(*) FROM payment_events WHERE payload LIKE ' . $db->quote('%' . $numero . '%'))->fetchColumn();
    }
    verif('Aucun numero de carte dans payment_events', $fuite === 0, (string) $fuite);
    $fichiersSim = implode('', array_map('file_get_contents', glob($racine . '/mock-gateways/storage/visa/*.json') ?: []));
    verif('... ni dans le stockage du simulateur', !str_contains($fichiersSim, '4111111111111111') && !str_contains($fichiersSim, '4000000000000259'));
    verif('Aucun champ de carte dans le code de l\'application',
        !preg_match('/name=["\'](card|cvc|cvv|pan)["\']/i', $source('includes/paiement/Paiements.php') . $source('includes/paiement/PasserelleGenerique.php') . (is_file($racine . '/paiement.php') ? $source('paiement.php') : '')));

    echo "\n=== G. Dollar US (diaspora) ===\n";
    // Sans connexion simulee : le taux de secours (600) rend les montants
    // previsibles. Le cours automatique a son propre test (pay-devises.php).
    $memoDevises = Devises::fichierMemo('USD');
    $memoDevisesAvant = is_file($memoDevises) ? file_get_contents($memoDevises) : null;
    @unlink($memoDevises);
    Devises::utiliserSource(static fn (string $devise): ?array => null);
    verif('Sans connexion : 1 USD = 600 XAF (secours)', Devises::taux('USD') === 600.0 && Devises::origine('USD') === 'secours', (string) Devises::taux('USD'));
    verif('Conversion exacte : 1 500 XAF = 2,50 USD (250 cents)', Devises::convertir(1500, 'USD') === 2.5 && Devises::enUnitesMineures(2.5, 'USD') === 250);
    verif('Arrondi au cent superieur : 1 000 XAF = 1,67 USD', Devises::convertir(1000, 'USD') === 1.67);
    verif('Devise inconnue refusee', Devises::convertir(1000, 'EUR') === null);
    [$oM, $refM] = $nouvelleCommande();
    $r = Paiements::initier($refM, $acheteur, 'moov_money', '65000001', 'USD');
    verif('Mobile money en USD refuse', $r['succes'] === false && str_contains((string) $r['erreur'], 'USD'), json_encode($r));

    $r = Paiements::initier($refM, $acheteur, 'visa', null, 'USD');
    $tU = (int) ($r['tentative']['id'] ?? 0);
    $ligne = $db->query("SELECT amount, currency, amount_xaf, exchange_rate FROM payment_intents WHERE id = {$tU}")->fetch(PDO::FETCH_ASSOC);
    verif('Tentative VISA en USD : 2,50 USD, taux 600 et 1 500 XAF figes',
        $r['succes'] && (float) $ligne['amount'] === 2.5 && $ligne['currency'] === 'USD'
        && (float) $ligne['amount_xaf'] === 1500.0 && (float) $ligne['exchange_rate'] === 600.0, json_encode($ligne));
    $requete = (string) $db->query("SELECT payload FROM payment_events WHERE intent_id = {$tU} AND direction = 'request' LIMIT 1")->fetchColumn();
    verif('... envoyee a l\'acquereur en cents : 250 USD', str_contains($requete, '"amount":250') && str_contains($requete, '"currency":"USD"'), $requete);
    verif('... la page de l\'acquereur affiche 2,50 $ US', str_contains((string) file_get_contents($r['tentative']['redirection']), '2,50 $ US'));
    $soumettre($r['tentative']['redirection'] . '/submit', ['card' => '4111111111111111', 'exp' => '12/30', 'cvc' => '123', 'name' => 'DIASPORA']);
    verif('... paiement confirme : commande payee', attendre(fn() => $etat($oM) === 'paid'), $etat($oM));
    verif('... la commande et la facture restent en XAF (1 500)',
        $db->query("SELECT CONCAT(currency, ' ', total) FROM orders WHERE id = {$oM}")->fetchColumn() === 'XAF 1500.00');

    [$oU2, $refU2] = $nouvelleCommande();
    $tU2 = (int) Paiements::initier($refU2, $acheteur, 'visa', null, 'USD')['tentative']['id'];
    verif('Callback annoncant 1 500 USD (confusion avec les francs) : revue',
        Paiements::appliquer($tU2, 'succeeded', 1500, 'USD', null, null, 'test') === 'revue');
    [$oU3, $refU3] = $nouvelleCommande();
    $tU3 = (int) Paiements::initier($refU3, $acheteur, 'visa', null, 'USD')['tentative']['id'];
    verif('Callback au bon montant mais en XAF au lieu d\'USD : revue',
        Paiements::appliquer($tU3, 'succeeded', 250, 'XAF', null, null, 'test') === 'revue');

    $essai = static function () use ($db): bool {
        try {
            $db->exec("UPDATE exchange_rates SET xaf_per_unit = 1 WHERE currency = 'USD'");
            return false;
        } catch (Throwable $e) {
            return str_contains($e->getMessage(), 'historique');
        }
    };
    verif('Historique des taux immuable (modification refusee par la base)', $essai());
    verif('Taux nul ou negatif refuse, sans ecriture', !Devises::definir('USD', 0, 'test') && !Devises::definir('USD', -5, 'test'));
    verif('Taux sans motif refuse', !Devises::definir('USD', 610, '  '));
    $sortie = [];
    exec(sprintf('"%s" "%s/scripts/devises.php" definir USD 610 2>&1', $php, $racine), $sortie, $code);
    verif('scripts/devises.php exige un motif', $code !== 0 && str_contains(implode(' ', $sortie), 'Motif obligatoire'));
    verif('Taux toujours a 600 apres ces refus', (int) $db->query("SELECT COUNT(*) FROM exchange_rates WHERE currency = 'USD'")->fetchColumn() >= 1 && Devises::taux('USD') === 600.0);
    Devises::utiliserSource(null);
    $memoDevisesAvant === null ? @unlink($memoDevises) : file_put_contents($memoDevises, $memoDevisesAvant);

    echo "\n=== H. Console de pilotage (PAY-09) ===\n";
    $console = static function (string $chemin, ?array $champs = null, array $entetes = []): array {
        $c = curl_init('http://127.0.0.1:9100' . $chemin);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $entetes]);
        if ($champs !== null) {
            curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($champs)]);
        }
        $r = (string) curl_exec($c);
        return [(int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), $r];
    };
    [$code, $page] = $console('/');
    verif('Console accessible en local', $code === 200 && str_contains($page, 'Console des simulateurs'), (string) $code);
    $jetonConsole = trim((string) @file_get_contents($racine . '/mock-gateways/storage/console-jeton'));

    [$oC, $refC] = $nouvelleCommande();
    $tC = (int) $payer($refC, 'airtel_money', '66000005')['tentative']['id'];
    $idC = (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$tC}")->fetchColumn();
    $champsC = ['passerelle' => 'airtel_money', 'id' => $idC, 'action' => 'valider'];

    [$code] = $console('/action', $champsC);
    verif('Action sans jeton : 403', $code === 403, (string) $code);
    [$code] = $console('/action', $champsC + ['jeton' => $jetonConsole], ['Origin: https://site-hostile.example']);
    verif('Action avec jeton mais depuis une autre origine : 403', $code === 403, (string) $code);
    [$code, $page] = $console('/t/airtel_money/' . $idC);
    verif('Detail d\'une transaction : actions d\'une transaction en attente proposees', $code === 200 && str_contains($page, 'value="valider"'));

    [$code] = $console('/action', $champsC + ['jeton' => $jetonConsole]);
    verif('Valider depuis la console : la commande est payee', $code === 303 && attendre(fn() => $etat($oC) === 'paid'), $etat($oC));
    $factures = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM payment_events WHERE intent_id = {$tC} AND direction = 'callback'")->fetchColumn();
    $avantRejeu = $factures();
    $console('/action', ['passerelle' => 'airtel_money', 'id' => $idC, 'action' => 'rejouer', 'jeton' => $jetonConsole]);
    verif('Rejouer le callback : recu, sans second effet', attendre(fn() => $factures() === $avantRejeu + 1) && $etat($oC) === 'paid'
        && Commandes::aLeDroit($acheteur, 'track', $titre));
    $journalC = Magasin_journal($racine, $idC);
    verif('... la console voit la reponse 200 de Tchadok', ($journalC[0]['http'] ?? 0) === 200, json_encode($journalC[0] ?? null));
    $console('/action', ['passerelle' => 'airtel_money', 'id' => $idC, 'action' => 'mal_signe', 'jeton' => $jetonConsole]);
    verif('Callback mal signe depuis la console : rejete (401)', attendre(fn() => (Magasin_journal($racine, $idC)[0]['http'] ?? 0) === 401));

    [$oA, $refA] = $nouvelleCommande();
    $tA = (int) $payer($refA, 'airtel_money', '66000001')['tentative']['id'];
    $idA = (string) $db->query("SELECT gateway_ref FROM payment_intents WHERE id = {$tA}")->fetchColumn();
    $console('/action', ['passerelle' => 'airtel_money', 'id' => $idA, 'action' => 'abandonner', 'jeton' => $jetonConsole]);
    usleep(1500000);
    verif('Abandonner : le callback programme ne part pas', $etatTentative($tA) === 'pending');

    $console('/reglages', ['facteur_delai' => '0.2', 'taux_echec' => '0', 'indisponibles' => ['gimac'], 'jeton' => $jetonConsole]);
    [$oI, $refI] = $nouvelleCommande();
    $r = $payer($refI, 'gimac', '62000001');
    verif('Panne simulee de GIMAC : paiement refuse avec un message clair', $r['succes'] === false && str_contains((string) $r['erreur'], 'indisponible'), json_encode($r));
    $console('/reglages', ['facteur_delai' => '0.2', 'taux_echec' => '0', 'jeton' => $jetonConsole]);
    verif('... et retablie : paiement accepte', $payer($refI, 'gimac', '62000001')['succes']);

    echo "\n=== F. Points d'entree ===\n";
    $c = curl_init(rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE'), '/') . '/api/payments/initier.php');
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_RETURNTRANSFER => true]);
    curl_exec($c);
    $codeInit = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    verif('initier.php sans session ni jeton : refuse', in_array($codeInit, [401, 403], true), (string) $codeInit);
    $c = curl_init(rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE'), '/') . '/api/payments/suivi.php?tentative=' . $t1);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true]);
    curl_exec($c);
    verif('suivi.php sans session : 401', (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE) === 401);
    verif('suivre() refuse la tentative d\'un autre membre', Paiements::suivre($t1, $userArtiste) === null);
    foreach (['mock-gateways/distributeur.php', 'mock-gateways/storage/reglages.json'] as $interdit) {
        $c = curl_init(rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE'), '/') . '/' . $interdit);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true]);
        curl_exec($c);
        $codeWeb = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        verif("{$interdit} inaccessible par le web", in_array($codeWeb, [403, 404], true), (string) $codeWeb);
    }
    verif('Le lanceur n\'ecoute que sur 127.0.0.1', !str_contains($source('scripts/mock-gateways.bat'), '0.0.0.0')
        && substr_count($source('scripts/mock-gateways.bat'), '-S 127.0.0.1:') === 5);
} finally {
    // Remise en etat : reglages des simulateurs, processus, donnees d'essai.
    if ($reglagesAvant === null) {
        @unlink($fichierReglages);
    } else {
        file_put_contents($fichierReglages, $reglagesAvant);
    }
    foreach ($processus as $p) {
        if (is_resource($p)) {
            $etatProc = proc_get_status($p);
            exec('taskkill /PID ' . (int) $etatProc['pid'] . ' /T /F >NUL 2>&1');
        }
    }

    if ($commandes) {
        $liste = implode(',', array_map('intval', $commandes));
        $db->exec("DELETE FROM entitlements WHERE user_id = {$acheteur}");
        $db->exec("DELETE FROM payment_intents WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec("DELETE FROM tracks WHERE id = {$titre}");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    $db->exec("DELETE FROM users WHERE id IN ({$acheteur}, {$userArtiste})");
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
