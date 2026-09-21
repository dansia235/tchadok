<?php
/**
 * API Moov Money (désactivée)
 */

header('Content-Type: application/json');
http_response_code(501);
echo json_encode([
    'success' => false,
    'error' => [
        'message' => 'Moov Money simulation desactivee. Branchez l\'API officielle.'
    ]
], JSON_UNESCAPED_UNICODE);
