-- Jeu d'essai SEC-10 : sessions.
-- Identifiants reserves : users 931 (fan) et 932 (administrateur).
-- Mot de passe : tchadok2026 (valeur locale).
SET NAMES utf8mb4;

INSERT INTO users (id, username, email, password, password_hash, first_name, last_name, is_active, email_verified)
VALUES
 (931, 'essai10_fan',   'fan10@essai.local',   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Fan',   1, 1),
 (932, 'essai10_admin', 'admin10@essai.local', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Admin', 1, 1);

INSERT INTO admins (user_id, role, permissions) VALUES (932, 'admin', '[]');

-- SEC-19 : c'est le role qui ouvre les droits, plus la table admins.
-- Sans cette ligne, le compte 932 n'aurait acces a aucun ecran d'administration.
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT 932, id FROM roles WHERE slug = 'admin_plateforme';
