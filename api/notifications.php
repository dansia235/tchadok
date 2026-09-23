<?php
/**
 * API de notifications - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
// SEC-18 : aucune ouverture entre origines. Cette API agit au nom de la
// personne connectee ; l'ouvrir a un autre site reviendrait a le laisser
// agir sur les comptes de ses visiteurs.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // SEC-18 : plus rien a negocier entre origines.
    http_response_code(405);
    exit();
}

try {
    if (!isLoggedIn()) {
        throw new Exception('Authentification requise', 401);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            throw new Exception('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    // SEC-15 : un message ecrit pour le client (400, 404, 405...) reste
    // affiche tel quel ; une panne renvoie une reference, jamais le detail.
    $erreur = GestionErreurs::erreurApi($e, 'api/notifications');
    http_response_code($erreur['code']);
    $corps = $erreur['reponse'];
    $corps['error']['timestamp'] = date('c');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
}

function handleGet() {
    $action = $_GET['action'] ?? 'list';
    $userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

    switch ($action) {
        case 'list':
            listNotifications($userId);
            break;
        case 'unread':
            listNotifications($userId, 'unread');
            break;
        case 'count':
            countNotifications($userId);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

function handlePost() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'send';
    if ($action !== 'send') {
        http_response_code(501);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Action non prise en charge.',
                'code' => 501
            ]
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    sendNotification($input);
}

function handlePut() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $action = $input['action'] ?? 'mark_read';
    if ($action === 'mark_read') {
        markNotificationRead($input);
        return;
    }

    if ($action === 'mark_all_read') {
        markAllNotificationsRead($input);
        return;
    }

    http_response_code(501);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Action non prise en charge.',
            'code' => 501
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handleDelete() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $action = $input['action'] ?? ($_GET['action'] ?? 'delete');
    if ($action === 'delete') {
        deleteNotification($input['notification_id'] ?? ($_GET['id'] ?? null));
        return;
    }

    if ($action === 'clear_all') {
        clearNotifications($input['user_id'] ?? ($_GET['user_id'] ?? null));
        return;
    }

    http_response_code(501);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Action non prise en charge.',
            'code' => 501
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function listNotifications($userId, $status = 'all') {
    if ($userId <= 0) {
        throw new Exception('ID utilisateur requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $type = $_GET['type'] ?? null;

    $where = "user_id = ?";
    $params = [$userId];

    if ($status === 'unread') {
        $where .= " AND read_at IS NULL";
    } elseif ($status === 'read') {
        $where .= " AND read_at IS NOT NULL";
    }

    if ($type) {
        $where .= " AND type = ?";
        $params[] = $type;
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("
        SELECT id, type, title, message, data, action_url, read_at, created_at
        FROM notifications
        WHERE $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $notifications = [];
    foreach ($rows as $row) {
        $decoded = null;
        if (!empty($row['data'])) {
            $decoded = json_decode($row['data'], true);
        }
        $notifications[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'data' => $decoded ?? $row['data'],
            'action_url' => $row['action_url'],
            'is_read' => $row['read_at'] ? true : false,
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at']
        ];
    }

    $total = countNotificationsRaw($db, $userId);
    $unread = countNotificationsRaw($db, $userId, true);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total_count' => $total,
        'unread_count' => $unread,
        'user_id' => $userId,
        'limit' => $limit,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);
}

function countNotifications($userId) {
    if ($userId <= 0) {
        throw new Exception('ID utilisateur requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    echo json_encode([
        'success' => true,
        'total_count' => countNotificationsRaw($db, $userId),
        'unread_count' => countNotificationsRaw($db, $userId, true),
        'user_id' => $userId
    ], JSON_UNESCAPED_UNICODE);
}

function countNotificationsRaw($db, $userId, $unreadOnly = false) {
    $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ?";
    if ($unreadOnly) {
        $sql .= " AND read_at IS NULL";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function sendNotification($data) {
    $userId = (int)($data['user_id'] ?? 0);
    $type = trim($data['type'] ?? '');
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');

    if ($userId <= 0 || $type === '' || $title === '' || $message === '') {
        throw new Exception('Champs requis manquants', 400);
    }

    if (!isAdmin() && $userId !== (int)$_SESSION['user_id']) {
        throw new Exception('Action non autorisée', 403);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $payload = $data['data'] ?? null;
    if (is_array($payload)) {
        $payload = json_encode($payload);
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, data, action_url, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $type,
        $title,
        $message,
        $payload,
        $data['action_url'] ?? null
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'notification_id' => (int)$db->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
}

function markNotificationRead($data) {
    $notificationId = (int)($data['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        throw new Exception('ID de notification requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, (int)$_SESSION['user_id']]);

    echo json_encode(['success' => true]);
}

function markAllNotificationsRead($data) {
    $userId = (int)($data['user_id'] ?? $_SESSION['user_id']);
    if ($userId <= 0) {
        throw new Exception('ID utilisateur requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);
}

function deleteNotification($notificationId) {
    $notificationId = (int)$notificationId;
    if ($notificationId <= 0) {
        throw new Exception('ID de notification requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, (int)$_SESSION['user_id']]);

    echo json_encode(['success' => true]);
}

function clearNotifications($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        throw new Exception('ID utilisateur requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('notifications')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);
}
