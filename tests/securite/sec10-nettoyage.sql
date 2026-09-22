-- Nettoyage du jeu d'essai SEC-10.
-- admins et user_sessions sont supprimes en cascade avec users.
DELETE FROM user_sessions WHERE user_id IN (931, 932);
DELETE FROM admins        WHERE user_id IN (931, 932);
DELETE FROM users         WHERE id IN (931, 932);
