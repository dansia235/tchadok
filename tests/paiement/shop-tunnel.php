<?php
/**
 * Tests LOT 6 : panier, passage en commande, facture, droits d'acces,
 * bibliotheque (SHOP-01 a SHOP-04).
 *
 * Passe par les vraies pages (Apache, session, jeton CSRF), et par les
 * simulateurs de paiement pour l'encaissement.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\shop-tunnel.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';
require_once $racine . '/includes/media-access.php';
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

/** Navigateur minimal : cookies, jeton CSRF de la derniere page. */
final class Navigateur
{
    private string $cookies;
    public string $jeton = '';

    public function __construct(private readonly string $base)
    {
        $this->cookies = (string) tempnam(sys_get_temp_dir(), 'shop');
    }

    /** @return array{0:int, 1:string, 2:string} code, corps, redirection */
    public function aller(string $chemin, ?array $post = null, ?string $json = null): array
    {
        $h = curl_init($this->base . $chemin);
        $entetes = [];
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies]);
        if ($json !== null) {
            $entetes = ['Content-Type: application/json', 'X-CSRF-Token: ' . $this->jeton];
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json]);
        } elseif ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post + ['csrf_token' => $this->jeton])]);
        }
        curl_setopt($h, CURLOPT_HTTPHEADER, $entetes);
        $brut = (string) curl_exec($h);
        $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
        $enTete = substr($brut, 0, $taille);
        $corps = substr($brut, $taille);
        if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $corps, $m)) {
            $this->jeton = $m[1];
        }
        preg_match('/^Location:\s*(\S+)/mi', $enTete, $l);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, $l[1] ?? ''];
    }

    public function fin(): void
    {
        @unlink($this->cookies);
    }
}

$db = TchadokDatabase::getInstance()->getConnection();
$php = PHP_BINARY;
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');

// Simulateurs pour l'encaissement
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

// Donnees d'essai, identifiants attribues par la base
$marque = 'zzshop' . bin2hex(random_bytes(3));
$motDePasse = 'Essai-Shop-2026!';
$creerCompte = static function (string $suffixe) use ($db, $marque, $motDePasse): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Essai', 'Boutique', 1, 1)")
       ->execute([$marque . $suffixe, $marque . $suffixe . '@test.local', password_hash($motDePasse, PASSWORD_BCRYPT)]);
    return (int) $db->lastInsertId();
};
$acheteur = $creerCompte('a');
$autre = $creerCompte('b');
$userArtiste = $creerCompte('c');
$db->prepare("INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)")->execute([$userArtiste, 'ZZSHOP ' . $marque]);
$artiste = (int) $db->lastInsertId();

$sortie = static function (string $titre, float $prix, int $parTitre) use ($db, $artiste, $marque): int {
    $db->prepare("INSERT INTO releases (artist_id, title, slug, format, price_bundle, allow_track_buy, status, release_date) VALUES (?, ?, ?, 'ep', ?, ?, 'approved', CURDATE())")
       ->execute([$artiste, $titre, $marque . '-' . bin2hex(random_bytes(3)), $prix, $parTitre]);
    return (int) $db->lastInsertId();
};
$titre = static function (string $nom, float $prix, ?int $releaseId, int $gratuit = 0, string $statut = 'approved') use ($db, $artiste): int {
    $db->prepare("INSERT INTO tracks (artist_id, release_id, title, audio_file, duration, is_free, price, status) VALUES (?, ?, ?, 'x.mp3', 200, ?, ?, ?)")
       ->execute([$artiste, $releaseId, $nom, $gratuit, $prix, $statut]);
    return (int) $db->lastInsertId();
};

$ep = $sortie('ZZSHOP EP', 2000, 1);
$t1 = $titre('ZZSHOP Titre 1', 300, $ep);
$t2 = $titre('ZZSHOP Titre 2', 300, $ep);
$tGratuit = $titre('ZZSHOP Gratuit', 0, null, 1);
$tBrouillon = $titre('ZZSHOP Brouillon', 300, null, 0, 'draft');
$lot = $sortie('ZZSHOP Lot indivisible', 1500, 0);
$tLot = $titre('ZZSHOP Titre du lot', 300, $lot);

$nav = new Navigateur($base);
$autreNav = new Navigateur($base);

try {
    echo "\n=== A. Regles du panier ===\n";
    $r = Panier::ajouter($acheteur, 'track', $t1);
    verif('Titre payant ajoute', $r['succes'], $r['message']);
    verif('Ajout en double : sans doublon', Panier::ajouter($acheteur, 'track', $t1)['succes'] && count(Panier::contenu($acheteur)['lignes']) === 1);
    verif('Titre gratuit refuse (il s\'ecoute sans achat)', !Panier::ajouter($acheteur, 'track', $tGratuit)['succes']);
    verif('Titre non publie refuse', !Panier::ajouter($acheteur, 'track', $tBrouillon)['succes']);
    $r = Panier::ajouter($acheteur, 'track', $tLot);
    verif('Titre d\'une sortie vendue en bloc refuse', !$r['succes'] && str_contains($r['message'], 'sortie complete'), $r['message']);
    $r = Panier::ajouter($acheteur, 'release', $ep);
    verif('Ajouter la sortie retire son titre deja present', $r['succes'] && str_contains($r['message'], 'retire'), $r['message']);
    $r = Panier::ajouter($acheteur, 'track', $t2);
    verif('Titre deja couvert par la sortie du panier : refuse', !$r['succes'] && str_contains($r['message'], 'inclus'), $r['message']);
    $contenu = Panier::contenu($acheteur);
    verif('Total relu en base : 2 000 FCFA', $contenu['total'] === 2000.0 && count($contenu['lignes']) === 1, json_encode($contenu['lignes']));

    $db->exec("UPDATE releases SET price_bundle = 2500 WHERE id = {$ep}");
    $r = Panier::commander($acheteur);
    verif('Prix modifie depuis l\'ajout : commande refusee, client averti', !$r['succes'] && str_contains(implode(' ', $r['avertissements']), '2 500'), json_encode($r));
    verif('... et le panier porte le nouveau prix', Panier::contenu($acheteur)['total'] === 2500.0);

    echo "\n=== B. Parcours web : visiteur, connexion, fusion ===\n";
    [$code] = $nav->aller('/panier.php');
    verif('Panier accessible au visiteur', $code === 200);
    [$code, $corps] = $nav->aller('/api/panier.php', null, json_encode(['action' => 'ajouter', 'type' => 'track', 'id' => $t1, 'prix' => 1, 'price' => 1]));
    $rep = json_decode($corps, true);
    verif('Visiteur : ajout accepte, prix envoye ignore', $code === 200 && ($rep['nombre'] ?? 0) === 1, $corps);
    [$code] = $nav->aller('/api/panier.php', ['action' => 'ajouter', 'type' => 'track', 'id' => $t1], null);
    [$code, , $vers] = $nav->aller('/login.php?redirect=' . rawurlencode('/panier.php'), ['email' => $marque . 'b@test.local', 'password' => $motDePasse, 'redirect' => '/panier.php']);
    verif('Connexion : retour au panier', $code === 302 && str_ends_with($vers, '/panier.php'), $vers);
    [$code, $corps] = $nav->aller('/panier.php');
    verif('Le panier du visiteur a rejoint son compte', $code === 200 && str_contains($corps, 'ZZSHOP Titre 1'));
    $ligne = $db->query("SELECT oi.unit_price FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.user_id = {$autre} AND o.status = 'cart' AND oi.item_id = {$t1}")->fetchColumn();
    verif('... au prix de la base (300), pas a celui envoye (1)', (float) $ligne === 300.0, (string) $ligne);
    $nav->aller('/logout.php', []);
    $nav->aller('/login.php');
    $nav->aller('/login.php', ['email' => $marque . 'b@test.local', 'password' => $motDePasse]);
    [, $corps] = $nav->aller('/panier.php');
    verif('Le panier survit a une deconnexion', str_contains($corps, 'ZZSHOP Titre 1'));

    [$code, , $vers] = $nav->aller('/panier.php', ['action' => 'commander']);
    $refCommande = preg_match('/commande=(TCHK-[0-9A-F-]+)/', urldecode($vers), $m) ? $m[1] : '';
    verif('Passer au paiement : commande figee, direction paiement.php', $code === 302 && $refCommande !== '', $vers);
    verif('... et un nouveau panier vide', Panier::contenu($autre)['lignes'] === []);
    [$code, $corps] = $nav->aller('/paiement.php?commande=' . $refCommande);
    verif('Page de paiement de la commande', $code === 200 && str_contains($corps, '300 FCFA'));
    [$code] = $autreNav->aller('/paiement.php?commande=' . $refCommande);
    verif('La commande d\'autrui est invisible (redirection vers la connexion)', $code === 302);

    echo "\n=== C. Encaissement, droits, facture ===\n";
    $r = Panier::commander($acheteur);
    verif('Seconde tentative apres avertissement : commande passee', $r['succes'], json_encode($r));
    $refEp = (string) $r['reference'];
    $paiement = Paiements::initier($refEp, $acheteur, 'airtel_money', '66000001');
    $idCommande = (int) $db->query('SELECT id FROM orders WHERE reference = ' . $db->quote($refEp))->fetchColumn();
    verif('Paiement Airtel : commande payee', $paiement['succes'] && attendre(fn() => $db->query("SELECT status FROM orders WHERE id = {$idCommande}")->fetchColumn() === 'paid'));
    $droits = $db->query("SELECT item_type, item_id FROM entitlements WHERE user_id = {$acheteur} AND revoked_at IS NULL ORDER BY item_type, item_id")->fetchAll(PDO::FETCH_NUM);
    verif('Achat de la sortie : un droit pour la sortie, un par titre', count($droits) === 3
        && in_array(['release', (string) $ep], $droits) && in_array(['track', (string) $t1], $droits) && in_array(['track', (string) $t2], $droits), json_encode($droits));
    $ligneTitre = $db->query("SELECT * FROM tracks WHERE id = {$t2}")->fetch(PDO::FETCH_ASSOC);
    verif('media.php ouvre le titre achete', MediaAccess::decider($db, $ligneTitre, $acheteur)['motif'] === 'achete');
    verif('... et pas a un autre membre', MediaAccess::decider($db, $ligneTitre, $autre)['acces'] !== 'complet');
    $r = Panier::ajouter($acheteur, 'track', $t2);
    verif('Racheter un titre possede : refuse', !$r['succes'] && str_contains($r['message'], 'possedez'), $r['message']);
    verif('Racheter la sortie possedee : refuse', !Panier::ajouter($acheteur, 'release', $ep)['succes']);

    $facture = (string) $db->query("SELECT invoice_number FROM orders WHERE id = {$idCommande}")->fetchColumn();
    $journalMail = (string) @file_get_contents($racine . '/storage/logs/mail.log');
    verif('E-mail de confirmation avec la facture', str_contains($journalMail, $facture) && str_contains($journalMail, $marque . 'a@test.local'));

    $navAcheteur = new Navigateur($base);
    $navAcheteur->aller('/login.php');
    $navAcheteur->aller('/login.php', ['email' => $marque . 'a@test.local', 'password' => $motDePasse]);
    [$code, $corps] = $navAcheteur->aller('/facture.php?commande=' . $refEp);
    verif('Facture consultable par l\'acheteur', $code === 200 && str_contains($corps, $facture) && str_contains($corps, 'Airtel Money') && str_contains($corps, '2 500 FCFA'));
    [$code] = $nav->aller('/facture.php?commande=' . $refEp);
    verif('... introuvable pour un autre membre (404)', $code === 404, (string) $code);
    [$code, $corps] = $navAcheteur->aller('/bibliotheque.php');
    verif('Bibliotheque : titres et sortie achetes, facture liee', $code === 200 && str_contains($corps, 'ZZSHOP Titre 2')
        && str_contains($corps, 'ZZSHOP EP') && str_contains($corps, $facture));

    echo "\n=== E. Livraison : journal et limitation par compte (SHOP-05) ===\n";
    $fichierTest = 'storage/uploads/audio/' . $marque . '.mp3';
    @mkdir(dirname($racine . '/' . $fichierTest), 0775, true);
    file_put_contents($racine . '/' . $fichierTest, 'ID3' . str_repeat("\0", 2048));
    $db->prepare('UPDATE tracks SET audio_file = ? WHERE id = ?')->execute([$fichierTest, $t2]);

    [, $corps] = $navAcheteur->aller('/api/track.php?action=resolve&id=' . $t2);
    $url = (string) (json_decode($corps, true)['data']['stream_url'] ?? json_decode($corps, true)['stream_url'] ?? '');
    verif('Le lecteur obtient une URL signee pour le titre achete', $url !== '', substr($corps, 0, 200));
    $cheminMedia = (string) preg_replace('#^https?://[^/]+/tchadok#', '', $url);
    [$code] = $navAcheteur->aller($cheminMedia);
    verif('Le titre achete est servi', $code === 200, (string) $code);
    $journal = $db->query("SELECT grant_reason FROM media_access_log WHERE user_id = {$acheteur} AND track_id = {$t2} ORDER BY id DESC LIMIT 1")->fetchColumn();
    verif('... et l\'ouverture est journalisee, avec son motif', $journal === 'achete', (string) $journal);
    [$code] = $autreNav->aller($cheminMedia);
    verif('L\'URL copiee dans un autre navigateur ne sert rien', $code === 403, (string) $code);

    $inserer = $db->prepare("INSERT INTO media_access_log (user_id, track_id, media_type, grant_reason, ip_address) VALUES (?, ?, 'audio', 'achete', '127.0.0.1')");
    for ($i = 0; $i < 120; $i++) {
        $inserer->execute([$acheteur, $t2]);
    }
    [$code] = $navAcheteur->aller($cheminMedia);
    verif('120 ouvertures en dix minutes : 429 (aspiration du catalogue)', $code === 429, (string) $code);
    $db->exec("DELETE FROM media_access_log WHERE user_id = {$acheteur}");
    [$code] = $navAcheteur->aller($cheminMedia);
    verif('... la limite porte sur le compte : une fois le compteur retombe, la lecture reprend', $code === 200, (string) $code);
    @unlink($racine . '/' . $fichierTest);
    $navAcheteur->fin();

    echo "\n=== D. Retour apres connexion : pas de redirection ouverte ===\n";
    foreach (['//site-hostile.example/x', 'https://site-hostile.example', '/\\site-hostile.example', '/../etc', 'javascript:alert(1)'] as $piege) {
        verif('Refuse : ' . $piege, destinationInterne($piege) === null);
    }
    verif('Accepte : /paiement.php?commande=TCHK-2026-ABCD1234', destinationInterne('/paiement.php?commande=TCHK-2026-ABCD1234') !== null);
    $navPiege = new Navigateur($base);
    $navPiege->aller('/login.php');
    [$code, , $vers] = $navPiege->aller('/login.php', ['email' => $marque . 'c@test.local', 'password' => $motDePasse, 'redirect' => '//site-hostile.example']);
    verif('Connexion avec redirect piege : retour a l\'accueil du site', $code === 302 && str_starts_with($vers, $base) && !str_contains($vers, 'hostile'), $vers);
    $navPiege->fin();
} finally {
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    $nav->fin();
    $autreNav->fin();

    $comptes = implode(',', [$acheteur, $autre, $userArtiste]);
    $db->exec("DELETE FROM entitlements WHERE user_id IN ({$comptes})");
    $db->exec("DELETE p FROM payment_intents p JOIN orders o ON o.id = p.order_id WHERE o.user_id IN ({$comptes})");
    $db->exec("DELETE i FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.user_id IN ({$comptes})");
    $db->exec("DELETE FROM orders WHERE user_id IN ({$comptes})");
    $db->exec("DELETE FROM tracks WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM releases WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    $db->exec("DELETE FROM user_sessions WHERE user_id IN ({$comptes})");
    $db->exec("DELETE FROM login_attempts WHERE identifier LIKE " . $db->quote($marque . '%'));
    try {
        $db->exec("DELETE FROM users WHERE id IN ({$comptes})");
    } catch (Throwable $e) {
        // Journal d'audit ou autre reference : le compte d'essai reste, inerte.
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
