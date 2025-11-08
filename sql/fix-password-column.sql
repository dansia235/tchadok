-- Script SQL pour corriger le problème de la colonne password
-- Tchadok Platform

-- Option 1: Si la table a une colonne 'password' et non 'password_hash'
ALTER TABLE users 
CHANGE COLUMN `password` `password_hash` VARCHAR(255) NOT NULL;

-- Option 2: Si la colonne n'existe pas du tout
-- Décommentez la ligne suivante si nécessaire
-- ALTER TABLE users ADD COLUMN `password_hash` VARCHAR(255) NOT NULL AFTER `email`;

-- Option 3: Script complet pour recréer la table avec la bonne structure
-- ATTENTION: Ceci supprimera toutes les données existantes !
/*
DROP TABLE IF EXISTS users;

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
*/

-- Insérer le compte admin avec le mot de passe 12345678
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    user_type, 
    is_verified
) VALUES (
    'admin_tchadok',
    'admin@tchadok.td',
    '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq', -- Hash de '12345678'
    'Admin',
    'Tchadok',
    'admin',
    1
) ON DUPLICATE KEY UPDATE 
    password_hash = '$2y$10$4OSzjPSWVxBpRK1KqRJu7eO.x0qZ0HQaF4Xt9aNQkclBD5lm0Cfyq';