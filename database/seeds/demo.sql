-- ================================================================
-- TCHADOK - JEU DE DEMONSTRATION (LOCAL UNIQUEMENT)
-- ================================================================
--
-- Tache SEC-05.
--
-- NE JAMAIS IMPORTER EN PRODUCTION.
--
-- Garde-fous :
--   - scripts/env-switch.php production REFUSE la bascule tant que ce
--     fichier est present sur le serveur ;
--   - includes/environment-guard.php le signale en production.
--
-- Contenu :
--   admin      / admin@tchadok.td   super-administrateur
--   user_demo  / user@tchadok.td    melomane
--
-- Mot de passe des deux comptes : tchadok2026
-- (identifiant local simple, par decision du 22/09/2026)
--
-- Import, APRES database/tchadok.sql :
--   C:\xampp\mysql\bin\mysql.exe -u root tchadok_local < database\seeds\demo.sql
--
-- Ces comptes etaient auparavant livres dans database/tchadok.sql, avec
-- un hash dont le mot de passe etait documente comme public : toute
-- installation de production faite depuis ce dump demarrait avec un
-- super-administrateur a identifiants connus.
-- ================================================================

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO `users`
  (`id`, `username`, `email`, `password`, `password_hash`,
   `first_name`, `last_name`, `country`, `city`, `preferred_language`,
   `premium_status`, `email_verified`, `is_active`, `created_at`, `updated_at`)
VALUES
  (1, 'admin', 'admin@tchadok.td',
   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG',
   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG',
   'Admin', 'Tchadok', 'Tchad', 'N''Djamena', 'fr', 0, 1, 1, NOW(), NOW()),
  (2, 'user_demo', 'user@tchadok.td',
   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG',
   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG',
   'Utilisateur', 'Demo', 'Tchad', 'N''Djamena', 'fr', 0, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

INSERT INTO `admins` (`user_id`, `role`, `permissions`, `created_at`)
VALUES (1, 'super_admin', '["all"]', NOW())
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);

COMMIT;
