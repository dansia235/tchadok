-- PAY-10 : rapprochement quotidien avec les releves des operateurs.
--
-- Sans rapprochement, un ecart entre ce que l'operateur a encaisse et ce que
-- Tchadok a enregistre passe inapercu jusqu'au litige -- avec un artiste qui
-- reclame ses ventes, ou un client debite sans avoir recu son achat.
--
--   reconciliation_runs           une execution : passerelle, jour, bilan
--   reconciliation_discrepancies  un ecart, jusqu'a sa cloture MOTIVEE
--
-- Types d'ecart :
--   operateur_sans_commande     argent recu, aucune tentative Tchadok
--   commande_sans_encaissement  tentative reussie cote Tchadok, absente du releve
--   ecart_montant               montant ou devise differents
--   statut_divergent            l'operateur et Tchadok ne disent pas la meme chose
--                               (ex. argent pris, commande non livree)
--   doublon                     une meme tentative encaissee plusieurs fois
--
-- Un ecart ne se supprime pas : il se clot, avec un motif et un auteur.

-- UP

CREATE TABLE IF NOT EXISTS `reconciliation_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(30) NOT NULL,
  `statement_date` date NOT NULL,
  `status` enum('ok','ecarts','erreur') NOT NULL,
  `operator_lines` int(11) NOT NULL DEFAULT 0,
  `platform_lines` int(11) NOT NULL DEFAULT 0,
  `discrepancies` int(11) NOT NULL DEFAULT 0,
  `error_message` varchar(500) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jour` (`statement_date`, `gateway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reconciliation_discrepancies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `gateway` varchar(30) NOT NULL,
  `statement_date` date NOT NULL,
  `type` enum('operateur_sans_commande','commande_sans_encaissement','ecart_montant','statut_divergent','doublon') NOT NULL,
  `gateway_ref` varchar(100) DEFAULT NULL,
  `intent_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `operator_amount` bigint(20) DEFAULT NULL,
  `platform_amount` bigint(20) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `detail` varchar(500) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ouverts` (`resolved_at`, `statement_date`),
  KEY `reference` (`gateway`, `gateway_ref`),
  KEY `execution` (`run_id`),
  CONSTRAINT `reconciliation_discrepancies_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `reconciliation_runs` (`id`),
  CONSTRAINT `reconciliation_discrepancies_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  -- Une cloture sans motif n'en est pas une.
  CONSTRAINT `cloture_motivee` CHECK (`resolved_at` IS NULL OR (`resolution` IS NOT NULL AND CHAR_LENGTH(TRIM(`resolution`)) >= 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DOWN

DROP TABLE IF EXISTS `reconciliation_discrepancies`;
DROP TABLE IF EXISTS `reconciliation_runs`;
