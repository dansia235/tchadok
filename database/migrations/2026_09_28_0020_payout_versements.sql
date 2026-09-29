-- LOT 8 : versements aux artistes.
--
-- DECISION DU 28/09/2026 : la plateforme encaisse, puis reverse a l'artiste
-- QUAND IL LE DEMANDE (et non par virement automatique).
--
-- SOLDE (PAYOUT-01)
--   Calcule sur `order_items.artist_net`, fige a la vente -- jamais sur la
--   grille courante. Une vente remboursee ou contestee sort du calcul ; si
--   elle avait deja ete versee, le solde devient negatif et se compense au
--   versement suivant. Retention de 30 jours avant qu'une vente soit
--   versable. `artist_adjustments` : corrections, avances, retenues --
--   motivees, immuables.
--
-- DEMANDE ET VALIDATION (PAYOUT-02)
--   L'artiste demande (created_by), la finance valide (approved_by), une
--   AUTRE personne execute (executed_by). Les trois contraintes vivent en
--   base : aucun code ne peut les contourner.
--
-- EXECUTION (PAYOUT-03, PAYOUT-04)
--   Chaque essai aupres de l'operateur est une ligne de `payout_attempts`
--   avec sa cle d'idempotence : un versement en echec se reprend sans jamais
--   payer deux fois.
--
-- CONTRAT (PAYOUT-05)
--   Versions publiees du contrat de distribution, et acceptations
--   horodatees, immuables, opposables.

-- UP

CREATE TABLE IF NOT EXISTS `artist_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `artiste` (`artist_id`, `created_at`),
  CONSTRAINT `artist_adjustments_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`),
  CONSTRAINT `artist_adjustments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ajustement_motive` CHECK (CHAR_LENGTH(TRIM(`reason`)) >= 5 AND `amount` <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `ajustements_sans_modification`;
DELIMITER //
CREATE TRIGGER `ajustements_sans_modification` BEFORE UPDATE ON `artist_adjustments` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'artist_adjustments est un journal : ajouter une correction, ne pas modifier.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `ajustements_sans_suppression`;
DELIMITER //
CREATE TRIGGER `ajustements_sans_suppression` BEFORE DELETE ON `artist_adjustments` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'artist_adjustments est un journal : aucune suppression.';
END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `artist_payout_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `method` enum('airtel_money','moov_money') NOT NULL,
  `msisdn` varchar(20) NOT NULL,
  `holder_name` varchar(120) NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verification_note` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `artiste` (`artist_id`),
  CONSTRAINT `artist_payout_accounts_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`),
  CONSTRAINT `artist_payout_accounts_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Versements a la demande : plusieurs demandes peuvent couvrir la meme
-- journee (une demande refusee puis refaite). L'unicite par periode ne tient
-- plus ; l'index reste.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'payouts' AND index_name = 'periode_unique'),
    'ALTER TABLE `payouts` DROP INDEX `periode_unique`, ADD KEY `periode` (`artist_id`, `period_start`, `period_end`)',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

ALTER TABLE `payouts`
  MODIFY `status` enum('draft','approved','processing','paid','failed','on_hold','rejected') NOT NULL DEFAULT 'draft';

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payouts' AND column_name = 'executed_by'),
    'DO 0',
    'ALTER TABLE `payouts`
       ADD COLUMN `requested_at` datetime DEFAULT NULL AFTER `created_by`,
       ADD COLUMN `executed_by` int(11) DEFAULT NULL AFTER `approved_at`,
       ADD COLUMN `executed_at` datetime DEFAULT NULL AFTER `executed_by`,
       ADD COLUMN `decision_reason` varchar(500) DEFAULT NULL AFTER `failure_reason`,
       ADD KEY `etat` (`status`, `created_at`),
       ADD CONSTRAINT `payouts_executant` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
       ADD CONSTRAINT `execution_separee` CHECK (`executed_by` IS NULL OR `approved_by` IS NULL OR `executed_by` <> `approved_by`),
       ADD CONSTRAINT `execution_hors_demandeur` CHECK (`executed_by` IS NULL OR `created_by` IS NULL OR `executed_by` <> `created_by`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

CREATE TABLE IF NOT EXISTS `payout_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payout_id` int(11) NOT NULL,
  `attempt` int(11) NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `idempotency_key` char(32) NOT NULL,
  `gateway_ref` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
  `failure_code` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `essai` (`payout_id`, `attempt`),
  UNIQUE KEY `idempotence` (`idempotency_key`),
  UNIQUE KEY `operateur` (`gateway_ref`),
  CONSTRAINT `payout_attempts_ibfk_1` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `distribution_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` mediumtext NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `retired_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `contract_acceptances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `accepted_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `body_sha256` char(64) NOT NULL,
  `accepted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceptation` (`artist_id`, `contract_id`),
  CONSTRAINT `contract_acceptances_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`),
  CONSTRAINT `contract_acceptances_ibfk_2` FOREIGN KEY (`contract_id`) REFERENCES `distribution_contracts` (`id`),
  CONSTRAINT `contract_acceptances_ibfk_3` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `acceptations_sans_modification`;
DELIMITER //
CREATE TRIGGER `acceptations_sans_modification` BEFORE UPDATE ON `contract_acceptances` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_acceptances est une preuve : aucune modification.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `acceptations_sans_suppression`;
DELIMITER //
CREATE TRIGGER `acceptations_sans_suppression` BEFORE DELETE ON `contract_acceptances` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_acceptances est une preuve : aucune suppression.';
END//
DELIMITER ;

-- Un contrat publie ne change plus de texte : une nouvelle version s'ajoute.
DROP TRIGGER IF EXISTS `contrat_publie_fige`;
DELIMITER //
CREATE TRIGGER `contrat_publie_fige` BEFORE UPDATE ON `distribution_contracts` FOR EACH ROW
BEGIN
    IF OLD.published_at IS NOT NULL AND (NEW.body <> OLD.body OR NEW.version <> OLD.version OR NEW.title <> OLD.title) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Contrat publie : publier une nouvelle version, ne pas modifier le texte.';
    END IF;
END//
DELIMITER ;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `payouts` WHERE `status` = 'rejected' OR `executed_by` IS NOT NULL)
    OR EXISTS(SELECT 1 FROM `contract_acceptances`) OR EXISTS(SELECT 1 FROM `artist_adjustments`),
    'SELECT 1 FROM `annulation_impossible__des_versements_ou_acceptations_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DROP TRIGGER IF EXISTS `contrat_publie_fige`;
DROP TRIGGER IF EXISTS `acceptations_sans_suppression`;
DROP TRIGGER IF EXISTS `acceptations_sans_modification`;
DROP TABLE IF EXISTS `contract_acceptances`;
DROP TABLE IF EXISTS `distribution_contracts`;
DROP TABLE IF EXISTS `payout_attempts`;
-- MariaDB 10.4 : DROP CONSTRAINT (et non DROP CHECK, syntaxe MySQL).
ALTER TABLE `payouts`
  DROP CONSTRAINT `execution_hors_demandeur`,
  DROP CONSTRAINT `execution_separee`,
  DROP FOREIGN KEY `payouts_executant`,
  DROP INDEX `etat`,
  DROP COLUMN `decision_reason`,
  DROP COLUMN `executed_at`,
  DROP COLUMN `executed_by`,
  DROP COLUMN `requested_at`;
ALTER TABLE `payouts`
  MODIFY `status` enum('draft','approved','processing','paid','failed','on_hold') NOT NULL DEFAULT 'draft';
DROP TABLE IF EXISTS `artist_payout_accounts`;
DROP TRIGGER IF EXISTS `ajustements_sans_suppression`;
DROP TRIGGER IF EXISTS `ajustements_sans_modification`;
DROP TABLE IF EXISTS `artist_adjustments`;
