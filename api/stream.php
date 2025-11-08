<?php
/**
 * API de streaming et analytics - Tchadok Platform
 * Endpoint: /api/stream.php
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            handleStreamStart();
            break;
            
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'analytics':
                        getStreamAnalytics();
                        break;
                    case 'popular':
                        getPopularTracks();
                        break;
                    case 'trending':
                        getTrendingTracks();
                        break;
                    case 'recommendations':
                        getRecommendations();
                        break;
                    default:
                        throw new Exception('Action non reconnue', 400);
                }
            } else {
                throw new Exception('Action requise', 400);
            }
            break;
            
        default:
            throw new Exception('Méthode non autorisée', 405);
    }
    
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

/**
 * Enregistrement d'un début de stream
 */
function handleStreamStart() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Données JSON invalides', 400);
    }
    
    $trackId = $input['track_id'] ?? null;
    $userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $timestamp = $input['timestamp'] ?? time();
    $duration = $input['duration'] ?? 0; // Durée écoutée en secondes
    $quality = $input['quality'] ?? 'standard'; // standard, hd, premium
    $platform = $input['platform'] ?? 'web'; // web, mobile, api
    $location = $input['location'] ?? 'TD'; // Code pays
    
    if (!$trackId) {
        throw new Exception('ID du titre requis', 400);
    }
    
    // Validation des données
    $trackId = (int)$trackId;
    $userId = $userId ? (int)$userId : null;
    $duration = max(0, (int)$duration);
    
    // Simulation d'enregistrement en base (à remplacer par vraie insertion)
    $streamData = [
        'id' => rand(1000, 9999),
        'track_id' => $trackId,
        'user_id' => $userId,
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'timestamp' => $timestamp,
        'duration' => $duration,
        'quality' => $quality,
        'platform' => $platform,
        'location' => $location,
        'is_complete' => $duration > 30, // Stream considéré comme complet après 30s
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Mise à jour des statistiques du titre
    updateTrackStats($trackId, $streamData);
    
    // Mise à jour des statistiques utilisateur
    if ($userId) {
        updateUserStats($userId, $streamData);
    }
    
    // Mise à jour des tendances
    updateTrendingStats($trackId, $streamData);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'stream_id' => $streamData['id'],
            'message' => 'Stream enregistré avec succès',
            'track_stats' => getTrackQuickStats($trackId)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Analytics des streams
 */
function getStreamAnalytics() {
    $period = $_GET['period'] ?? '7d'; // 1d, 7d, 30d, 1y
    $trackId = $_GET['track_id'] ?? null;
    $artistId = $_GET['artist_id'] ?? null;
    
    $analytics = [
        'period' => $period,
        'total_streams' => rand(10000, 100000),
        'unique_listeners' => rand(5000, 50000),
        'total_duration' => rand(500000, 2000000), // en secondes
        'average_completion' => rand(65, 85), // pourcentage
        'by_day' => generateDailyStats($period),
        'by_country' => generateCountryStats(),
        'by_platform' => generatePlatformStats(),
        'by_quality' => generateQualityStats(),
        'demographics' => generateDemographics(),
        'top_tracks' => generateTopTracks(10),
        'revenue' => generateRevenueStats($period)
    ];
    
    if ($trackId) {
        $analytics['track_details'] = getTrackAnalytics($trackId);
    }
    
    if ($artistId) {
        $analytics['artist_details'] = getArtistAnalytics($artistId);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $analytics
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Titres populaires
 */
function getPopularTracks() {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $genre = $_GET['genre'] ?? '';
    $country = $_GET['country'] ?? 'TD';
    
    $tracks = [];
    for ($i = 1; $i <= $limit; $i++) {
        $tracks[] = [
            'id' => $i,
            'title' => 'Titre Populaire ' . $i,
            'artist_id' => rand(1, 10),
            'artist_name' => 'Artiste ' . rand(1, 10),
            'album_cover' => 'assets/images/default-cover.jpg',
            'duration' => rand(180, 300),
            'genre' => ['Afrobeat', 'Hip-Hop', 'Gospel'][rand(0, 2)],
            'total_streams' => rand(50000, 500000),
            'streams_today' => rand(500, 5000),
            'streams_week' => rand(3000, 30000),
            'position' => $i,
            'position_change' => rand(-5, 5),
            'first_release' => date('Y-m-d', strtotime('-' . rand(30, 365) . ' days')),
            'is_trending' => $i <= 10,
            'popularity_score' => rand(70, 100)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'tracks' => $tracks,
            'updated_at' => date('c'),
            'country' => $country,
            'genre' => $genre ?: 'all'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Titres en tendance
 */
function getTrendingTracks() {
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    
    $tracks = [];
    for ($i = 1; $i <= $limit; $i++) {
        $tracks[] = [
            'id' => $i + 100,
            'title' => 'Tendance ' . $i,
            'artist_name' => 'Artiste Trending ' . $i,
            'album_cover' => 'assets/images/default-cover.jpg',
            'streams_growth' => rand(50, 500), // Pourcentage de croissance
            'velocity_score' => rand(70, 100), // Score de vélocité
            'trend_duration' => rand(1, 14), // Jours en tendance
            'peak_position' => rand(1, 50),
            'current_streams' => rand(10000, 100000),
            'daily_growth' => rand(5, 50) // Pourcentage de croissance quotidienne
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'trending_tracks' => $tracks,
            'algorithm_version' => '2.1',
            'last_update' => date('c'),
            'next_update' => date('c', strtotime('+1 hour'))
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Recommandations personnalisées
 */
function getRecommendations() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $limit = min(30, max(1, (int)($_GET['limit'] ?? 15)));
    $seed_track = $_GET['seed_track'] ?? null;
    $seed_artist = $_GET['seed_artist'] ?? null;
    $seed_genre = $_GET['seed_genre'] ?? null;
    
    // Algorithme de recommandation simplifié
    $recommendations = [
        'user_id' => $userId,
        'recommendation_id' => uniqid('rec_'),
        'algorithm' => 'collaborative_filtering_v2',
        'confidence_score' => rand(75, 95),
        'tracks' => generateRecommendedTracks($limit, $seed_track, $seed_artist, $seed_genre),
        'explanation' => generateRecommendationExplanation($seed_track, $seed_artist, $seed_genre),
        'diversity_score' => rand(60, 90),
        'freshness_score' => rand(40, 80),
        'generated_at' => date('c'),
        'expires_at' => date('c', strtotime('+6 hours'))
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $recommendations
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Mise à jour des statistiques d'un titre
 */
function updateTrackStats($trackId, $streamData) {
    // Simulation de mise à jour (à remplacer par vraie requête DB)
    $updates = [
        'total_streams' => '+1',
        'total_duration' => '+' . $streamData['duration'],
        'last_played' => date('Y-m-d H:i:s'),
        'play_count_today' => '+1',
        'unique_listeners' => $streamData['user_id'] ? '+1' : '0'
    ];
    
    return $updates;
}

/**
 * Mise à jour des statistiques utilisateur
 */
function updateUserStats($userId, $streamData) {
    return [
        'total_listening_time' => '+' . $streamData['duration'],
        'tracks_played_today' => '+1',
        'last_activity' => date('Y-m-d H:i:s'),
        'favorite_genre' => 'calculated_based_on_history'
    ];
}

/**
 * Génération de statistiques quotidiennes
 */
function generateDailyStats($period) {
    $days = [
        '1d' => 1,
        '7d' => 7,
        '30d' => 30,
        '1y' => 365
    ][$period] ?? 7;
    
    $stats = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stats[] = [
            'date' => $date,
            'streams' => rand(1000, 10000),
            'unique_listeners' => rand(500, 5000),
            'revenue' => rand(10000, 100000) // en FCFA
        ];
    }
    
    return $stats;
}

/**
 * Génération de statistiques par pays
 */
function generateCountryStats() {
    return [
        ['country' => 'TD', 'name' => 'Tchad', 'streams' => rand(50000, 200000), 'percentage' => rand(60, 80)],
        ['country' => 'CM', 'name' => 'Cameroun', 'streams' => rand(10000, 50000), 'percentage' => rand(8, 15)],
        ['country' => 'CF', 'name' => 'Centrafrique', 'streams' => rand(5000, 20000), 'percentage' => rand(3, 8)],
        ['country' => 'FR', 'name' => 'France', 'streams' => rand(3000, 15000), 'percentage' => rand(2, 6)],
        ['country' => 'OTHER', 'name' => 'Autres', 'streams' => rand(2000, 10000), 'percentage' => rand(1, 4)]
    ];
}

/**
 * Génération de statistiques par plateforme
 */
function generatePlatformStats() {
    return [
        ['platform' => 'web', 'streams' => rand(30000, 80000), 'percentage' => rand(40, 60)],
        ['platform' => 'mobile_app', 'streams' => rand(20000, 60000), 'percentage' => rand(25, 45)],
        ['platform' => 'api', 'streams' => rand(5000, 20000), 'percentage' => rand(5, 15)],
        ['platform' => 'embed', 'streams' => rand(1000, 10000), 'percentage' => rand(1, 8)]
    ];
}

/**
 * Génération de recommandations
 */
function generateRecommendedTracks($limit, $seedTrack, $seedArtist, $seedGenre) {
    $tracks = [];
    for ($i = 1; $i <= $limit; $i++) {
        $tracks[] = [
            'id' => $i + 200,
            'title' => 'Recommandé ' . $i,
            'artist_name' => 'Artiste Reco ' . $i,
            'album_cover' => 'assets/images/default-cover.jpg',
            'duration' => rand(180, 300),
            'genre' => $seedGenre ?: ['Afrobeat', 'Hip-Hop', 'Gospel'][rand(0, 2)],
            'similarity_score' => rand(70, 95),
            'reason' => ['similar_artists', 'same_genre', 'popular_with_similar_users'][rand(0, 2)],
            'confidence' => rand(60, 90)
        ];
    }
    return $tracks;
}

/**
 * Génération d'explication des recommandations
 */
function generateRecommendationExplanation($seedTrack, $seedArtist, $seedGenre) {
    $explanations = [
        'Basé sur vos écoutes récentes',
        'Artistes similaires à vos favoris',
        'Populaire parmi les utilisateurs comme vous',
        'Nouvelles découvertes dans vos genres préférés'
    ];
    
    return $explanations[array_rand($explanations)];
}

/**
 * Statistiques rapides d'un titre
 */
function getTrackQuickStats($trackId) {
    return [
        'total_streams' => rand(1000, 100000),
        'streams_today' => rand(10, 1000),
        'current_listeners' => rand(0, 50),
        'peak_listeners' => rand(20, 200)
    ];
}

/**
 * Autres fonctions helper...
 */
function generateCountryStats() { /* ... */ }
function generatePlatformStats() { /* ... */ }
function generateQualityStats() { 
    return [
        ['quality' => 'standard', 'streams' => rand(40000, 70000), 'percentage' => rand(50, 70)],
        ['quality' => 'hd', 'streams' => rand(15000, 40000), 'percentage' => rand(20, 40)],
        ['quality' => 'premium', 'streams' => rand(5000, 20000), 'percentage' => rand(5, 20)]
    ];
}

function generateDemographics() {
    return [
        'age_groups' => [
            ['range' => '18-24', 'percentage' => rand(25, 40)],
            ['range' => '25-34', 'percentage' => rand(30, 45)],
            ['range' => '35-44', 'percentage' => rand(15, 25)],
            ['range' => '45+', 'percentage' => rand(5, 15)]
        ],
        'gender' => [
            ['gender' => 'male', 'percentage' => rand(45, 65)],
            ['gender' => 'female', 'percentage' => rand(35, 55)]
        ]
    ];
}

function generateTopTracks($limit) {
    $tracks = [];
    for ($i = 1; $i <= $limit; $i++) {
        $tracks[] = [
            'position' => $i,
            'id' => $i + 300,
            'title' => 'Top Titre ' . $i,
            'artist_name' => 'Top Artiste ' . $i,
            'streams' => rand(10000, 100000)
        ];
    }
    return $tracks;
}

function generateRevenueStats($period) {
    return [
        'total_revenue' => rand(100000, 1000000), // FCFA
        'revenue_per_stream' => rand(10, 50), // FCFA
        'premium_revenue' => rand(50000, 500000),
        'ads_revenue' => rand(20000, 200000),
        'downloads_revenue' => rand(30000, 300000)
    ];
}

function getTrackAnalytics($trackId) { return []; }
function getArtistAnalytics($artistId) { return []; }
function updateTrendingStats($trackId, $streamData) { return true; }
?>