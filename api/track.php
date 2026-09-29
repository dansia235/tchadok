<?php
/**
 * API Titres - Tchadok Platform
 *
 * Retourne les informations necessaires a la lecture d'un titre.
 *
 * SEC-06 : la reponse ne contient PLUS AUCUN chemin de fichier.
 *
 * Auparavant, audio_file et preview_file etaient renvoyes pour tous les
 * titres, payants compris, avec Access-Control-Allow-Origin "*". Le choix de
 * lire ou non etait fait dans le navigateur (assets/js/player.js) : lire la
 * reponse suffisait pour telecharger n'importe quel titre.
 *
 * Desormais la decision est prise ici, cote serveur, et la reponse porte :
 *   access          full | preview | none
 *   access_reason   gratuit, achete, premium, proprietaire, administrateur,
 *                   anonyme, payant, non_publie
 *   access_message  texte affichable en cas de refus
 *   stream_url      URL signee vers media.php, UNIQUEMENT si access = full
 *   preview_url     URL signee de l'extrait, si un extrait existe et que
 *                   l'acces le permet
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/media-access.php';
require_once __DIR__ . '/../includes/ecoutes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Aucun en-tete CORS : cette API sert le site lui-meme, et ses URL signees
// sont liees a la session du visiteur. L'ouvrir a toute origine permettait
// a n'importe quel site tiers d'interroger le catalogue au nom du visiteur.

function repondre(array $contenu, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    repondre(['success' => false, 'error' => ['message' => 'Methode non autorisee']], 405);
}

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    repondre(['success' => false, 'error' => ['message' => 'Service indisponible']], 503);
}

$action = (string) ($_GET['action'] ?? 'get');
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    repondre(['success' => false, 'error' => ['message' => 'Identifiant requis']], 400);
}

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;

// Colonnes lues. audio_file et preview_file servent a la decision et a la
// construction des URL, mais ne sont jamais renvoyes au client.
const COLONNES_TITRE = '
    t.id, t.title, t.duration, t.status, t.audio_file, t.preview_file,
    t.is_free, t.price, t.artist_id, t.album_id,
    ar.stage_name AS artist_name, al.cover_image AS album_cover';

function titreParId(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT ' . COLONNES_TITRE . '
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.id = ?
        LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Premier titre publie d'un album. */
function titreParAlbum(PDO $db, int $albumId): ?array
{
    $stmt = $db->prepare('SELECT ' . COLONNES_TITRE . "
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.album_id = ? AND t.status = 'approved'
        ORDER BY t.track_number ASC, t.created_at ASC
        LIMIT 1");
    $stmt->execute([$albumId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Titre publie le plus ecoute d'un artiste. */
function titreParArtiste(PDO $db, int $artistId): ?array
{
    $stmt = $db->prepare('SELECT ' . COLONNES_TITRE . "
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.artist_id = ? AND t.status = 'approved'
        ORDER BY t.total_streams DESC, t.created_at DESC
        LIMIT 1");
    $stmt->execute([$artistId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $titre = match ($action) {
        'resolve' => titreParId($db, $id) ?? titreParAlbum($db, $id),
        'artist'  => titreParArtiste($db, $id),
        default   => titreParId($db, $id),
    };

    if (!$titre) {
        repondre(['success' => false, 'error' => ['message' => 'Titre introuvable']], 404);
    }

    $decision = MediaAccess::decider($db, $titre, $userId);
    $trackId  = (int) $titre['id'];

    // Un titre non publie ne doit pas etre revele a un tiers : meme reponse
    // qu'un titre inexistant.
    if ($decision['motif'] === 'non_publie') {
        repondre(['success' => false, 'error' => ['message' => 'Titre introuvable']], 404);
    }

    $streamUrl = null;
    $ecoute = null;
    if ($decision['acces'] === MediaAccess::ACCES_COMPLET && trim((string) $titre['audio_file']) !== '') {
        $streamUrl = MediaAccess::urlSignee($trackId, MediaAccess::TYPE_AUDIO);
        // STAT-02 : jeton d'ecoute, a usage unique, pour le titre COMPLET
        // seulement -- un extrait n'est pas une ecoute.
        $ecoute = Ecoutes::ouvrir($trackId, $userId);
    }

    $previewUrl = null;
    if (in_array($decision['acces'], [MediaAccess::ACCES_COMPLET, MediaAccess::ACCES_EXTRAIT], true)
        && trim((string) $titre['preview_file']) !== '') {
        $previewUrl = MediaAccess::urlSignee($trackId, MediaAccess::TYPE_EXTRAIT);
    }

    repondre([
        'success' => true,
        'data' => [
            'id'             => $trackId,
            'title'          => $titre['title'],
            'duration'       => (int) $titre['duration'],
            'is_free'        => (int) $titre['is_free'],
            'price'          => (float) $titre['price'],
            'artist_id'      => (int) $titre['artist_id'],
            'album_id'       => $titre['album_id'] !== null ? (int) $titre['album_id'] : null,
            'artist_name'    => $titre['artist_name'],
            'album_cover'    => $titre['album_cover'],
            'access'         => $decision['acces'],
            'access_reason'  => $decision['motif'],
            'access_message' => $decision['acces'] === MediaAccess::ACCES_COMPLET
                ? null
                : MediaAccess::messageRefus($decision['motif']),
            'stream_url'     => $streamUrl,
            'preview_url'    => $previewUrl,
            'ecoute'         => $ecoute,
        ],
    ]);
} catch (Throwable $e) {
    // Jamais de message technique au client (SEC-15).
    $reference = strtoupper(bin2hex(random_bytes(4)));
    error_log(sprintf('[Tchadok][api/track] %s : %s', $reference, $e->getMessage()));
    repondre(['success' => false, 'error' => ['message' => 'Erreur interne', 'reference' => $reference]], 500);
}
