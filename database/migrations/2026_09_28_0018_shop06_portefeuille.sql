-- SHOP-06 : portefeuille prepaye.
--
-- POURQUOI
--   Une transaction mobile money coute du temps et des frais : pour un titre a
--   300 FCFA, c'est le principal frein a l'achat a l'unite. On recharge une
--   fois (1 000, 2 500, 5 000, 10 000 FCFA), puis on achete en un clic.
--
-- LE JOURNAL FAIT FOI
--   `wallet_transactions` n'accepte que des ajouts : chaque credit (+) ou
--   debit (-) avec le solde qui en resulte. `users.wallet_balance` n'est
--   qu'un CACHE, toujours recalculable depuis ce journal
--   (scripts/portefeuille.php verifier).
--
--   topup       rechargement paye par une passerelle (commande `wallet_topup`)
--   purchase    achat regle par le portefeuille (montant negatif)
--   refund      remboursement d'un achat regle par le portefeuille
--   adjustment  correction par l'equipe finance, motivee
--
-- `reference` est unique : un callback rejoue ne credite pas deux fois.

-- UP

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('topup','purchase','refund','adjustment') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `reference` varchar(80) NOT NULL,
  `note` varchar(300) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `membre` (`user_id`, `id`),
  KEY `commande` (`order_id`),
  CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `wallet_transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `solde_positif` CHECK (`balance_after` >= 0),
  CONSTRAINT `mouvement_non_nul` CHECK (`amount` <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `portefeuille_sans_modification`;
DELIMITER //
CREATE TRIGGER `portefeuille_sans_modification` BEFORE UPDATE ON `wallet_transactions` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'wallet_transactions est un journal : ajouter une correction, ne pas modifier.';
END//
DELIMITER ;

DROP TRIGGER IF EXISTS `portefeuille_sans_suppression`;
DELIMITER //
CREATE TRIGGER `portefeuille_sans_suppression` BEFORE DELETE ON `wallet_transactions` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'wallet_transactions est un journal : aucune suppression.';
END//
DELIMITER ;

-- Soldes existants : une ligne d'ouverture, pour que journal et cache
-- concordent des le depart.
INSERT IGNORE INTO `wallet_transactions` (`user_id`, `type`, `amount`, `balance_after`, `reference`, `note`)
SELECT `id`, 'adjustment', `wallet_balance`, `wallet_balance`, CONCAT('ouverture:', `id`), 'Solde d''ouverture (migration 0018)'
  FROM `users` WHERE `wallet_balance` > 0;

-- Un rechargement est une ligne de commande comme une autre.
ALTER TABLE `order_items`
  MODIFY `item_type` enum('track','release','subscription','wallet_topup') NOT NULL;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `order_items` WHERE `item_type` = 'wallet_topup')
    OR EXISTS(SELECT 1 FROM `wallet_transactions` WHERE `reference` NOT LIKE 'ouverture:%'),
    'SELECT 1 FROM `annulation_impossible__le_portefeuille_a_servi`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

ALTER TABLE `order_items`
  MODIFY `item_type` enum('track','release','subscription') NOT NULL;
DROP TRIGGER IF EXISTS `portefeuille_sans_suppression`;
DROP TRIGGER IF EXISTS `portefeuille_sans_modification`;
DROP TABLE IF EXISTS `wallet_transactions`;
