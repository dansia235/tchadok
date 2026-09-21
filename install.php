<?php
/**
 * Script d'installation Tchadok Platform
 * Cree la base de donnees, les tables et des comptes utilisateurs
 */

set_time_limit(300); // 5 minutes timeout

// Configuration de la base de donnees
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'tchadok_db'
];

function renderInstallStep($status, $title, $message) {
    $styles = [
        'success' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
        'error' => 'border-rose-400/40 bg-rose-500/10 text-rose-200',
        'warning' => 'border-amber-400/40 bg-amber-500/10 text-amber-200',
        'info' => 'border-sky-400/40 bg-sky-500/10 text-sky-200'
    ];
    $icons = [
        'success' => 'fa-check',
        'error' => 'fa-times',
        'warning' => 'fa-exclamation-triangle',
        'info' => 'fa-info-circle'
    ];

    $class = $styles[$status] ?? $styles['info'];
    $icon = $icons[$status] ?? $icons['info'];
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    return '<div class="rounded-2xl border ' . $class . ' p-4 text-sm">' .
        '<div class="flex items-start gap-3">' .
        '<i class="fas ' . $icon . ' mt-0.5"></i>' .
        '<div><p class="font-semibold">' . $safeTitle . '</p><p class="mt-1 text-xs opacity-90">' . $safeMessage . '</p></div>' .
        '</div></div>';
}

if (!defined('SITE_URL')) {
    define('SITE_URL', '');
}
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Tchadok');
}
if (!defined('SITE_TAGLINE')) {
    define('SITE_TAGLINE', 'La musique tchadienne a portee de clic');
}
if (!defined('FACEBOOK_URL')) {
    define('FACEBOOK_URL', '#');
}
if (!defined('TWITTER_URL')) {
    define('TWITTER_URL', '#');
}
if (!defined('INSTAGRAM_URL')) {
    define('INSTAGRAM_URL', '#');
}
if (!defined('YOUTUBE_URL')) {
    define('YOUTUBE_URL', '#');
}
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return false;
    }
}
if (!function_exists('displayFlashMessages')) {
    function displayFlashMessages() {
        return '';
    }
}

$pageTitle = 'Installation Tchadok';
$pageDescription = 'Configuration automatique de la plateforme musicale tchadienne';
$hideTopNav = true;
$hideFooter = true;

include 'includes/header-tailwind.php';

?>
<main class="pt-24 pb-16">
    <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2 sm:p-10">
                <div class="text-center">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-accent/20 text-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" class="h-10 w-10">
                            <circle cx="50" cy="50" r="45" fill="currentColor" opacity="0.2"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h1 class="mt-6 text-2xl font-display font-bold text-text">Installation Tchadok Platform</h1>
                    <p class="mt-2 text-sm text-muted">Configuration automatique de la plateforme musicale tchadienne.</p>
                </div>

                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
                    echo '<div id="installation-progress" class="mt-8 space-y-4">';

                    // Etape 1: Connexion a MySQL
                    try {
                        $pdo = new PDO("mysql:host={$config['host']}", $config['username'], $config['password']);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        echo renderInstallStep('success', 'Etape 1: Connexion MySQL', 'Connexion reussie.');
                        flush();
                    } catch (PDOException $e) {
                        echo renderInstallStep('error', 'Etape 1: Connexion MySQL', 'Erreur MySQL: ' . $e->getMessage());
                        echo '</div>';
                        exit;
                    }

                    // Etape 2: Creation de la base de donnees
                    try {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("USE `{$config['database']}`");
                        echo renderInstallStep('success', 'Etape 2: Base de donnees', 'Base de donnees creee avec succes.');
                        flush();
                    } catch (PDOException $e) {
                        echo renderInstallStep('error', 'Etape 2: Base de donnees', 'Erreur DB: ' . $e->getMessage());
                        echo '</div>';
                        exit;
                    }

                    // Etape 3: Creation des tables
                    $tables = [
                        "CREATE TABLE IF NOT EXISTS users (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            username VARCHAR(50) UNIQUE NOT NULL,
                            email VARCHAR(100) UNIQUE NOT NULL,
                            password_hash VARCHAR(255) NOT NULL,
                            first_name VARCHAR(50) NOT NULL,
                            last_name VARCHAR(50) NOT NULL,
                            user_type ENUM('fan', 'artist', 'admin') DEFAULT 'fan',
                            avatar_url VARCHAR(255),
                            premium_status BOOLEAN DEFAULT FALSE,
                            premium_expires DATETIME NULL,
                            phone VARCHAR(20),
                            country VARCHAR(2) DEFAULT 'TD',
                            city VARCHAR(50),
                            bio TEXT,
                            verified BOOLEAN DEFAULT FALSE,
                            email_verified BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            last_login TIMESTAMP NULL
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS artists (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            artist_name VARCHAR(100) NOT NULL,
                            stage_name VARCHAR(100),
                            genre_primary VARCHAR(50),
                            genre_secondary VARCHAR(50),
                            biography TEXT,
                            website VARCHAR(255),
                            social_facebook VARCHAR(255),
                            social_instagram VARCHAR(255),
                            social_twitter VARCHAR(255),
                            social_youtube VARCHAR(255),
                            monthly_listeners INT DEFAULT 0,
                            total_plays INT DEFAULT 0,
                            followers_count INT DEFAULT 0,
                            featured BOOLEAN DEFAULT FALSE,
                            verified BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS albums (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            artist_id INT,
                            title VARCHAR(150) NOT NULL,
                            album_type ENUM('album', 'ep', 'single') DEFAULT 'album',
                            cover_image VARCHAR(255),
                            release_date DATE,
                            description TEXT,
                            genre VARCHAR(50),
                            total_tracks INT DEFAULT 0,
                            total_duration INT DEFAULT 0,
                            price DECIMAL(8,2) DEFAULT 0.00,
                            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                            plays_count INT DEFAULT 0,
                            downloads_count INT DEFAULT 0,
                            featured BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS tracks (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            album_id INT,
                            artist_id INT,
                            title VARCHAR(150) NOT NULL,
                            duration INT NOT NULL,
                            track_number INT,
                            file_path VARCHAR(255),
                            file_size INT,
                            genre VARCHAR(50),
                            lyrics TEXT,
                            price DECIMAL(6,2) DEFAULT 0.00,
                            plays_count INT DEFAULT 0,
                            downloads_count INT DEFAULT 0,
                            likes_count INT DEFAULT 0,
                            featured BOOLEAN DEFAULT FALSE,
                            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
                            FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS playlists (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            name VARCHAR(100) NOT NULL,
                            description TEXT,
                            cover_image VARCHAR(255),
                            is_public BOOLEAN DEFAULT TRUE,
                            tracks_count INT DEFAULT 0,
                            total_duration INT DEFAULT 0,
                            plays_count INT DEFAULT 0,
                            likes_count INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS playlist_tracks (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            playlist_id INT,
                            track_id INT,
                            position INT,
                            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
                            FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
                            UNIQUE KEY unique_playlist_track (playlist_id, track_id)
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS radio_shows (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            title VARCHAR(100) NOT NULL,
                            description TEXT,
                            host_name VARCHAR(100),
                            host_avatar VARCHAR(255),
                            start_time TIME,
                            end_time TIME,
                            days_of_week VARCHAR(20),
                            cover_image VARCHAR(255),
                            status ENUM('active', 'inactive') DEFAULT 'active',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS radio_live (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            current_track_id INT,
                            current_show_id INT,
                            listeners_count INT DEFAULT 0,
                            stream_url VARCHAR(255),
                            is_live BOOLEAN DEFAULT TRUE,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (current_track_id) REFERENCES tracks(id) ON DELETE SET NULL,
                            FOREIGN KEY (current_show_id) REFERENCES radio_shows(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS payments (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            transaction_id VARCHAR(100) UNIQUE,
                            payment_method ENUM('airtel_money', 'moov_money', 'card', 'bank_transfer'),
                            amount DECIMAL(10,2) NOT NULL,
                            currency VARCHAR(3) DEFAULT 'XAF',
                            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                            item_type ENUM('track', 'album', 'premium', 'subscription'),
                            item_id INT,
                            phone_number VARCHAR(20),
                            provider_transaction_id VARCHAR(100),
                            provider_response TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            completed_at TIMESTAMP NULL,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_posts (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            title VARCHAR(200) NOT NULL,
                            slug VARCHAR(200) UNIQUE,
                            content LONGTEXT NOT NULL,
                            excerpt TEXT,
                            featured_image VARCHAR(255),
                            author_id INT,
                            category VARCHAR(50),
                            tags TEXT,
                            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                            featured BOOLEAN DEFAULT FALSE,
                            views_count INT DEFAULT 0,
                            likes_count INT DEFAULT 0,
                            comments_count INT DEFAULT 0,
                            published_at TIMESTAMP NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_categories (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            slug VARCHAR(120) UNIQUE,
                            description TEXT,
                            color VARCHAR(20) DEFAULT '#2F6DE0',
                            icon VARCHAR(50) DEFAULT 'fa-newspaper',
                            is_active TINYINT(1) DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_tags (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(80) NOT NULL,
                            slug VARCHAR(90) UNIQUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_post_tags (
                            post_id INT NOT NULL,
                            tag_id INT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (post_id, tag_id),
                            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                            FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_post_meta (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            post_id INT NOT NULL,
                            meta_key VARCHAR(80) NOT NULL,
                            meta_value LONGTEXT,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_post_meta (post_id, meta_key),
                            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_post_views (
                            id BIGINT AUTO_INCREMENT PRIMARY KEY,
                            post_id INT NOT NULL,
                            user_id INT NULL,
                            viewer_hash CHAR(64) NOT NULL,
                            view_date DATE NOT NULL,
                            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_post_viewer_day (post_id, viewer_hash, view_date),
                            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_post_shares (
                            id BIGINT AUTO_INCREMENT PRIMARY KEY,
                            post_id INT NOT NULL,
                            user_id INT NULL,
                            platform ENUM('facebook','x','twitter','whatsapp','linkedin','telegram','email','copy') DEFAULT 'copy',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        "CREATE TABLE IF NOT EXISTS blog_comments (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            post_id INT NOT NULL,
                            user_id INT NOT NULL,
                            parent_id INT NULL,
                            content TEXT NOT NULL,
                            status ENUM('pending','approved','rejected') DEFAULT 'pending',
                            likes_count INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                    ];

                    try {
                        foreach ($tables as $sql) {
                            $pdo->exec($sql);
                        }
                        echo renderInstallStep('success', 'Etape 3: Tables', count($tables) . ' tables creees.');
                        flush();
                    } catch (PDOException $e) {
                        echo renderInstallStep('error', 'Etape 3: Tables', 'Erreur tables: ' . $e->getMessage());
                        echo '</div>';
                        exit;
                    }

                    // Etape 4: Insertion des donnees de test
                    try {
                        $users = [
                            ['admin', 'admin@tchadok.td', 'Admin', 'Systeme', 'admin', TRUE, TRUE],
                            ['user_demo', 'user@tchadok.td', 'Utilisateur', 'Demo', 'fan', FALSE, TRUE]
                        ];

                        foreach ($users as $user) {
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, user_type, premium_status, verified, password_hash, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute(array_merge($user, [password_hash('password123'), TRUE]));
                        }

                        echo renderInstallStep('success', 'Etape 4: Donnees', count($users) . ' comptes utilisateurs crees.');
                        flush();
                    } catch (PDOException $e) {
                        echo renderInstallStep('error', 'Etape 4: Donnees', 'Erreur donnees: ' . $e->getMessage());
                        echo '</div>';
                        exit;
                    }

                    // Etape 5: Configuration des fichiers
                    try {
                        $configContent = "<?php
/**
 * Configuration Tchadok Platform
 * Genere automatiquement lors de l'installation
 */

// Base de donnees
define('DB_HOST', '{$config['host']}');
define('DB_NAME', '{$config['database']}');
define('DB_USER', '{$config['username']}');
define('DB_PASS', '{$config['password']}');

// Parametres du site
define('SITE_NAME', 'Tchadok');
define('SITE_TAGLINE', 'La musique tchadienne a portee de clic');
define('SITE_URL', 'http://localhost/tchadok');

// URLs des reseaux sociaux
define('FACEBOOK_URL', 'https://facebook.com/tchadok');
define('TWITTER_URL', 'https://twitter.com/tchadok');
define('INSTAGRAM_URL', 'https://instagram.com/tchadok');
define('YOUTUBE_URL', 'https://youtube.com/tchadok');

// Configuration des paiements
define('AIRTEL_MONEY_API_KEY', 'demo_airtel_key_123');
define('MOOV_MONEY_API_KEY', 'demo_moov_key_456');

// Configuration radio
define('RADIO_STREAM_URL', '/api/radio/stream');
define('RADIO_METADATA_URL', '/api/radio/metadata');

// Securite
define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');
define('ENCRYPT_KEY', '" . bin2hex(random_bytes(16)) . "');

// Chemins
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MUSIC_PATH', __DIR__ . '/music/');
define('IMAGES_PATH', __DIR__ . '/assets/images/');

// Installation
define('TCHADOK_INSTALLED', true);
define('INSTALLATION_DATE', '" . date('Y-m-d H:i:s') . "');
?>";

                        file_put_contents('config.php', $configContent);

                        // Creer les dossiers necessaires
                        $dirs = ['uploads', 'music', 'logs', 'api', 'api/radio', 'api/payments'];
                        foreach ($dirs as $dir) {
                            if (!is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }
                        }

                        echo renderInstallStep('success', 'Etape 5: Configuration', 'Fichiers crees avec succes.');
                        flush();
                    } catch (Exception $e) {
                        echo renderInstallStep('error', 'Etape 5: Configuration', 'Erreur configuration: ' . $e->getMessage());
                        echo '</div>';
                        exit;
                    }

                    echo '</div>';
                    ?>
                    <div class="mt-8 space-y-6">
                        <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-5 text-emerald-200">
                            <h2 class="text-lg font-semibold">Installation terminee</h2>
                            <p class="mt-2 text-sm text-emerald-100/80">Tchadok est pret a etre utilise.</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="index.php" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                <i class="fas fa-home"></i> Acceder au site
                            </a>
                            <a href="admin-panel.php" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                <i class="fas fa-cog"></i> Panel admin
                            </a>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-sm text-muted">
                            <h3 class="text-base font-semibold text-white">Comptes de test crees</h3>
                            <ul class="mt-3 space-y-1 text-muted">
                                <li><strong>Admin:</strong> admin@tchadok.td / password123</li>
                                <li><strong>Utilisateur:</strong> user@tchadok.td / password123</li>
                            </ul>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <form method="POST" class="mt-8 space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Prerequis</h2>
                            <ul class="mt-4 space-y-3 text-sm text-muted">
                                <li class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <span>MySQL/MariaDB Server</span>
                                    <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200">Requis</span>
                                </li>
                                <li class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <span>PHP 7.4 ou superieur</span>
                                    <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200">Requis</span>
                                </li>
                                <li class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <span>Extension PDO MySQL</span>
                                    <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200">Requis</span>
                                </li>
                                <li class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <span>Droits d'ecriture</span>
                                    <span class="rounded-full bg-amber-500/20 px-3 py-1 text-xs font-semibold text-amber-200">Recommande</span>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h2 class="text-lg font-semibold text-text">Ce qui sera installe</h2>
                            <ul class="mt-4 space-y-2 text-sm text-muted">
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Base de donnees complete avec 12 tables</li>
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">2 comptes utilisateurs (admin + utilisateur demo)</li>
                                <li class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Configuration et dossiers systeme</li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-exclamation-triangle mt-0.5"></i>
                                <div>
                                    <p class="font-semibold">Attention</p>
                                    <p class="mt-1 text-xs text-amber-100/80">Cette installation va creer/remplacer la base de donnees "tchadok_db". Sauvegardez vos donnees existantes si necessaire.</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" name="install" class="inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                <i class="fas fa-rocket"></i> Lancer l'installation
                            </button>
                        </div>
                    </form>
                    <?php
                }
                ?>
        </div>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
