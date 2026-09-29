-- SHOP-05 : journal des fichiers servis, et base de la limitation par compte.
--
-- Chaque OUVERTURE d'un fichier par media.php est inscrite : qui, quel titre,
-- quel type (integral ou extrait), a quel titre (achat, abonnement, gratuit),
-- depuis quelle adresse. Les requetes partielles d'une meme lecture (avance
-- rapide, reprise) ne sont pas comptees : seule la premiere l'est.
--
-- Deux usages :
--   - repondre a « qui a ecoute ou recupere quoi » en cas de litige ou de
--     fuite d'un titre ;
--   - limiter le nombre d'ouvertures PAR COMPTE (par adresse pour un
--     visiteur) : une aspiration du catalogue ouvre des centaines de titres
--     en quelques minutes, une ecoute humaine non. Par compte, et non par
--     adresse seulement : un cybercafe partage une meme adresse.
--
-- Conservation : 90 jours (docs/exploitation/conservation.md).

-- UP

CREATE TABLE IF NOT EXISTS `media_access_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `track_id` int(11) NOT NULL,
  `media_type` varchar(20) NOT NULL,
  `grant_reason` varchar(30) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `compte` (`user_id`, `created_at`),
  KEY `adresse` (`ip_address`, `created_at`),
  KEY `titre` (`track_id`, `created_at`),
  KEY `anciennete` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN

DROP TABLE IF EXISTS `media_access_log`;
