-- SHOP-07 : remboursements et reclamations.
--
-- UNE ECRITURE D'ANNULATION, JAMAIS UNE REECRITURE
--   La commande et ses lignes d'origine restent intactes : elles prouvent la
--   vente. Le remboursement est une ecriture a part (`refunds`), detaillee
--   par ligne (`refund_items`) avec la part artiste et la commission
--   reprises.
--
-- LA PART DE L'ARTISTE
--   `artist_net_reversed` est la somme a retirer de ses revenus. Si le
--   versement de la periode a deja eu lieu (`after_payout` = 1), elle devient
--   un ajustement negatif du versement suivant (LOT 8) : l'argent deja verse
--   ne se reclame pas, il se compense.
--
-- CYCLE
--   demande     reclamation du client, en attente d'une decision
--   refusee     decision negative, motivee
--   en_cours    remboursement demande a l'operateur, confirmation attendue
--   effectue    confirme par l'operateur (callback refund.succeeded ou reponse)
--   manuel      l'operateur ne permet pas le remboursement en ligne :
--               a faire a la main (virement, mobile money), puis a cloturer
--   echoue      l'operateur a refuse ; a reprendre
-- Un remboursement se decide avec un motif (contrainte en base).

-- UP

CREATE TABLE IF NOT EXISTS `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `intent_id` int(11) DEFAULT NULL,
  `status` enum('demande','refusee','en_cours','effectue','manuel','echoue') NOT NULL,
  `amount_xaf` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'XAF',
  `gateway` varchar(30) DEFAULT NULL,
  `gateway_refund_ref` varchar(100) DEFAULT NULL,
  `customer_reason` varchar(40) DEFAULT NULL,
  `customer_message` varchar(1000) DEFAULT NULL,
  `decision_reason` varchar(500) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failure_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `commande` (`order_id`),
  KEY `etat` (`status`, `created_at`),
  KEY `operateur` (`gateway`, `gateway_refund_ref`),
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refunds_ibfk_3` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  -- Toute decision (hors simple demande) porte un motif.
  CONSTRAINT `decision_motivee` CHECK (`status` = 'demande' OR CHAR_LENGTH(TRIM(COALESCE(`decision_reason`, ''))) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `refund_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refund_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `artist_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `commission_reversed` decimal(10,2) NOT NULL,
  `artist_net_reversed` decimal(10,2) NOT NULL,
  `after_payout` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ligne_unique` (`refund_id`, `order_item_id`),
  KEY `artiste` (`artist_id`),
  CONSTRAINT `refund_items_ibfk_1` FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`),
  CONSTRAINT `refund_items_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN

DROP TABLE IF EXISTS `refund_items`;
DROP TABLE IF EXISTS `refunds`;
