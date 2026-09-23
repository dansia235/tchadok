<?php
/**
 * API de recherche - Tchadok Platform
 * Endpoint: /api/search.php
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_once '../includes/cors.php';

header('Content-Type: application/json');

// SEC-18 : recherche publique en lecture. Ouverte aux seules origines
// declarees dans CORS_ALLOWED_ORIGINS ; liste vide = meme origine seulement.
// Le prevol (OPTIONS) est traite la.
Cors::ouvrirEnLecture(['GET']);

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
    // SEC-15 : un message ecrit pour le client (400, 404, 405...) reste
    // affiche tel quel ; une panne renvoie une reference, jamais le detail.
    $erreur = GestionErreurs::erreurApi($e, 'api/search');
    http_response_code($erreur['code']);
    $corps = $erreur['reponse'];
    $corps['error']['timestamp'] = date('c');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
}
