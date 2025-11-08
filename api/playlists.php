<?php
/**
 * API de gestion des playlists et favoris - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId && in_array($method, ['POST', 'PUT', 'DELETE'])) {
        throw new Exception('Authentification requise', 401);
    }
    
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
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
 * Gestion des requêtes GET
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'list';
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
    
    switch ($action) {
        case 'list':
            getUserPlaylists($userId);
            break;
        case 'get':
            getPlaylist($_GET['id'] ?? null);
            break;
        case 'tracks':
            getPlaylistTracks($_GET['playlist_id'] ?? null);
            break;
        case 'favorites':
            getUserFavorites($userId, $_GET['type'] ?? 'tracks');
            break;
        case 'public':
            getPublicPlaylists();
            break;
        case 'featured':
            getFeaturedPlaylists();
            break;
        case 'search':
            searchPlaylists($_GET['q'] ?? '');
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes POST
 */
function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            createPlaylist($input ?: $_POST);
            break;
        case 'add_track':
            addTrackToPlaylist($input ?: $_POST);
            break;
        case 'add_favorite':
            addToFavorites($input ?: $_POST);
            break;
        case 'follow':
            followPlaylist($input ?: $_POST);
            break;
        case 'share':
            sharePlaylist($input ?: $_POST);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes PUT
 */
function handlePutRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'update';
    
    switch ($action) {
        case 'update':
            updatePlaylist($input);
            break;
        case 'reorder':
            reorderPlaylistTracks($input);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes DELETE
 */
function handleDeleteRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? 'delete';
    
    switch ($action) {
        case 'delete':
            deletePlaylist($input['playlist_id'] ?? $_GET['id']);
            break;
        case 'remove_track':
            removeTrackFromPlaylist($input);
            break;
        case 'remove_favorite':
            removeFromFavorites($input);
            break;
        case 'unfollow':
            unfollowPlaylist($input);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Récupérer les playlists d'un utilisateur
 */
function getUserPlaylists($userId) {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    // Simulation de playlists utilisateur
    $playlists = [
        [
            'id' => 1,
            'name' => 'Mes favoris',
            'description' => 'Ma collection de titres préférés',
            'cover_image' => 'assets/images/default-playlist.jpg',
            'is_public' => false,
            'is_system' => true,
            'track_count' => rand(15, 50),
            'total_duration' => rand(3600, 10800),
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'play_count' => rand(100, 1000),
            'is_collaborative' => false,
            'tags' => ['favoris', 'personnel']
        ],
        [
            'id' => 2,
            'name' => 'Afrobeat Vibes',
            'description' => 'Les meilleurs sons afrobeat tchadiens',
            'cover_image' => 'assets/images/default-playlist.jpg',
            'is_public' => true,
            'is_system' => false,
            'track_count' => rand(20, 40),
            'total_duration' => rand(4800, 9600),
            'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'play_count' => rand(50, 500),
            'is_collaborative' => false,
            'tags' => ['afrobeat', 'energie', 'danse']
        ],
        [
            'id' => 3,
            'name' => 'Chill Tchadien',
            'description' => 'Pour les moments de détente',
            'cover_image' => 'assets/images/default-playlist.jpg',
            'is_public' => true,
            'is_system' => false,
            'track_count' => rand(10, 25),
            'total_duration' => rand(2400, 6000),
            'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            'play_count' => rand(20, 200),
            'is_collaborative' => true,
            'tags' => ['chill', 'relax', 'ambiance']
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'playlists' => $playlists,
            'total_count' => count($playlists),
            'user_id' => $userId
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupérer une playlist spécifique
 */
function getPlaylist($playlistId) {
    if (!$playlistId) {
        throw new Exception('ID de playlist requis', 400);
    }
    
    $playlist = [
        'id' => $playlistId,
        'name' => 'Afrobeat Vibes',
        'description' => 'Les meilleurs sons afrobeat tchadiens pour danser et vibrer',
        'cover_image' => 'assets/images/default-playlist.jpg',
        'owner' => [
            'id' => $_SESSION['user_id'] ?? 1,
            'name' => 'Utilisateur Tchadok',
            'avatar' => 'assets/images/default-avatar.png'
        ],
        'is_public' => true,
        'is_collaborative' => false,
        'track_count' => rand(15, 30),
        'total_duration' => rand(3600, 7200),
        'total_plays' => rand(1000, 10000),
        'followers_count' => rand(50, 500),
        'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
        'updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'tags' => ['afrobeat', 'danse', 'energie', 'tchad'],
        'genre_distribution' => [
            'Afrobeat' => 60,
            'Hip-Hop' => 25,
            'R&B' => 15
        ],
        'is_following' => rand(0, 1) === 1,
        'can_edit' => $_SESSION['user_id'] ?? false,
        'share_url' => SITE_URL . '/playlist/' . $playlistId,
        'embed_code' => '<iframe src="' . SITE_URL . '/embed/playlist/' . $playlistId . '" width="400" height="600"></iframe>'
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $playlist
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupérer les titres d'une playlist
 */
function getPlaylistTracks($playlistId) {
    if (!$playlistId) {
        throw new Exception('ID de playlist requis', 400);
    }
    
    $tracks = [];
    for ($i = 1; $i <= rand(10, 20); $i++) {
        $tracks[] = [
            'id' => $i,
            'position' => $i,
            'track' => [
                'id' => $i + 100,
                'title' => 'Titre Playlist ' . $i,
                'artist_id' => rand(1, 10),
                'artist_name' => 'Artiste ' . rand(1, 10),
                'album_id' => rand(1, 5),
                'album_title' => 'Album ' . rand(1, 5),
                'album_cover' => 'assets/images/default-cover.jpg',
                'duration' => rand(180, 300),
                'genre' => ['Afrobeat', 'Hip-Hop', 'R&B'][rand(0, 2)],
                'total_streams' => rand(1000, 50000),
                'is_free' => rand(0, 1) === 1,
                'price' => rand(500, 2000),
                'audio_preview' => 'assets/audio/preview_' . $i . '.mp3'
            ],
            'added_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'added_by' => [
                'id' => rand(1, 5),
                'name' => 'Utilisateur ' . rand(1, 5)
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'playlist_id' => $playlistId,
            'tracks' => $tracks,
            'total_count' => count($tracks),
            'total_duration' => array_sum(array_column(array_column($tracks, 'track'), 'duration'))
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupérer les favoris d'un utilisateur
 */
function getUserFavorites($userId, $type = 'tracks') {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    $favorites = [];
    
    switch ($type) {
        case 'tracks':
            for ($i = 1; $i <= rand(10, 30); $i++) {
                $favorites[] = [
                    'id' => $i,
                    'item' => [
                        'id' => $i + 200,
                        'title' => 'Titre Favori ' . $i,
                        'artist_name' => 'Artiste Favori ' . $i,
                        'album_cover' => 'assets/images/default-cover.jpg',
                        'duration' => rand(180, 300),
                        'genre' => ['Afrobeat', 'Hip-Hop', 'Gospel'][rand(0, 2)]
                    ],
                    'added_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
                    'play_count' => rand(5, 50)
                ];
            }
            break;
            
        case 'artists':
            for ($i = 1; $i <= rand(5, 15); $i++) {
                $favorites[] = [
                    'id' => $i,
                    'item' => [
                        'id' => $i + 300,
                        'stage_name' => 'Artiste Favori ' . $i,
                        'profile_image' => 'assets/images/default-avatar.png',
                        'verified' => $i <= 3,
                        'total_tracks' => rand(10, 50),
                        'genres' => ['Afrobeat', 'Hip-Hop']
                    ],
                    'added_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'))
                ];
            }
            break;
            
        case 'albums':
            for ($i = 1; $i <= rand(3, 10); $i++) {
                $favorites[] = [
                    'id' => $i,
                    'item' => [
                        'id' => $i + 400,
                        'title' => 'Album Favori ' . $i,
                        'artist_name' => 'Artiste ' . $i,
                        'album_cover' => 'assets/images/default-cover.jpg',
                        'track_count' => rand(8, 15),
                        'release_date' => date('Y-m-d', strtotime('-' . rand(30, 365) . ' days'))
                    ],
                    'added_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'))
                ];
            }
            break;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'type' => $type,
            'user_id' => $userId,
            'favorites' => $favorites,
            'total_count' => count($favorites),
            'last_updated' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Créer une nouvelle playlist
 */
function createPlaylist($data) {
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $isPublic = (bool)($data['is_public'] ?? false);
    $isCollaborative = (bool)($data['is_collaborative'] ?? false);
    $tags = $data['tags'] ?? [];
    
    if (empty($name)) {
        throw new Exception('Le nom de la playlist est requis', 400);
    }
    
    if (strlen($name) > 100) {
        throw new Exception('Le nom de la playlist ne peut pas dépasser 100 caractères', 400);
    }
    
    $playlist = [
        'id' => rand(1000, 9999),
        'name' => $name,
        'description' => $description,
        'owner_id' => $_SESSION['user_id'],
        'is_public' => $isPublic,
        'is_collaborative' => $isCollaborative,
        'cover_image' => 'assets/images/default-playlist.jpg',
        'track_count' => 0,
        'total_duration' => 0,
        'play_count' => 0,
        'followers_count' => 0,
        'tags' => is_array($tags) ? $tags : [],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Simulation de sauvegarde
    savePlaylist($playlist);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'playlist' => $playlist,
            'message' => 'Playlist créée avec succès'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Ajouter un titre à une playlist
 */
function addTrackToPlaylist($data) {
    $playlistId = (int)($data['playlist_id'] ?? 0);
    $trackId = (int)($data['track_id'] ?? 0);
    $position = (int)($data['position'] ?? null);
    
    if (!$playlistId || !$trackId) {
        throw new Exception('ID de playlist et de titre requis', 400);
    }
    
    // Vérification des permissions
    if (!canEditPlaylist($playlistId, $_SESSION['user_id'])) {
        throw new Exception('Permission refusée', 403);
    }
    
    // Vérification si le titre n'est pas déjà dans la playlist
    if (isTrackInPlaylist($playlistId, $trackId)) {
        throw new Exception('Ce titre est déjà dans la playlist', 409);
    }
    
    $playlistTrack = [
        'id' => rand(10000, 99999),
        'playlist_id' => $playlistId,
        'track_id' => $trackId,
        'position' => $position ?: getNextPosition($playlistId),
        'added_by' => $_SESSION['user_id'],
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    // Simulation de sauvegarde
    savePlaylistTrack($playlistTrack);
    updatePlaylistStats($playlistId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'playlist_track' => $playlistTrack,
            'message' => 'Titre ajouté à la playlist avec succès'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Ajouter aux favoris
 */
function addToFavorites($data) {
    $itemId = (int)($data['item_id'] ?? 0);
    $itemType = $data['item_type'] ?? 'track'; // track, artist, album
    
    if (!$itemId) {
        throw new Exception('ID de l\'élément requis', 400);
    }
    
    if (!in_array($itemType, ['track', 'artist', 'album'])) {
        throw new Exception('Type d\'élément invalide', 400);
    }
    
    // Vérifier si déjà en favoris
    if (isInFavorites($_SESSION['user_id'], $itemId, $itemType)) {
        throw new Exception('Cet élément est déjà dans vos favoris', 409);
    }
    
    $favorite = [
        'id' => rand(10000, 99999),
        'user_id' => $_SESSION['user_id'],
        'item_id' => $itemId,
        'item_type' => $itemType,
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    // Simulation de sauvegarde
    saveFavorite($favorite);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'favorite' => $favorite,
            'message' => 'Ajouté aux favoris avec succès'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Playlists publiques populaires
 */
function getPublicPlaylists() {
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    $genre = $_GET['genre'] ?? '';
    
    $playlists = [];
    for ($i = 1; $i <= $limit; $i++) {
        $playlists[] = [
            'id' => $i + 500,
            'name' => 'Playlist Publique ' . $i,
            'description' => 'Une sélection de titres populaires',
            'owner' => [
                'id' => rand(1, 100),
                'name' => 'Utilisateur ' . rand(1, 100),
                'avatar' => 'assets/images/default-avatar.png'
            ],
            'cover_image' => 'assets/images/default-playlist.jpg',
            'track_count' => rand(15, 50),
            'followers_count' => rand(10, 1000),
            'play_count' => rand(100, 10000),
            'tags' => ['populaire', 'tendance'],
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
            'is_following' => rand(0, 1) === 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'playlists' => $playlists,
            'total_count' => $limit,
            'genre' => $genre
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Playlists mises en avant
 */
function getFeaturedPlaylists() {
    $featured = [
        [
            'id' => 999,
            'name' => 'Top Afrobeat Tchadien 2024',
            'description' => 'Les meilleurs titres afrobeat de l\'année',
            'cover_image' => 'assets/images/featured-afrobeat.jpg',
            'track_count' => 25,
            'followers_count' => 5000,
            'is_official' => true,
            'curator' => 'Équipe Tchadok'
        ],
        [
            'id' => 998,
            'name' => 'Découvertes de la Semaine',
            'description' => 'Nouveaux talents à découvrir',
            'cover_image' => 'assets/images/featured-discovery.jpg',
            'track_count' => 15,
            'followers_count' => 2500,
            'is_official' => true,
            'curator' => 'Équipe Tchadok'
        ],
        [
            'id' => 997,
            'name' => 'Gospel Inspiration',
            'description' => 'Musique spirituelle tchadienne',
            'cover_image' => 'assets/images/featured-gospel.jpg',
            'track_count' => 20,
            'followers_count' => 3000,
            'is_official' => true,
            'curator' => 'Équipe Tchadok'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'featured_playlists' => $featured,
            'updated_at' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// Fonctions helper (simulation)
function savePlaylist($playlist) { return true; }
function savePlaylistTrack($playlistTrack) { return true; }
function saveFavorite($favorite) { return true; }
function canEditPlaylist($playlistId, $userId) { return true; }
function isTrackInPlaylist($playlistId, $trackId) { return rand(0, 1) === 0; }
function isInFavorites($userId, $itemId, $itemType) { return rand(0, 1) === 0; }
function getNextPosition($playlistId) { return rand(1, 50); }
function updatePlaylistStats($playlistId) { return true; }

function updatePlaylist($data) {
    echo json_encode(['success' => true, 'message' => 'Playlist mise à jour']);
}

function deletePlaylist($playlistId) {
    echo json_encode(['success' => true, 'message' => 'Playlist supprimée']);
}

function removeTrackFromPlaylist($data) {
    echo json_encode(['success' => true, 'message' => 'Titre retiré de la playlist']);
}

function removeFromFavorites($data) {
    echo json_encode(['success' => true, 'message' => 'Retiré des favoris']);
}

function followPlaylist($data) {
    echo json_encode(['success' => true, 'message' => 'Playlist suivie']);
}

function unfollowPlaylist($data) {
    echo json_encode(['success' => true, 'message' => 'Playlist plus suivie']);
}

function sharePlaylist($data) {
    echo json_encode(['success' => true, 'share_url' => SITE_URL . '/playlist/' . $data['playlist_id']]);
}

function searchPlaylists($query) {
    echo json_encode(['success' => true, 'data' => ['playlists' => [], 'query' => $query]]);
}

function reorderPlaylistTracks($data) {
    echo json_encode(['success' => true, 'message' => 'Ordre des titres mis à jour']);
}
?>