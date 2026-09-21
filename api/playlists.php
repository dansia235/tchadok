<?php
/**
 * API Playlists & Favoris - Tchadok Platform
 * Requetes dynamiques basees sur la base de donnees
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if (!$db) {
    respond(['success' => false, 'error' => ['message' => 'Connexion base indisponible']], 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? null;

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $userId);
            break;
        case 'POST':
            handlePost($db, $userId, $input);
            break;
        case 'PUT':
            handlePut($db, $userId, $input);
            break;
        case 'DELETE':
            handleDelete($db, $userId, $input);
            break;
        default:
            respond(['success' => false, 'error' => ['message' => 'Methode non autorisee']], 405);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    respond(['success' => false, 'error' => ['message' => $e->getMessage()]], $code);
}

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function requireAuth($userId) {
    if (!$userId) {
        respond(['success' => false, 'error' => ['message' => 'Authentification requise']], 401);
    }
}

function handleGet($db, $userId) {
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            requireAuth($userId);
            $stmt = $db->prepare("
                SELECT id, name, description, cover_image, is_public, is_collaborative,
                       total_tracks, total_duration, total_plays, created_at, updated_at
                FROM playlists
                WHERE user_id = ?
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$userId]);
            respond(['success' => true, 'data' => ['playlists' => $stmt->fetchAll()]]);
            break;

        case 'get':
            $playlistId = (int) ($_GET['id'] ?? 0);
            if (!$playlistId) {
                respond(['success' => false, 'error' => ['message' => 'ID playlist requis']], 400);
            }
            $stmt = $db->prepare("
                SELECT p.*, u.username
                FROM playlists p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmt->execute([$playlistId]);
            $playlist = $stmt->fetch();
            if (!$playlist) {
                respond(['success' => false, 'error' => ['message' => 'Playlist introuvable']], 404);
            }
            respond(['success' => true, 'data' => ['playlist' => $playlist]]);
            break;

        case 'tracks':
            $playlistId = (int) ($_GET['playlist_id'] ?? 0);
            if (!$playlistId) {
                respond(['success' => false, 'error' => ['message' => 'ID playlist requis']], 400);
            }
            $stmt = $db->prepare("
                SELECT pt.id AS playlist_track_id, pt.position, pt.added_at,
                       t.id AS track_id, t.title, t.duration, t.is_free, t.price,
                       ar.stage_name AS artist, al.cover_image AS album_cover
                FROM playlist_tracks pt
                JOIN tracks t ON pt.track_id = t.id
                JOIN artists ar ON t.artist_id = ar.id
                LEFT JOIN albums al ON t.album_id = al.id
                WHERE pt.playlist_id = ?
                ORDER BY pt.position ASC, pt.added_at ASC
            ");
            $stmt->execute([$playlistId]);
            $tracks = $stmt->fetchAll();
            respond(['success' => true, 'data' => ['tracks' => $tracks]]);
            break;

        default:
            respond(['success' => false, 'error' => ['message' => 'Action non reconnue']], 400);
    }
}

function handlePost($db, $userId, $input) {
    requireAuth($userId);
    $data = array_merge($_POST, $input);
    $action = $data['action'] ?? 'create';

    switch ($action) {
        case 'create':
            $name = trim($data['name'] ?? $data['playlist_name'] ?? '');
            if ($name === '') {
                respond(['success' => false, 'error' => ['message' => 'Nom requis']], 400);
            }
            $description = trim($data['description'] ?? $data['playlist_description'] ?? '');
            $visibility = $data['visibility'] ?? null;
            $isPublic = isset($data['is_public'])
                ? (int) (bool) $data['is_public']
                : ($visibility ? (int) ($visibility === 'public') : 1);
            $isCollaborative = !empty($data['is_collaborative']) ? 1 : 0;
            $coverImage = trim($data['cover_image'] ?? '');

            $stmt = $db->prepare("
                INSERT INTO playlists (user_id, name, description, cover_image, is_public, is_collaborative, total_tracks, total_duration, total_plays, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, NOW(), NOW())
            ");
            $stmt->execute([$userId, $name, $description, $coverImage ?: null, $isPublic, $isCollaborative]);
            $playlistId = (int) $db->lastInsertId();
            respond(['success' => true, 'data' => ['playlist_id' => $playlistId]]);
            break;

        case 'add_track':
            $playlistId = (int) ($data['playlist_id'] ?? 0);
            $trackId = (int) ($data['track_id'] ?? 0);
            if (!$playlistId || !$trackId) {
                respond(['success' => false, 'error' => ['message' => 'IDs requis']], 400);
            }

            ensurePlaylistOwner($db, $playlistId, $userId);

            $stmt = $db->prepare("SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?");
            $stmt->execute([$playlistId, $trackId]);
            if ((int) $stmt->fetchColumn() > 0) {
                respond(['success' => false, 'error' => ['message' => 'Titre deja present']], 409);
            }

            $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = ?");
            $stmt->execute([$playlistId]);
            $position = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)");
            $stmt->execute([$playlistId, $trackId, $position]);

            recalculatePlaylistStats($db, $playlistId);
            respond(['success' => true, 'data' => ['playlist_id' => $playlistId, 'track_id' => $trackId]]);
            break;

        case 'toggle_favorite':
            $itemId = (int) ($data['item_id'] ?? 0);
            $itemType = $data['item_type'] ?? 'track';
            if (!$itemId || !in_array($itemType, ['track', 'album', 'artist', 'playlist'], true)) {
                respond(['success' => false, 'error' => ['message' => 'Parametres invalides']], 400);
            }

            $stmt = $db->prepare("SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ? LIMIT 1");
            $stmt->execute([$userId, $itemType, $itemId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $del = $db->prepare("DELETE FROM favorites WHERE id = ?");
                $del->execute([$existing]);
                respond(['success' => true, 'data' => ['is_favorite' => false]]);
            }

            $ins = $db->prepare("INSERT INTO favorites (user_id, item_type, item_id) VALUES (?, ?, ?)");
            $ins->execute([$userId, $itemType, $itemId]);
            respond(['success' => true, 'data' => ['is_favorite' => true]]);
            break;

        case 'update':
            handlePut($db, $userId, $data);
            break;

        case 'delete':
            handleDelete($db, $userId, array_merge($data, ['action' => 'delete']));
            break;

        default:
            respond(['success' => false, 'error' => ['message' => 'Action non reconnue']], 400);
    }
}

function handlePut($db, $userId, $input) {
    requireAuth($userId);
    $action = $input['action'] ?? 'update';

    if ($action !== 'update') {
        respond(['success' => false, 'error' => ['message' => 'Action non reconnue']], 400);
    }

    $playlistId = (int) ($input['playlist_id'] ?? $input['id'] ?? 0);
    if (!$playlistId) {
        respond(['success' => false, 'error' => ['message' => 'ID playlist requis']], 400);
    }

    ensurePlaylistOwner($db, $playlistId, $userId);

    $fields = [];
    $values = [];

    if (isset($input['name'])) {
        $fields[] = 'name = ?';
        $values[] = trim($input['name']);
    }
    if (isset($input['description'])) {
        $fields[] = 'description = ?';
        $values[] = trim($input['description']);
    }
    if (isset($input['is_public'])) {
        $fields[] = 'is_public = ?';
        $values[] = (int) (bool) $input['is_public'];
    }

    if (empty($fields)) {
        respond(['success' => false, 'error' => ['message' => 'Aucune mise a jour']], 400);
    }

    $values[] = $playlistId;
    $stmt = $db->prepare("UPDATE playlists SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?");
    $stmt->execute($values);

    respond(['success' => true, 'data' => ['playlist_id' => $playlistId]]);
}

function handleDelete($db, $userId, $input) {
    requireAuth($userId);
    $action = $input['action'] ?? $_GET['action'] ?? 'delete';

    switch ($action) {
        case 'delete':
            $playlistId = (int) ($input['playlist_id'] ?? $_GET['id'] ?? 0);
            if (!$playlistId) {
                respond(['success' => false, 'error' => ['message' => 'ID playlist requis']], 400);
            }
            ensurePlaylistOwner($db, $playlistId, $userId);
            $stmt = $db->prepare("DELETE FROM playlists WHERE id = ? AND user_id = ?");
            $stmt->execute([$playlistId, $userId]);
            respond(['success' => true]);
            break;

        case 'remove_track':
            $playlistId = (int) ($input['playlist_id'] ?? 0);
            $trackId = (int) ($input['track_id'] ?? 0);
            if (!$playlistId || !$trackId) {
                respond(['success' => false, 'error' => ['message' => 'IDs requis']], 400);
            }
            ensurePlaylistOwner($db, $playlistId, $userId);
            $stmt = $db->prepare("DELETE FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?");
            $stmt->execute([$playlistId, $trackId]);
            recalculatePlaylistStats($db, $playlistId);
            respond(['success' => true]);
            break;

        default:
            respond(['success' => false, 'error' => ['message' => 'Action non reconnue']], 400);
    }
}

function ensurePlaylistOwner($db, $playlistId, $userId) {
    $stmt = $db->prepare("SELECT user_id FROM playlists WHERE id = ?");
    $stmt->execute([$playlistId]);
    $ownerId = $stmt->fetchColumn();
    if (!$ownerId) {
        respond(['success' => false, 'error' => ['message' => 'Playlist introuvable']], 404);
    }
    if ((int) $ownerId !== (int) $userId) {
        respond(['success' => false, 'error' => ['message' => 'Acces refuse']], 403);
    }
}

function recalculatePlaylistStats($db, $playlistId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_tracks,
               COALESCE(SUM(t.duration), 0) AS total_duration
        FROM playlist_tracks pt
        JOIN tracks t ON pt.track_id = t.id
        WHERE pt.playlist_id = ?
    ");
    $stmt->execute([$playlistId]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare("
        UPDATE playlists
        SET total_tracks = ?, total_duration = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        (int) ($stats['total_tracks'] ?? 0),
        (int) ($stats['total_duration'] ?? 0),
        $playlistId
    ]);
}
