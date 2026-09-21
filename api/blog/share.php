<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/blog-manager.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$postId = (int) ($data['post_id'] ?? 0);
$platform = trim((string) ($data['platform'] ?? 'copy'));
$csrfToken = trim((string) ($data['csrf_token'] ?? ''));

if ($postId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'post_id invalide']);
    exit();
}

if (isLoggedIn() && !verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF invalide']);
    exit();
}

blogRecordShare($postId, $platform, isLoggedIn() ? (int) ($_SESSION['user_id'] ?? 0) : null);

echo json_encode(['success' => true]);
