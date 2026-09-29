<?php
/**
 * Service des fichiers audio - Tchadok Platform
 *
 * Tache SEC-06.
 *
 * Unique point de service des fichiers audio. Les fichiers eux-memes ne sont
 * plus accessibles directement : storage/ est bloque par .htaccess, et
 * uploads/audio porte sa propre interdiction.
 *
 *   media.php?id=<titre>&t=audio|preview&exp=<horodatage>&sig=<signature>
 *
 * Les URL sont emises par api/track.php, uniquement si l'acces est accorde.
 * Chaque requete reverifie :
 *   1. la signature, son expiration et sa liaison a la session ;
 *   2. le droit d'acces, recalcule depuis la base -- une URL valide ne suffit
 *      pas si le droit a disparu entre-temps (remboursement, expiration de
 *      l'abonnement, titre depublie) ;
 *   3. que le fichier est bien dans un repertoire de medias autorise.
 *
 * Supporte les requetes partielles (Range), indispensables a la lecture en
 * continu et a l'avance rapide.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/media-access.php';

/**
 * Reponse d'erreur. Volontairement sobre : aucun detail sur la raison exacte
 * du refus n'est renvoye au client, pour ne pas aider a forger une URL.
 */
function refuser(int $code, string $motifJournal): never
{
    error_log(sprintf(
        '[Tchadok][media] refus %d : %s (id=%s, t=%s, ip=%s)',
        $code,
        $motifJournal,
        $_GET['id'] ?? '-',
        $_GET['t'] ?? '-',
        function_exists('clientIp') ? clientIp() : ($_SERVER['REMOTE_ADDR'] ?? '-')
    ));
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo match ($code) {
        403     => "Acces refuse.\n",
        404     => "Media introuvable.\n",
        416     => "Plage demandee invalide.\n",
        default => "Requete invalide.\n",
    };
    exit;
}

// ---------------------------------------------------------------------
// 1. Parametres et signature
// ---------------------------------------------------------------------
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    refuser(405, 'methode non autorisee');
}

$trackId    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$type       = (string) ($_GET['t'] ?? '');
$expiration = filter_input(INPUT_GET, 'exp', FILTER_VALIDATE_INT);
$signature  = (string) ($_GET['sig'] ?? '');

if (!$trackId || $expiration === false || $expiration === null || $signature === '') {
    refuser(400, 'parametres manquants');
}

try {
    $erreurSignature = MediaAccess::verifier($trackId, $type, $expiration, $signature);
} catch (Throwable $e) {
    error_log('[Tchadok][media] ' . $e->getMessage());
    refuser(503, 'signature impossible a verifier');
}
if ($erreurSignature !== null) {
    refuser(403, $erreurSignature);
}

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;

// Liberer le verrou de session AVANT de servir le fichier.
// PHP verrouille le fichier de session pendant toute la requete : sans cette
// ligne, toute autre page ouverte par l'utilisateur resterait bloquee tant
// que la musique joue -- le site paraitrait fige pendant l'ecoute.
session_write_close();

// ---------------------------------------------------------------------
// 2. Droit d'acces, recalcule depuis la base
// ---------------------------------------------------------------------
$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    refuser(503, 'base indisponible');
}

$stmt = $db->prepare(
    'SELECT id, status, is_free, artist_id, album_id, release_id, audio_file, preview_file
       FROM tracks WHERE id = ? LIMIT 1'
);
$stmt->execute([$trackId]);
$titre = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$titre) {
    refuser(404, 'titre inexistant');
}

$decision = MediaAccess::decider($db, $titre, $userId);

if ($type === MediaAccess::TYPE_AUDIO) {
    if ($decision['acces'] !== MediaAccess::ACCES_COMPLET) {
        refuser(403, 'acces complet refuse : ' . $decision['motif']);
    }
    $valeurFichier = (string) $titre['audio_file'];
} else {
    if (!in_array($decision['acces'], [MediaAccess::ACCES_COMPLET, MediaAccess::ACCES_EXTRAIT], true)) {
        refuser(403, 'extrait refuse : ' . $decision['motif']);
    }
    $valeurFichier = (string) $titre['preview_file'];
}

// ---------------------------------------------------------------------
// 3. Resolution securisee du fichier
// ---------------------------------------------------------------------
$chemin = MediaAccess::resoudreFichier($valeurFichier);
if ($chemin === null) {
    refuser(404, 'fichier absent, externe ou hors des repertoires autorises');
}

$taille = filesize($chemin);
if ($taille === false || $taille === 0) {
    refuser(404, 'fichier vide ou illisible');
}

// ---------------------------------------------------------------------
// 3 bis. Journal et limitation par compte (SHOP-05)
// ---------------------------------------------------------------------
// Seule l'OUVERTURE d'un fichier compte (pas de Range, ou Range depuis 0) :
// une ecoute avec avance rapide emet plusieurs requetes partielles.
$ouverture = !isset($_SERVER['HTTP_RANGE']) || preg_match('/^bytes=0-/', trim((string) $_SERVER['HTTP_RANGE']));
if ($ouverture) {
    $adresse = function_exists('clientIp') ? clientIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    try {
        // Une ecoute humaine n'ouvre pas 120 titres en dix minutes ; une
        // aspiration du catalogue, si. Par compte, par adresse pour un visiteur.
        $compte = $userId !== null
            ? $db->prepare('SELECT COUNT(*) FROM media_access_log WHERE user_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE')
            : $db->prepare('SELECT COUNT(*) FROM media_access_log WHERE user_id IS NULL AND ip_address = ? AND created_at > NOW() - INTERVAL 10 MINUTE');
        $compte->execute([$userId ?? $adresse]);
        if ((int) $compte->fetchColumn() >= (int) EnvLoader::get('MEDIA_OUVERTURES_MAX', '120')) {
            error_log(sprintf('[Tchadok][media][ALERTE] ouvertures saturees : compte %s, adresse %s', $userId ?? 'anonyme', $adresse));
            LimiteDebit::refuser('lecture-fichier', 600);
        }

        $db->prepare('INSERT INTO media_access_log (user_id, track_id, media_type, grant_reason, ip_address) VALUES (?, ?, ?, ?, ?)')
           ->execute([$userId, $trackId, substr($type, 0, 20), substr((string) $decision['motif'], 0, 30), substr($adresse, 0, 45)]);

        // Conservation 90 jours : purge une ouverture sur cinq cents, pour ne
        // pas payer un DELETE a chaque ecoute.
        if (random_int(1, 500) === 1) {
            $db->exec('DELETE FROM media_access_log WHERE created_at < NOW() - INTERVAL 90 DAY');
        }
    } catch (Throwable $e) {
        // Journal indisponible (migration non appliquee) : on sert quand
        // meme, et on le signale -- la lecture ne doit pas tomber pour ca.
        error_log('[Tchadok][media] journal indisponible : ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// 4. En-tetes communs
// ---------------------------------------------------------------------
$mime = MediaAccess::typeMime($chemin);
$dernierModif = filemtime($chemin) ?: time();
$etag = '"' . substr(sha1($chemin . '|' . $taille . '|' . $dernierModif), 0, 20) . '"';

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
// Lecture dans le navigateur, pas de telechargement force.
header('Content-Disposition: inline');
// Reponse personnelle : jamais en cache partage (proxy, CDN).
header('Cache-Control: private, max-age=3600');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $dernierModif) . ' GMT');

// ---------------------------------------------------------------------
// 5. Requete partielle (Range)
// ---------------------------------------------------------------------
$debut = 0;
$fin   = $taille - 1;
$partiel = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
        header('Content-Range: bytes */' . $taille);
        refuser(416, 'en-tete Range mal forme');
    }

    if ($m[1] === '' && $m[2] === '') {
        header('Content-Range: bytes */' . $taille);
        refuser(416, 'plage vide');
    }

    if ($m[1] === '') {
        // Suffixe : les N derniers octets
        $debut = max(0, $taille - (int) $m[2]);
    } else {
        $debut = (int) $m[1];
        if ($m[2] !== '') {
            $fin = min((int) $m[2], $taille - 1);
        }
    }

    if ($debut > $fin || $debut >= $taille) {
        header('Content-Range: bytes */' . $taille);
        refuser(416, 'plage hors du fichier');
    }

    $partiel = true;
}

$longueur = $fin - $debut + 1;

if ($partiel) {
    http_response_code(206);
    header(sprintf('Content-Range: bytes %d-%d/%d', $debut, $fin, $taille));
} else {
    http_response_code(200);
}
header('Content-Length: ' . $longueur);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

// ---------------------------------------------------------------------
// 6. Envoi
// ---------------------------------------------------------------------
// Si mod_xsendfile est installe (voir docs/exploitation/pre-requis.md),
// deleguer le transfert au serveur web libere PHP immediatement.
// Il n'est pas fourni avec XAMPP : repli sur un envoi par blocs.
if (strtolower((string) env('MEDIA_SENDFILE', '')) === 'xsendfile' && !$partiel) {
    header('X-Sendfile: ' . $chemin);
    exit;
}

@set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}

$flux = fopen($chemin, 'rb');
if ($flux === false) {
    error_log('[Tchadok][media] ouverture impossible : ' . $chemin);
    exit;
}

fseek($flux, $debut);
$restant = $longueur;
$bloc = 64 * 1024;

while ($restant > 0 && !feof($flux) && connection_status() === CONNECTION_NORMAL) {
    $lu = fread($flux, (int) min($bloc, $restant));
    if ($lu === false || $lu === '') {
        break;
    }
    echo $lu;
    flush();
    $restant -= strlen($lu);
}

fclose($flux);
exit;
