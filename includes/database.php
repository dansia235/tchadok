<?php
/**
 * Fonctions d'acces a la base de donnees - Tchadok Platform
 * Requetes dynamiques basees sur le schema reel de la BD
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/radio-engine.php';

class TchadokDatabase {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // SEC-03 : aucune valeur de repli sur les identifiants.
            // Auparavant env('DB_USERNAME', 'dansia') masquait l'absence de
            // configuration et versionnait un identifiant dans le code.
            // Une variable manquante doit echouer bruyamment, pas silencieusement.
            $required = ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
            $missing = [];
            foreach ($required as $key) {
                if (env($key, null) === null || env($key, null) === '') {
                    $missing[] = $key;
                }
            }
            if ($missing) {
                throw new RuntimeException(
                    'Configuration de base de donnees incomplete. Variables manquantes : '
                    . implode(', ', $missing)
                    . '. Verifiez votre fichier d\'environnement.'
                );
            }

            $host = env('DB_HOST', '127.0.0.1');
            $database = env('DB_DATABASE');
            $username = env('DB_USERNAME');
            $password = env('DB_PASSWORD');
            $charset = env('DB_CHARSET', 'utf8mb4');
            $port = env('DB_PORT', '3306');

            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=$charset";

            $this->pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // Heure de N'Djamena (UTC+1, sans heure d'ete) pour NOW() :
                    // les dates d'ecoute et de vente doivent tomber dans la meme
                    // journee que celle calculee par PHP (arretes du barometre),
                    // quel que soit le fuseau du serveur de base de donnees.
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset, time_zone = '+01:00'"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->pdo = null;
        } catch (RuntimeException $e) {
            error_log("Database configuration error: " . $e->getMessage());
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

/**
 * Verifie si une table existe dans la base courante
 */
function tableExists($tableName) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return false;

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// FONCTIONS DE RECUPERATION DE DONNEES
// ============================================================

/**
 * Recupere les nouvelles sorties (albums)
 */
function getNewReleases($limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT a.id, a.title, ar.stage_name AS artist, a.type,
                   a.price, a.is_free, a.is_featured, a.release_date,
                   a.cover_image, a.total_streams,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres, g.name AS genre_name, g.color AS genre_color
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.id
            LEFT JOIN genres g ON a.genre_id = g.id
            WHERE a.status = 'approved' AND a.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY a.release_date DESC, a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $releases = $stmt->fetchAll();

        foreach ($releases as &$release) {
            $release['badge'] = ucfirst($release['type'] ?? 'album');
            $release['badge_class'] = getBadgeClass($release['type'], $release['is_featured']);
            $release['color'] = ltrim($release['genre_color'] ?? '', '#') ?: getColorForGenre($release['artist_genres'] ?? '');
            $release['bg'] = 'FFFFFF';

            if ($release['is_free'] || $release['is_featured']) {
                $release['extra'] = '<i class="fas fa-fire text-danger"></i> Tendance';
                $release['price'] = 'Gratuit';
            } else {
                $release['price'] = number_format($release['price'], 0, ',', ' ') . ' FCFA';
            }
        }

        return $releases;
    } catch (Exception $e) {
        error_log("Error fetching new releases: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les artistes populaires (tries par total_streams)
 */
function getPopularArtists($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT ar.id, ar.stage_name AS name, (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS genre,
                   ar.total_streams, ar.verified, ar.featured,
                   ar.profile_image
            FROM artists ar
            WHERE ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY ar.total_streams DESC, ar.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$artist) {
            $artist['color'] = getColorForGenre($artist['genre'] ?? '');
            $artist['bg'] = 'FFFFFF';
        }

        return $artists;
    } catch (Exception $e) {
        error_log("Error fetching popular artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les emissions radio
 */
function getRadioShows($limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('radio_shows')) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT id, title, description, host_name, start_time, end_time
            FROM radio_shows
            WHERE status = 'active'
            ORDER BY start_time ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $shows = $stmt->fetchAll();

        foreach ($shows as &$show) {
            $show['host'] = $show['host_name'] ?: 'Tchadok Radio';
            $show['time_display'] = formatTimeRange($show['start_time'] ?? null, $show['end_time'] ?? null);
            if (empty($show['description'])) {
                $show['description'] = 'Emission musicale en direct.';
            }
        }

        return $shows;
    } catch (Exception $e) {
        error_log("Error fetching radio shows: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les statistiques reelles de la plateforme
 */
function getPlatformStats() {
    $db = TchadokDatabase::getInstance();
    $defaults = [
        'total_tracks' => 0,
        'total_artists' => 0,
        'total_users' => 0,
        'total_genres' => 0,
        'streaming_hours' => 0
    ];

    if (!$db->isConnected()) return $defaults;

    try {
        $pdo = $db->getConnection();
        $stats = [];

        // SEC-08 : seuls les titres publies entrent dans les statistiques
        // publiques. Brouillons, titres en attente et titres rejetes en
        // etaient auparavant, faussant les chiffres affiches en page d'accueil.
        $stmt = $pdo->query("SELECT COUNT(*) FROM tracks WHERE status = 'approved' AND deleted_at IS NULL AND artist_id IN (SELECT id FROM artists WHERE is_active = 1 AND deleted_at IS NULL)");
        $stats['total_tracks'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM artists WHERE is_active = 1 AND deleted_at IS NULL");
        $stats['total_artists'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stats['total_users'] = (int) $stmt->fetchColumn();

        // DATA-08 : une categorie (ligne ayant des genres enfants) n'est pas un genre.
        $stmt = $pdo->query("SELECT COUNT(*) FROM genres g WHERE g.is_active = 1 AND g.status = 'active'
                             AND NOT EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = g.id)");
        $stats['total_genres'] = (int) $stmt->fetchColumn();

        // Heures de streaming: SUM(total_streams * duration) / 3600
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_streams * duration) / 3600, 0) FROM tracks WHERE status = 'approved' AND deleted_at IS NULL AND artist_id IN (SELECT id FROM artists WHERE is_active = 1 AND deleted_at IS NULL)");
        $stats['streaming_hours'] = max(0, round((float) $stmt->fetchColumn()));

        return $stats;
    } catch (Exception $e) {
        error_log("Error fetching platform stats: " . $e->getMessage());
        return $defaults;
    }
}

/**
 * Compte les ecoutes sur les 30 derniers jours
 */
function getMonthlyStreamsCount() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('streams')) return 0;

    try {
        $stmt = $db->getConnection()->query("
            SELECT COUNT(*) FROM streams
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Recupere les pistes tendances (par total_streams)
 */
function getTrendingTracks($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, t.price, t.is_free, ar.stage_name AS artist, t.total_streams,
                   t.duration, t.is_featured, t.release_date,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres, g.name AS genre_name, g.color AS genre_color
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN genres g ON t.genre_id = g.id
            WHERE t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY t.total_streams DESC, t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $tracks = $stmt->fetchAll();

        foreach ($tracks as &$track) {
            $track['plays'] = formatStreamCount($track['total_streams']);
            $track['color'] = ltrim($track['genre_color'] ?? '', '#') ?: getColorForGenre($track['artist_genres'] ?? '');
            $track['duration_formatted'] = formatDurationShort($track['duration']);
        }

        return $tracks;
    } catch (Exception $e) {
        error_log("Error fetching trending tracks: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les nouvelles pistes (sorties recentes)
 */
function getNewTracks($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, t.price, t.is_free, ar.stage_name AS artist, t.total_streams,
                   t.duration, t.release_date, t.created_at,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres, g.name AS genre_name, g.color AS genre_color
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN genres g ON t.genre_id = g.id
            WHERE t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY t.created_at DESC, t.release_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $tracks = $stmt->fetchAll();

        foreach ($tracks as &$track) {
            $track['color'] = ltrim($track['genre_color'] ?? '', '#') ?: getColorForGenre($track['artist_genres'] ?? '');
            $track['release'] = timeAgoFrench($track['created_at']);
            $track['duration_formatted'] = formatDurationShort($track['duration']);
        }

        return $tracks;
    } catch (Exception $e) {
        error_log("Error fetching new tracks: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les pistes classiques (les plus anciennes avec des streams)
 */
function getClassicTracks($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, t.price, t.is_free, ar.stage_name AS artist, t.total_streams,
                   t.duration, t.release_date, t.created_at,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres, g.name AS genre_name, g.color AS genre_color
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN genres g ON t.genre_id = g.id
            WHERE t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY t.release_date ASC, t.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $tracks = $stmt->fetchAll();

        foreach ($tracks as &$track) {
            $track['color'] = ltrim($track['genre_color'] ?? '', '#') ?: '8B4513';
            $track['year'] = $track['release_date'] ? date('Y', strtotime($track['release_date'])) : date('Y', strtotime($track['created_at']));
            $track['duration_formatted'] = formatDurationShort($track['duration']);
        }

        return $tracks;
    } catch (Exception $e) {
        error_log("Error fetching classic tracks: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les artistes emergents (recents avec croissance)
 */
function getRisingArtists($limit = 6) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT ar.id, ar.stage_name AS artist, ar.total_streams,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres, ar.profile_image, ar.created_at,
                   (SELECT COUNT(*) FROM follows f WHERE f.followed_id = ar.id AND f.followed_type = 'artist') AS followers_count,
                   (SELECT COUNT(*) FROM tracks t WHERE t.artist_id = ar.id AND t.status = 'approved' AND t.deleted_at IS NULL) AS tracks_count
            FROM artists ar
            WHERE ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY ar.created_at DESC, ar.total_streams DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$artist) {
            $artist['color'] = getColorForGenre($artist['artist_genres'] ?? '');
            $artist['followers'] = formatStreamCount($artist['followers_count']);
        }

        return $artists;
    } catch (Exception $e) {
        error_log("Error fetching rising artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les artistes en vedette (verifies ou featured)
 */
function getFeaturedArtists($limit = 3) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT ar.id, ar.stage_name AS name, (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS genre,
                   ar.total_streams, ar.verified, ar.featured,
                   ar.profile_image, ar.bio,
                   (SELECT COUNT(*) FROM follows f WHERE f.followed_id = ar.id AND f.followed_type = 'artist') AS followers_count,
                   (SELECT COUNT(*) FROM tracks t WHERE t.artist_id = ar.id AND t.status = 'approved' AND t.deleted_at IS NULL) AS tracks_count
            FROM artists ar
            WHERE ar.is_active = 1 AND ar.deleted_at IS NULL AND (ar.featured = 1 OR ar.verified = 1)
            ORDER BY ar.featured DESC, ar.total_streams DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$artist) {
            $artist['color'] = getColorForGenre($artist['genre'] ?? '');
            $artist['plays'] = formatStreamCount($artist['total_streams']);
            $artist['followers'] = formatStreamCount($artist['followers_count']);
            $artist['trending'] = $artist['featured'] ? true : false;
        }

        return $artists;
    } catch (Exception $e) {
        error_log("Error fetching featured artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere tous les artistes avec pagination
 */
function getAllArtists($limit = 12, $offset = 0, $genre = null, $filter = null, $sort = 'popularity') {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $where = "ar.is_active = 1 AND ar.deleted_at IS NULL";
        $params = [];

        if ($genre && $genre !== 'all') {
            $where .= " AND EXISTS (SELECT 1 FROM artist_genres agf JOIN genres gf ON gf.id = agf.genre_id WHERE agf.artist_id = ar.id AND LOWER(COALESCE(gf.name_french, gf.name)) LIKE LOWER(?))";
            $params[] = "%$genre%";
        }

        if ($filter === 'verified') {
            $where .= " AND ar.verified = 1";
        } elseif ($filter === 'new') {
            $where .= " AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        } elseif ($filter === 'trending') {
            $where .= " AND ar.featured = 1";
        }

        $orderBy = match($sort) {
            'alphabetical' => 'ar.stage_name ASC',
            'newest' => 'ar.created_at DESC',
            'plays' => 'ar.total_streams DESC',
            default => 'ar.total_streams DESC, ar.featured DESC'
        };

        $sql = "
            SELECT ar.id, ar.stage_name AS name, (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS genre,
                   ar.total_streams, ar.verified, ar.featured,
                   ar.profile_image, ar.created_at,
                   (SELECT COUNT(*) FROM tracks t WHERE t.artist_id = ar.id AND t.status = 'approved' AND t.deleted_at IS NULL) AS tracks_count
            FROM artists ar
            WHERE $where
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $artists = $stmt->fetchAll();

        foreach ($artists as &$artist) {
            $artist['color'] = getColorForGenre($artist['genre'] ?? '');
            $artist['plays'] = formatStreamCount($artist['total_streams']);
            $artist['new'] = (strtotime($artist['created_at']) > strtotime('-3 months'));
        }

        return $artists;
    } catch (Exception $e) {
        error_log("Error fetching all artists: " . $e->getMessage());
        return [];
    }
}

/**
 * Compte le total d'artistes (pour pagination)
 */
function countArtists($genre = null, $filter = null) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return 0;

    try {
        // Memes conditions que getArtists() : le total doit correspondre a la liste.
        $where = "ar.is_active = 1 AND ar.deleted_at IS NULL";
        $params = [];

        if ($genre && $genre !== 'all') {
            // TAXO-02 : genres du referentiel (artist_genres), plus de texte libre.
            $where .= " AND EXISTS (SELECT 1 FROM artist_genres agf JOIN genres gf ON gf.id = agf.genre_id WHERE agf.artist_id = ar.id AND LOWER(COALESCE(gf.name_french, gf.name)) LIKE LOWER(?))";
            $params[] = "%$genre%";
        }
        if ($filter === 'verified') $where .= " AND ar.verified = 1";
        elseif ($filter === 'new') $where .= " AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        elseif ($filter === 'trending') $where .= " AND ar.featured = 1";

        $stmt = $db->getConnection()->prepare("SELECT COUNT(*) FROM artists ar WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Recupere les genres actifs depuis la BD
 */
function getGenres() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT g.id, g.name, g.name_french, g.color, g.icon, g.description
            FROM genres g
            WHERE g.is_active = 1 AND g.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = g.id)
            ORDER BY g.name ASC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching genres: " . $e->getMessage());
        return [];
    }
}

/**
 * Genres proposables au depot d'un titre ou d'une sortie (DATA-08).
 *
 * Le referentiel est hierarchique : une ligne sans parent est une CATEGORIE
 * (« Musiques urbaines »), pas un genre. Classer un titre dans une categorie
 * fausserait le barometre par genre ; seules les lignes rattachees a une
 * categorie sont donc proposees. Les genres fusionnes ou archives ne le sont
 * plus : leurs titres restent classes, mais on n'en ajoute pas.
 *
 * @return array<int,array{id:int,name:string,categorie:string}>
 */
function getGenresSelectionnables(): array {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT g.id, g.name, c.name AS categorie
            FROM genres g
            JOIN genres c ON c.id = g.parent_id
            WHERE g.status = 'active' AND g.is_active = 1
              AND c.status = 'active' AND c.is_active = 1
            ORDER BY c.sort_order, c.name, g.sort_order, g.name
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching selectable genres: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifie cote serveur qu'un genre recu d'un formulaire est proposable.
 * La liste affichee ne protege rien : l'identifiant se modifie avant envoi.
 */
function estGenreSelectionnable(?int $genreId): bool {
    if (!$genreId) return false;

    foreach (getGenresSelectionnables() as $genre) {
        if ((int) $genre['id'] === $genreId) return true;
    }
    return false;
}

/**
 * Recupere les streams recents (pour la radio)
 */
function getRecentStreams($limit = 5) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT t.id AS track_id, t.title, ar.stage_name AS artist, t.duration,
                   s.created_at AS played_at
            FROM streams s
            JOIN tracks t ON s.track_id = t.id
            JOIN artists ar ON s.artist_id = ar.id
            WHERE t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            ORDER BY s.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $streams = $stmt->fetchAll();

        foreach ($streams as &$stream) {
            $stream['time'] = date('H:i', strtotime($stream['played_at']));
            $stream['duration_formatted'] = formatDurationShort($stream['duration']);
        }

        return $streams;
    } catch (Exception $e) {
        error_log("Error fetching recent streams: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les stats de decouverte d'un utilisateur
 */
function getUserDiscoveryStats($userId) {
    $db = TchadokDatabase::getInstance();
    $defaults = ['artists_discovered' => 0, 'listening_hours' => 0, 'favorites_count' => 0];

    if (!$db->isConnected() || !$userId) return $defaults;

    try {
        $pdo = $db->getConnection();

        // Artistes distincts ecoutes ce mois.
        // DATA-07 : la fonction s'applique a NOW(), PAS a la colonne. La
        // comparaison reste donc utilisable par l'index (user_id, created_at).
        // L'inverse serait fautif : appliquer la fonction a la COLONNE filtree
        // interdirait tout index.
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT artist_id) FROM streams
            WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$userId]);
        $defaults['artists_discovered'] = (int) $stmt->fetchColumn();

        // Heures d'ecoute cette semaine
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(duration_played) / 3600, 0) FROM streams
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $defaults['listening_hours'] = round((float) $stmt->fetchColumn());

        // Nombre de favoris
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $stmt->execute([$userId]);
        $defaults['favorites_count'] = (int) $stmt->fetchColumn();

        return $defaults;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Recherche dans la base de donnees
 */
function searchContent($query, $limit = 10) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return ['tracks' => [], 'artists' => [], 'albums' => []];

    try {
        $searchTerm = "%$query%";
        $results = [];

        // Recherche de pistes
        $stmt = $db->getConnection()->prepare("
            SELECT t.id, t.title, t.price, t.is_free, ar.stage_name AS artist, t.total_streams,
                   t.duration, t.is_free, t.price,
                   a.cover_image AS album_cover
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN albums a ON t.album_id = a.id
            WHERE t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
              AND (t.title LIKE ? OR ar.stage_name LIKE ?)
            ORDER BY t.total_streams DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        $results['tracks'] = $stmt->fetchAll();

        // Recherche d'artistes
        $stmt = $db->getConnection()->prepare("
            SELECT ar.id, ar.stage_name AS name,
                   (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS genre,
                   ar.total_streams, ar.profile_image, ar.verified
            FROM artists ar
            WHERE ar.is_active = 1 AND ar.deleted_at IS NULL
              AND (ar.stage_name LIKE ? OR ar.real_name LIKE ?
                   OR EXISTS (SELECT 1 FROM artist_genres agf JOIN genres gf ON gf.id = agf.genre_id WHERE agf.artist_id = ar.id AND COALESCE(gf.name_french, gf.name) LIKE ?))
            ORDER BY ar.total_streams DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['artists'] = $stmt->fetchAll();

        // Recherche d'albums
        $stmt = $db->getConnection()->prepare("
            SELECT a.id, a.title, ar.stage_name AS artist,
                   a.cover_image, a.release_date, a.total_tracks
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.id
            WHERE a.status = 'approved' AND a.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
              AND (a.title LIKE ? OR ar.stage_name LIKE ?)
            ORDER BY a.release_date DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        $results['albums'] = $stmt->fetchAll();

        return $results;
    } catch (Exception $e) {
        error_log("Error searching content: " . $e->getMessage());
        return ['tracks' => [], 'artists' => [], 'albums' => []];
    }
}

/**
 * Recupere les albums avec filtres
 */
function getAlbums($limit = 12, $offset = 0, $genre = null, $type = null, $sort = 'recent', $search = null) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $where = "a.status = 'approved' AND a.deleted_at IS NULL AND ar.is_active = 1 AND ar.deleted_at IS NULL";
        $params = [];

        if ($genre && $genre !== 'all') {
            $where .= " AND (LOWER(g.name) = LOWER(?) OR LOWER(g.name_french) = LOWER(?) OR EXISTS (SELECT 1 FROM artist_genres agf JOIN genres gf ON gf.id = agf.genre_id WHERE agf.artist_id = ar.id AND LOWER(COALESCE(gf.name_french, gf.name)) LIKE LOWER(?)))";
            $params[] = $genre;
            $params[] = $genre;
            $params[] = '%' . $genre . '%';
        }

        if ($type && $type !== 'all') {
            $where .= " AND a.type = ?";
            $params[] = $type;
        }

        if ($search) {
            $where .= " AND (a.title LIKE ? OR ar.stage_name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $orderBy = match($sort) {
            'alphabetical' => 'a.title ASC',
            'tracks' => 'a.total_tracks DESC',
            'popular' => 'a.total_streams DESC',
            default => 'a.release_date DESC, a.created_at DESC'
        };

        $sql = "
            SELECT a.id, a.title, a.type, a.total_tracks, a.cover_image,
                   a.price, a.is_free, a.is_featured, a.release_date,
                   ar.stage_name AS artist, (SELECT COALESCE(gp.name_french, gp.name) FROM artist_genres agp JOIN genres gp ON gp.id = agp.genre_id WHERE agp.artist_id = ar.id AND agp.is_primary = 1) AS artist_genres,
                   g.name AS genre_name, g.color AS genre_color
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.id
            LEFT JOIN genres g ON a.genre_id = g.id
            WHERE $where
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";

        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $albums = $stmt->fetchAll();

        foreach ($albums as &$album) {
            $album['badge'] = ucfirst($album['type'] ?? 'album');
            $album['genre'] = $album['genre_name'] ?: ($album['artist_genres'] ?: 'inconnu');
            $album['color'] = ltrim($album['genre_color'] ?? '', '#') ?: getColorForGenre($album['genre']);
            $album['price_label'] = $album['is_free'] ? 'Gratuit' : number_format((float) $album['price'], 0, ',', ' ') . ' FCFA';
        }

        return $albums;
    } catch (Exception $e) {
        error_log("Error fetching albums: " . $e->getMessage());
        return [];
    }
}

/**
 * Compte le total d'albums
 */
function countAlbums($genre = null, $type = null, $search = null) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return 0;

    try {
        $where = "a.status = 'approved' AND a.deleted_at IS NULL AND ar.is_active = 1 AND ar.deleted_at IS NULL";
        $params = [];

        if ($genre && $genre !== 'all') {
            $where .= " AND (LOWER(g.name) = LOWER(?) OR LOWER(g.name_french) = LOWER(?) OR EXISTS (SELECT 1 FROM artist_genres agf JOIN genres gf ON gf.id = agf.genre_id WHERE agf.artist_id = ar.id AND LOWER(COALESCE(gf.name_french, gf.name)) LIKE LOWER(?)))";
            $params[] = $genre;
            $params[] = $genre;
            $params[] = '%' . $genre . '%';
        }

        if ($type && $type !== 'all') {
            $where .= " AND a.type = ?";
            $params[] = $type;
        }

        if ($search) {
            $where .= " AND (a.title LIKE ? OR ar.stage_name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $stmt = $db->getConnection()->prepare("
            SELECT COUNT(*) 
            FROM albums a
            JOIN artists ar ON a.artist_id = ar.id
            LEFT JOIN genres g ON a.genre_id = g.id
            WHERE $where
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Recupere les types d'albums existants
 */
function getAlbumTypes() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT DISTINCT type
            FROM albums
            WHERE type IS NOT NULL AND type <> '' AND status = 'approved' AND deleted_at IS NULL
            ORDER BY type ASC
        ");
        return array_map(function($row) {
            return $row['type'];
        }, $stmt->fetchAll());
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Recupere les posts du blog
 */
function getBlogPosts($limit = 6, $offset = 0, $category = null, $featuredOnly = false) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('blog_posts')) return [];

    try {
        $where = "p.status = 'published'";
        $params = [];

        if ($category && $category !== 'all') {
            $where .= " AND LOWER(p.category) = LOWER(?)";
            $params[] = $category;
        }

        if ($featuredOnly) {
            $where .= " AND p.featured = 1";
        }

        $sql = "
            SELECT p.id, p.title, p.content, p.excerpt, p.category,
                   p.featured_image, p.featured, p.views_count, p.likes_count, p.comments_count,
                   p.published_at, p.created_at,
                   u.first_name, u.last_name
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE $where
            ORDER BY p.published_at DESC, p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        foreach ($posts as &$post) {
            $post['author'] = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')) ?: 'Tchadok';
            $post['excerpt'] = $post['excerpt'] ?: truncateText(strip_tags($post['content'] ?? ''), 140);
            $post['date_label'] = timeAgoFrench($post['published_at'] ?: $post['created_at']);
            $post['read_time'] = estimateReadTime($post['content'] ?? '');
        }

        return $posts;
    } catch (Exception $e) {
        error_log("Error fetching blog posts: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupere les categories du blog
 */
function getBlogCategories() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('blog_posts')) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT DISTINCT category
            FROM blog_posts
            WHERE status = 'published' AND category IS NOT NULL AND category <> ''
            ORDER BY category ASC
        ");
        $categories = [];
        foreach ($stmt->fetchAll() as $row) {
            $label = trim($row['category']);
            if ($label === '') continue;
            $key = slugifyText($label);
            $categories[] = [
                'key' => $key,
                'label' => $label,
                'icon' => getCategoryIcon($label)
            ];
        }
        return $categories;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Statistiques pour le blog
 */
function getBlogStats() {
    $db = TchadokDatabase::getInstance();
    $defaults = ['total_posts' => 0, 'interviews' => 0, 'views' => 0];

    if (!$db->isConnected() || !tableExists('blog_posts')) return $defaults;

    try {
        $pdo = $db->getConnection();

        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
        $defaults['total_posts'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = 'published' AND LOWER(category) LIKE 'interview%'");
        $stmt->execute();
        $defaults['interviews'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COALESCE(SUM(views_count), 0) FROM blog_posts WHERE status = 'published'");
        $defaults['views'] = (int) $stmt->fetchColumn();

        return $defaults;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Genres avec statistiques
 */
function getGenresWithStats() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT g.id, g.name, g.name_french, g.slug, g.description, g.color, g.icon,
                   COUNT(t.id) AS track_count,
                   COALESCE(SUM(t.total_streams), 0) AS total_streams
            FROM genres g
            LEFT JOIN tracks t ON t.genre_id = g.id AND t.status = 'approved' AND t.deleted_at IS NULL
                AND t.artist_id IN (SELECT id FROM artists WHERE is_active = 1 AND deleted_at IS NULL)
            WHERE g.is_active = 1 AND g.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = g.id)
            GROUP BY g.id
            ORDER BY g.name ASC
        ");
        $genres = $stmt->fetchAll();

        $maxStreams = 0;
        $maxTracks = 0;
        foreach ($genres as $genre) {
            $maxStreams = max($maxStreams, (int) $genre['total_streams']);
            $maxTracks = max($maxTracks, (int) $genre['track_count']);
        }

        foreach ($genres as &$genre) {
            if ($maxStreams > 0) {
                $genre['popularity'] = (int) round(($genre['total_streams'] / $maxStreams) * 100);
            } elseif ($maxTracks > 0) {
                $genre['popularity'] = (int) round(($genre['track_count'] / $maxTracks) * 100);
            } else {
                $genre['popularity'] = 0;
            }
        }

        return $genres;
    } catch (Exception $e) {
        error_log("Error fetching genres stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Artistes principaux d'un genre
 */
function getTopArtistsByGenre($genreId, $limit = 4) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !$genreId) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT ar.id, ar.stage_name AS name, ar.total_streams,
                   COUNT(t.id) AS track_count
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            WHERE t.genre_id = ?
              AND t.status = 'approved' AND t.deleted_at IS NULL
              AND ar.is_active = 1 AND ar.deleted_at IS NULL
            GROUP BY ar.id
            ORDER BY ar.total_streams DESC
            LIMIT ?
        ");
        $stmt->execute([$genreId, (int) $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Programmes radio (liste simple)
 */
function getRadioSchedule($limit = 12) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('radio_shows')) return [];

    try {
        $stmt = $db->getConnection()->prepare("
            SELECT id, title, description, host_name, start_time, end_time
            FROM radio_shows
            WHERE status = 'active'
            ORDER BY start_time ASC
            LIMIT ?
        ");
        $stmt->execute([(int) $limit]);
        $shows = $stmt->fetchAll();

        foreach ($shows as &$show) {
            $show['host'] = $show['host_name'] ?: 'Tchadok Radio';
            $show['time_display'] = formatTimeRange($show['start_time'] ?? null, $show['end_time'] ?? null);
        }

        return $shows;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Infos radio live (piste + emission)
 */
function getRadioLiveInfo() {
    $db = TchadokDatabase::getInstance();
    $defaults = [
        'is_live' => radioEnvBool('RADIO_ENGINE_ENABLED', false),
        'listeners_count' => 0,
        'stream_url' => radioGetPublicStreamUrl(null),
        'current_track' => null,
        'current_show' => null
    ];

    if (!$db->isConnected() || !tableExists('radio_live')) return $defaults;

    try {
        $stmt = $db->getConnection()->query("
            SELECT rl.id, rl.current_track_id, rl.current_show_id,
                   rl.listeners_count, rl.stream_url, rl.is_live, rl.updated_at,
                   t.title AS track_title, t.duration AS track_duration,
                   t.audio_file AS track_audio, ar.stage_name AS track_artist,
                   al.cover_image AS album_cover,
                   rs.title AS show_title, rs.host_name AS show_host,
                   rs.start_time AS show_start, rs.end_time AS show_end
            FROM radio_live rl
            LEFT JOIN tracks t ON rl.current_track_id = t.id
            LEFT JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN albums al ON t.album_id = al.id
            LEFT JOIN radio_shows rs ON rl.current_show_id = rs.id
            ORDER BY rl.id ASC
            LIMIT 1
        ");
        $row = $stmt->fetch();
        if (!$row) return $defaults;

        $defaults['is_live'] = (bool) $row['is_live'];
        $defaults['listeners_count'] = (int) ($row['listeners_count'] ?? 0);
        $defaults['stream_url'] = radioGetPublicStreamUrl($row['stream_url'] ?? null);

        if (!empty($row['track_title'])) {
            $defaults['current_track'] = [
                'id' => (int) $row['current_track_id'],
                'title' => $row['track_title'],
                'artist' => $row['track_artist'] ?: 'Tchadok Radio',
                'duration' => (int) ($row['track_duration'] ?? 0),
                'audio_file' => $row['track_audio'] ?: null,
                'cover_image' => $row['album_cover'] ?: null
            ];
        }

        if (!empty($row['show_title'])) {
            $defaults['current_show'] = [
                'id' => (int) $row['current_show_id'],
                'title' => $row['show_title'],
                'host' => $row['show_host'] ?: 'Tchadok Radio',
                'start_time' => $row['show_start'],
                'end_time' => $row['show_end']
            ];
        }

        return $defaults;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Compte les emissions actives
 */
function getRadioShowsCount() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('radio_shows')) return 0;

    try {
        $stmt = $db->getConnection()->query("SELECT COUNT(*) FROM radio_shows WHERE status = 'active'");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Recupere les podcasts actifs
 */
function getPodcasts($limit = 6, $category = null) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('podcasts')) return [];

    try {
        $where = "p.status = 'active'";
        $params = [];
        if ($category && $category !== 'all') {
            $where .= " AND LOWER(p.category) = LOWER(?)";
            $params[] = $category;
        }

        $sql = "
            SELECT p.*,
                   (SELECT COUNT(*) FROM podcast_episodes e
                    WHERE e.podcast_id = p.id AND e.status = 'published') AS episodes_count,
                   (SELECT COALESCE(SUM(duration), 0) FROM podcast_episodes e
                    WHERE e.podcast_id = p.id AND e.status = 'published') AS total_duration
            FROM podcasts p
            WHERE $where
            ORDER BY p.is_featured DESC, p.created_at DESC
            LIMIT ?
        ";

        $params[] = (int) $limit;

        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $podcasts = $stmt->fetchAll();

        foreach ($podcasts as &$podcast) {
            $podcast['episodes_count'] = (int) ($podcast['episodes_count'] ?? 0);
            $podcast['total_duration'] = (int) ($podcast['total_duration'] ?? 0);
            $podcast['duration_label'] = formatDurationHours($podcast['total_duration']);
        }

        return $podcasts;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Compte les podcasts actifs
 */
function getPodcastsCount() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('podcasts')) return 0;

    try {
        $stmt = $db->getConnection()->query("SELECT COUNT(*) FROM podcasts WHERE status = 'active'");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Compte les episodes (tous ou par podcast)
 */
function getPodcastEpisodesCount($podcastId = null) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('podcast_episodes')) return 0;

    try {
        if ($podcastId) {
            $stmt = $db->getConnection()->prepare("SELECT COUNT(*) FROM podcast_episodes WHERE podcast_id = ? AND status = 'published'");
            $stmt->execute([$podcastId]);
            return (int) $stmt->fetchColumn();
        }

        $stmt = $db->getConnection()->query("SELECT COUNT(*) FROM podcast_episodes WHERE status = 'published'");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Categories de podcasts avec stats
 */
function getPodcastCategories() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected() || !tableExists('podcasts')) return [];

    try {
        $stmt = $db->getConnection()->query("
            SELECT p.category,
                   COUNT(DISTINCT p.id) AS podcasts_count,
                   COUNT(e.id) AS episodes_count
            FROM podcasts p
            LEFT JOIN podcast_episodes e ON e.podcast_id = p.id AND e.status = 'published'
            WHERE p.status = 'active' AND p.category IS NOT NULL AND p.category <> ''
            GROUP BY p.category
            ORDER BY podcasts_count DESC, episodes_count DESC
        ");
        $rows = $stmt->fetchAll();

        $tones = [
            'from-emerald-500/20 via-emerald-500/10 to-transparent',
            'from-amber-400/25 via-amber-400/10 to-transparent',
            'from-sky-500/20 via-sky-500/10 to-transparent',
            'from-rose-400/20 via-rose-400/10 to-transparent',
            'from-violet-500/20 via-violet-500/10 to-transparent',
            'from-cyan-300/20 via-cyan-300/10 to-transparent',
            'from-indigo-500/20 via-indigo-500/10 to-transparent',
            'from-fuchsia-400/20 via-fuchsia-500/10 to-transparent'
        ];

        $categories = [];
        foreach ($rows as $index => $row) {
            $label = trim($row['category']);
            if ($label === '') continue;
            $podcastCount = (int) $row['podcasts_count'];
            $episodesCount = (int) $row['episodes_count'];

            $categories[] = [
                'key' => slugifyText($label),
                'title' => $label,
                'desc' => $podcastCount . ' podcasts • ' . $episodesCount . ' episodes',
                'count' => $episodesCount . ' episodes',
                'icon' => getPodcastCategoryIcon($label),
                'tone' => $tones[$index % count($tones)]
            ];
        }

        return $categories;
    } catch (Exception $e) {
        return [];
    }
}

// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================

function getBadgeClass($type, $isFeatured) {
    if ($isFeatured) return 'bg-success';

    switch (strtolower($type ?? '')) {
        case 'album': return 'bg-primary';
        case 'ep': return 'bg-info text-dark';
        case 'single': return 'bg-secondary';
        case 'live': return 'bg-danger';
        default: return 'bg-warning text-dark';
    }
}

function getColorForGenre($genre) {
    if (!$genre) return '0066CC';

    $colors = [
        'Afrobeat' => 'FFD700', 'Afro-Soul' => '0066CC',
        'Hip Hop' => '228B22', 'Hip-Hop' => '228B22', 'Rap' => '228B22',
        'R&B' => '0066CC', 'R&B/Soul' => '0066CC', 'Pop' => 'FF69B4',
        'Gospel' => '667eea', 'Jazz' => 'f093fb', 'Jazz Fusion' => 'f093fb',
        'Traditionnel' => 'CC3333', 'Sara Traditionnel' => 'F39C12',
        'Reggae' => '32CD32', 'Blues' => '4169E1', 'Folk' => '8B4513',
        'Electronic' => '4158d0', 'Trap' => '6c5ce7',
        'Bikutsi' => 'FF6B35', 'Makossa' => 'E74C3C', 'Zouk' => '9B59B6',
        'Rap Tchadien' => '34495E', 'Kanem' => 'E67E22',
        'Coupe-Decale' => '2ECC71', 'Ndombolo' => '1ABC9C',
        'Rumba' => 'E91E63', 'Soukous' => 'FF5722'
    ];

    // Try exact match first
    if (isset($colors[$genre])) return $colors[$genre];

    // Try partial match (for comma-separated genre strings like "Rap, Hip-Hop, Afrobeat")
    foreach ($colors as $key => $color) {
        if (stripos($genre, $key) !== false) return $color;
    }

    return '0066CC';
}

function getGradientForShow($genre) {
    $gradients = [
        'Variete' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'Hip Hop' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'Jazz' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'Gospel' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'Traditionnel' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'Afrobeat' => 'linear-gradient(135deg, #FFD700 0%, #FF6B35 100%)',
        'R&B' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'Rap' => 'linear-gradient(135deg, #232526 0%, #414345 100%)',
        'Electronic' => 'linear-gradient(135deg, #4158d0 0%, #c850c0 100%)'
    ];

    if ($genre && isset($gradients[$genre])) return $gradients[$genre];

    // Partial match
    if ($genre) {
        foreach ($gradients as $key => $val) {
            if (stripos($genre, $key) !== false) return $val;
        }
    }

    return 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
}

/**
 * Formate un nombre de streams pour affichage (ex: 1500000 -> "1.5M")
 */
function formatStreamCount($count) {
    $count = (int) $count;
    if ($count >= 1000000) {
        return round($count / 1000000, 1) . 'M';
    } elseif ($count >= 1000) {
        return round($count / 1000, 1) . 'K';
    }
    return (string) $count;
}

/**
 * Formate une duree en secondes vers m:ss
 */
function formatDurationShort($seconds) {
    if (!$seconds) return '0:00';
    $seconds = (int) $seconds;
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
}

/**
 * Formate une duree en secondes vers h/min (ex: 3720 -> "1h02")
 */
function formatDurationHours($seconds) {
    $seconds = (int) $seconds;
    if ($seconds <= 0) return '0 min';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return $hours . 'h' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }
    return max(1, $minutes) . ' min';
}

/**
 * Retourne un temps relatif en francais
 */
function timeAgoFrench($datetime) {
    if (!$datetime) return 'recemment';
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'a l\'instant';
    if ($time < 3600) return floor($time / 60) . ' min';
    if ($time < 86400) return floor($time / 3600) . 'h';
    if ($time < 172800) return 'hier';
    if ($time < 604800) return floor($time / 86400) . ' jours';
    if ($time < 2592000) return floor($time / 604800) . ' semaines';
    if ($time < 31536000) return floor($time / 2592000) . ' mois';
    return floor($time / 31536000) . ' ans';
}

/**
 * Formate une heure HH:MM:SS en format court (ex: 6h ou 6h30)
 */
function formatTimeShort($time) {
    if (!$time) return '';
    $parts = explode(':', $time);
    $hour = isset($parts[0]) ? (int) $parts[0] : 0;
    $minute = isset($parts[1]) ? (int) $parts[1] : 0;
    if ($minute === 0) {
        return $hour . 'h';
    }
    return sprintf('%dh%02d', $hour, $minute);
}

/**
 * Formate une plage horaire
 */
function formatTimeRange($start, $end) {
    if (!$start || !$end) return '';
    return formatTimeShort($start) . ' - ' . formatTimeShort($end);
}

/**
 * Tronque un texte proprement
 */
function truncateText($text, $maxLength = 140) {
    $text = trim($text);
    if ($text === '') return '';
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) return $text;
        return mb_substr($text, 0, $maxLength, 'UTF-8') . '...';
    }
    if (strlen($text) <= $maxLength) return $text;
    return substr($text, 0, $maxLength) . '...';
}

/**
 * Estime un temps de lecture
 */
function estimateReadTime($content, $wpm = 200) {
    $plain = strip_tags((string) $content);
    $wordCount = str_word_count($plain);
    if ($wordCount === 0) return '1 min';
    $minutes = max(1, (int) ceil($wordCount / $wpm));
    return $minutes . ' min';
}

/**
 * Slug simple pour filtres
 */
function slugifyText($text) {
    $text = strtolower((string) $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Icones par categorie de blog
 */
function getCategoryIcon($label) {
    $key = strtolower(trim((string) $label));
    $icons = [
        'interviews' => 'fa-microphone',
        'interview' => 'fa-microphone',
        'actualites' => 'fa-newspaper',
        'actualite' => 'fa-newspaper',
        'analyse' => 'fa-chart-line',
        'analyses' => 'fa-chart-line',
        'evenements' => 'fa-calendar-star',
        'evenement' => 'fa-calendar-star',
        'culture' => 'fa-palette'
    ];

    return $icons[$key] ?? 'fa-tag';
}

/**
 * Icones par categorie de podcast
 */
function getPodcastCategoryIcon($label) {
    $key = strtolower(trim((string) $label));
    $icons = [
        'musique' => 'fa-music',
        'music' => 'fa-music',
        'culture' => 'fa-palette',
        'societe' => 'fa-comments',
        'society' => 'fa-comments',
        'interview' => 'fa-microphone',
        'interviews' => 'fa-microphone',
        'actualites' => 'fa-newspaper',
        'actualite' => 'fa-newspaper',
        'sport' => 'fa-futbol',
        'education' => 'fa-graduation-cap',
        'business' => 'fa-briefcase',
        'entrepreneuriat' => 'fa-briefcase',
        'sante' => 'fa-heartbeat',
        'technologie' => 'fa-microchip'
    ];

    return $icons[$key] ?? 'fa-podcast';
}

/**
 * Verifie les informations de connexion admin
 */
function checkAdminCredentials($username, $password) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return false;

    try {
        // DATA-02 : `password_hash` seule fait foi (la colonne `password` a ete
        // retiree). Et `is_active` est enfin controle : un compte desactive
        // continuait d'ouvrir l'administration.
        $stmt = $db->getConnection()->prepare("
            SELECT u.id, u.password_hash, a.role
            FROM users u
            JOIN admins a ON u.id = a.user_id
            WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && !empty($admin['password_hash']) && password_verify($password, $admin['password_hash'])) {
            return $admin['id'];
        }

        return false;
    } catch (Exception $e) {
        error_log("Error checking admin credentials: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupere les informations d'un utilisateur
 */
function getUserById($userId) {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) return null;

    try {
        $stmt = $db->getConnection()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}
?>
