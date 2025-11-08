-- Script pour vérifier la structure exacte de la table users

-- 1. Afficher toutes les colonnes de la table users
SHOW COLUMNS FROM users;

-- 2. Vérifier spécifiquement les colonnes liées au mot de passe
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'tchadok' 
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME LIKE '%pass%';

-- 3. Afficher la structure complète
DESCRIBE users;