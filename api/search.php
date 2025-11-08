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

try {
    // Récupération des paramètres
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all'; // all, tracks, artists, albums
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20))); // Limite entre 1 et 50
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $genre = $_GET['genre'] ?? '';
    
    // Validation de la requête
    if (empty($query) || strlen($query) < 2) {
        throw new Exception('La requête de recherche doit contenir au moins 2 caractères', 400);
    }
    
    // Nettoyage de la requête
    $query = trim($query);
    $searchTerms = explode(' ', $query);
    $searchTerms = array_filter($searchTerms, function($term) {
        return strlen($term) > 1;
    });
    
    if (empty($searchTerms)) {
        throw new Exception('Requête de recherche invalide', 400);
    }
    
    // Initialisation des résultats
    $results = [
        'query' => $query,
        'total_results' => 0,
        'results' => [
            'tracks' => [],
            'artists' => [],
            'albums' => []
        ],
        'suggestions' => [],
        'filters' => [
            'genres' => [],
            'years' => [],
            'artists' => []
        ]
    ];
    
    // Simulation de données de recherche (à remplacer par vraies requêtes DB)
    if ($type === 'all' || $type === 'tracks') {
        $results['results']['tracks'] = searchTracks($query, $genre, $limit, $offset);
    }
    
    if ($type === 'all' || $type === 'artists') {
        $results['results']['artists'] = searchArtists($query, $limit, $offset);
    }
    
    if ($type === 'all' || $type === 'albums') {
        $results['results']['albums'] = searchAlbums($query, $genre, $limit, $offset);
    }
    
    // Calcul du total
    $results['total_results'] = count($results['results']['tracks']) + 
                               count($results['results']['artists']) + 
                               count($results['results']['albums']);
    
    // Suggestions de recherche
    if ($results['total_results'] === 0) {
        $results['suggestions'] = generateSearchSuggestions($query);
    }
    
    // Filtres populaires
    $results['filters'] = generateFilters();
    
    // Métadonnées
    $results['metadata'] = [
        'search_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => false // À implémenter avec pagination réelle
    ];
    
    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $results
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Gestion des erreurs
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

/**
 * Recherche de titres
 */
function searchTracks($query, $genre = '', $limit = 20, $offset = 0) {
    $tracks = [];
    
    // Simulation de données (à remplacer par requête DB réelle)
    $sampleTracks = [
        'Ndamena Beat', 'Sahara Vibes', 'Tchad mon pays', 'Afrobeat Connection',
        'Sara Traditional', 'Hip-Hop Tchadien', 'Gospel de la paix', 'Reggae du Logone',
        'Folk des montagnes', 'Zouk tropical', 'R&B moderne', 'Musique ancestrale'
    ];
    
    foreach ($sampleTracks as $index => $title) {
        if (stripos($title, $query) !== false) {
            $tracks[] = [
                'id' => $index + 1,
                'title' => $title,
                'artist_id' => rand(1, 10),
                'artist_name' => 'Artiste ' . rand(1, 10),
                'album_id' => rand(1, 5),
                'album_title' => 'Album ' . rand(1, 5),
                'album_cover' => 'assets/images/default-cover.jpg',
                'duration' => rand(180, 300),
                'genre' => ['Afrobeat', 'Hip-Hop', 'Gospel', 'Traditionnel'][rand(0, 3)],
                'release_date' => date('Y-m-d', strtotime('-' . rand(30, 1095) . ' days')),
                'total_streams' => rand(1000, 100000),
                'total_likes' => rand(50, 5000),
                'is_free' => rand(0, 1) === 1,
                'price' => rand(500, 2000),
                'is_premium' => rand(0, 1) === 1,
                'audio_preview' => 'assets/audio/preview_' . ($index + 1) . '.mp3',
                'relevance_score' => calculateRelevance($title, $query)
            ];
        }
    }
    
    // Tri par pertinence
    usort($tracks, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });
    
    return array_slice($tracks, $offset, $limit);
}

/**
 * Recherche d'artistes
 */
function searchArtists($query, $limit = 20, $offset = 0) {
    $artists = [];
    
    $sampleArtists = [
        'Khalil MC', 'Safiya la Diva', 'Ahmed Traditional', 'Sarah Gospel',
        'Ibrahim Reggae', 'Fatima R&B', 'Moussa Folk', 'Aisha Zouk'
    ];
    
    foreach ($sampleArtists as $index => $name) {
        if (stripos($name, $query) !== false) {
            $artists[] = [
                'id' => $index + 1,
                'stage_name' => $name,
                'real_name' => 'Nom Réel ' . ($index + 1),
                'profile_image' => 'assets/images/default-avatar.png',
                'cover_image' => 'assets/images/default-cover.jpg',
                'bio' => 'Artiste tchadien talentueux spécialisé dans la musique moderne.',
                'verified' => $index < 3,
                'genres' => [['Afrobeat', 'Hip-Hop'], ['Gospel', 'R&B'], ['Traditionnel']][rand(0, 2)],
                'total_tracks' => rand(5, 50),
                'total_albums' => rand(1, 8),
                'total_streams' => rand(10000, 500000),
                'total_followers' => rand(500, 25000),
                'monthly_listeners' => rand(1000, 50000),
                'formed_date' => date('Y-m-d', strtotime('-' . rand(365, 3650) . ' days')),
                'social_links' => [
                    'facebook' => 'https://facebook.com/artist' . ($index + 1),
                    'instagram' => 'https://instagram.com/artist' . ($index + 1),
                    'youtube' => 'https://youtube.com/artist' . ($index + 1)
                ],
                'relevance_score' => calculateRelevance($name, $query)
            ];
        }
    }
    
    usort($artists, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });
    
    return array_slice($artists, $offset, $limit);
}

/**
 * Recherche d'albums
 */
function searchAlbums($query, $genre = '', $limit = 20, $offset = 0) {
    $albums = [];
    
    $sampleAlbums = [
        'Best of Tchad', 'Sahara Dreams', 'Urban Ndamena', 'Gospel Collection',
        'Traditional Roots', 'Modern Beats', 'Afro Fusion', 'Desert Sounds'
    ];
    
    foreach ($sampleAlbums as $index => $title) {
        if (stripos($title, $query) !== false) {
            $albums[] = [
                'id' => $index + 1,
                'title' => $title,
                'artist_id' => rand(1, 8),
                'artist_name' => 'Artiste ' . rand(1, 8),
                'album_cover' => 'assets/images/default-cover.jpg',
                'release_date' => date('Y-m-d', strtotime('-' . rand(30, 1095) . ' days')),
                'genre' => ['Afrobeat', 'Hip-Hop', 'Gospel', 'Traditionnel'][rand(0, 3)],
                'track_count' => rand(8, 20),
                'total_duration' => rand(2400, 4800), // en secondes
                'total_streams' => rand(5000, 200000),
                'total_likes' => rand(100, 10000),
                'label' => 'Tchadok Records',
                'producer' => 'Producer ' . rand(1, 5),
                'price' => rand(2000, 8000),
                'is_available' => true,
                'type' => ['Album', 'EP', 'Single', 'Compilation'][rand(0, 3)],
                'relevance_score' => calculateRelevance($title, $query)
            ];
        }
    }
    
    usort($albums, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });
    
    return array_slice($albums, $offset, $limit);
}

/**
 * Calcul de la pertinence d'un résultat
 */
function calculateRelevance($text, $query) {
    $text = strtolower($text);
    $query = strtolower($query);
    
    // Score de base
    $score = 0;
    
    // Correspondance exacte (score élevé)
    if ($text === $query) {
        $score += 100;
    }
    
    // Commence par la requête
    if (strpos($text, $query) === 0) {
        $score += 75;
    }
    
    // Contient la requête
    if (strpos($text, $query) !== false) {
        $score += 50;
    }
    
    // Correspondance de mots individuels
    $queryWords = explode(' ', $query);
    $textWords = explode(' ', $text);
    
    foreach ($queryWords as $queryWord) {
        foreach ($textWords as $textWord) {
            if (strpos($textWord, $queryWord) !== false) {
                $score += 25;
            }
        }
    }
    
    return $score;
}

/**
 * Génération de suggestions de recherche
 */
function generateSearchSuggestions($query) {
    $suggestions = [
        'afrobeat tchadien',
        'musique traditionnelle',
        'hip hop ndjamena',
        'gospel tchad',
        'artistes emergents',
        'nouveautés 2024',
        'reggae africain',
        'zouk tropical'
    ];
    
    // Filtrer les suggestions pertinentes
    $filteredSuggestions = array_filter($suggestions, function($suggestion) use ($query) {
        return stripos($suggestion, $query) !== false || 
               levenshtein(strtolower($query), strtolower($suggestion)) < 3;
    });
    
    return array_values($filteredSuggestions);
}

/**
 * Génération de filtres pour la recherche
 */
function generateFilters() {
    return [
        'genres' => [
            ['name' => 'Afrobeat', 'count' => rand(50, 200)],
            ['name' => 'Hip-Hop', 'count' => rand(30, 150)],
            ['name' => 'Gospel', 'count' => rand(20, 100)],
            ['name' => 'Traditionnel', 'count' => rand(15, 80)],
            ['name' => 'Reggae', 'count' => rand(10, 60)],
            ['name' => 'R&B', 'count' => rand(25, 120)]
        ],
        'years' => [
            ['year' => '2024', 'count' => rand(30, 100)],
            ['year' => '2023', 'count' => rand(40, 120)],
            ['year' => '2022', 'count' => rand(35, 90)],
            ['year' => '2021', 'count' => rand(25, 70)],
            ['year' => '2020', 'count' => rand(20, 60)]
        ],
        'artists' => [
            ['name' => 'Khalil MC', 'count' => rand(10, 30)],
            ['name' => 'Safiya la Diva', 'count' => rand(8, 25)],
            ['name' => 'Ahmed Traditional', 'count' => rand(5, 20)],
            ['name' => 'Sarah Gospel', 'count' => rand(6, 18)]
        ]
    ];
}
?>