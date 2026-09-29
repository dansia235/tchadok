-- STAT-06 : revocation d'ecoutes certifiees.
--
-- Une fraude peut n'etre decouverte qu'apres certification (ferme a clics
-- reperee trois jours plus tard). `streams_certified` reste immuable : la
-- correction est une REVOCATION, elle aussi immuable, motivee et tracee. Les
-- agregats, les compteurs et la repartition des abonnements ignorent les
-- ecoutes revoquees ; le recalcul du jour fait baisser les compteurs.

-- UP

CREATE TABLE IF NOT EXISTS `stream_revocations` (
  `stream_id` bigint(20) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `revoked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stream_id`),
  KEY `moment` (`revoked_at`),
  CONSTRAINT `stream_revocations_ibfk_1` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revocation_motivee` CHECK (CHAR_LENGTH(TRIM(`reason`)) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `revocations_sans_modification`;
DELIMITER //
CREATE TRIGGER `revocations_sans_modification` BEFORE UPDATE ON `stream_revocations` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stream_revocations est un journal : aucune modification.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `revocations_sans_suppression`;
DELIMITER //
CREATE TRIGGER `revocations_sans_suppression` BEFORE DELETE ON `stream_revocations` FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM `streams_certified` WHERE `stream_id` = OLD.stream_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stream_revocations est un journal : aucune suppression.';
    END IF;
END//
DELIMITER ;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `stream_revocations`),
    'SELECT 1 FROM `annulation_impossible__des_revocations_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DROP TRIGGER IF EXISTS `revocations_sans_suppression`;
DROP TRIGGER IF EXISTS `revocations_sans_modification`;
DROP TABLE IF EXISTS `stream_revocations`;
