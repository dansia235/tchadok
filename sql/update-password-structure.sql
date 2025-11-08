-- Script SQL pour mise à jour de la structure de la table users
-- et création du compte administrateur
-- Tchadok Platform

-- 1. Vérifier si la colonne password existe et la renommer
ALTER TABLE users 
CHANGE COLUMN `password` `password_hash` VARCHAR(255) NOT NULL;

-- OU si la colonne n'existe pas du tout, l'ajouter
-- ALTER TABLE users ADD COLUMN `password_hash` VARCHAR(255) NOT NULL AFTER `email`;

-- 2. Ajouter les colonnes manquantes si elles n'existent pas
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) AFTER `last_name`,
ADD COLUMN IF NOT EXISTS `profile_image` VARCHAR(255) AFTER `user_type`,
ADD COLUMN IF NOT EXISTS `bio` TEXT AFTER `profile_image`,
ADD COLUMN IF NOT EXISTS `date_of_birth` DATE AFTER `bio`,
ADD COLUMN IF NOT EXISTS `gender` ENUM('male', 'female', 'other') AFTER `date_of_birth`,
ADD COLUMN IF NOT EXISTS `location` VARCHAR(100) AFTER `gender`,
ADD COLUMN IF NOT EXISTS `is_verified` BOOLEAN DEFAULT FALSE AFTER `location`;

-- 3. Mettre à jour tous les mots de passe existants vers '12345678'
-- Le hash pour '12345678' généré avec password_hash() PHP
UPDATE users 
SET password_hash = '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq'
WHERE 1;

-- 4. Créer le compte administrateur (si n'existe pas)
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    phone,
    user_type, 
    profile_image,
    bio,
    date_of_birth,
    gender,
    location,
    is_verified,
    created_at
) VALUES (
    'admin_tchadok',
    'admin@tchadok.td',
    '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq', -- Hash de '12345678'
    'Admin',
    'Tchadok',
    '62123456',
    'admin',
    NULL,
    'Administrateur principal de la plateforme Tchadok',
    '1985-01-15',
    'male',
    'N\'Djamena, Tchad',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq',
    user_type = 'admin',
    is_verified = 1;

-- 5. Afficher le nombre d'utilisateurs mis à jour
SELECT COUNT(*) as 'Nombre d\'utilisateurs mis à jour' FROM users;

-- 6. Afficher le compte admin
SELECT id, username, email, user_type, is_verified 
FROM users 
WHERE username = 'admin_tchadok';

-- Message de fin
SELECT 'Mise à jour terminée ! Tous les comptes utilisent maintenant le mot de passe: 12345678' as 'Message';