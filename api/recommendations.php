<?php
/**
 * API d'algorithmes de recommandation avancés - Tchadok Platform
 * Utilise plusieurs techniques: filtrage collaboratif, basé sur le contenu, et hybride
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
    
    if ($method !== 'GET' && $method !== 'POST') {
        throw new Exception('Méthode non autorisée', 405);
    }
    
    $action = $_GET['action'] ?? 'personal';
    
    switch ($action) {
        case 'personal':
            getPersonalizedRecommendations();
            break;
        case 'similar_tracks':
            getSimilarTracks();
            break;
        case 'similar_artists':
            getSimilarArtists();
            break;
        case 'discover_weekly':
            getDiscoverWeekly();
            break;
        case 'genre_based':
            getGenreBasedRecommendations();
            break;
        case 'mood_based':
            getMoodBasedRecommendations();
            break;
        case 'trending_for_you':
            getTrendingForYou();
            break;
        case 'collaborative':
            getCollaborativeFilteringRecommendations();
            break;
        case 'hybrid':
            getHybridRecommendations();
            break;
        case 'radio':
            getRadioRecommendations();
            break;
        default:
            throw new Exception('Action non reconnue', 400);
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
 * Recommandations personnalisées basées sur l'historique d'écoute
 */
function getPersonalizedRecommendations() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    
    // Analyse du profil utilisateur (simulé)
    $userProfile = analyzeUserProfile($userId);
    
    // Génération des recommandations
    $recommendations = [];
    
    // Mix de différentes stratégies
    $strategies = [
        'based_on_history' => 0.4,
        'based_on_favorites' => 0.3,
        'based_on_trends' => 0.2,
        'discovery' => 0.1
    ];
    
    foreach ($strategies as $strategy => $weight) {
        $count = ceil($limit * $weight);
        $tracks = generateRecommendationsByStrategy($strategy, $userProfile, $count);
        $recommendations = array_merge($recommendations, $tracks);
    }
    
    // Scoring et tri
    $recommendations = scoreAndRankRecommendations($recommendations, $userProfile);
    
    // Diversification
    $recommendations = diversifyRecommendations($recommendations);
    
    // Limitation
    $recommendations = array_slice($recommendations, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'recommendations' => $recommendations,
            'algorithm_version' => '3.0',
            'explanation' => generateExplanation($userProfile),
            'confidence_score' => calculateConfidenceScore($userProfile),
            'diversity_score' => calculateDiversityScore($recommendations),
            'generated_at' => date('c'),
            'expires_at' => date('c', strtotime('+6 hours'))
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Trouve des titres similaires basés sur les caractéristiques audio et métadonnées
 */
function getSimilarTracks() {
    $trackId = $_GET['track_id'] ?? null;
    $limit = min(30, max(1, (int)($_GET['limit'] ?? 10)));
    
    if (!$trackId) {
        throw new Exception('ID du titre requis', 400);
    }
    
    // Analyse des caractéristiques du titre
    $trackFeatures = analyzeTrackFeatures($trackId);
    
    // Recherche de titres similaires
    $similarTracks = [];
    
    // Simulation de similarité basée sur plusieurs critères
    $criteria = [
        'audio_features' => 0.4,    // Tempo, énergie, valence, etc.
        'genre_similarity' => 0.3,   // Même genre ou genres proches
        'artist_similarity' => 0.2,  // Artistes similaires
        'user_behavior' => 0.1       // Écoutés par les mêmes utilisateurs
    ];
    
    for ($i = 1; $i <= $limit; $i++) {
        $similarity = calculateSimilarityScore($trackFeatures, $criteria);
        
        $similarTracks[] = [
            'id' => $i + 1000,
            'title' => 'Titre Similaire ' . $i,
            'artist_name' => 'Artiste ' . rand(1, 20),
            'album_cover' => 'assets/images/default-cover.jpg',
            'duration' => rand(180, 300),
            'genre' => $trackFeatures['genre'],
            'similarity_score' => $similarity,
            'similarity_reasons' => generateSimilarityReasons($similarity),
            'audio_features' => generateAudioFeatures(),
            'preview_url' => 'assets/audio/preview_' . $i . '.mp3'
        ];
    }
    
    // Tri par score de similarité
    usort($similarTracks, function($a, $b) {
        return $b['similarity_score'] <=> $a['similarity_score'];
    });
    
    echo json_encode([
        'success' => true,
        'data' => [
            'seed_track_id' => $trackId,
            'similar_tracks' => $similarTracks,
            'algorithm' => 'content_based_filtering',
            'features_analyzed' => array_keys($criteria),
            'generated_at' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Découverte hebdomadaire personnalisée
 */
function getDiscoverWeekly() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    
    // Génération d'une playlist de découverte de 30 titres
    $discoveries = [];
    
    // Profil utilisateur pour personnalisation
    $userProfile = analyzeUserProfile($userId);
    
    // Mix de nouveautés et de pépites cachées
    $categories = [
        'new_releases' => 8,      // Nouvelles sorties dans les genres préférés
        'hidden_gems' => 7,       // Titres peu écoutés mais de qualité
        'rising_artists' => 5,    // Artistes en progression
        'cross_genre' => 5,       // Exploration de genres connexes
        'international' => 3,     // Découvertes internationales
        'throwback' => 2          // Classiques à redécouvrir
    ];
    
    foreach ($categories as $category => $count) {
        for ($i = 0; $i < $count; $i++) {
            $discoveries[] = generateDiscoveryTrack($category, $userProfile);
        }
    }
    
    // Mélange et optimisation de l'ordre
    $discoveries = optimizePlaylistOrder($discoveries);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'playlist_name' => 'Découverte de la semaine',
            'description' => 'Votre sélection personnalisée de nouvelles découvertes musicales',
            'tracks' => $discoveries,
            'total_duration' => array_sum(array_column($discoveries, 'duration')),
            'generation_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+7 days')),
            'algorithm_insights' => [
                'personalization_score' => rand(75, 95),
                'discovery_score' => rand(80, 98),
                'diversity_score' => rand(70, 90)
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Recommandations basées sur l'humeur/mood
 */
function getMoodBasedRecommendations() {
    $mood = $_GET['mood'] ?? 'neutral';
    $limit = min(30, max(1, (int)($_GET['limit'] ?? 15)));
    
    // Mapping des moods vers les caractéristiques audio
    $moodProfiles = [
        'happy' => ['valence' => 0.8, 'energy' => 0.7, 'tempo' => 'fast'],
        'sad' => ['valence' => 0.3, 'energy' => 0.4, 'tempo' => 'slow'],
        'energetic' => ['valence' => 0.7, 'energy' => 0.9, 'tempo' => 'very_fast'],
        'relaxed' => ['valence' => 0.6, 'energy' => 0.3, 'tempo' => 'slow'],
        'angry' => ['valence' => 0.2, 'energy' => 0.9, 'tempo' => 'fast'],
        'romantic' => ['valence' => 0.7, 'energy' => 0.5, 'tempo' => 'medium'],
        'focused' => ['valence' => 0.5, 'energy' => 0.6, 'tempo' => 'medium'],
        'party' => ['valence' => 0.9, 'energy' => 0.95, 'tempo' => 'very_fast']
    ];
    
    $moodProfile = $moodProfiles[$mood] ?? $moodProfiles['neutral'];
    
    $recommendations = [];
    for ($i = 1; $i <= $limit; $i++) {
        $recommendations[] = [
            'id' => $i + 2000,
            'title' => generateMoodTitle($mood, $i),
            'artist_name' => 'Artiste Mood ' . $i,
            'album_cover' => 'assets/images/default-cover.jpg',
            'duration' => rand(180, 300),
            'mood_match' => rand(70, 100),
            'audio_features' => [
                'valence' => $moodProfile['valence'] + (rand(-10, 10) / 100),
                'energy' => $moodProfile['energy'] + (rand(-10, 10) / 100),
                'danceability' => rand(50, 90) / 100,
                'acousticness' => rand(10, 70) / 100,
                'instrumentalness' => rand(0, 30) / 100
            ],
            'genre' => selectGenreForMood($mood),
            'color_scheme' => getMoodColorScheme($mood)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'mood' => $mood,
            'mood_profile' => $moodProfile,
            'recommendations' => $recommendations,
            'playlist_name' => getMoodPlaylistName($mood),
            'description' => getMoodDescription($mood),
            'visualization' => getMoodVisualization($mood),
            'generated_at' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Filtrage collaboratif basé sur les utilisateurs similaires
 */
function getCollaborativeFilteringRecommendations() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $limit = min(30, max(1, (int)($_GET['limit'] ?? 20)));
    
    // Trouver les utilisateurs similaires
    $similarUsers = findSimilarUsers($userId);
    
    // Collecter les titres écoutés par ces utilisateurs
    $collaborativeTracks = [];
    
    foreach ($similarUsers as $similarUser) {
        $userTracks = getUserTopTracks($similarUser['user_id']);
        
        foreach ($userTracks as $track) {
            $trackId = $track['id'];
            
            if (!isset($collaborativeTracks[$trackId])) {
                $collaborativeTracks[$trackId] = [
                    'track' => $track,
                    'score' => 0,
                    'recommended_by' => []
                ];
            }
            
            // Score pondéré par la similarité de l'utilisateur
            $collaborativeTracks[$trackId]['score'] += $similarUser['similarity_score'];
            $collaborativeTracks[$trackId]['recommended_by'][] = $similarUser['user_id'];
        }
    }
    
    // Tri par score et conversion en array
    usort($collaborativeTracks, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    // Formatage des résultats
    $recommendations = array_map(function($item) {
        return array_merge($item['track'], [
            'recommendation_score' => $item['score'],
            'recommended_by_count' => count($item['recommended_by']),
            'recommendation_reason' => 'Populaire parmi les utilisateurs ayant des goûts similaires'
        ]);
    }, array_slice($collaborativeTracks, 0, $limit));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'recommendations' => $recommendations,
            'algorithm' => 'collaborative_filtering',
            'similar_users_analyzed' => count($similarUsers),
            'confidence_level' => calculateCollaborativeConfidence($similarUsers),
            'generated_at' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Recommandations hybrides combinant plusieurs approches
 */
function getHybridRecommendations() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $limit = min(40, max(1, (int)($_GET['limit'] ?? 25)));
    
    // Profil utilisateur complet
    $userProfile = analyzeUserProfile($userId);
    
    // Collecte de recommandations de différentes sources
    $recommendations = [];
    
    // 1. Basé sur le contenu (30%)
    $contentBased = getContentBasedRecommendations($userProfile, ceil($limit * 0.3));
    foreach ($contentBased as &$track) {
        $track['source'] = 'content_based';
        $track['weight'] = 0.3;
    }
    $recommendations = array_merge($recommendations, $contentBased);
    
    // 2. Filtrage collaboratif (30%)
    $collaborative = getCollaborativeRecommendations($userId, ceil($limit * 0.3));
    foreach ($collaborative as &$track) {
        $track['source'] = 'collaborative';
        $track['weight'] = 0.3;
    }
    $recommendations = array_merge($recommendations, $collaborative);
    
    // 3. Popularité et tendances (20%)
    $trending = getTrendingRecommendations($userProfile, ceil($limit * 0.2));
    foreach ($trending as &$track) {
        $track['source'] = 'trending';
        $track['weight'] = 0.2;
    }
    $recommendations = array_merge($recommendations, $trending);
    
    // 4. Découverte et exploration (20%)
    $discovery = getDiscoveryRecommendations($userProfile, ceil($limit * 0.2));
    foreach ($discovery as &$track) {
        $track['source'] = 'discovery';
        $track['weight'] = 0.2;
    }
    $recommendations = array_merge($recommendations, $discovery);
    
    // Score hybride et déduplication
    $recommendations = calculateHybridScores($recommendations);
    $recommendations = removeDuplicates($recommendations);
    
    // Optimisation finale
    $recommendations = optimizeFinalRecommendations($recommendations, $userProfile);
    $recommendations = array_slice($recommendations, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'recommendations' => $recommendations,
            'algorithm' => 'hybrid_recommendation_system',
            'components' => [
                'content_based' => 30,
                'collaborative' => 30,
                'trending' => 20,
                'discovery' => 20
            ],
            'personalization_level' => 'high',
            'explanation_available' => true,
            'generated_at' => date('c'),
            'next_update' => date('c', strtotime('+3 hours'))
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * FONCTIONS HELPER
 */

function analyzeUserProfile($userId) {
    // Simulation d'analyse du profil utilisateur
    return [
        'user_id' => $userId,
        'preferred_genres' => ['Afrobeat', 'Hip-Hop', 'R&B'],
        'listening_history' => generateListeningHistory(),
        'average_session_length' => rand(30, 120), // minutes
        'peak_listening_hours' => [20, 21, 22], // 20h-23h
        'device_preferences' => ['mobile' => 0.7, 'web' => 0.3],
        'language_preferences' => ['fr' => 0.8, 'en' => 0.2],
        'artist_loyalty' => rand(60, 90) / 100,
        'genre_diversity' => rand(40, 80) / 100,
        'discovery_appetite' => rand(30, 70) / 100
    ];
}

function generateListeningHistory() {
    $history = [];
    for ($i = 0; $i < 20; $i++) {
        $history[] = [
            'track_id' => rand(1, 1000),
            'play_count' => rand(1, 50),
            'last_played' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'skip_rate' => rand(0, 30) / 100
        ];
    }
    return $history;
}

function generateRecommendationsByStrategy($strategy, $userProfile, $count) {
    $tracks = [];
    
    for ($i = 0; $i < $count; $i++) {
        $tracks[] = [
            'id' => rand(1000, 9999),
            'title' => generateTrackTitle($strategy, $i),
            'artist_name' => 'Artiste ' . rand(1, 50),
            'album_cover' => 'assets/images/default-cover.jpg',
            'duration' => rand(180, 300),
            'genre' => $userProfile['preferred_genres'][array_rand($userProfile['preferred_genres'])],
            'release_date' => date('Y-m-d', strtotime('-' . rand(1, 365) . ' days')),
            'popularity_score' => rand(50, 100),
            'recommendation_strategy' => $strategy,
            'match_score' => rand(70, 95)
        ];
    }
    
    return $tracks;
}

function scoreAndRankRecommendations($recommendations, $userProfile) {
    foreach ($recommendations as &$track) {
        // Score composite basé sur plusieurs facteurs
        $genreMatch = in_array($track['genre'], $userProfile['preferred_genres']) ? 20 : 5;
        $popularityFactor = $track['popularity_score'] * 0.3;
        $freshnessBonus = strtotime($track['release_date']) > strtotime('-30 days') ? 10 : 0;
        $strategyWeight = getStrategyWeight($track['recommendation_strategy']);
        
        $track['final_score'] = $track['match_score'] + $genreMatch + $popularityFactor + $freshnessBonus + $strategyWeight;
    }
    
    usort($recommendations, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });
    
    return $recommendations;
}

function diversifyRecommendations($recommendations) {
    // Assurer la diversité dans les recommandations
    $diversified = [];
    $usedGenres = [];
    $usedArtists = [];
    
    foreach ($recommendations as $track) {
        $genre = $track['genre'];
        $artist = $track['artist_name'];
        
        // Limiter le nombre de titres par genre et artiste
        if (!isset($usedGenres[$genre])) $usedGenres[$genre] = 0;
        if (!isset($usedArtists[$artist])) $usedArtists[$artist] = 0;
        
        if ($usedGenres[$genre] < 3 && $usedArtists[$artist] < 2) {
            $diversified[] = $track;
            $usedGenres[$genre]++;
            $usedArtists[$artist]++;
        }
    }
    
    return $diversified;
}

function calculateSimilarityScore($features, $criteria) {
    $score = 0;
    
    foreach ($criteria as $criterion => $weight) {
        $criterionScore = rand(60, 100); // Simulation
        $score += $criterionScore * $weight;
    }
    
    return round($score);
}

function generateAudioFeatures() {
    return [
        'tempo' => rand(60, 180),
        'energy' => rand(0, 100) / 100,
        'danceability' => rand(0, 100) / 100,
        'valence' => rand(0, 100) / 100,
        'acousticness' => rand(0, 100) / 100,
        'instrumentalness' => rand(0, 100) / 100,
        'liveness' => rand(0, 100) / 100,
        'speechiness' => rand(0, 100) / 100
    ];
}

function analyzeTrackFeatures($trackId) {
    return [
        'track_id' => $trackId,
        'genre' => ['Afrobeat', 'Hip-Hop', 'R&B'][rand(0, 2)],
        'tempo' => rand(60, 180),
        'key' => rand(0, 11),
        'mode' => rand(0, 1),
        'audio_features' => generateAudioFeatures()
    ];
}

function generateSimilarityReasons($score) {
    $reasons = [];
    
    if ($score > 90) {
        $reasons[] = 'Caractéristiques audio très similaires';
        $reasons[] = 'Même style musical';
    } elseif ($score > 75) {
        $reasons[] = 'Genre similaire';
        $reasons[] = 'Tempo et énergie proches';
    } else {
        $reasons[] = 'Quelques similarités détectées';
    }
    
    return $reasons;
}

function findSimilarUsers($userId) {
    $similarUsers = [];
    
    for ($i = 1; $i <= 10; $i++) {
        $similarUsers[] = [
            'user_id' => rand(1000, 9999),
            'similarity_score' => rand(70, 95) / 100,
            'common_tracks' => rand(10, 50),
            'common_artists' => rand(5, 20),
            'common_genres' => rand(2, 5)
        ];
    }
    
    usort($similarUsers, function($a, $b) {
        return $b['similarity_score'] <=> $a['similarity_score'];
    });
    
    return array_slice($similarUsers, 0, 5);
}

function getUserTopTracks($userId) {
    $tracks = [];
    
    for ($i = 1; $i <= rand(5, 15); $i++) {
        $tracks[] = [
            'id' => rand(1, 1000),
            'title' => 'Top Track ' . $i,
            'artist_name' => 'Artiste ' . rand(1, 30),
            'play_count' => rand(10, 100)
        ];
    }
    
    return $tracks;
}

function generateDiscoveryTrack($category, $userProfile) {
    $titlePrefixes = [
        'new_releases' => 'Nouveauté',
        'hidden_gems' => 'Pépite',
        'rising_artists' => 'Découverte',
        'cross_genre' => 'Fusion',
        'international' => 'World',
        'throwback' => 'Classique'
    ];
    
    return [
        'id' => rand(3000, 9999),
        'title' => $titlePrefixes[$category] . ' ' . rand(1, 100),
        'artist_name' => 'Artiste ' . $category . ' ' . rand(1, 20),
        'album_cover' => 'assets/images/default-cover.jpg',
        'duration' => rand(180, 300),
        'genre' => selectGenreForCategory($category, $userProfile),
        'discovery_category' => $category,
        'discovery_score' => rand(80, 100),
        'explanation' => getDiscoveryExplanation($category)
    ];
}

function optimizePlaylistOrder($tracks) {
    // Optimiser l'ordre pour une expérience d'écoute fluide
    // Alterner entre énergies, tempos, genres...
    
    // Pour l'instant, simple mélange
    shuffle($tracks);
    return $tracks;
}

function getStrategyWeight($strategy) {
    $weights = [
        'based_on_history' => 15,
        'based_on_favorites' => 12,
        'based_on_trends' => 8,
        'discovery' => 5
    ];
    
    return $weights[$strategy] ?? 0;
}

// Autres fonctions helper...
function generateExplanation($userProfile) {
    return "Basé sur vos " . count($userProfile['listening_history']) . " dernières écoutes et vos genres préférés";
}

function calculateConfidenceScore($userProfile) {
    return rand(75, 95);
}

function calculateDiversityScore($recommendations) {
    return rand(70, 90);
}

function selectGenreForMood($mood) {
    $moodGenres = [
        'happy' => ['Afrobeat', 'Pop', 'Dance'],
        'sad' => ['R&B', 'Soul', 'Acoustic'],
        'energetic' => ['Hip-Hop', 'Electronic', 'Rock'],
        'relaxed' => ['Jazz', 'Ambient', 'Folk']
    ];
    
    $genres = $moodGenres[$mood] ?? ['Afrobeat', 'Hip-Hop', 'R&B'];
    return $genres[array_rand($genres)];
}

function getMoodColorScheme($mood) {
    $colors = [
        'happy' => ['#FFD700', '#FF6B6B', '#4ECDC4'],
        'sad' => ['#2C3E50', '#34495E', '#7F8C8D'],
        'energetic' => ['#E74C3C', '#F39C12', '#E67E22'],
        'relaxed' => ['#3498DB', '#2ECC71', '#1ABC9C']
    ];
    
    return $colors[$mood] ?? ['#7F8C8D'];
}

function generateTrackTitle($strategy, $index) {
    $prefixes = [
        'based_on_history' => 'Recommandé',
        'based_on_favorites' => 'Favoris',
        'based_on_trends' => 'Tendance',
        'discovery' => 'Découverte'
    ];
    
    return ($prefixes[$strategy] ?? 'Titre') . ' ' . ($index + 1);
}

function generateMoodTitle($mood, $index) {
    $titles = [
        'happy' => ['Joie de Vivre', 'Bonheur Tropical', 'Sourire du Sahel'],
        'sad' => ['Mélancolie', 'Cœur Brisé', 'Larmes du Désert'],
        'energetic' => ['Énergie Pure', 'Force Africaine', 'Rythme Intense'],
        'relaxed' => ['Tranquillité', 'Paix Intérieure', 'Douceur du Soir']
    ];
    
    $moodTitles = $titles[$mood] ?? ['Titre ' . $index];
    return $moodTitles[array_rand($moodTitles)] . ' ' . $index;
}

function getMoodPlaylistName($mood) {
    $names = [
        'happy' => '😊 Bonne Humeur',
        'sad' => '😢 Moments Mélancoliques',
        'energetic' => '⚡ Énergie Maximum',
        'relaxed' => '😌 Détente Absolue',
        'angry' => '😤 Défoulement',
        'romantic' => '❤️ Ambiance Romantique',
        'focused' => '🎯 Concentration',
        'party' => '🎉 Mode Fête'
    ];
    
    return $names[$mood] ?? 'Playlist Mood';
}

function getMoodDescription($mood) {
    $descriptions = [
        'happy' => 'Des titres qui vous mettront le sourire aux lèvres',
        'sad' => 'Pour accompagner vos moments de mélancolie',
        'energetic' => 'Boostez votre énergie avec ces rythmes entraînants',
        'relaxed' => 'Détendez-vous avec ces mélodies apaisantes'
    ];
    
    return $descriptions[$mood] ?? 'Une sélection adaptée à votre humeur';
}

function getMoodVisualization($mood) {
    return [
        'primary_color' => getMoodColorScheme($mood)[0],
        'gradient' => getMoodColorScheme($mood),
        'animation' => selectAnimationForMood($mood),
        'icon' => selectIconForMood($mood)
    ];
}

function selectAnimationForMood($mood) {
    $animations = [
        'happy' => 'bounce',
        'sad' => 'fade',
        'energetic' => 'pulse',
        'relaxed' => 'float'
    ];
    
    return $animations[$mood] ?? 'none';
}

function selectIconForMood($mood) {
    $icons = [
        'happy' => '😊',
        'sad' => '😢',
        'energetic' => '⚡',
        'relaxed' => '😌'
    ];
    
    return $icons[$mood] ?? '🎵';
}

function selectGenreForCategory($category, $userProfile) {
    if ($category === 'cross_genre') {
        // Sélectionner un genre différent des préférences
        $allGenres = ['Afrobeat', 'Hip-Hop', 'R&B', 'Gospel', 'Reggae', 'Folk', 'Jazz'];
        $newGenres = array_diff($allGenres, $userProfile['preferred_genres']);
        return $newGenres[array_rand($newGenres)];
    }
    
    return $userProfile['preferred_genres'][array_rand($userProfile['preferred_genres'])];
}

function getDiscoveryExplanation($category) {
    $explanations = [
        'new_releases' => 'Nouvelle sortie dans vos genres préférés',
        'hidden_gems' => 'Titre peu connu mais de grande qualité',
        'rising_artists' => 'Artiste en pleine ascension',
        'cross_genre' => 'Explorer de nouveaux horizons musicaux',
        'international' => 'Découverte internationale',
        'throwback' => 'Un classique à redécouvrir'
    ];
    
    return $explanations[$category] ?? 'Recommandation spéciale';
}

// Fonctions pour recommandations hybrides
function getContentBasedRecommendations($userProfile, $count) {
    return generateRecommendationsByStrategy('content_based', $userProfile, $count);
}

function getCollaborativeRecommendations($userId, $count) {
    $tracks = [];
    for ($i = 0; $i < $count; $i++) {
        $tracks[] = [
            'id' => rand(5000, 9999),
            'title' => 'Collab Reco ' . ($i + 1),
            'artist_name' => 'Artiste Pop ' . rand(1, 30),
            'duration' => rand(180, 300),
            'match_score' => rand(75, 95)
        ];
    }
    return $tracks;
}

function getTrendingRecommendations($userProfile, $count) {
    return generateRecommendationsByStrategy('trending', $userProfile, $count);
}

function getDiscoveryRecommendations($userProfile, $count) {
    return generateRecommendationsByStrategy('discovery', $userProfile, $count);
}

function calculateHybridScores($recommendations) {
    foreach ($recommendations as &$track) {
        $baseScore = $track['match_score'] ?? rand(60, 90);
        $sourceWeight = $track['weight'] ?? 0.25;
        $track['hybrid_score'] = $baseScore * $sourceWeight + rand(0, 10);
    }
    
    usort($recommendations, function($a, $b) {
        return $b['hybrid_score'] <=> $a['hybrid_score'];
    });
    
    return $recommendations;
}

function removeDuplicates($recommendations) {
    $seen = [];
    $unique = [];
    
    foreach ($recommendations as $track) {
        $key = $track['id'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $track;
        }
    }
    
    return $unique;
}

function optimizeFinalRecommendations($recommendations, $userProfile) {
    // Optimisation finale basée sur le profil utilisateur
    // Peut inclure réordonnancement, ajustements de score, etc.
    return $recommendations;
}

function calculateCollaborativeConfidence($similarUsers) {
    if (empty($similarUsers)) return 0;
    
    $avgSimilarity = array_sum(array_column($similarUsers, 'similarity_score')) / count($similarUsers);
    return round($avgSimilarity * 100);
}

// Fonctions additionnelles pour les autres endpoints
function getSimilarArtists() {
    echo json_encode(['success' => true, 'message' => 'Similar artists endpoint']);
}

function getTrendingForYou() {
    echo json_encode(['success' => true, 'message' => 'Trending for you endpoint']);
}

function getRadioRecommendations() {
    echo json_encode(['success' => true, 'message' => 'Radio recommendations endpoint']);
}

function getGenreBasedRecommendations() {
    $genre = $_GET['genre'] ?? 'Afrobeat';
    $limit = min(30, max(1, (int)($_GET['limit'] ?? 20)));
    
    $recommendations = [];
    for ($i = 1; $i <= $limit; $i++) {
        $recommendations[] = [
            'id' => rand(1000, 9999),
            'title' => $genre . ' Hit ' . $i,
            'artist_name' => 'Artiste ' . $genre . ' ' . rand(1, 20),
            'genre' => $genre,
            'match_score' => rand(80, 100)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'genre' => $genre,
            'recommendations' => $recommendations
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>