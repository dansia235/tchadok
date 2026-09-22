-- ================================================================
-- Jeu d'essai SEC-06 : controle d'acces aux medias
-- ================================================================
-- A importer sur la base LOCALE uniquement, via
-- tests/securite/sec06-acces-media.ps1 qui gere mise en place et
-- nettoyage. Identifiants reserves : users 901-905, artists 901,
-- tracks 9001-9007.
--
-- Mot de passe de tous les comptes : tchadok2026 (valeur locale).
-- ================================================================

SET NAMES utf8mb4;

INSERT INTO users (id, username, email, password, password_hash, first_name, last_name,
                   is_active, email_verified, premium_status, premium_expires_at)
VALUES
 (901, 'essai_artiste',  'artiste@essai.local',  '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Artiste',  1, 1, 0, NULL),
 (902, 'essai_premium',  'premium@essai.local',  '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Premium',  1, 1, 1, DATE_ADD(NOW(), INTERVAL 30 DAY)),
 (903, 'essai_expire',   'expire@essai.local',   '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Expire',   1, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (904, 'essai_acheteur', 'acheteur@essai.local', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Acheteur', 1, 1, 0, NULL),
 (905, 'essai_fan',      'fan@essai.local',      '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', '$2y$12$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', 'Essai', 'Fan',      1, 1, 0, NULL);

INSERT INTO artists (id, user_id, stage_name, is_active) VALUES (901, 901, 'Artiste Essai', 1);

INSERT INTO tracks (id, artist_id, title, audio_file, preview_file, duration, price, is_free, status) VALUES
 (9001, 901, 'Gratuit publie',       'storage/uploads/audio/test-gratuit.mp3', NULL, 180, 0,   1, 'approved'),
 (9002, 901, 'Payant avec extrait',  'storage/uploads/audio/test-payant.mp3',  'storage/uploads/audio/test-extrait.mp3', 180, 500, 0, 'approved'),
 (9003, 901, 'Payant sans extrait',  'storage/uploads/audio/test-payant.mp3',  NULL, 180, 500, 0, 'approved'),
 (9004, 901, 'Gratuit en brouillon', 'storage/uploads/audio/test-gratuit.mp3', NULL, 180, 0,   1, 'draft'),
 (9005, 901, 'URL externe',          'http://site-tiers.example/x.mp3',        NULL, 180, 0,   1, 'approved'),
 (9006, 901, 'Traversee',            '../../../../xampp/php/php.ini',          NULL, 180, 0,   1, 'approved'),
 (9007, 901, 'Fichier historique',   'uploads/audio/test-historique.mp3',      NULL, 180, 0,   1, 'approved');

INSERT INTO purchases (user_id, item_type, item_id, artist_id, amount, commission, payment_method, payment_status)
VALUES (904, 'track', 9002, 901, 500, 75, 'airtel_money', 'completed');
