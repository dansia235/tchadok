<?php
/**
 * Script de réinitialisation de la base de données - Tchadok Platform
 * Supprime et recrée toutes les tables avec la structure correcte
 */

session_start();
require_once '../includes/database.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = getUserById($_SESSION['admin_id']);
if (!$admin || $admin['user_type'] !== 'admin') {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Suppression des tables existantes
        $dropTables = [
            'playlist_tracks', 'playlists', 'payments', 'blog_posts', 
            'radio_shows', 'radio_live', 'tracks', 'albums', 'artists', 'users'
        ];
        
        foreach ($dropTables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table");
        }
        
        // Création des tables avec la structure correcte
        $createSQL = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            user_type ENUM('fan', 'artist', 'admin') DEFAULT 'fan',
            profile_image VARCHAR(255),
            bio TEXT,
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other'),
            location VARCHAR(100),
            is_verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE artists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            stage_name VARCHAR(100) NOT NULL,
            real_name VARCHAR(100),
            bio TEXT,
            genre VARCHAR(50),
            country VARCHAR(50) DEFAULT 'Tchad',
            city VARCHAR(50),
            website VARCHAR(255),
            facebook VARCHAR(100),
            instagram VARCHAR(100),
            twitter VARCHAR(100),
            youtube VARCHAR(100),
            spotify VARCHAR(100),
            apple_music VARCHAR(100),
            record_label VARCHAR(100),
            debut_year YEAR,
            monthly_listeners INT DEFAULT 0,
            total_streams BIGINT DEFAULT 0,
            is_verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE albums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            artist_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            genre VARCHAR(50),
            release_date DATE,
            album_type ENUM('Album', 'EP', 'Single', 'Compilation', 'Live') DEFAULT 'Album',
            total_tracks INT DEFAULT 0,
            duration_seconds INT DEFAULT 0,
            price DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'XAF',
            cover_image VARCHAR(255),
            record_label VARCHAR(100),
            producer VARCHAR(100),
            is_featured BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE tracks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            album_id INT NOT NULL,
            artist_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            duration_seconds INT NOT NULL,
            track_number INT DEFAULT 1,
            genre VARCHAR(50),
            lyrics TEXT,
            audio_file VARCHAR(255),
            is_featured BOOLEAN DEFAULT FALSE,
            play_count BIGINT DEFAULT 0,
            download_count BIGINT DEFAULT 0,
            price DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'XAF',
            release_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
            FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
            FULLTEXT KEY ft_title (title)
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE playlists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            is_public BOOLEAN DEFAULT TRUE,
            cover_image VARCHAR(255),
            total_tracks INT DEFAULT 0,
            total_duration INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE playlist_tracks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            playlist_id INT NOT NULL,
            track_id INT NOT NULL,
            position INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
            UNIQUE KEY unique_playlist_track (playlist_id, track_id)
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE radio_shows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            host_user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            days_of_week VARCHAR(20) DEFAULT '1,2,3,4,5,6,7',
            genre VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE radio_live (
            id INT AUTO_INCREMENT PRIMARY KEY,
            current_track_id INT,
            current_show_id INT,
            listeners_count INT DEFAULT 0,
            is_live BOOLEAN DEFAULT TRUE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (current_track_id) REFERENCES tracks(id) ON DELETE SET NULL,
            FOREIGN KEY (current_show_id) REFERENCES radio_shows(id) ON DELETE SET NULL
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_id VARCHAR(100) UNIQUE NOT NULL,
            payment_method ENUM('airtel_money', 'moov_money', 'card', 'bank_transfer') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'XAF',
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
            reference VARCHAR(100),
            description TEXT,
            provider_reference VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author_user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            excerpt TEXT,
            featured_image VARCHAR(255),
            category VARCHAR(50),
            tags VARCHAR(255),
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            is_featured BOOLEAN DEFAULT FALSE,
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FULLTEXT KEY ft_title_content (title, content)
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Exécution des requêtes
        $queries = explode(';', $createSQL);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }

        $message = "Base de données réinitialisée avec succès ! Toutes les tables ont été recréées.";
        
    } catch (Exception $e) {
        $error = "Erreur lors de la réinitialisation : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation Base de Données - Tchadok Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFD700;
            --secondary-color: #2C3E50;
            --accent-color: #0066CC;
        }
        
        .navbar-admin {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
        }
        
        .card-danger {
            border: 2px solid #dc3545;
            background: #fff5f5;
        }
        
        .card-warning {
            border: 2px solid #ffc107;
            background: #fffbf0;
        }
        
        .btn-danger-confirm {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <svg width="30" height="30" class="me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                    <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                </svg>
                Réinitialisation Base de Données
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour au dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card card-danger">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Réinitialisation de la Base de Données
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-warning me-2"></i>ATTENTION - ACTION IRRÉVERSIBLE</h5>
                            <p class="mb-0">Cette action va <strong>SUPPRIMER DÉFINITIVEMENT</strong> toutes les données existantes et recréer la structure de base de données avec les colonnes correctes.</p>
                        </div>
                        
                        <h6>Cette opération va :</h6>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item"><i class="fas fa-trash text-danger me-2"></i>Supprimer toutes les tables existantes</li>
                            <li class="list-group-item"><i class="fas fa-database text-primary me-2"></i>Recréer la structure complète avec les bonnes colonnes</li>
                            <li class="list-group-item"><i class="fas fa-table text-info me-2"></i>Créer 10 tables : users, artists, albums, tracks, playlists, etc.</li>
                            <li class="list-group-item"><i class="fas fa-key text-warning me-2"></i>Configurer les clés étrangères et index</li>
                            <li class="list-group-item"><i class="fas fa-eraser text-danger me-2"></i>Perdre TOUTES les données actuelles</li>
                        </ul>
                        
                        <div class="card card-warning mb-4">
                            <div class="card-body">
                                <h6><i class="fas fa-info-circle me-2"></i>Tables qui seront créées :</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled small">
                                            <li><i class="fas fa-table me-1"></i> users (avec password_hash)</li>
                                            <li><i class="fas fa-table me-1"></i> artists</li>
                                            <li><i class="fas fa-table me-1"></i> albums</li>
                                            <li><i class="fas fa-table me-1"></i> tracks</li>
                                            <li><i class="fas fa-table me-1"></i> playlists</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled small">
                                            <li><i class="fas fa-table me-1"></i> playlist_tracks</li>
                                            <li><i class="fas fa-table me-1"></i> radio_shows</li>
                                            <li><i class="fas fa-table me-1"></i> radio_live</li>
                                            <li><i class="fas fa-table me-1"></i> payments</li>
                                            <li><i class="fas fa-table me-1"></i> blog_posts</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('DERNIÈRE CONFIRMATION: Êtes-vous absolument sûr de vouloir supprimer TOUTES les données et réinitialiser la base de données? Cette action est IRRÉVERSIBLE.')">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="understand" required>
                                <label class="form-check-label fw-bold" for="understand">
                                    Je comprends que cette action va supprimer TOUTES les données existantes
                                </label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="confirm" required>
                                <label class="form-check-label fw-bold" for="confirm">
                                    Je confirme vouloir procéder à la réinitialisation complète
                                </label>
                            </div>
                            
                            <button type="submit" name="confirm_reset" value="1" class="btn btn-danger-confirm">
                                <i class="fas fa-bomb me-2"></i>
                                RÉINITIALISER LA BASE DE DONNÉES
                            </button>
                            
                            <a href="dashboard.php" class="btn btn-secondary ms-3">
                                <i class="fas fa-times me-2"></i>
                                Annuler
                            </a>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Action réservée aux administrateurs - <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>