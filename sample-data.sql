-- Données d'exemple pour Tchadok Platform
-- À exécuter après l'installation de base

USE tchadok;

-- Créer quelques artistes d'exemple
INSERT INTO artists (user_id, stage_name, real_name, bio, genres, verified, featured, is_active) VALUES
(1, 'Kaar Kaas Sonn', 'Moussa Hassan', 'Artiste rap tchadien de renom, pionnier du hip-hop au Tchad', '["Rap Tchadien", "Afrobeat"]', 1, 1, 1);

-- Ajouter un utilisateur artiste
INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) VALUES
('kaar_kaas', 'kaar@tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Moussa', 'Hassan', '+235 66 12 34 56', 'Tchad', 'N''Djamena', 1, 1),
('africando', 'africando@tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ibrahim', 'Mahamat', '+235 77 98 76 54', 'Tchad', 'N''Djamena', 1, 1),
('diva_hapsatou', 'diva@tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hapsatou', 'Ibrahim', '+235 99 11 22 33', 'Tchad', 'Moundou', 1, 1);

-- Mettre à jour les artistes avec les bons user_id
UPDATE artists SET user_id = 2 WHERE stage_name = 'Kaar Kaas Sonn';

-- Créer d'autres artistes
INSERT INTO artists (user_id, stage_name, real_name, bio, genres, verified, featured, is_active) VALUES
(3, 'Africando', 'Ibrahim Mahamat', 'Chanteur traditionnel fusionnant musique Sara et moderne', '["Sara Traditionnel", "Afrobeat"]', 1, 1, 1),
(4, 'Diva Hapsatou', 'Hapsatou Ibrahim', 'Diva de la musique tchadienne, voix d''or du Ouaddaï', '["Afro-Pop", "Zouk"]', 1, 1, 1);

-- Créer quelques albums
INSERT INTO albums (artist_id, title, description, type, price, release_date, language, is_featured, status, total_tracks) VALUES
(1, 'Renaissance Tchadienne', 'Premier album studio de Kaar Kaas Sonn célébrant la culture tchadienne', 'album', 2500, '2024-01-15', 'Français/Sara', 1, 'approved', 12),
(2, 'Racines du Chari', 'Album traditionnel revisité avec des sonorités modernes', 'album', 2000, '2024-02-10', 'Sara/Français', 1, 'approved', 10),
(3, 'Femme d''Afrique', 'Hommage aux femmes africaines et tchadiennes', 'album', 3000, '2024-03-05', 'Français/Arabe', 1, 'approved', 8);

-- Créer quelques titres populaires
INSERT INTO tracks (album_id, artist_id, title, description, genre_id, audio_file, duration, price, is_free, language, release_date, status, total_streams, total_sales, is_featured) VALUES
-- Album 1 - Kaar Kaas Sonn
(1, 1, 'Tchad Mon Pays', 'Hymne patriotique célébrant la beauté du Tchad', 6, 'uploads/audio/tchad_mon_pays.mp3', 245, 250, 0, 'Français', '2024-01-15', 'approved', 15420, 89, 1),
(1, 1, 'Sara Flow', 'Fusion rap et musique traditionnelle Sara', 6, 'uploads/audio/sara_flow.mp3', 198, 250, 0, 'Sara/Français', '2024-01-15', 'approved', 12890, 67, 1),
(1, 1, 'N''Djamena City', 'Ode à la capitale tchadienne', 6, 'uploads/audio/ndjamena_city.mp3', 220, 250, 1, 'Français', '2024-01-15', 'approved', 18750, 0, 1),

-- Album 2 - Africando
(2, 2, 'Fleuve Chari', 'Ballade sur le plus grand fleuve du Tchad', 7, 'uploads/audio/fleuve_chari.mp3', 280, 200, 0, 'Sara', '2024-02-10', 'approved', 9560, 45, 1),
(2, 2, 'Danse des Ancêtres', 'Musique traditionnelle pour les cérémonies', 7, 'uploads/audio/danse_ancetres.mp3', 195, 200, 0, 'Sara', '2024-02-10', 'approved', 7830, 38, 0),

-- Album 3 - Diva Hapsatou
(3, 3, 'Mama Africa', 'Hommage aux mères africaines', 10, 'uploads/audio/mama_africa.mp3', 265, 300, 0, 'Français', '2024-03-05', 'approved', 11240, 52, 1),
(3, 3, 'Étoile du Sahel', 'Chanson d''amour sur fond de musique du Ouaddaï', 10, 'uploads/audio/etoile_sahel.mp3', 230, 300, 0, 'Arabe/Français', '2024-03-05', 'approved', 8960, 41, 0),

-- Quelques singles
(NULL, 1, 'Liberté', 'Single engagé sur la liberté d''expression', 6, 'uploads/audio/liberte.mp3', 210, 200, 0, 'Français', '2024-06-01', 'approved', 22100, 95, 1),
(NULL, 2, 'Tam-Tam du Village', 'Single traditionnel acoustique', 7, 'uploads/audio/tamtam_village.mp3', 185, 150, 1, 'Sara', '2024-05-15', 'approved', 6540, 0, 0),
(NULL, 3, 'Beauté Tchadienne', 'Célébration de la femme tchadienne', 10, 'uploads/audio/beaute_tchadienne.mp3', 255, 250, 0, 'Français', '2024-07-10', 'approved', 13780, 68, 1);

-- Mettre à jour les statistiques des artistes
UPDATE artists SET 
    total_streams = (SELECT SUM(total_streams) FROM tracks WHERE artist_id = artists.id),
    total_sales = (SELECT SUM(total_sales * price) FROM tracks WHERE artist_id = artists.id)
WHERE id IN (1, 2, 3);

-- Créer quelques articles de blog
INSERT INTO blog_posts (author_id, author_type, title, slug, content, excerpt, category, status, featured, published_at, views_count, likes_count) VALUES
(2, 'artist', 'L''évolution de la musique tchadienne moderne', 'evolution-musique-tchadienne', 
'<p>La musique tchadienne a connu une transformation remarquable ces dernières années. De la musique traditionnelle Sara et Kanem aux nouvelles sonorités afrobeat, notre pays développe une identité musicale unique.</p><p>Les jeunes artistes comme nous mélangent les rythmes ancestraux avec les technologies modernes, créant un son authentiquement tchadien qui résonne aussi bien dans les villages que dans les capitales africaines.</p>', 
'Découvrez comment la musique tchadienne évolue entre tradition et modernité', 'Analyse', 'published', 1, '2024-06-15 10:00:00', 1250, 45),

(4, 'artist', 'La femme dans la musique tchadienne', 'femme-musique-tchadienne',
'<p>Les femmes ont toujours joué un rôle central dans la musique traditionnelle tchadienne. Aujourd''hui, nous continuons cette tradition en apportant notre voix unique à la scène musicale moderne.</p><p>En tant qu''artiste féminine, je veux inspirer les jeunes filles à poursuivre leurs rêves musicaux malgré les défis socioculturels.</p>',
'Réflexion sur le rôle et l''importance des femmes artistes au Tchad', 'Société', 'published', 1, '2024-07-01 14:30:00', 890, 32),

(1, 'admin', 'Tchadok : La révolution musicale tchadienne', 'tchadok-revolution-musicale',
'<p>Avec le lancement de Tchadok, nous entrons dans une nouvelle ère pour la musique tchadienne. Notre plateforme permet enfin aux artistes locaux de toucher un public mondial tout en restant enracinés dans leur culture.</p><p>Cette révolution numérique va transformer la façon dont la musique tchadienne est créée, partagée et consommée.</p>',
'Découvrez comment Tchadok transforme l''industrie musicale tchadienne', 'Plateforme', 'published', 1, '2024-06-28 09:00:00', 2100, 78);

-- Créer quelques utilisateurs fans
INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active, premium_status) VALUES
('music_lover_td', 'fan1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatima', 'Abdoulaye', '+235 66 55 44 33', 'Tchad', 'N''Djamena', 1, 1, 0),
('tchadmusic_fan', 'fan2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mahamat', 'Ali', '+235 77 88 99 00', 'Tchad', 'Sarh', 1, 1, 1),
('sara_traditional', 'fan3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Clarisse', 'Ndoubabe', '+235 99 77 55 33', 'Tchad', 'Moundou', 1, 1, 0);

-- Créer quelques achats d'exemple
INSERT INTO purchases (user_id, item_type, item_id, artist_id, amount, commission, payment_method, payment_status, currency) VALUES
(5, 'track', 1, 1, 250, 37.5, 'airtel_money', 'completed', 'XAF'),
(6, 'track', 8, 1, 200, 30, 'moov_money', 'completed', 'XAF'),
(7, 'album', 1, 1, 2500, 375, 'wallet', 'completed', 'XAF'),
(5, 'track', 6, 3, 300, 45, 'airtel_money', 'completed', 'XAF'),
(6, 'album', 3, 3, 3000, 450, 'ecobank', 'completed', 'XAF');

-- Créer quelques playlists
INSERT INTO playlists (user_id, name, description, is_public, total_tracks) VALUES
(5, 'Mes Favoris Tchadiens', 'Ma sélection personnelle de musique tchadienne', 1, 3),
(6, 'Rap Tchadien Power', 'Les meilleurs titres de rap du Tchad', 1, 2),
(7, 'Femmes Artistes', 'Célébration des voix féminines tchadiennes', 1, 2);

-- Ajouter des titres aux playlists
INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES
(1, 1, 1), (1, 6, 2), (1, 8, 3),
(2, 1, 1), (2, 2, 2),
(3, 6, 1), (3, 7, 2);

-- Ajouter quelques favoris
INSERT INTO favorites (user_id, item_type, item_id) VALUES
(5, 'artist', 1), (5, 'track', 1), (5, 'album', 1),
(6, 'artist', 1), (6, 'artist', 3), (6, 'track', 8),
(7, 'artist', 3), (7, 'track', 6), (7, 'track', 7);

-- Ajouter quelques streams récents
INSERT INTO streams (user_id, track_id, artist_id, ip_address, country, city, duration_played, completed, source) VALUES
(5, 1, 1, '192.168.1.100', 'Tchad', 'N''Djamena', 245, 1, 'web'),
(6, 8, 1, '192.168.1.101', 'Tchad', 'Sarh', 180, 0, 'web'),
(7, 6, 3, '192.168.1.102', 'Tchad', 'Moundou', 265, 1, 'web'),
(5, 2, 1, '192.168.1.100', 'Tchad', 'N''Djamena', 198, 1, 'web'),
(NULL, 3, 1, '41.203.15.45', 'Cameroun', 'Douala', 150, 0, 'web'),
(NULL, 1, 1, '154.72.164.12', 'France', 'Paris', 245, 1, 'web');

-- Message de confirmation
SELECT 'Données d''exemple ajoutées avec succès!' as message;
SELECT 
    (SELECT COUNT(*) FROM artists) as artistes,
    (SELECT COUNT(*) FROM tracks) as titres,
    (SELECT COUNT(*) FROM albums) as albums,
    (SELECT COUNT(*) FROM users WHERE id > 1) as utilisateurs,
    (SELECT COUNT(*) FROM blog_posts) as articles;