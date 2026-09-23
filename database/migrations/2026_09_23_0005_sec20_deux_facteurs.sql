-- SEC-20 : authentification a deux facteurs reelle.
--
-- L'ecran precedent (retire en SEC-14) n'enregistrait rien : il affichait un
-- QR code construit sur un secret d'exemple public, identique pour tous, et
-- envoye a un service tiers. La protection paraissait active sans l'etre.
--
-- Deux tables :
--   user_2fa_settings  le secret, chiffre, et le dernier pas de temps utilise
--   user_backup_codes  dix codes de secours, a usage unique
--
-- last_step est la protection contre le rejeu : un code reste valable trente
-- secondes, et rien n'empeche de le reutiliser dans cet intervalle. En
-- refusant tout pas inferieur ou egal au dernier accepte, un code capte ne
-- sert qu'une fois.

-- UP

CREATE TABLE IF NOT EXISTS `user_2fa_settings` (
  `user_id` int(11) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'totp',
  -- Secret chiffre (AES-256-GCM, cle derivee de APP_KEY). Une base volee ne
  -- doit pas livrer les seconds facteurs.
  `secret_chiffre` text NOT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `last_step` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_2fa_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_backup_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  -- SHA-256 et non bcrypt : un code de secours est une valeur aleatoire de
  -- dix caracteres, pas un mot de passe choisi par un humain. Il n'y a rien a
  -- ralentir, et verifier dix bcrypt a chaque essai couterait cher pour rien.
  `code_hash` char(64) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur` (`user_id`, `used_at`),
  CONSTRAINT `user_backup_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN

DROP TABLE IF EXISTS `user_backup_codes`;
DROP TABLE IF EXISTS `user_2fa_settings`;
