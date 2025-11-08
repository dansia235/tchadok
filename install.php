<?php
/**
 * Script d'installation Tchadok Platform
 * Crée la base de données, les tables et génère les données de test
 */

set_time_limit(300); // 5 minutes timeout

// Configuration de la base de données
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'tchadok_db'
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Tchadok Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0066CC, #FFD700); min-height: 100vh; }
        .install-container { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .step { padding: 20px; border-left: 4px solid #0066CC; margin: 15px 0; border-radius: 8px; background: #f8f9fa; }
        .step.success { border-color: #28a745; background: #d4edda; }
        .step.error { border-color: #dc3545; background: #f8d7da; }
        .step.running { border-color: #ffc107; background: #fff3cd; }
        .logo { width: 80px; height: 80px; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="install-container p-5">
                    <div class="text-center mb-4">
                        <svg class="logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                        </svg>
                        <h1 class="h2 text-primary">Installation Tchadok Platform</h1>
                        <p class="text-muted">Configuration automatique de la plateforme musicale tchadienne</p>
                    </div>

                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
                        echo '<div id="installation-progress">';
                        
                        // Étape 1: Connexion à MySQL
                        echo '<div class="step running"><i class="fas fa-database"></i> <strong>Étape 1:</strong> Connexion à MySQL...</div>';
                        flush();
                        
                        try {
                            $pdo = new PDO("mysql:host={$config['host']}", $config['username'], $config['password']);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            echo '<script>document.querySelector(".step.running").className = "step success";</script>';
                            echo '<div class="step success"><i class="fas fa-check"></i> <strong>Connexion MySQL:</strong> Réussie</div>';
                        } catch (PDOException $e) {
                            echo '<script>document.querySelector(".step.running").className = "step error";</script>';
                            echo '<div class="step error"><i class="fas fa-times"></i> <strong>Erreur MySQL:</strong> ' . $e->getMessage() . '</div>';
                            exit;
                        }
                        
                        // Étape 2: Création de la base de données
                        echo '<div class="step running"><i class="fas fa-plus"></i> <strong>Étape 2:</strong> Création de la base de données...</div>';
                        flush();
                        
                        try {
                            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $pdo->exec("USE `{$config['database']}`");
                            echo '<script>document.querySelectorAll(".step.running")[1].className = "step success";</script>';
                            echo '<div class="step success"><i class="fas fa-check"></i> <strong>Base de données:</strong> Créée avec succès</div>';
                        } catch (PDOException $e) {
                            echo '<div class="step error"><i class="fas fa-times"></i> <strong>Erreur DB:</strong> ' . $e->getMessage() . '</div>';
                            exit;
                        }
                        
                        // Étape 3: Création des tables
                        echo '<div class="step running"><i class="fas fa-table"></i> <strong>Étape 3:</strong> Création des tables...</div>';
                        flush();
                        
                        $tables = [
                            // Table des utilisateurs
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
                            
                            // Table des artistes
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
                            
                            // Table des albums
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
                            
                            // Table des tracks
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
                            
                            // Table des playlists
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
                            
                            // Table des tracks dans les playlists
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
                            
                            // Table des émissions radio
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
                            
                            // Table de la radio en direct
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
                            
                            // Table des paiements
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
                            
                            // Table des articles de blog
                            "CREATE TABLE IF NOT EXISTS blog_posts (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                title VARCHAR(200) NOT NULL,
                                slug VARCHAR(200) UNIQUE,
                                content TEXT NOT NULL,
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
                            ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                        ];
                        
                        try {
                            foreach ($tables as $sql) {
                                $pdo->exec($sql);
                            }
                            echo '<script>document.querySelectorAll(".step.running")[2].className = "step success";</script>';
                            echo '<div class="step success"><i class="fas fa-check"></i> <strong>Tables:</strong> ' . count($tables) . ' tables créées</div>';
                        } catch (PDOException $e) {
                            echo '<div class="step error"><i class="fas fa-times"></i> <strong>Erreur Tables:</strong> ' . $e->getMessage() . '</div>';
                            exit;
                        }
                        
                        // Étape 4: Insertion des données de test
                        echo '<div class="step running"><i class="fas fa-users"></i> <strong>Étape 4:</strong> Génération des données de test...</div>';
                        flush();
                        
                        try {
                            // Utilisateurs
                            $users = [
                                ['admin', 'admin@tchadok.td', 'Admin', 'Système', 'admin', TRUE, TRUE],
                                ['mounira_mitchala', 'mounira@tchadok.td', 'Mounira', 'Mitchala', 'artist', TRUE, TRUE],
                                ['clement_masdongar', 'clement@tchadok.td', 'Clément', 'Masdongar', 'artist', TRUE, TRUE],
                                ['h2o_assoumane', 'h2o@tchadok.td', 'H2O', 'Assoumane', 'artist', TRUE, TRUE],
                                ['maimouna_youssouf', 'maimouna@tchadok.td', 'Maimouna', 'Youssouf', 'artist', TRUE, TRUE],
                                ['caleb_rimtobaye', 'caleb@tchadok.td', 'Caleb', 'Rimtobaye', 'artist', TRUE, TRUE],
                                ['abakar_sultan', 'abakar@tchadok.td', 'Abakar', 'Sultan', 'artist', TRUE, TRUE],
                                ['djamil_fan', 'djamil@example.com', 'Djamil', 'Ngaro', 'fan', FALSE, TRUE],
                                ['fatima_fan', 'fatima@example.com', 'Fatima', 'Hassan', 'fan', TRUE, TRUE],
                                ['ibrahim_fan', 'ibrahim@example.com', 'Ibrahim', 'Deby', 'fan', FALSE, TRUE]
                            ];
                            
                            foreach ($users as $user) {
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, user_type, premium_status, verified, password_hash, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute(array_merge($user, [password_hash('password123'), TRUE]));
                            }
                            
                            // Artistes
                            $artists = [
                                [2, 'Mounira Mitchala', 'Mounira', 'Soul/R&B', 'Pop', 'Chanteuse soul tchadienne reconnue pour sa voix puissante et ses mélodies envoûtantes.', 150000, 2500000, 45000],
                                [3, 'Clément Masdongar', 'Clément', 'Afrobeat', 'World Music', 'Maître de l\'afrobeat tchadien, fusion parfaite entre tradition et modernité.', 120000, 1800000, 38000],
                                [4, 'H2O Assoumane', 'H2O', 'Hip Hop', 'Rap', 'Rappeur engagé, voix de la jeunesse tchadienne contemporaine.', 200000, 3200000, 67000],
                                [5, 'Maimouna Youssouf', 'Maimouna', 'Traditionnel', 'Folk', 'Gardienne des traditions musicales tchadiennes, voix authentique du patrimoine.', 80000, 1200000, 25000],
                                [6, 'Caleb Rimtobaye', 'Caleb', 'Gospel', 'Spirituel', 'Artiste gospel inspirant, porteur d\'espoir à travers la musique.', 95000, 1500000, 32000],
                                [7, 'Abakar Sultan', 'Abakar', 'Jazz Fusion', 'Jazz', 'Innovateur du jazz tchadien, créateur de sonorités uniques.', 60000, 900000, 18000]
                            ];
                            
                            foreach ($artists as $artist) {
                                $stmt = $pdo->prepare("INSERT INTO artists (user_id, artist_name, stage_name, genre_primary, genre_secondary, biography, monthly_listeners, total_plays, followers_count, featured, verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)");
                                $stmt->execute($artist);
                            }
                            
                            // Albums
                            $albums = [
                                [1, 'Renaissance Africaine', 'album', '2024-01-15', 'Soul/R&B', 12, 2850, 'Premier album studio de Mounira Mitchala'],
                                [1, 'Voix du Cœur', 'ep', '2024-06-20', 'Soul', 6, 1420, 'EP intimiste et personnel'],
                                [2, 'Rythmes de N\'Djamena', 'album', '2023-11-10', 'Afrobeat', 10, 2340, 'Fusion entre tradition et modernité'],
                                [3, 'Révolution Urbaine', 'album', '2024-03-05', 'Hip Hop', 15, 3600, 'Album engagé sur la jeunesse africaine'],
                                [3, 'Freestyle Sessions', 'ep', '2024-08-12', 'Rap', 8, 1890, 'Collection de freestyles et collaborations'],
                                [4, 'Héritage Ancestral', 'album', '2023-09-25', 'Traditionnel', 14, 3120, 'Retour aux sources musicales tchadiennes'],
                                [5, 'Lumière Divine', 'album', '2024-02-14', 'Gospel', 11, 2670, 'Messages d\'espoir et de foi'],
                                [6, 'Jazz Sahélien', 'album', '2024-05-30', 'Jazz Fusion', 9, 2140, 'Innovation jazz dans le contexte sahélien']
                            ];
                            
                            foreach ($albums as $album) {
                                $stmt = $pdo->prepare("INSERT INTO albums (artist_id, title, album_type, release_date, genre, total_tracks, total_duration, description, status, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', TRUE)");
                                $stmt->execute($album);
                            }
                            
                            // Émissions radio
                            $shows = [
                                ['Réveil Musical', 'Commencez la journée avec les hits du moment', 'Abakar Mahamat', '06:00:00', '09:00:00', 'Mon,Tue,Wed,Thu,Fri'],
                                ['Soirée Traditionnelle', 'Découvrez les sons authentiques du Tchad', 'DJ Moussa', '19:00:00', '21:00:00', 'Mon,Wed,Fri'],
                                ['Urban Beats', 'Le meilleur du rap et hip-hop tchadien', 'MC Kelem', '21:00:00', '23:00:00', 'Tue,Thu,Sat'],
                                ['Spécial Artistes', 'Interviews exclusives et coulisses', 'Sarah Ndong', '14:00:00', '16:00:00', 'Wed,Sat'],
                                ['Jazz & Soul', 'Ambiance jazz et soul pour vos soirées', 'Ibrahim Jazz', '20:00:00', '22:00:00', 'Sun'],
                                ['Musique du Monde', 'Voyage musical à travers l\'Afrique', 'Fatima World', '16:00:00', '18:00:00', 'Sat,Sun']
                            ];
                            
                            foreach ($shows as $show) {
                                $stmt = $pdo->prepare("INSERT INTO radio_shows (title, description, host_name, start_time, end_time, days_of_week, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                                $stmt->execute($show);
                            }
                            
                            // Configuration radio live
                            $pdo->exec("INSERT INTO radio_live (current_track_id, current_show_id, listeners_count, stream_url, is_live) VALUES (1, 1, 245, '/api/radio/stream', TRUE)");
                            
                            echo '<script>document.querySelectorAll(".step.running")[3].className = "step success";</script>';
                            echo '<div class="step success"><i class="fas fa-check"></i> <strong>Données:</strong> ' . count($users) . ' utilisateurs, ' . count($artists) . ' artistes, ' . count($albums) . ' albums créés</div>';
                        } catch (PDOException $e) {
                            echo '<div class="step error"><i class="fas fa-times"></i> <strong>Erreur Données:</strong> ' . $e->getMessage() . '</div>';
                            exit;
                        }
                        
                        // Étape 5: Configuration des fichiers
                        echo '<div class="step running"><i class="fas fa-cog"></i> <strong>Étape 5:</strong> Configuration des fichiers...</div>';
                        flush();
                        
                        // Créer le fichier de configuration
                        $configContent = "<?php
/**
 * Configuration Tchadok Platform
 * Généré automatiquement lors de l'installation
 */

// Base de données
define('DB_HOST', '{$config['host']}');
define('DB_NAME', '{$config['database']}');
define('DB_USER', '{$config['username']}');
define('DB_PASS', '{$config['password']}');

// Paramètres du site
define('SITE_NAME', 'Tchadok');
define('SITE_TAGLINE', 'La musique tchadienne à portée de clic');
define('SITE_URL', 'http://localhost/tchadok');

// URLs des réseaux sociaux
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

// Sécurité
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
                        
                        // Créer les dossiers nécessaires
                        $dirs = ['uploads', 'music', 'logs', 'api', 'api/radio', 'api/payments'];
                        foreach ($dirs as $dir) {
                            if (!is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }
                        }
                        
                        echo '<script>document.querySelectorAll(".step.running")[4].className = "step success";</script>';
                        echo '<div class="step success"><i class="fas fa-check"></i> <strong>Configuration:</strong> Fichiers créés avec succès</div>';
                        
                        // Installation terminée
                        echo '<div class="step success"><i class="fas fa-trophy"></i> <strong>Installation terminée!</strong> Tchadok est prêt à être utilisé</div>';
                        echo '<div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary btn-lg me-3"><i class="fas fa-home"></i> Accéder au site</a>
                                <a href="admin-panel.php" class="btn btn-success btn-lg"><i class="fas fa-cog"></i> Panel Admin</a>
                              </div>';
                        echo '<div class="mt-4 p-3 bg-light rounded">
                                <h5>Comptes de test créés:</h5>
                                <ul>
                                    <li><strong>Admin:</strong> admin@tchadok.td / password123</li>
                                    <li><strong>Artiste:</strong> mounira@tchadok.td / password123</li>
                                    <li><strong>Fan:</strong> djamil@example.com / password123</li>
                                </ul>
                              </div>';
                        echo '</div>';
                        
                    } else {
                        // Formulaire d'installation
                        ?>
                        <form method="POST">
                            <div class="mb-4">
                                <h4><i class="fas fa-info-circle text-primary"></i> Prérequis</h4>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>MySQL/MariaDB Server</span>
                                        <span class="badge bg-success">Requis</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>PHP 7.4 ou supérieur</span>
                                        <span class="badge bg-success">Requis</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Extension PDO MySQL</span>
                                        <span class="badge bg-success">Requis</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Droits d'écriture</span>
                                        <span class="badge bg-warning">Recommandé</span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="mb-4">
                                <h4><i class="fas fa-list text-primary"></i> Ce qui sera installé</h4>
                                <ul class="list-group">
                                    <li class="list-group-item">Base de données complète avec 12 tables</li>
                                    <li class="list-group-item">10 utilisateurs de test (admin, artistes, fans)</li>
                                    <li class="list-group-item">6 artistes tchadiens avec leurs albums</li>
                                    <li class="list-group-item">8 albums et nombreux tracks</li>
                                    <li class="list-group-item">6 émissions radio programmées</li>
                                    <li class="list-group-item">APIs de paiement mobile (simulation)</li>
                                    <li class="list-group-item">Serveur radio local fonctionnel</li>
                                    <li class="list-group-item">Configuration et dossiers système</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Attention:</strong> Cette installation va créer/remplacer la base de données 'tchadok_db'. 
                                Assurez-vous d'avoir sauvegardé vos données existantes si nécessaire.
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="install" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-rocket"></i> Lancer l'installation
                                </button>
                            </div>
                        </form>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-scroll vers le bas pendant l'installation
        if (document.getElementById('installation-progress')) {
            setInterval(function() {
                window.scrollTo(0, document.body.scrollHeight);
            }, 1000);
        }
    </script>
</body>
</html>