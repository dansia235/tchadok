-- ================================================================
-- Script de création des comptes de test - Plateforme Tchadok
-- VERSION SIMPLIFIÉE pour exécution via PHP PDO
-- ================================================================
-- Ce script supprime les anciens comptes de test et en crée de nouveaux
-- Mot de passe par défaut pour tous les comptes : tchadok2024
-- ================================================================

-- ================================================================
-- ÉTAPE 1: SUPPRESSION DES ANCIENS COMPTES DE TEST
-- ================================================================

-- Supprimer les artistes de test (sans subquery)
DELETE FROM artists
WHERE user_id IN (
    SELECT user_id FROM (
        SELECT id as user_id FROM users
        WHERE email LIKE '%@test.tchadok.td'
        OR email LIKE '%@tchadok.test'
        OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3')
    ) AS temp_users
);

-- Supprimer les admins de test (sans subquery)
DELETE FROM admins
WHERE user_id IN (
    SELECT user_id FROM (
        SELECT id as user_id FROM users
        WHERE email LIKE '%@test.tchadok.td'
        OR email LIKE '%@tchadok.test'
        OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3')
    ) AS temp_users
);

-- Supprimer les utilisateurs de test
DELETE FROM users
WHERE email LIKE '%@test.tchadok.td'
OR email LIKE '%@tchadok.test'
OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3');

-- ================================================================
-- ÉTAPE 2: CRÉATION DES NOUVEAUX COMPTES
-- ================================================================

-- COMPTE 1: ADMINISTRATEUR
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, gender, premium_status, email_verified, is_active, created_at)
VALUES ('admin_test', 'admin@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'Tchadok', '+235 62 00 00 00', 'Tchad', 'N''Djamena', 'M', 1, 1, 1, NOW());

-- COMPTE 2: FAN PREMIUM
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, premium_expires_at, wallet_balance, loyalty_points, email_verified, is_active, created_at)
VALUES ('fan_test1', 'fan1@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amina', 'Hassan', '+235 62 11 11 11', 'Tchad', 'N''Djamena', '1995-03-15', 'F', 1, DATE_ADD(NOW(), INTERVAL 1 YEAR), 5000.00, 850, 1, 1, NOW());

-- COMPTE 3: FAN STANDARD
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, wallet_balance, loyalty_points, email_verified, is_active, created_at)
VALUES ('fan_test2', 'fan2@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mahamat', 'Idriss', '+235 62 22 22 22', 'Tchad', 'Abéché', '1998-07-22', 'M', 0, 2500.00, 320, 1, 1, NOW());

-- COMPTE 4: FAN ÉTUDIANT
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, premium_expires_at, wallet_balance, loyalty_points, email_verified, is_active, created_at)
VALUES ('fan_test3', 'fan3@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatima', 'Oumar', '+235 62 33 33 33', 'Tchad', 'Moundou', '2002-11-08', 'F', 1, DATE_ADD(NOW(), INTERVAL 6 MONTH), 1200.00, 150, 1, 1, NOW());

-- COMPTE 5: ARTISTE VÉRIFIÉ
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, wallet_balance, email_verified, is_active, created_at)
VALUES ('artist_test1', 'artist1@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Abdoulaye', 'Ngaradoumbé', '+235 62 44 44 44', 'Tchad', 'N''Djamena', '1990-05-18', 'M', 1, 25000.00, 1, 1, NOW());

-- COMPTE 6: ARTISTE ÉMERGENTE
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, wallet_balance, email_verified, is_active, created_at)
VALUES ('artist_test2', 'artist2@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Djimadoum', '+235 62 55 55 55', 'Tchad', 'Sarh', '1996-09-25', 'F', 1, 8500.00, 1, 1, NOW());

-- COMPTE 7: ARTISTE DÉBUTANT
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, city, date_of_birth, gender, premium_status, wallet_balance, email_verified, is_active, created_at)
VALUES ('artist_test3', 'artist3@test.tchadok.td', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ibrahim', 'Ahmat', '+235 62 66 66 66', 'Tchad', 'Abéché', '2000-12-03', 'M', 0, 1200.00, 1, 1, NOW());

-- ================================================================
-- ÉTAPE 3: AJOUT DES PROFILS SPÉCIALISÉS
-- ================================================================

-- Profil Admin
INSERT INTO admins (user_id, role, permissions, created_at)
SELECT id, 'super_admin', 'all', NOW() FROM users WHERE username = 'admin_test';

-- Profil Artiste 1 (Vérifié)
INSERT INTO artists (user_id, stage_name, real_name, bio, facebook, instagram, twitter, youtube, birth_date, birth_place, genres, verified, featured, total_streams, total_sales, total_earnings, commission_rate, is_active, created_at)
SELECT id, 'Ngar Star', 'Abdoulaye Ngaradoumbé', 'Artiste tchadien de renom, pionnier du rap tchadien moderne. Avec plus de 10 ans de carrière, je fusionne les rythmes traditionnels avec les sons urbains contemporains.', 'https://facebook.com/ngarstar', 'https://instagram.com/ngarstar', 'https://twitter.com/ngarstar', 'https://youtube.com/@ngarstar', '1990-05-18', 'N''Djamena, Tchad', 'Rap, Hip-Hop, Afrobeat', 1, 1, 150000, 45000.00, 38250.00, 15.00, 1, NOW() FROM users WHERE username = 'artist_test1';

-- Profil Artiste 2 (Émergente)
INSERT INTO artists (user_id, stage_name, real_name, bio, facebook, instagram, twitter, youtube, birth_date, birth_place, genres, verified, featured, total_streams, total_sales, total_earnings, commission_rate, is_active, created_at)
SELECT id, 'Sasa Voice', 'Sarah Djimadoum', 'Chanteuse et auteure-compositrice tchadienne. Ma musique mélange les sonorités afro-soul avec des influences R&B modernes pour raconter des histoires authentiques.', 'https://facebook.com/sasavoice', 'https://instagram.com/sasavoice', 'https://twitter.com/sasavoice', 'https://youtube.com/@sasavoice', '1996-09-25', 'Sarh, Tchad', 'Afro-Soul, R&B, Pop', 0, 1, 32000, 9800.00, 8330.00, 15.00, 1, NOW() FROM users WHERE username = 'artist_test2';

-- Profil Artiste 3 (Débutant)
INSERT INTO artists (user_id, stage_name, real_name, bio, facebook, instagram, birth_date, birth_place, genres, verified, featured, total_streams, total_sales, total_earnings, commission_rate, is_active, created_at)
SELECT id, 'Ibro Beats', 'Ibrahim Ahmat', 'Jeune producteur et beatmaker tchadien. Passionné par la fusion des rythmes traditionnels avec les beats modernes pour créer un son unique.', 'https://facebook.com/ibrobeats', 'https://instagram.com/ibrobeats', '2000-12-03', 'Abéché, Tchad', 'Afrobeat, Trap, Electronic', 0, 0, 5400, 850.00, 722.50, 15.00, 1, NOW() FROM users WHERE username = 'artist_test3';
