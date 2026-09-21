<?php
/**
 * API Paiement (désactivée)
 */

header('Content-Type: application/json');
http_response_code(501);
echo json_encode([
    'success' => false,
    'error' => [
        'message' => 'API de paiement desactivee. Integrez un fournisseur reel (Airtel/Moov/etc).'
    ]
], JSON_UNESCAPED_UNICODE);
