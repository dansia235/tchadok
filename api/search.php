<?php
/**
 * API de recherche - Tchadok Platform
 * Endpoint: /api/search.php
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Headers pour API JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// SEC-12 : chaque recherche declenche plusieurs requetes SQL avec LIKE.
// Soixante par minute couvrent la frappe au clavier la plus rapide.
LimiteDebit::appliquer('recherche');

try {
    $query = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? 'all'; // all, tracks, artists, albums
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

    if ($query === '' || strlen($query) < 2) {
        throw new Exception('La requête de recherche doit contenir au moins 2 caractères', 400);
    }

    $results = searchContent($query, $limit);

    if ($type !== 'all') {
        $results = [
            'tracks' => $type === 'tracks' ? $results['tracks'] : [],
            'artists' => $type === 'artists' ? $results['artists'] : [],
            'albums' => $type === 'albums' ? $results['albums'] : []
        ];
    }

    $totalResults = count($results['tracks']) + count($results['artists']) + count($results['albums']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total_results' => $totalResults
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'code' => $statusCode,
            'timestamp' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}
