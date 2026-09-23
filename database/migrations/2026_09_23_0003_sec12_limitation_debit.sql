-- SEC-12 : limitation de debit et verrouillage de compte.
--
-- Avant : rien. Un mot de passe pouvait etre essaye indefiniment, et chaque
-- point d'entree public acceptait autant de requetes que le client voulait en
-- envoyer.
--
-- login_attempts porte l'historique des tentatives de connexion. Le verrou
-- s'applique au couple identifiant + adresse : verrouiller sur le seul
-- identifiant permettrait de bloquer le compte de n'importe qui a distance, en
-- se trompant de mot de passe a sa place. Cette table sert aussi d'historique
-- presente a l'utilisateur (SEC-14), d'ou une conservation de 90 jours.
--
-- rate_limit_hits compte les requetes par action et par adresse, en fenetre
-- glissante. Une ligne par requete comptee, purgee automatiquement.

-- UP

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  -- Identifiant saisi (email ou nom d'utilisateur), en minuscules. Ce n'est
  -- pas necessairement un compte existant : c'est ce que le client a envoye.
  `identifier` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `identifiant_date` (`identifier`, `created_at`),
  KEY `adresse_date` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `rate_limit_hits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  -- Nom de l'action limitee : inscription, contact, recherche, ecoute...
  `bucket` varchar(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fenetre` (`bucket`, `ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN

DROP TABLE IF EXISTS `rate_limit_hits`;
DROP TABLE IF EXISTS `login_attempts`;
