-- STAT-03 : filtrage anti-fraude et certification des ecoutes.
--
-- `streams` reste le journal BRUT. Chaque ecoute y recoit un verdict, une
-- seule fois (`stream_verdicts`) :
--   certifiee   -> copiee dans `streams_certified`, le SEUL fait qui alimente
--                  classements et remuneration ;
--   exclue      -> ecoute d'un artiste sur ses propres titres (regle STAT-01) ;
--   quarantaine -> signal de fraude : ni supprimee ni comptee, elle attend une
--                  decision humaine (validee -> certifiee, ou rejetee), motivee
--                  et tracee.
-- Volume certifie <= volume brut, toujours ; l'ecart est mesurable.
-- `streams_certified` est immuable (declencheurs) : une ecoute certifiee a tort
-- se corrige par une decision tracee, pas par une suppression discrete.

-- UP

CREATE TABLE IF NOT EXISTS `stream_verdicts` (
  `stream_id` bigint(20) NOT NULL,
  `verdict` enum('certifiee','exclue','quarantaine','rejetee') NOT NULL,
  `signals` varchar(500) DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`stream_id`),
  KEY `verdict` (`verdict`, `checked_at`),
  CONSTRAINT `stream_verdicts_ibfk_1` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stream_verdicts_ibfk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `decision_motivee` CHECK (`decided_at` IS NULL OR CHAR_LENGTH(TRIM(COALESCE(`decision_reason`, ''))) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `streams_certified` (
  `stream_id` bigint(20) NOT NULL,
  `track_id` int(11) NOT NULL,
  `artist_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `listener_key` varchar(80) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `duration_played` int(11) NOT NULL DEFAULT 0,
  `listened_at` datetime NOT NULL,
  `certified_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `via` enum('automatique','revue') NOT NULL DEFAULT 'automatique',
  PRIMARY KEY (`stream_id`),
  KEY `titre` (`track_id`, `listened_at`),
  KEY `artiste` (`artist_id`, `listened_at`),
  KEY `auditeur` (`user_id`, `listened_at`),
  KEY `moment` (`listened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `certifiees_sans_modification`;
DELIMITER //
CREATE TRIGGER `certifiees_sans_modification` BEFORE UPDATE ON `streams_certified` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'streams_certified est immuable : une correction passe par une decision tracee.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `certifiees_sans_suppression`;
DELIMITER //
-- Seule exception : le titre n'existe plus du tout (un titre retire du
-- catalogue n'est que marque, DATA-06 ; une suppression physique ne concerne
-- que les jeux d'essai).
CREATE TRIGGER `certifiees_sans_suppression` BEFORE DELETE ON `streams_certified` FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM `tracks` WHERE `id` = OLD.track_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'streams_certified est immuable : aucune suppression.';
    END IF;
END//
DELIMITER ;

-- STAT-04 : artiste ou compte sous surveillance -- toutes ses ecoutes passent
-- en revue humaine tant que la surveillance dure.
CREATE TABLE IF NOT EXISTS `stream_watchlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_type` enum('artiste','compte') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cible` (`target_type`, `target_id`, `lifted_at`),
  CONSTRAINT `stream_watchlist_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stream_watchlist_ibfk_2` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surveillance_motivee` CHECK (CHAR_LENGTH(TRIM(`reason`)) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Permission d'instruire les anomalies ; la consultation reste a
-- statistique.lire.
INSERT IGNORE INTO `permissions` (`slug`, `domaine`, `libelle`)
VALUES ('ecoute.moderer', 'mesure', 'Instruire les anomalies d''ecoute (quarantaine, surveillance)');
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.slug = 'ecoute.moderer'
 WHERE r.slug IN ('super_admin', 'admin_plateforme', 'moderateur_catalogue');

-- Un verdict automatique ne se reecrit pas ; seule une quarantaine peut
-- recevoir une decision, une fois.
DROP TRIGGER IF EXISTS `verdict_une_fois`;
DELIMITER //
CREATE TRIGGER `verdict_une_fois` BEFORE UPDATE ON `stream_verdicts` FOR EACH ROW
BEGIN
    IF NOT (OLD.verdict = 'quarantaine' AND NEW.verdict IN ('certifiee', 'rejetee') AND NEW.decided_at IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verdict definitif : seule une quarantaine recoit une decision, une fois.';
    END IF;
END//
DELIMITER ;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `streams_certified`) OR EXISTS(SELECT 1 FROM `stream_verdicts` WHERE `decided_at` IS NOT NULL),
    'SELECT 1 FROM `annulation_impossible__des_ecoutes_certifiees_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DELETE rp FROM `role_permissions` rp JOIN `permissions` p ON p.id = rp.permission_id WHERE p.slug = 'ecoute.moderer';
DELETE FROM `permissions` WHERE `slug` = 'ecoute.moderer';
DROP TABLE IF EXISTS `stream_watchlist`;
DROP TRIGGER IF EXISTS `verdict_une_fois`;
DROP TRIGGER IF EXISTS `certifiees_sans_suppression`;
DROP TRIGGER IF EXISTS `certifiees_sans_modification`;
DROP TABLE IF EXISTS `streams_certified`;
DROP TABLE IF EXISTS `stream_verdicts`;
