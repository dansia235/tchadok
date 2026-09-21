<?php
/**
 * API Server (désactivée)
 */

header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => [
        'message' => 'Endpoint desactive. Utilisez les APIs specialisees (search, notifications, stream, playlists).'
    ]
], JSON_UNESCAPED_UNICODE);
