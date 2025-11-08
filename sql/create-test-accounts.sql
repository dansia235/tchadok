-- ================================================================
-- Script de création des comptes de test - Plateforme Tchadok
-- ================================================================
-- Ce script supprime les anciens comptes de test et en crée de nouveaux
-- pour chaque profil de la plateforme (Admin, Fan, Artiste)
--
-- Mot de passe par défaut pour tous les comptes : tchadok2024
-- Hash bcrypt : $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ================================================================
-- ÉTAPE 1: SUPPRESSION DES ANCIENS COMPTES DE TEST
-- ================================================================

-- Supprimer les artistes associés aux comptes de test
DELETE FROM artists
WHERE user_id IN (
    SELECT id FROM users
    WHERE email LIKE '%@test.tchadok.td'
    OR email LIKE '%@tchadok.test'
    OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3')
);

-- Supprimer les admins associés aux comptes de test
DELETE FROM admins
WHERE user_id IN (
    SELECT id FROM users
    WHERE email LIKE '%@test.tchadok.td'
    OR email LIKE '%@tchadok.test'
    OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3')
);

-- Supprimer les utilisateurs de test
DELETE FROM users
WHERE email LIKE '%@test.tchadok.td'
OR email LIKE '%@tchadok.test'
OR username IN ('admin_test', 'fan_test1', 'fan_test2', 'fan_test3', 'artist_test1', 'artist_test2', 'artist_test3');

-- ================================================================
-- ÉTAPE 2: CRÉATION DES NOUVEAUX COMPTES
-- ================================================================

-- ----------------------------------------------------------------
-- 2.1 COMPTE ADMINISTRATEUR
-- ----------------------------------------------------------------
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    gender,
    premium_status,
    email_verified,
    is_active,
    created_at
) VALUES (
    'admin_test',
    'admin@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Administrateur',
    'Tchadok',
    '+235 62 00 00 00',
    'Tchad',
    'N''Djamena',
    'M',
    1,
    1,
    1,
    NOW()
);

-- Ajouter l'entrée admin
INSERT INTO admins (user_id, role, permissions, created_at)
SELECT
    id,
    'super_admin',
    'all',
    NOW()
FROM users
WHERE username = 'admin_test';

-- ----------------------------------------------------------------
-- 2.2 COMPTES FANS (Utilisateurs standards)
-- ----------------------------------------------------------------

-- Fan 1 - Premium
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    premium_expires_at,
    wallet_balance,
    loyalty_points,
    email_verified,
    is_active,
    created_at
) VALUES (
    'fan_test1',
    'fan1@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Amina',
    'Hassan',
    '+235 62 11 11 11',
    'Tchad',
    'N''Djamena',
    '1995-03-15',
    'F',
    1,
    DATE_ADD(NOW(), INTERVAL 1 YEAR),
    5000.00,
    850,
    1,
    1,
    NOW()
);

-- Fan 2 - Standard
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    wallet_balance,
    loyalty_points,
    email_verified,
    is_active,
    created_at
) VALUES (
    'fan_test2',
    'fan2@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Mahamat',
    'Idriss',
    '+235 62 22 22 22',
    'Tchad',
    'Abéché',
    '1998-07-22',
    'M',
    0,
    2500.00,
    320,
    1,
    1,
    NOW()
);

-- Fan 3 - Étudiant Premium
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    premium_expires_at,
    wallet_balance,
    loyalty_points,
    email_verified,
    is_active,
    created_at
) VALUES (
    'fan_test3',
    'fan3@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Fatima',
    'Oumar',
    '+235 62 33 33 33',
    'Tchad',
    'Moundou',
    '2002-11-08',
    'F',
    1,
    DATE_ADD(NOW(), INTERVAL 6 MONTH),
    1200.00,
    150,
    1,
    1,
    NOW()
);

-- ----------------------------------------------------------------
-- 2.3 COMPTES ARTISTES
-- ----------------------------------------------------------------

-- Artiste 1 - Artiste vérifié et populaire
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    wallet_balance,
    email_verified,
    is_active,
    created_at
) VALUES (
    'artist_test1',
    'artist1@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Abdoulaye',
    'Ngaradoumbé',
    '+235 62 44 44 44',
    'Tchad',
    'N''Djamena',
    '1990-05-18',
    'M',
    1,
    25000.00,
    1,
    1,
    NOW()
);

INSERT INTO artists (
    user_id,
    stage_name,
    real_name,
    bio,
    facebook,
    instagram,
    twitter,
    youtube,
    birth_date,
    birth_place,
    genres,
    verified,
    featured,
    total_streams,
    total_sales,
    total_earnings,
    commission_rate,
    is_active,
    created_at
)
SELECT
    id,
    'Ngar Star',
    'Abdoulaye Ngaradoumbé',
    'Artiste tchadien de renom, pionnier du rap tchadien moderne. Avec plus de 10 ans de carrière, je fusionne les rythmes traditionnels avec les sons urbains contemporains.',
    'https://facebook.com/ngarstar',
    'https://instagram.com/ngarstar',
    'https://twitter.com/ngarstar',
    'https://youtube.com/@ngarstar',
    '1990-05-18',
    'N''Djamena, Tchad',
    'Rap, Hip-Hop, Afrobeat',
    1,
    1,
    150000,
    45000.00,
    38250.00,
    15.00,
    1,
    NOW()
FROM users
WHERE username = 'artist_test1';

-- Artiste 2 - Artiste émergente
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    wallet_balance,
    email_verified,
    is_active,
    created_at
) VALUES (
    'artist_test2',
    'artist2@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Sarah',
    'Djimadoum',
    '+235 62 55 55 55',
    'Tchad',
    'Sarh',
    '1996-09-25',
    'F',
    1,
    8500.00,
    1,
    1,
    NOW()
);

INSERT INTO artists (
    user_id,
    stage_name,
    real_name,
    bio,
    facebook,
    instagram,
    twitter,
    youtube,
    birth_date,
    birth_place,
    genres,
    verified,
    featured,
    total_streams,
    total_sales,
    total_earnings,
    commission_rate,
    is_active,
    created_at
)
SELECT
    id,
    'Sasa Voice',
    'Sarah Djimadoum',
    'Chanteuse et auteure-compositrice tchadienne. Ma musique mélange les sonorités afro-soul avec des influences R&B modernes pour raconter des histoires authentiques.',
    'https://facebook.com/sasavoice',
    'https://instagram.com/sasavoice',
    'https://twitter.com/sasavoice',
    'https://youtube.com/@sasavoice',
    '1996-09-25',
    'Sarh, Tchad',
    'Afro-Soul, R&B, Pop',
    0,
    1,
    32000,
    9800.00,
    8330.00,
    15.00,
    1,
    NOW()
FROM users
WHERE username = 'artist_test2';

-- Artiste 3 - Artiste débutant
INSERT INTO users (
    username,
    email,
    password_hash,
    first_name,
    last_name,
    phone,
    country,
    city,
    date_of_birth,
    gender,
    premium_status,
    wallet_balance,
    email_verified,
    is_active,
    created_at
) VALUES (
    'artist_test3',
    'artist3@test.tchadok.td',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- tchadok2024
    'Ibrahim',
    'Ahmat',
    '+235 62 66 66 66',
    'Tchad',
    'Abéché',
    '2000-12-03',
    'M',
    0,
    1200.00,
    1,
    1,
    NOW()
);

INSERT INTO artists (
    user_id,
    stage_name,
    real_name,
    bio,
    facebook,
    instagram,
    birth_date,
    birth_place,
    genres,
    verified,
    featured,
    total_streams,
    total_sales,
    total_earnings,
    commission_rate,
    is_active,
    created_at
)
SELECT
    id,
    'Ibro Beats',
    'Ibrahim Ahmat',
    'Jeune producteur et beatmaker tchadien. Passionné par la fusion des rythmes traditionnels avec les beats modernes pour créer un son unique.',
    'https://facebook.com/ibrobeats',
    'https://instagram.com/ibrobeats',
    '2000-12-03',
    'Abéché, Tchad',
    'Afrobeat, Trap, Electronic',
    0,
    0,
    5400,
    850.00,
    722.50,
    15.00,
    1,
    NOW()
FROM users
WHERE username = 'artist_test3';

-- ================================================================
-- ÉTAPE 3: VÉRIFICATION DES COMPTES CRÉÉS
-- ================================================================

-- Afficher tous les comptes de test créés
SELECT
    u.id,
    u.username,
    u.email,
    u.first_name,
    u.last_name,
    u.premium_status,
    u.wallet_balance,
    CASE
        WHEN a.id IS NOT NULL THEN 'Artiste'
        WHEN adm.id IS NOT NULL THEN 'Admin'
        ELSE 'Fan'
    END as type_profil,
    CASE
        WHEN a.id IS NOT NULL THEN ar.stage_name
        ELSE NULL
    END as nom_artiste,
    u.created_at
FROM users u
LEFT JOIN artists a ON u.id = a.user_id
LEFT JOIN artists ar ON a.id = ar.id
LEFT JOIN admins adm ON u.id = adm.user_id
WHERE u.email LIKE '%@test.tchadok.td'
ORDER BY
    CASE
        WHEN adm.id IS NOT NULL THEN 1
        WHEN a.id IS NOT NULL THEN 2
        ELSE 3
    END,
    u.id;

-- Afficher le résumé
SELECT
    '=== RÉSUMÉ DES COMPTES DE TEST CRÉÉS ===' as message;

SELECT
    'Total comptes créés' as info,
    COUNT(*) as nombre
FROM users
WHERE email LIKE '%@test.tchadok.td'
UNION ALL
SELECT
    'Administrateurs' as info,
    COUNT(*) as nombre
FROM admins adm
JOIN users u ON adm.user_id = u.id
WHERE u.email LIKE '%@test.tchadok.td'
UNION ALL
SELECT
    'Artistes' as info,
    COUNT(*) as nombre
FROM artists a
JOIN users u ON a.user_id = u.id
WHERE u.email LIKE '%@test.tchadok.td'
UNION ALL
SELECT
    'Fans' as info,
    COUNT(*) as nombre
FROM users u
LEFT JOIN artists a ON u.id = a.user_id
LEFT JOIN admins adm ON u.id = adm.user_id
WHERE u.email LIKE '%@test.tchadok.td'
AND a.id IS NULL
AND adm.id IS NULL;

-- ================================================================
-- INFORMATIONS DE CONNEXION
-- ================================================================

SELECT
    '=== INFORMATIONS DE CONNEXION ===' as message;

SELECT
    '1. ADMINISTRATEUR' as type,
    'admin_test' as username,
    'admin@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Accès complet à la plateforme' as description
UNION ALL
SELECT
    '2. FAN PREMIUM' as type,
    'fan_test1' as username,
    'fan1@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Utilisateur premium avec solde de 5000 FCFA' as description
UNION ALL
SELECT
    '3. FAN STANDARD' as type,
    'fan_test2' as username,
    'fan2@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Utilisateur standard avec solde de 2500 FCFA' as description
UNION ALL
SELECT
    '4. FAN ÉTUDIANT' as type,
    'fan_test3' as username,
    'fan3@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Étudiant premium avec solde de 1200 FCFA' as description
UNION ALL
SELECT
    '5. ARTISTE VÉRIFIÉ' as type,
    'artist_test1' as username,
    'artist1@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Ngar Star - Artiste populaire vérifié' as description
UNION ALL
SELECT
    '6. ARTISTE ÉMERGENT' as type,
    'artist_test2' as username,
    'artist2@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Sasa Voice - Artiste en vedette' as description
UNION ALL
SELECT
    '7. ARTISTE DÉBUTANT' as type,
    'artist_test3' as username,
    'artist3@test.tchadok.td' as email,
    'tchadok2024' as password,
    'Ibro Beats - Jeune artiste' as description;

COMMIT;

-- ================================================================
-- FIN DU SCRIPT
-- ================================================================
-- Tous les comptes ont été créés avec succès.
-- Mot de passe pour tous les comptes : tchadok2024
-- ================================================================
