<?php
/**
 * API Recommandations (désactivée)
 */

header('Content-Type: application/json');
http_response_code(501);
echo json_encode([
    'success' => false,
    'error' => [
        'message' => 'API de recommandations desactivee. Activez un moteur de recommandation reel.'
    ]
], JSON_UNESCAPED_UNICODE);
