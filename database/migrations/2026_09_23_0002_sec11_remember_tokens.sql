-- SEC-11 : « se souvenir de moi » par selecteur et verificateur.
--
-- Avant : une colonne users.remember_token portant un hash bcrypt. Pour
-- identifier le porteur d'un cookie, il fallait lire tous les comptes munis
-- d'un jeton et calculer un bcrypt sur chacun : une requete anonyme pouvait
-- occuper le serveur plusieurs minutes.
--
-- Apres : une table dediee, un couple selecteur + verificateur. Le selecteur
-- est un identifiant public indexe : une lecture par cle unique, puis une
-- seule comparaison.
--
-- Le verificateur est stocke en SHA-256 et non en bcrypt : c'est une valeur
-- aleatoire de 32 octets, pas un mot de passe choisi par un humain. Il n'y a
-- rien a ralentir, et le calcul doit rester negligeable pour ne pas recreer le
-- deni de service que cette migration corrige.
--
-- Cette migration ne fait rien sur une installation deja passee par la
-- photographie du schema : elle sert aux bases anterieures a SEC-11.

-- UP

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` char(32) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `previous_validator_hash` char(64) DEFAULT NULL,
  `rotated_at` datetime DEFAULT NULL,
  `device_label` varchar(120) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Les jetons de l'ancien format ne sont pas repris : ils ne portent ni
-- selecteur ni date d'expiration. Les personnes concernees se reconnectent une
-- fois. La colonne disparait pour qu'aucun code ne puisse y revenir.
--
-- DROP COLUMN IF EXISTS n'existe pas en MySQL : le test passe par
-- information_schema, ce qui rend la migration rejouable sans erreur.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'remember_token'
    ),
    'ALTER TABLE `users` DROP COLUMN `remember_token`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'remember_token'
    ),
    'DO 0',
    'ALTER TABLE `users` ADD COLUMN `remember_token` varchar(255) DEFAULT NULL AFTER `reset_expires`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DROP TABLE IF EXISTS `remember_tokens`;
