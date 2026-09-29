-- PAY-07 : paiement en dollar US pour la diaspora.
--
-- DECISION DU 28/09/2026
--   Les clients hors zone franc paient en dollar US (USD), par carte VISA.
--   Les portefeuilles mobile money de la CEMAC restent en XAF.
--
-- LA COMPTABILITE RESTE EN XAF
--   Les prix, les commandes, les factures et les revenus des artistes sont en
--   francs CFA. Le dollar n'existe qu'au moment du paiement : la tentative
--   porte le montant debite en USD, le TAUX applique et le montant XAF qu'il
--   represente. Un changement de taux n'affecte donc jamais un paiement en
--   cours, ni la part revenant a l'artiste.
--
-- TAUX INITIAL
--   1 USD = 600 XAF, choisi le 28/09/2026 comme valeur ronde proche du cours.
--   Il se modifie sans deploiement : scripts/devises.php, trace au journal
--   d'audit. Aucune ligne n'est jamais modifiee ni supprimee : un nouveau taux
--   est une nouvelle ligne datee, l'historique reste lisible pour les litiges.
--
-- ARRONDI
--   Au cent SUPERIEUR : 1 500 XAF / 600 = 2,50 USD ; 1 000 XAF / 600 =
--   1,666... -> 1,67 USD. La plateforme ne percoit jamais moins que le prix
--   en francs, et l'ecart ne depasse pas un cent.

-- UP

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `currency` char(3) NOT NULL,
  `xaf_per_unit` decimal(12,4) NOT NULL,
  `active_from` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(300) DEFAULT NULL,
  `set_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `en_vigueur` (`currency`, `active_from`),
  CONSTRAINT `exchange_rates_ibfk_1` FOREIGN KEY (`set_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `taux_positif` CHECK (`xaf_per_unit` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exchange_rates` (`currency`, `xaf_per_unit`, `active_from`, `reason`)
SELECT 'USD', 600.0000, '2026-09-28 00:00:00', 'Taux initial (decision du 28/09/2026)'
 WHERE NOT EXISTS (SELECT 1 FROM `exchange_rates` WHERE `currency` = 'USD');

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'payment_intents' AND column_name = 'exchange_rate'),
    'DO 0',
    'ALTER TABLE `payment_intents`
       ADD COLUMN `amount_xaf` decimal(10,2) DEFAULT NULL AFTER `currency`,
       ADD COLUMN `exchange_rate` decimal(12,4) DEFAULT NULL AFTER `amount_xaf`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Tentatives anterieures : toutes en XAF.
UPDATE `payment_intents` SET `amount_xaf` = `amount` WHERE `amount_xaf` IS NULL AND `currency` = 'XAF';

-- Historique immuable, comme payment_events.
DROP TRIGGER IF EXISTS `taux_sans_modification`;
DELIMITER //
CREATE TRIGGER `taux_sans_modification` BEFORE UPDATE ON `exchange_rates` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'exchange_rates est un historique : ajouter un nouveau taux, ne pas modifier.';
END//
DELIMITER ;

DROP TRIGGER IF EXISTS `taux_sans_suppression`;
DELIMITER //
CREATE TRIGGER `taux_sans_suppression` BEFORE DELETE ON `exchange_rates` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'exchange_rates est un historique : aucune suppression.';
END//
DELIMITER ;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `payment_intents` WHERE `currency` <> 'XAF'),
    'SELECT 1 FROM `annulation_impossible__des_tentatives_sont_en_devise`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DROP TRIGGER IF EXISTS `taux_sans_suppression`;
DROP TRIGGER IF EXISTS `taux_sans_modification`;
ALTER TABLE `payment_intents` DROP COLUMN `exchange_rate`, DROP COLUMN `amount_xaf`;
DROP TABLE IF EXISTS `exchange_rates`;
