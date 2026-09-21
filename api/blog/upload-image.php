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

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acces refuse']);
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF invalide']);
    exit();
}

if (empty($_FILES['file'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier']);
    exit();
}

$upload = uploadFile(
    $_FILES['file'],
    __DIR__ . '/../../' . IMAGES_PATH . 'blog/',
    ALLOWED_IMAGE_TYPES,
    MAX_IMAGE_SIZE
);

if (!$upload['success']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $upload['message'] ?? "Echec upload"]);
    exit();
}

$relativePath = IMAGES_PATH . 'blog/' . $upload['filename'];
$location = blogMakeAbsoluteUrl($relativePath);

echo json_encode([
    'success' => true,
    'location' => $location
]);
