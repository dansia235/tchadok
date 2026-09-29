-- Part des artistes dans les abonnements Premium.
--
-- DECISION DU 28/09/2026 : le pourcentage du revenu d'abonnement reverse aux
-- artistes se regle depuis la console d'administration, et pourra changer.
-- Valeur proposee au depart : 70 % (usage du marche du streaming : environ
-- 70 % aux ayants droit, 30 % pour la plateforme, qui porte les frais de
-- paiement, l'hebergement et l'ecoute).
--
-- TAUX (revenue_share_settings)
--   Un historique, pas une valeur ecrasee : chaque taux a une date d'effet
--   (premier jour d'un mois), un motif et un auteur. On ajoute une ligne,
--   on ne modifie ni ne supprime (declencheurs). Le taux d'un mois est celui
--   en vigueur au premier jour de ce mois.
--
-- REPARTITION (subscription_distributions)
--   Mensuelle, cloturee par la finance. Modele « centre sur l'abonne » : la
--   part d'un abonne va aux artistes QU'IL a ecoutes, au prorata de SES
--   ecoutes. Une ecoute fabriquee ne detourne donc que l'abonnement de celui
--   qui la fabrique. La part d'un abonne sans ecoute du mois reste a la
--   plateforme, et le montant est affiche.
--   Chaque ligne artiste devient un ajustement de son solde
--   (artist_adjustments) : elle suit ensuite le circuit des versements.
--   Un mois cloture ne se recalcule pas.

-- UP

CREATE TABLE IF NOT EXISTS `revenue_share_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope` enum('subscription') NOT NULL DEFAULT 'subscription',
  `artist_rate` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `reason` varchar(300) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vigueur` (`scope`, `effective_from`, `id`),
  CONSTRAINT `revenue_share_settings_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `taux_borne` CHECK (`artist_rate` >= 0 AND `artist_rate` <= 100),
  CONSTRAINT `taux_motive` CHECK (CHAR_LENGTH(TRIM(`reason`)) >= 5),
  CONSTRAINT `taux_debut_de_mois` CHECK (DAYOFMONTH(`effective_from`) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `part_artistes_sans_modification`;
DELIMITER //
CREATE TRIGGER `part_artistes_sans_modification` BEFORE UPDATE ON `revenue_share_settings` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'revenue_share_settings est un historique : ajouter un nouveau taux, ne pas modifier.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `part_artistes_sans_suppression`;
DELIMITER //
CREATE TRIGGER `part_artistes_sans_suppression` BEFORE DELETE ON `revenue_share_settings` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'revenue_share_settings est un historique : aucune suppression.';
END//
DELIMITER ;

-- Taux propose au depart, en vigueur depuis le debut du mois de la decision.
INSERT INTO `revenue_share_settings` (`scope`, `artist_rate`, `effective_from`, `reason`)
SELECT 'subscription', 70.00, '2026-09-01', 'Proposition initiale (28/09/2026) : 70 % aux artistes, modifiable depuis la console'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `revenue_share_settings` WHERE `scope` = 'subscription');

CREATE TABLE IF NOT EXISTS `subscription_distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period` char(7) NOT NULL,
  `artist_rate` decimal(5,2) NOT NULL,
  `revenue` decimal(12,2) NOT NULL,
  `pool` decimal(12,2) NOT NULL,
  `distributed` decimal(12,2) NOT NULL,
  `unallocated` decimal(12,2) NOT NULL,
  `subscribers` int(11) NOT NULL,
  `listeners` int(11) NOT NULL,
  `streams` int(11) NOT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `periode` (`period`),
  CONSTRAINT `subscription_distributions_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repartition_equilibree` CHECK (ABS(`distributed` + `unallocated` - `pool`) < 0.01)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `subscription_distribution_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_id` int(11) NOT NULL,
  `artist_id` int(11) NOT NULL,
  `streams` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `adjustment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ligne` (`distribution_id`, `artist_id`),
  CONSTRAINT `subscription_distribution_lines_ibfk_1` FOREIGN KEY (`distribution_id`) REFERENCES `subscription_distributions` (`id`),
  CONSTRAINT `subscription_distribution_lines_ibfk_2` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`),
  CONSTRAINT `subscription_distribution_lines_ibfk_3` FOREIGN KEY (`adjustment_id`) REFERENCES `artist_adjustments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `repartition_close`;
DELIMITER //
CREATE TRIGGER `repartition_close` BEFORE UPDATE ON `subscription_distributions` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repartition cloturee : elle ne se modifie plus.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `repartition_sans_suppression`;
DELIMITER //
CREATE TRIGGER `repartition_sans_suppression` BEFORE DELETE ON `subscription_distributions` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repartition cloturee : aucune suppression.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `lignes_repartition_figees`;
DELIMITER //
CREATE TRIGGER `lignes_repartition_figees` BEFORE UPDATE ON `subscription_distribution_lines` FOR EACH ROW
BEGIN
    IF NOT (OLD.adjustment_id IS NULL AND NEW.adjustment_id IS NOT NULL
            AND NEW.amount = OLD.amount AND NEW.artist_id = OLD.artist_id AND NEW.streams = OLD.streams) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ligne de repartition figee.';
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `lignes_repartition_sans_suppression`;
DELIMITER //
CREATE TRIGGER `lignes_repartition_sans_suppression` BEFORE DELETE ON `subscription_distribution_lines` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ligne de repartition : aucune suppression.';
END//
DELIMITER ;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `subscription_distributions`) OR (SELECT COUNT(*) FROM `revenue_share_settings`) > 1,
    'SELECT 1 FROM `annulation_impossible__des_repartitions_ou_taux_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DROP TRIGGER IF EXISTS `lignes_repartition_sans_suppression`;
DROP TRIGGER IF EXISTS `lignes_repartition_figees`;
DROP TRIGGER IF EXISTS `repartition_sans_suppression`;
DROP TRIGGER IF EXISTS `repartition_close`;
DROP TABLE IF EXISTS `subscription_distribution_lines`;
DROP TABLE IF EXISTS `subscription_distributions`;
DROP TRIGGER IF EXISTS `part_artistes_sans_suppression`;
DROP TRIGGER IF EXISTS `part_artistes_sans_modification`;
DROP TABLE IF EXISTS `revenue_share_settings`;
