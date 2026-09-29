<?php
/**
 * Tests LOT 7 : abonnements Premium.
 *
 * Criteres du plan : un changement de prix n'affecte pas les abonnements en
 * cours ; un paiement echoue n'active rien ; la resiliation ne coupe pas
 * l'acces immediatement ; aucun double abonnement actif simultane ; un
 * abonnement expire perd ses avantages sans reconnexion ; les droits issus
 * d'un achat ne sont pas touches ; chaque avantage annonce est reel.
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\sub-abonnements.php
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

$marque = 'zzsub' . bin2hex(random_bytes(3));
$motDePasse = 'Essai-Premium-2026!';
$creer = static function (string $s) use ($db, $marque, $motDePasse): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Abonne', 'Essai', 1, 1)")
       ->execute([$marque . $s, $marque . $s . '@test.local', password_hash($motDePasse, PASSWORD_BCRYPT)]);
    return (int) $db->lastInsertId();
};
$membre = $creer('a');
$autre = $creer('b');
$userArtiste = $creer('c');
$db->prepare("INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)")->execute([$userArtiste, 'ZZSUB ' . $marque]);
$artiste = (int) $db->lastInsertId();
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, preview_file, duration, is_free, price, status) VALUES (?, 'ZZSUB payant', 'x.mp3', 'y.mp3', 200, 0, 500, 'approved')")->execute([$artiste]);
$titre = (int) $db->lastInsertId();
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, preview_file, duration, is_free, price, status) VALUES (?, 'ZZSUB achete', 'x.mp3', 'y.mp3', 200, 0, 500, 'approved')")->execute([$artiste]);
$titreAchete = (int) $db->lastInsertId();

$reglerAirtel = static function (string $reference, int $userId, string $numero) use ($db): int {
    $id = (int) $db->query('SELECT id FROM orders WHERE reference = ' . $db->quote($reference))->fetchColumn();
    Paiements::initier($reference, $userId, 'airtel_money', $numero);
    attendre(fn() => in_array($db->query("SELECT status FROM orders WHERE id = {$id}")->fetchColumn(), ['paid', 'failed'], true));
    return $id;
};
$premium = static function (int $userId): bool {
    Abonnements::oublier();
    return Abonnements::estPremium($userId);
};
$lignesTitre = $db->query("SELECT * FROM tracks WHERE id = {$titre}")->fetch(PDO::FETCH_ASSOC);

try {
    echo "\n=== A. Plans (SUB-01) ===\n";
    $plans = Abonnements::plans();
    verif('Deux plans proposes, prix lus dans la grille', ($plans['premium_monthly']['prix'] ?? 0) === 2000.0 && ($plans['premium_annual']['prix'] ?? 0) === 20000.0, json_encode(array_column($plans, 'prix', 'code')));
    verif('Plan inconnu refuse', !Abonnements::souscrire($membre, 'premium_gratuit')['succes']);

    echo "\n=== B. Souscription et activation (SUB-02) ===\n";
    $r = Abonnements::souscrire($membre, 'premium_monthly');
    verif('Commande d\'abonnement creee', $r['succes'], json_encode($r));
    $o1 = $reglerAirtel((string) $r['reference'], $membre, '66000003');
    verif('Paiement echoue : aucun abonnement', $db->query("SELECT status FROM orders WHERE id = {$o1}")->fetchColumn() === 'failed'
        && !$premium($membre) && (int) $db->query("SELECT COUNT(*) FROM subscriptions WHERE user_id = {$membre}")->fetchColumn() === 0);
    $r2 = Abonnements::souscrire($membre, 'premium_monthly');
    verif('Nouvelle tentative : la meme commande est reprise', $r2['reference'] === $r['reference']);
    $reglerAirtel((string) $r['reference'], $membre, '66000001');
    verif('Paiement confirme : Premium actif', $premium($membre));
    $s1 = $db->query("SELECT * FROM subscriptions WHERE order_id = {$o1}")->fetch(PDO::FETCH_ASSOC);
    verif('Periode d\'un mois, calculee a l\'activation', abs(strtotime((string) $s1['end_date']) - strtotime('+1 month', strtotime((string) $s1['start_date']))) < 5
        && abs(strtotime((string) $s1['start_date']) - time()) < 120, $s1['start_date'] . ' -> ' . $s1['end_date']);
    verif('Cache du compte a jour', (int) $db->query("SELECT premium_status FROM users WHERE id = {$membre}")->fetchColumn() === 1);
    verif('Ecoute integrale des titres payants', MediaAccess::decider($db, $lignesTitre, $membre)['motif'] === 'premium');
    verif('Facture emise', str_starts_with((string) $db->query("SELECT invoice_number FROM orders WHERE id = {$o1}")->fetchColumn(), 'FAC-'));

    $db->exec("UPDATE pricing_rules SET suggested = 2500, min_price = 2500, max_price = 2500 WHERE scope = 'subscription' AND format = 'premium_monthly'");
    Tarifs::oublier();
    verif('Changement de prix : visible pour les nouvelles souscriptions', Abonnements::plans()['premium_monthly']['prix'] === 2500.0);
    verif('... sans toucher l\'abonnement en cours', (float) $db->query("SELECT amount FROM subscriptions WHERE order_id = {$o1}")->fetchColumn() === 2000.0);
    $db->exec("UPDATE pricing_rules SET suggested = 2000, min_price = 2000, max_price = 2000 WHERE scope = 'subscription' AND format = 'premium_monthly'");
    Tarifs::oublier();

    echo "\n=== C. Renouvellement sans chevauchement ===\n";
    $r = Abonnements::souscrire($membre, 'premium_monthly');
    verif('Renouvellement refuse a plus de 7 jours de l\'echeance', !$r['succes'] && str_contains((string) $r['erreur'], '7 jours'), json_encode($r));
    $db->exec("UPDATE subscriptions SET end_date = NOW() + INTERVAL 5 DAY WHERE order_id = {$o1}");
    $r = Abonnements::souscrire($membre, 'premium_annual');
    verif('Renouvellement ouvert a 5 jours de l\'echeance', $r['succes']);
    $o2 = $reglerAirtel((string) $r['reference'], $membre, '66000001');
    $fin1 = (string) $db->query("SELECT end_date FROM subscriptions WHERE order_id = {$o1}")->fetchColumn();
    $s2 = $db->query("SELECT * FROM subscriptions WHERE order_id = {$o2}")->fetch(PDO::FETCH_ASSOC);
    verif('La nouvelle periode commence a la fin de la precedente', $s2 && $s2['start_date'] === $fin1, ($s2['start_date'] ?? '?') . ' / ' . $fin1);
    verif('... et dure un an', $s2 && abs(strtotime((string) $s2['end_date']) - strtotime('+12 months', strtotime((string) $s2['start_date']))) < 5);
    $actives = (int) $db->query("SELECT COUNT(*) FROM subscriptions WHERE user_id = {$membre} AND status = 'active' AND start_date <= NOW() AND end_date > NOW()")->fetchColumn();
    verif('Une seule periode active a un instant donne', $actives === 1, (string) $actives);
    verif('Periode suivante deja payee : pas de troisieme souscription', !Abonnements::souscrire($membre, 'premium_monthly')['succes']);

    echo "\n=== D. Resiliation, echeance, achats preserves (SUB-03) ===\n";
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $membre]);
    $oAchat = (int) $db->lastInsertId();
    Commandes::ajouterArticle($oAchat, 'track', $titreAchete, 500.0, $artiste, 'ZZSUB achete');
    Commandes::marquerPayee($oAchat, 'ZZSUB-ACHAT-' . $oAchat, 'airtel_money');
    verif('Resiliation en un clic', Abonnements::resilier($membre));
    verif('... l\'acces n\'est pas coupe', $premium($membre));
    $db->exec("UPDATE subscriptions SET start_date = NOW() - INTERVAL 40 DAY, end_date = NOW() - INTERVAL 1 MINUTE WHERE user_id = {$membre}");
    verif('Echeance depassee : avantages perdus sans attendre la tache de nuit', !$premium($membre)
        && MediaAccess::decider($db, $lignesTitre, $membre)['acces'] !== 'full');
    $bilan = Abonnements::traiterEcheances();
    verif('Tache quotidienne : periodes closes', $bilan['expirees'] >= 2 && (int) $db->query("SELECT COUNT(*) FROM subscriptions WHERE user_id = {$membre} AND status = 'active'")->fetchColumn() === 0, json_encode($bilan));
    verif('... resiliee = arretee, non resiliee = terminee', (int) $db->query("SELECT COUNT(*) FROM subscriptions WHERE user_id = {$membre} AND status = 'cancelled'")->fetchColumn() >= 1);
    verif('... cache du compte remis a zero', (int) $db->query("SELECT premium_status FROM users WHERE id = {$membre}")->fetchColumn() === 0);
    verif('Le titre ACHETE reste accessible', Commandes::aLeDroit($membre, 'track', $titreAchete));

    echo "\n=== E. Rappels d'echeance ===\n";
    $r = Abonnements::souscrire($autre, 'premium_monthly');
    $o3 = $reglerAirtel((string) $r['reference'], $autre, '66000001');
    $db->exec("UPDATE subscriptions SET end_date = NOW() + INTERVAL 6 DAY WHERE order_id = {$o3}");
    Abonnements::traiterEcheances();
    $mails = (string) @file_get_contents($racine . '/storage/logs/mail.log');
    verif('Rappel J-7 envoye', substr_count($mails, $marque . 'b@test.local') >= 2 && str_contains($mails, 'se termine'));
    $avant = substr_count((string) file_get_contents($racine . '/storage/logs/mail.log'), 'votre Premium se termine');
    Abonnements::traiterEcheances();
    verif('... une seule fois', substr_count((string) file_get_contents($racine . '/storage/logs/mail.log'), 'votre Premium se termine') === $avant);

    echo "\n=== F. Remboursement et portefeuille ===\n";
    Remboursements::rembourser($o3, 'Souscription par erreur', null);
    verif('Abonnement rembourse : Premium arrete immediatement', !$premium($autre)
        && $db->query("SELECT status FROM subscriptions WHERE order_id = {$o3}")->fetchColumn() === 'cancelled');
    Portefeuille::ajuster($autre, 2000, 'Credit de test pour abonnement', null);
    $r = Abonnements::souscrire($autre, 'premium_monthly');
    $paye = Portefeuille::payer((string) $r['reference'], $autre);
    verif('Abonnement regle par le portefeuille : actif aussitot', $paye['succes'] && $premium($autre), json_encode($paye));

    echo "\n=== G. Avantages reels (SUB-04) ===\n";
    // La page telle que le PUBLIC la voit (le code source, lui, cite ces
    // anciennes promesses dans un commentaire qui explique leur retrait).
    $h = curl_init($base . '/premium.php');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $page = (string) curl_exec($h);
    foreach (['320 kbps', 'hors ligne', 'Téléchargements', 'essai pour', 'Ecobank', 'jusqu\'à 5 appareils', 'Sans publicité'] as $promesse) {
        verif("premium.php n'annonce plus « {$promesse} »", stripos($page, $promesse) === false);
    }
    foreach (Abonnements::AVANTAGES as $avantage) {
        verif('Avantage reel affiche : ' . $avantage['titre'], str_contains($page, $avantage['titre']));
    }
    verif('Les avantages affiches viennent de la liste verifiee', str_contains((string) file_get_contents($racine . '/premium.php'), 'Abonnements::AVANTAGES'));
    for ($i = 0; $i < Abonnements::limitePlaylists(); $i++) {
        $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)')->execute([$membre, 'ZZSUB ' . $i]);
    }
    $limite = Abonnements::peutCreerPlaylist($membre);
    verif('Compte gratuit : playlists limitees a ' . Abonnements::limitePlaylists(), !$limite['permis'] && str_contains((string) $limite['message'], 'Premium'));
    $db->prepare('INSERT INTO playlists (user_id, name, is_public) SELECT ?, name, 0 FROM playlists WHERE user_id = ?')->execute([$autre, $membre]);
    verif('Abonne au-dela de la limite : toujours permis', Abonnements::peutCreerPlaylist($autre)['permis']);

    Remboursements::demander($membre, (string) $db->query("SELECT reference FROM orders WHERE id = {$oAchat}")->fetchColumn(), 'autre', 'Gratuit');
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $autre]);
    $oPrio = (int) $db->lastInsertId();
    Commandes::ajouterArticle($oPrio, 'track', $titre, 500.0, $artiste, 'ZZSUB');
    Commandes::marquerPayee($oPrio, 'ZZSUB-PRIO-' . $oPrio, 'airtel_money');
    Remboursements::demander($autre, (string) $db->query("SELECT reference FROM orders WHERE id = {$oPrio}")->fetchColumn(), 'autre', 'Premium');
    $file = array_values(array_filter(Remboursements::aTraiter(), fn($d) => in_array((int) $d['order_id'], [$oAchat, $oPrio], true)));
    verif('Reclamation d\'un abonne traitee en premier', (int) ($file[0]['order_id'] ?? 0) === $oPrio && !empty($file[0]['prioritaire']), json_encode(array_column($file, 'order_id')));

    echo "\n=== H. Pages ===\n";
    $cookies = (string) tempnam(sys_get_temp_dir(), 'sub');
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
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $marque . 'b@test.local', 'password' => $motDePasse]);
    [$code, $page] = $http($base . '/abonnement.php');
    verif('Mon abonnement : statut et echeance', $code === 200 && str_contains($page, 'Premium actif') && str_contains($page, 'Resilier'));
    verif('Couronne Premium dans l\'en-tete (statut lu en base)', str_contains($page, 'fa-crown text-yellow-400'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/abonnement.php', ['csrf_token' => $m[1] ?? '', 'action' => 'resilier']);
    [, $page] = $http($base . '/abonnement.php');
    verif('Resiliation depuis la page : Premium maintenu jusqu\'a l\'echeance', str_contains($page, 'resilie') && $premium($autre));
    [$code, $page] = $http($base . '/premium.php');
    verif('Page Premium publique', $code === 200 && str_contains($page, 'Tout le catalogue en entier') && str_contains($page, 'GIMAC'));
    @unlink($cookies);
} finally {
    $reglagesAvant === null ? @unlink($fichierReglages) : file_put_contents($fichierReglages, $reglagesAvant);
    foreach ($processus as $p) {
        if (is_resource($p)) {
            exec('taskkill /PID ' . (int) proc_get_status($p)['pid'] . ' /T /F >NUL 2>&1');
        }
    }
    $db->exec("UPDATE pricing_rules SET suggested = 2000, min_price = 2000, max_price = 2000 WHERE scope = 'subscription' AND format = 'premium_monthly'");
    // Le compte « autre » a un journal de portefeuille (immuable) : il reste,
    // inerte. Le reste des donnees d'essai part.
    $comptes = implode(',', [$membre, $autre]);
    $db->exec("DELETE FROM playlists WHERE user_id IN ({$comptes})");
    $db->exec("DELETE FROM entitlements WHERE user_id IN ({$comptes})");
    $db->exec("DELETE FROM subscriptions WHERE user_id = {$membre}");
    $db->exec("DELETE ri FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id JOIN orders o ON o.id = r.order_id WHERE o.user_id = {$membre}");
    $db->exec("DELETE r FROM refunds r JOIN orders o ON o.id = r.order_id WHERE o.user_id = {$membre}");
    $db->exec("DELETE p FROM payment_intents p JOIN orders o ON o.id = p.order_id WHERE o.user_id = {$membre}");
    $db->exec("DELETE i FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.user_id = {$membre}");
    $db->exec("DELETE FROM orders WHERE user_id = {$membre}");
    $db->exec("DELETE FROM users WHERE id = {$membre}");
    $db->exec("UPDATE order_items SET artist_id = NULL WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM tracks WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    $db->exec("DELETE FROM users WHERE id = {$userArtiste}");
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
