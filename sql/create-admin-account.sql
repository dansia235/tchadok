-- Script SQL pour créer le compte administrateur Tchadok
-- Basé sur la structure réelle de la base de données

-- 1. Créer l'utilisateur admin_tchadok
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

-- 2. Récupérer l'ID de l'utilisateur créé
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin_tchadok');

-- 3. Créer l'entrée dans la table admins
INSERT INTO admins (
    user_id,
    role,
    permissions
) VALUES (
    @admin_user_id,
    'super_admin',
    '["all"]'
) ON DUPLICATE KEY UPDATE 
    role = 'super_admin',
    permissions = '["all"]';

-- 4. Afficher les informations de connexion
SELECT 
    u.id,
    u.username,
    u.email,
    a.role,
    'Mot de passe: 12345678' as password_info
FROM users u
JOIN admins a ON u.id = a.user_id
WHERE u.username = 'admin_tchadok';

-- 5. Mettre à jour tous les mots de passe existants vers 12345678
UPDATE users 
SET password = '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq'
WHERE 1;

SELECT 'Tous les comptes utilisent maintenant le mot de passe: 12345678' as message;