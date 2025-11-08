-- Script pour vérifier la structure actuelle de la table users
-- Tchadok Platform

-- 1. Afficher la structure complète de la table users
DESCRIBE users;

-- 2. Afficher les colonnes de la table
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'tchadok' 
    AND TABLE_NAME = 'users'
ORDER BY 
    ORDINAL_POSITION;

-- 3. Vérifier si la colonne password ou password_hash existe
SELECT 
    COLUMN_NAME 
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'tchadok' 
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME IN ('password', 'password_hash');

-- 4. Compter les utilisateurs existants
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN user_type = 'artist' THEN 1 ELSE 0 END) as artist_count,
    SUM(CASE WHEN user_type = 'fan' THEN 1 ELSE 0 END) as fan_count
FROM users;