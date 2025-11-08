<?php
/**
 * Fonctions d'accès à la base de données - Tchadok Platform
 * Remplace les données statiques par des requêtes dynamiques
 */

require_once __DIR__ . '/../config/constants.php';

class TchadokDatabase {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 
                'dansia', 
                'dansia',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->pdo = null;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function isConnected() {
        return $this->pdo !== null;
    }
}

// Fonctions pour récupérer les données dynamiquement

/**
 * Récupère les nouvelles sorties
 */
function getNewReleases($limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT a.title, ar.stage_name as artist, a.album_type as type, 
                   a.price, a.currency, a.is_featured, a.release_date, a.id,
                   ar.genre
            FROM albums a 
            JOIN artists ar ON a.artist_id = ar.id 
            ORDER BY a.release_date DESC, a.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $releases = $stmt->fetchAll();
        
        // Transforme pour compatibilité avec l'ancien format
        foreach ($releases as &$release) {
            $release['badge'] = $release['type'];
            $release['badge_class'] = getBadgeClass($release['type'], $release['is_featured']);
            $release['price'] = $release['price'] . ' ' . $release['currency'];
            $release['color'] = getColorForGenre($release['genre']);
            $release['bg'] = 'FFFFFF';
            
            if ($release['is_featured']) {
                $release['extra'] = '<i class="fas fa-fire text-danger"></i> Tendance';
                $release['price'] = 'Gratuit';
            }
        }
        
        return $releases;
    } catch (Exception $e) {
        error_log("Error fetching new releases: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les artistes populaires
 */
function getPopularArtists($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT stage_name as name, genre, monthly_listeners, is_verified
            FROM artists 
            ORDER BY monthly_listeners DESC, total_streams DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $artists = $stmt->fetchAll();
        
        // Transforme pour compatibilité
        foreach ($artists as &$artist) {
            $artist['color'] = getColorForGenre($artist['genre']);
            $artist['bg'] = 'FFFFFF';
        }
        
        return $artists;
    } catch (Exception $e) {
        error_log("Error fetching popular artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les émissions radio
 */
function getRadioShows($limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT rs.title, rs.description, rs.start_time, rs.end_time, 
                   u.first_name, u.last_name, rs.genre, rs.is_active
            FROM radio_shows rs
            JOIN users u ON rs.host_user_id = u.id
            WHERE rs.is_active = 1
            ORDER BY rs.start_time
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $shows = $stmt->fetchAll();
        
        // Formate pour l'affichage
        foreach ($shows as &$show) {
            $show['time_display'] = date('H\h - ', strtotime($show['start_time'])) . date('H\h', strtotime($show['end_time']));
            $show['host'] = $show['first_name'] . ' ' . $show['last_name'];
            $show['background'] = getGradientForShow($show['genre']);
        }
        
        return $shows;
    } catch (Exception $e) {
        error_log("Error fetching radio shows: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les statistiques de la plateforme
 */
function getPlatformStats() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) {
        return [
            'total_tracks' => 50000,
            'total_artists' => 500,
            'total_users' => 10000,
            'streaming_hours' => 24
        ];
    }
    
    try {
        $stats = [];
        
        // Nombre total de pistes
        $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM tracks");
        $stats['total_tracks'] = $stmt->fetchColumn();
        
        // Nombre total d'artistes
        $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM artists");
        $stats['total_artists'] = $stmt->fetchColumn();
        
        // Nombre total d'utilisateurs actifs
        $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM users WHERE user_type != 'admin'");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Heures de streaming (simulation basée sur les écoutes)
        $stmt = $db->getConnection()->query("SELECT SUM(play_count * duration_seconds) / 3600 as hours FROM tracks");
        $streamingHours = $stmt->fetchColumn() ?: 24;
        $stats['streaming_hours'] = round($streamingHours);
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error fetching platform stats: " . $e->getMessage());
        return [
            'total_tracks' => 50000,
            'total_artists' => 500,
            'total_users' => 10000,
            'streaming_hours' => 24
        ];
    }
}

/**
 * Récupère les pistes tendances
 */
function getTrendingTracks($limit = 5) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, ar.stage_name as artist, t.play_count as plays,
                   a.title as album
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            JOIN albums a ON t.album_id = a.id
            ORDER BY t.play_count DESC, t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching trending tracks: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les artistes en vedette
 */
function getFeaturedArtists($limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT id, stage_name as name, genre, monthly_listeners as followers
            FROM artists 
            WHERE is_verified = 1 OR monthly_listeners > 5000
            ORDER BY monthly_listeners DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching featured artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Recherche dans la base de données
 */
function searchContent($query, $limit = 10) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return ['tracks' => [], 'artists' => [], 'albums' => []];
    
    try {
        $searchTerm = "%$query%";
        $results = [];
        
        // Recherche de pistes
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, ar.stage_name as artist, 
                   MATCH(t.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            WHERE t.title LIKE ? OR ar.stage_name LIKE ?
            ORDER BY relevance DESC, t.play_count DESC
            LIMIT ?
        ");
        $stmt->execute([$query, $searchTerm, $searchTerm, $limit]);
        $results['tracks'] = $stmt->fetchAll();
        
        // Recherche d'artistes
        $stmt = $db->getConnection()->prepare("
            SELECT id, stage_name as name, genre,
                   MATCH(stage_name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            FROM artists
            WHERE stage_name LIKE ? OR real_name LIKE ? OR genre LIKE ?
            ORDER BY relevance DESC, monthly_listeners DESC
            LIMIT ?
        ");
        $stmt->execute([$query, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['artists'] = $stmt->fetchAll();
        
        // Recherche d'albums
        $stmt = $db->getConnection()->prepare("
            SELECT a.id, a.title, ar.stage_name as artist,
                   MATCH(a.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.id
            WHERE a.title LIKE ? OR ar.stage_name LIKE ?
            ORDER BY relevance DESC, a.release_date DESC
            LIMIT ?
        ");
        $stmt->execute([$query, $searchTerm, $searchTerm, $limit]);
        $results['albums'] = $stmt->fetchAll();
        
        return $results;
    } catch (Exception $e) {
        error_log("Error searching content: " . $e->getMessage());
        return ['tracks' => [], 'artists' => [], 'albums' => []];
    }
}

// Fonctions utilitaires

function getBadgeClass($type, $isFeatured) {
    if ($isFeatured) return 'bg-success';
    
    switch (strtolower($type)) {
        case 'album': return 'bg-primary';
        case 'ep': return 'bg-info text-dark';
        case 'single': return 'bg-secondary';
        case 'live': return 'bg-danger';
        default: return 'bg-warning text-dark';
    }
}

function getColorForGenre($genre) {
    $colors = [
        'Afrobeat' => 'FFD700',
        'Hip Hop' => '228B22',
        'R&B/Soul' => '0066CC',
        'Gospel' => '667eea',
        'Jazz Fusion' => 'f093fb',
        'Traditionnel' => 'CC3333',
        'Pop' => 'FF69B4',
        'Reggae' => '32CD32',
        'Blues' => '4169E1',
        'Folk' => '8B4513'
    ];
    
    return $colors[$genre] ?? '0066CC';
}

function getGradientForShow($genre) {
    $gradients = [
        'Variété' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'Hip Hop' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'Jazz' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'Gospel' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'Traditionnel' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'
    ];
    
    return $gradients[$genre] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
}

/**
 * Vérifie les informations de connexion admin
 */
function checkAdminCredentials($username, $password) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return false;
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT u.id, u.password, u.password_hash, a.role 
            FROM users u
            JOIN admins a ON u.id = a.user_id
            WHERE (u.username = ? OR u.email = ?)
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        // Vérifier les deux colonnes password et password_hash
        if ($admin) {
            if (password_verify($password, $admin['password']) || password_verify($password, $admin['password_hash'])) {
                return $admin['id'];
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking admin credentials: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les informations d'un utilisateur
 */
function getUserById($userId) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return null;
    
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT * FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}
?>