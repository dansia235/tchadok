-- =========================================================
-- Reset du mot de passe admin (table users + admins)
-- =========================================================
-- IMPORTANT:
-- 1) Generez un hash bcrypt PHP pour votre nouveau mot de passe:
--    php -r "echo password_hash('NouveauMotDePasse!', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
-- 2) Remplacez la valeur de @new_password_hash ci-dessous.
-- 3) Ce script met a jour uniquement le compte admin cible.

START TRANSACTION;

-- Cible admin (adaptez si necessaire)
SET @admin_username = 'admin';
SET @admin_email = 'admin@tchadok.td';

-- Hash par defaut pour le mot de passe: 12345678
-- Remplacez cette valeur pour un vrai mot de passe securise.
SET @new_password_hash = '$2y$12$44Eg1vk9c72lCYqRWv9SG.NbUOZOigietSixa3vQOULB2sBy6bgHq';

UPDATE users u
INNER JOIN admins a ON a.user_id = u.id
SET
    u.password = @new_password_hash,
    u.password_hash = @new_password_hash,
    u.reset_token = NULL,
    u.reset_expires = NULL,
    u.remember_token = NULL,
    u.updated_at = NOW()
WHERE (BINARY u.username = BINARY @admin_username OR BINARY u.email = BINARY @admin_email);

SELECT ROW_COUNT() AS updated_admin_accounts;

SELECT
    u.id,
    u.username,
    u.email,
    a.role,
    u.is_active,
    u.updated_at
FROM users u
INNER JOIN admins a ON a.user_id = u.id
WHERE (BINARY u.username = BINARY @admin_username OR BINARY u.email = BINARY @admin_email);

COMMIT;

-- ---------------------------------------------------------
-- Optionnel: reset de TOUS les comptes admin
-- ---------------------------------------------------------
-- UPDATE users u
-- INNER JOIN admins a ON a.user_id = u.id
-- SET
--     u.password = @new_password_hash,
--     u.password_hash = @new_password_hash,
--     u.reset_token = NULL,
--     u.reset_expires = NULL,
--     u.remember_token = NULL,
--     u.updated_at = NOW();
