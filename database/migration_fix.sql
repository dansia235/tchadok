-- Script de migration pour corriger la base de données Tchadok
-- Version: 1.0 - Corrections des erreurs
-- Date: 2024

USE tchadok;

-- Ajouter les colonnes manquantes dans la table users (vérification préalable)
SET @sql = '';
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = 'tchadok' AND table_name = 'users' AND column_name = 'verification_token';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) AFTER email_verified;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = 'tchadok' AND table_name = 'users' AND column_name = 'reset_token';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) AFTER verification_token;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = 'tchadok' AND table_name = 'users' AND column_name = 'reset_expires';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN reset_expires DATETIME AFTER reset_token;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = 'tchadok' AND table_name = 'users' AND column_name = 'remember_token';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) AFTER reset_expires;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Supprimer les anciens triggers qui causent des erreurs
DROP TRIGGER IF EXISTS update_album_tracks_count;
DROP TRIGGER IF EXISTS update_stream_stats;
DROP TRIGGER IF EXISTS update_purchase_stats;

-- Recréer les triggers avec la syntaxe corrigée
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
    
    -- Mettre à jour les statistiques de l'album via JOIN (si le titre appartient à un album)
    UPDATE albums a 
    INNER JOIN tracks t ON a.id = t.album_id 
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

-- Créer les vues si elles n'existent pas
DROP VIEW IF EXISTS top_tracks;
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

DROP VIEW IF EXISTS top_artists;
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

-- Mettre à jour ou insérer les genres musicaux
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
('Salsa', 'Salsa', 'سالسا', 'Musique latine dansante', '#D35400')
ON DUPLICATE KEY UPDATE
name_french = VALUES(name_french),
name_arabic = VALUES(name_arabic),
description = VALUES(description),
color = VALUES(color);

-- Créer l'utilisateur admin par défaut s'il n'existe pas
INSERT IGNORE INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) VALUES
('admin', 'admin@tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Tchadok', '+235 XX XX XX XX', 'Tchad', 'N''Djamena', TRUE, TRUE);

-- Créer le profil admin s'il n'existe pas
INSERT IGNORE INTO admins (user_id, role, permissions) 
SELECT 1, 'super_admin', '["all"]' 
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Ajouter les index manquants (vérification préalable)
SET @sql = '';
SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'tracks' AND index_name = 'idx_tracks_artist';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_tracks_artist ON tracks(artist_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'tracks' AND index_name = 'idx_tracks_album';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_tracks_album ON tracks(album_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'tracks' AND index_name = 'idx_tracks_genre';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_tracks_genre ON tracks(genre_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'albums' AND index_name = 'idx_albums_artist';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_albums_artist ON albums(artist_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'streams' AND index_name = 'idx_streams_track';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_streams_track ON streams(track_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'streams' AND index_name = 'idx_streams_date';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_streams_date ON streams(created_at);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'purchases' AND index_name = 'idx_purchases_user';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_purchases_user ON purchases(user_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'purchases' AND index_name = 'idx_purchases_date';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_purchases_date ON purchases(created_at);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'favorites' AND index_name = 'idx_favorites_user';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_favorites_user ON favorites(user_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists FROM information_schema.statistics 
WHERE table_schema = 'tchadok' AND table_name = 'notifications' AND index_name = 'idx_notifications_user';
SET @sql = IF(@index_exists = 0, 'CREATE INDEX idx_notifications_user ON notifications(user_id);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Message de fin
SELECT 'Migration terminée avec succès !' as message;