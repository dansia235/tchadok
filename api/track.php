<?php
/**
 * API Tracks - Tchadok Platform
 * Retourne les informations d'un titre pour la lecture
 */

require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if (!$db) {
    respond(['success' => false, 'error' => ['message' => 'Connexion base indisponible']], 500);
}

$action = $_GET['action'] ?? 'get';
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    respond(['success' => false, 'error' => ['message' => 'ID requis']], 400);
}

try {
    $track = null;
    if ($action === 'resolve') {
        $track = fetchTrack($db, $id);
        if (!$track) {
            $track = fetchTrackByAlbum($db, $id);
        }
    } elseif ($action === 'artist') {
        $track = fetchTrackByArtist($db, $id);
    } else {
        $track = fetchTrack($db, $id);
    }

    if (!$track) {
        respond(['success' => false, 'error' => ['message' => 'Titre introuvable']], 404);
    }

    respond(['success' => true, 'data' => $track]);
} catch (Exception $e) {
    respond(['success' => false, 'error' => ['message' => $e->getMessage()]], 500);
}

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function fetchTrack($db, $trackId) {
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.duration, t.audio_file, t.preview_file,
               t.is_free, t.price, t.artist_id, t.album_id,
               ar.stage_name AS artist_name, al.cover_image AS album_cover
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$trackId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchTrackByAlbum($db, $albumId) {
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.duration, t.audio_file, t.preview_file,
               t.is_free, t.price, t.artist_id, t.album_id,
               ar.stage_name AS artist_name, al.cover_image AS album_cover
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.album_id = ?
        ORDER BY t.track_number ASC, t.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$albumId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchTrackByArtist($db, $artistId) {
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.duration, t.audio_file, t.preview_file,
               t.is_free, t.price, t.artist_id, t.album_id,
               ar.stage_name AS artist_name, al.cover_image AS album_cover
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.artist_id = ?
        ORDER BY t.total_streams DESC, t.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$artistId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
