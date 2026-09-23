-- SEC-11 : refonte de « se souvenir de moi ».
--
-- Avant : une colonne users.remember_token portant un hash bcrypt. Pour
-- identifier le porteur d'un cookie, il fallait lire tous les comptes munis
-- d'un jeton et calculer un bcrypt sur chacun : une requete anonyme pouvait
-- occuper le serveur plusieurs minutes.
--
-- Apres : une table dediee, un couple selecteur + verificateur. Le selecteur
-- est un identifiant public indexe : une lecture par cle unique, puis une
-- seule comparaison. Chaque appareil a sa ligne, donc sa revocation.
--
-- Le verificateur est stocke en SHA-256 et non en bcrypt : il s'agit d'une
-- valeur aleatoire de 32 octets, pas d'un mot de passe choisi par un humain.
-- Il n'y a rien a ralentir, et le calcul doit rester negligeable pour ne pas
-- recreer le deni de service que cette migration corrige.
--
-- Application :
--   mysql -u <utilisateur> -p <base> < database/migrations/2026-09-22-sec11-remember-tokens.sql

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  -- Selecteur : partie publique du cookie, sert de cle de recherche.
  `selector` char(32) NOT NULL,
  -- Verificateur : SHA-256 hexadecimal de la partie secrete du cookie.
  `validator_hash` char(64) NOT NULL,
  -- Verificateur precedent, accepte pendant quelques secondes apres une
  -- rotation : un navigateur envoie souvent plusieurs requetes en parallele
  -- avec le meme cookie, et seule la derniere reponse fixe le nouveau.
  `previous_validator_hash` char(64) DEFAULT NULL,
  `rotated_at` datetime DEFAULT NULL,
  `device_label` varchar(120) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  -- Session ouverte depuis cet appareil, pour que fermer une session depuis
  -- l'ecran « Appareils connectes » retire aussi sa connexion automatique.
  -- Sans ce lien, l'appareil ecarte revenait des la requete suivante.
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
-- selecteur ni date d'expiration. Les personnes concernees se reconnectent
-- une fois. La colonne disparait pour qu'aucun code ne puisse y revenir.
ALTER TABLE `users` DROP COLUMN `remember_token`;
