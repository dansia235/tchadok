<?php
/**
 * Générateur de données (désactivé)
 */

header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => [
        'message' => 'Generateur de donnees desactive. Utilisez les pages admin pour ajouter du contenu.'
    ]
], JSON_UNESCAPED_UNICODE);
