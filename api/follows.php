<?php
/**
 * API Follows - Tchadok Platform
 * Toggle et consultation des abonnements (artists, users)
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    respond(['success' => false, 'error' => ['message' => 'Authentification requise']], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'toggle';
$followedId = (int) ($input['followed_id'] ?? $_POST['followed_id'] ?? $_GET['followed_id'] ?? 0);
$followedType = $input['followed_type'] ?? $_POST['followed_type'] ?? $_GET['followed_type'] ?? 'artist';

if (!$followedId || !in_array($followedType, ['artist', 'user'], true)) {
    respond(['success' => false, 'error' => ['message' => 'Parametres invalides']], 400);
}

try {
    switch ($action) {
        case 'toggle':
            $stmt = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND followed_id = ? AND followed_type = ? LIMIT 1");
            $stmt->execute([$userId, $followedId, $followedType]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $del = $db->prepare("DELETE FROM follows WHERE id = ?");
                $del->execute([$existing]);
                $isFollowing = false;
            } else {
                $ins = $db->prepare("INSERT INTO follows (follower_id, followed_id, followed_type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $followedId, $followedType]);
                $isFollowing = true;
            }

            $count = getFollowersCount($db, $followedId, $followedType);
            respond(['success' => true, 'data' => ['is_following' => $isFollowing, 'followers_count' => $count]]);
            break;

        case 'status':
            $stmt = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND followed_id = ? AND followed_type = ? LIMIT 1");
            $stmt->execute([$userId, $followedId, $followedType]);
            $isFollowing = (bool) $stmt->fetchColumn();
            $count = getFollowersCount($db, $followedId, $followedType);
            respond(['success' => true, 'data' => ['is_following' => $isFollowing, 'followers_count' => $count]]);
            break;

        default:
            respond(['success' => false, 'error' => ['message' => 'Action non reconnue']], 400);
    }
} catch (Exception $e) {
    respond(['success' => false, 'error' => ['message' => $e->getMessage()]], 500);
}

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function getFollowersCount($db, $followedId, $followedType) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ? AND followed_type = ?");
    $stmt->execute([$followedId, $followedType]);
    return (int) $stmt->fetchColumn();
}
?>

