-- Base de données Tchadok - Plateforme Musicale Tchadienne (VERSION CORRIGÉE)
-- Version: 1.0
-- Date: 2024

CREATE DATABASE IF NOT EXISTS tchadok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tchadok;

-- Table des utilisateurs (fans/mélomanes)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50) DEFAULT 'Tchad',
    city VARCHAR(50),
    profile_image VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('M', 'F', 'Autre'),
    preferred_language VARCHAR(10) DEFAULT 'fr',
    premium_status BOOLEAN DEFAULT FALSE,
    premium_expires_at DATETIME NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    loyalty_points INT DEFAULT 0,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    remember_token VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des artistes
CREATE TABLE artists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    stage_name VARCHAR(100) NOT NULL,
    real_name VARCHAR(100),
    bio TEXT,
    website VARCHAR(255),
    facebook VARCHAR(255),
    instagram VARCHAR(255),
    twitter VARCHAR(255),
    youtube VARCHAR(255),
    spotify VARCHAR(255),
    birth_date DATE,
    birth_place VARCHAR(100),
    genres TEXT, -- JSON array of genres
    profile_image VARCHAR(255),
    cover_image VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE,
    featured BOOLEAN DEFAULT FALSE,
    total_streams BIGINT DEFAULT 0,
    total_sales DECIMAL(10,2) DEFAULT 0.00,
    total_earnings DECIMAL(10,2) DEFAULT 0.00,
    commission_rate DECIMAL(4,2) DEFAULT 15.00, -- Commission percentage
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des administrateurs
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    permissions TEXT, -- JSON array of permissions
    last_access DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des genres musicaux
CREATE TABLE genres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    name_french VARCHAR(50),
    name_arabic VARCHAR(50),
    description TEXT,
    color VARCHAR(7), -- Hex color code
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des albums
CREATE TABLE albums (
    id INT PRIMARY KEY AUTO_INCREMENT,
    artist_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255),
    genre_id INT,
    type ENUM('album', 'ep', 'single', 'maxi_single') DEFAULT 'album',
    price DECIMAL(8,2) DEFAULT 0.00,
    release_date DATE,
    language VARCHAR(50),
    total_tracks INT DEFAULT 0,
    total_duration INT DEFAULT 0, -- in seconds
    is_free BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft',
    total_streams BIGINT DEFAULT 0,
    total_sales INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id)
);

-- Table des titres/chansons
CREATE TABLE tracks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    album_id INT,
    artist_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    genre_id INT,
    audio_file VARCHAR(255) NOT NULL,
    preview_file VARCHAR(255), -- 30-second preview
    lyrics TEXT,
    duration INT NOT NULL, -- in seconds
    track_number INT,
    price DECIMAL(8,2) DEFAULT 0.00,
    is_free BOOLEAN DEFAULT FALSE,
    download_allowed BOOLEAN DEFAULT TRUE,
    language VARCHAR(50),
    release_date DATE,
    bpm INT,
    key_signature VARCHAR(10),
    explicit_content BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft',
    total_streams BIGINT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    total_sales INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id)
);

-- Table des playlists
CREATE TABLE playlists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255),
    is_public BOOLEAN DEFAULT TRUE,
    is_collaborative BOOLEAN DEFAULT FALSE,
    total_tracks INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    total_plays BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des titres dans les playlists
CREATE TABLE playlist_tracks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    playlist_id INT NOT NULL,
    track_id INT NOT NULL,
    position INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_playlist_track (playlist_id, track_id)
);

-- Table des écoutes/streams
CREATE TABLE streams (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    track_id INT NOT NULL,
    artist_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    country VARCHAR(50),
    city VARCHAR(50),
    duration_played INT DEFAULT 0, -- seconds played
    completed BOOLEAN DEFAULT FALSE, -- if 80% of track was played
    source VARCHAR(50), -- web, mobile, api
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
    INDEX idx_track_date (track_id, created_at),
    INDEX idx_artist_date (artist_id, created_at),
    INDEX idx_user_date (user_id, created_at)
);

-- Table des achats
CREATE TABLE purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_type ENUM('track', 'album') NOT NULL,
    item_id INT NOT NULL,
    artist_id INT NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    commission DECIMAL(8,2) NOT NULL,
    payment_method ENUM('airtel_money', 'moov_money', 'ecobank', 'visa', 'gimac', 'wallet') NOT NULL,
    payment_reference VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_fee DECIMAL(8,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'XAF',
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT 5,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
);

-- Table des favoris
CREATE TABLE favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_type ENUM('track', 'album', 'artist', 'playlist') NOT NULL,
    item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, item_type, item_id)
);

-- Table du blog
CREATE TABLE blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    author_id INT NOT NULL,
    author_type ENUM('user', 'artist', 'admin') NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(255),
    category VARCHAR(50),
    tags TEXT, -- JSON array
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des commentaires de blog
CREATE TABLE blog_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
);

-- Table des commentaires sur les titres
CREATE TABLE track_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    track_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des suivis (following)
CREATE TABLE follows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    followed_type ENUM('user', 'artist') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, followed_id, followed_type)
);

-- Table des notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data TEXT, -- JSON data
    read_at DATETIME NULL,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, read_at)
);

-- Table des sessions utilisateur
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des rapports/signalements
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id INT NOT NULL,
    reported_type ENUM('track', 'album', 'artist', 'user', 'comment', 'post') NOT NULL,
    reported_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewing', 'resolved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des paiements et transactions
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    artist_id INT,
    type ENUM('purchase', 'commission', 'withdrawal', 'deposit', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'XAF',
    description TEXT,
    reference VARCHAR(100) UNIQUE,
    gateway VARCHAR(50),
    gateway_response TEXT,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE SET NULL
);

-- Table des statistiques globales
CREATE TABLE site_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL UNIQUE,
    total_users INT DEFAULT 0,
    new_users INT DEFAULT 0,
    total_artists INT DEFAULT 0,
    new_artists INT DEFAULT 0,
    total_tracks INT DEFAULT 0,
    new_tracks INT DEFAULT 0,
    total_streams BIGINT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    total_sales DECIMAL(12,2) DEFAULT 0.00,
    page_views BIGINT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des charts/classements
CREATE TABLE charts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chart_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    item_type ENUM('track', 'album', 'artist') NOT NULL,
    item_id INT NOT NULL,
    position INT NOT NULL,
    streams_count BIGINT DEFAULT 0,
    sales_count INT DEFAULT 0,
    chart_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chart_date_type (chart_date, chart_type, item_type)
);

-- Insertion des genres musicaux tchadiens
INSERT INTO genres (name, name_french, name_arabic, description, color) VALUES
('Bikutsi', 'Bikutsi', 'بيكوتسي', 'Genre musical traditionnel du Cameroun populaire au Tchad', '#FF6B35'),
('Coupé-Décalé', 'Coupé-Décalé', 'كوبيه ديكاليه', 'Musique de danse ivoirienne très populaire', '#2ECC71'),
('Afrobeat', 'Afrobeat', 'أفروبيت', 'Fusion de jazz, funk et musiques traditionnelles africaines', '#3498DB'),
('Makossa', 'Makossa', 'ماكوسا', 'Genre camerounais influent en Afrique Centrale', '#E74C3C'),
('Zouk', 'Zouk', 'زوك', 'Musique des Antilles populaire en Afrique francophone', '#9B59B6'),
('Rap Tchadien', 'Rap Tchadien', 'راب تشادي', 'Hip-hop avec influences locales tchadiennes', '#34495E'),
('Sara Traditionnel', 'Sara Traditionnel', 'سارا تقليدي', 'Musique traditionnelle du peuple Sara', '#F39C12'),
('Kanem', 'Kanem', 'كانم', 'Musique traditionnelle de la région du Kanem', '#E67E22'),
('Gospel', 'Gospel', 'الإنجيل', 'Musique chrétienne spirituelle', '#27AE60'),
('Afro-Pop', 'Afro-Pop', 'أفرو بوب', 'Pop africaine moderne', '#8E44AD'),
('Reggae', 'Reggae', 'ريغي', 'Musique jamaïcaine populaire en Afrique', '#16A085'),
('Salsa', 'Salsa', 'سالسا', 'Musique latine dansante', '#D35400');

-- Création de l'utilisateur admin par défaut
INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) VALUES
('admin', 'admin@tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Tchadok', '+235 XX XX XX XX', 'Tchad', 'N''Djamena', TRUE, TRUE);

INSERT INTO admins (user_id, role, permissions) VALUES
(1, 'super_admin', '["all"]');

-- Index pour améliorer les performances
CREATE INDEX idx_tracks_artist ON tracks(artist_id);
CREATE INDEX idx_tracks_album ON tracks(album_id);
CREATE INDEX idx_tracks_genre ON tracks(genre_id);
CREATE INDEX idx_albums_artist ON albums(artist_id);
CREATE INDEX idx_streams_track ON streams(track_id);
CREATE INDEX idx_streams_date ON streams(created_at);
CREATE INDEX idx_purchases_user ON purchases(user_id);
CREATE INDEX idx_purchases_date ON purchases(created_at);
CREATE INDEX idx_favorites_user ON favorites(user_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);

-- Triggers pour mettre à jour les statistiques (VERSION CORRIGÉE)
DELIMITER $$

CREATE TRIGGER update_album_tracks_count AFTER INSERT ON tracks
FOR EACH ROW
BEGIN
    IF NEW.album_id IS NOT NULL THEN
        UPDATE albums SET total_tracks = (
            SELECT COUNT(*) FROM tracks WHERE album_id = NEW.album_id
        ) WHERE id = NEW.album_id;
    END IF;
END$$

CREATE TRIGGER update_stream_stats AFTER INSERT ON streams
FOR EACH ROW
BEGIN
    -- Mettre à jour les statistiques du titre
    UPDATE tracks SET total_streams = total_streams + 1 WHERE id = NEW.track_id;
    
    -- Mettre à jour les statistiques de l'artiste
    UPDATE artists SET total_streams = total_streams + 1 WHERE id = NEW.artist_id;
    
    -- Mettre à jour les statistiques de l'album si le titre appartient à un album
    UPDATE albums a 
    JOIN tracks t ON a.id = t.album_id 
    SET a.total_streams = a.total_streams + 1 
    WHERE t.id = NEW.track_id AND t.album_id IS NOT NULL;
END$$

CREATE TRIGGER update_purchase_stats AFTER INSERT ON purchases
FOR EACH ROW
BEGIN
    IF NEW.item_type = 'track' THEN
        UPDATE tracks SET total_sales = total_sales + 1 WHERE id = NEW.item_id;
    ELSEIF NEW.item_type = 'album' THEN
        UPDATE albums SET total_sales = total_sales + 1 WHERE id = NEW.item_id;
    END IF;
    UPDATE artists SET total_sales = total_sales + NEW.amount WHERE id = NEW.artist_id;
END$$

DELIMITER ;

-- Vue pour les top tracks
CREATE VIEW top_tracks AS
SELECT 
    t.id,
    t.title,
    t.total_streams,
    t.total_sales,
    a.stage_name as artist_name,
    g.name as genre_name
FROM tracks t
JOIN artists a ON t.artist_id = a.id
LEFT JOIN genres g ON t.genre_id = g.id
WHERE t.status = 'approved'
ORDER BY t.total_streams DESC;

-- Vue pour les top artists
CREATE VIEW top_artists AS
SELECT 
    a.id,
    a.stage_name,
    a.total_streams,
    a.total_sales,
    COUNT(t.id) as total_tracks
FROM artists a
LEFT JOIN tracks t ON a.id = t.artist_id
WHERE a.is_active = TRUE
GROUP BY a.id
ORDER BY a.total_streams DESC;