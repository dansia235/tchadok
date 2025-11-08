-- Script pour corriger la table users

-- 1. Vérifier d'abord la structure actuelle
SHOW COLUMNS FROM users;

-- 2. Ajouter la colonne password si elle n'existe pas
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL AFTER email;

-- 3. Si vous avez une erreur avec IF NOT EXISTS, utilisez cette version :
-- ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email;

-- 4. Créer l'utilisateur admin_tchadok
INSERT INTO users (
    username, 
    email, 
    password, 
    first_name, 
    last_name,
    phone,
    country,
    city,
    email_verified,
    is_active
) VALUES (
    'admin_tchadok',
    'admin@tchadok.td',
    '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq', -- Hash de '12345678'
    'Admin',
    'Tchadok',
    '62123456',
    'Tchad',
    'N\'Djamena',
    1,
    1
) ON DUPLICATE KEY UPDATE 
    password = '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq',
    email_verified = 1,
    is_active = 1;

-- 5. Récupérer l'ID de l'utilisateur
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin_tchadok');

-- 6. Créer l'entrée admin
INSERT INTO admins (
    user_id,
    role,
    permissions
) VALUES (
    @admin_user_id,
    'super_admin',
    '["all"]'
) ON DUPLICATE KEY UPDATE 
    role = 'super_admin';

-- 7. Vérifier le résultat
SELECT 
    u.id,
    u.username,
    u.email,
    a.role,
    'Mot de passe: 12345678' as info
FROM users u
LEFT JOIN admins a ON u.id = a.user_id
WHERE u.username = 'admin_tchadok';