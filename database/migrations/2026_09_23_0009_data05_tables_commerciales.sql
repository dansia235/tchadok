-- DATA-05 : les tables commerciales.
--
-- La plateforme annonce des ventes sans rien pour les enregistrer. La seule
-- table existante, `purchases`, est lue par quatre ecrans et n'est ecrite par
-- personne : le seul code qui l'ecrivait, includes/payment.php, ne se charge
-- meme pas (erreur de syntaxe). Vendre exige une commande, une facture, un
-- droit d'acces, un versement, et la trace de ce que l'operateur a repondu.
--
-- CE QUI NE SE NEGOCIE PAS
--   1. `gateway_ref` UNIQUE sur les commandes et les tentatives. Les operateurs
--      mobile money rejouent leurs callbacks : sans cette cle, un paiement se
--      compte deux fois. L'unicite est portee par la BASE, pas par un test
--      applicatif qui perd la course entre deux requetes simultanees.
--   2. `unit_price` ET `commission_rate` figes sur chaque ligne de commande. Un
--      changement de tarif (DATA-04) ne doit jamais reecrire l'historique :
--      l'artiste doit retrouver ce qu'on lui avait promis le jour de la vente.
--   3. `payment_events` immuable. C'est la preuve en cas de litige avec un
--      operateur ou un artiste ; une preuve modifiable n'est pas une preuve.
--      Des declencheurs refusent UPDATE et DELETE, quels que soient les droits
--      du compte applicatif.
--   4. Un versement n'est jamais approuve par celui qui l'a prepare. La
--      contrainte le refuse au niveau de la base.
--   5. Numerotation de facture sequentielle et sans trou (obligation
--      comptable), d'ou le compteur dedie plutot qu'un AUTO_INCREMENT, qui
--      saute des valeurs des qu'une transaction echoue.

-- UP

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gateway_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'XAF',
  `status` enum('cart','awaiting_payment','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'cart',
  `payment_method` varchar(30) DEFAULT NULL,
  -- Reference de l'operateur : unique, donc rejouable sans dommage.
  `gateway_ref` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `invoice_number` varchar(30) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refund_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `gateway_ref` (`gateway_ref`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `acheteur` (`user_id`, `status`),
  KEY `paiement` (`paid_at`),
  -- Supprimer un compte ne doit pas effacer une ecriture comptable : la base
  -- refuse. DATA-06 remplacera la suppression par une anonymisation.
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_type` enum('track','release','subscription') NOT NULL,
  `item_id` int(11) NOT NULL,
  `artist_id` int(11) DEFAULT NULL,
  `label` varchar(200) DEFAULT NULL,
  -- Figes au moment de la vente. Ne jamais recalculer depuis pricing_rules.
  `unit_price` decimal(8,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `commission_rate` decimal(4,2) NOT NULL,
  `commission` decimal(8,2) NOT NULL,
  `artist_net` decimal(8,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `article_unique` (`order_id`, `item_type`, `item_id`),
  KEY `artiste` (`artist_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `entitlements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_type` enum('track','release') NOT NULL,
  `item_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `source` enum('purchase','subscription','gift','promo') NOT NULL,
  `downloads_used` int(11) NOT NULL DEFAULT 0,
  `max_downloads` int(11) NOT NULL DEFAULT 5,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Un meme droit ne s'accorde pas deux fois par la meme voie : un callback
  -- rejoue ne doit pas doubler le quota de telechargement.
  UNIQUE KEY `droit_unique` (`user_id`, `item_type`, `item_id`, `source`),
  KEY `ligne` (`order_item_id`),
  KEY `expiration` (`expires_at`),
  CONSTRAINT `entitlements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `entitlements_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission` decimal(10,2) NOT NULL DEFAULT 0.00,
  `adjustments` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'XAF',
  `method` enum('airtel_money','moov_money','bank_transfer') NOT NULL,
  -- Numero ou IBAN. Chiffre au repos par l'application (SEC-20 fournit
  -- l'AES-256-GCM) : la colonne accueille le cryptogramme, pas le clair.
  `destination` varchar(255) NOT NULL,
  `status` enum('draft','approved','processing','paid','failed','on_hold') NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `statement_url` varchar(255) DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `periode_unique` (`artist_id`, `period_start`, `period_end`),
  KEY `redacteur` (`created_by`),
  KEY `approbateur` (`approved_by`),
  -- Aucun versement ne doit pouvoir etre declenche par une seule personne.
  CONSTRAINT `double_validation` CHECK (`approved_by` IS NULL OR `created_by` IS NULL OR `approved_by` <> `created_by`),
  CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payouts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payment_intents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  -- Une commande peut donner lieu a plusieurs tentatives : reseau coupe,
  -- solde insuffisant, mauvais code. Chacune est tracee.
  `attempt` int(11) NOT NULL DEFAULT 1,
  `gateway` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'XAF',
  `status` enum('created','pending','succeeded','failed','expired','cancelled') NOT NULL DEFAULT 'created',
  `gateway_ref` varchar(100) DEFAULT NULL,
  `msisdn` varchar(30) DEFAULT NULL,
  `error_code` varchar(60) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tentative_unique` (`order_id`, `attempt`),
  UNIQUE KEY `gateway_ref` (`gateway_ref`),
  KEY `etat` (`status`),
  CONSTRAINT `payment_intents_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `intent_id` int(11) DEFAULT NULL,
  `gateway` varchar(30) NOT NULL,
  `direction` enum('request','response','callback','reconciliation') NOT NULL,
  `event_type` varchar(60) DEFAULT NULL,
  `http_status` smallint(6) DEFAULT NULL,
  -- Corps brut echange avec l'operateur, tel qu'il est passe sur le fil.
  `payload` text DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `signature_valid` tinyint(1) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `commande` (`order_id`),
  KEY `tentative` (`intent_id`),
  KEY `horodatage` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Compteur de facture. AUTO_INCREMENT ne convient pas : il saute des valeurs
-- des qu'une transaction echoue, et la comptabilite exige une suite continue.
CREATE TABLE IF NOT EXISTS `invoice_counters` (
  `year` smallint(6) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- `purchases` disparait. Elle n'etait ecrite par personne -- son seul
-- ecrivain, includes/payment.php, ne se charge meme pas -- donc les quatre
-- ecrans qui la lisaient affichaient toujours zero. Ils lisent desormais
-- `orders` et `order_items` : dashboard artiste, dashboard membre,
-- portefeuille ; et includes/media-access.php lit `entitlements`.
--
-- POURQUOI PAS UNE VUE DE COMPATIBILITE
--   C'etait le plan. Mais la migration 0001, photographie du schema, cree un
--   declencheur SUR `purchases`. Un declencheur ne se pose pas sur une vue :
--   rejouer les migrations depuis un registre vide -- le scenario de reprise
--   que DATA-01 garantit -- echouait alors avec « purchases is not of type
--   BASE TABLE ». Supprimer la table laisse 0001 la recreer, vide et inoffensive,
--   avant que cette migration ne la retire de nouveau. La rejouabilite est
--   preservee sans toucher a une migration deja appliquee ailleurs.
DROP VIEW IF EXISTS `purchases`;
DROP TABLE IF EXISTS `purchases`;

-- Le declencheur `update_purchase_stats` vivait sur cette table et disparait
-- avec elle. Il comptait la vente a l'INSERT, ce qui etait faux : une commande
-- creee n'est pas une commande payee. Le compteur suit desormais le PASSAGE
-- A « paid ».
DROP TRIGGER IF EXISTS `update_purchase_stats`;
DROP TRIGGER IF EXISTS `compter_vente_payee`;

DELIMITER $$
CREATE TRIGGER `compter_vente_payee` AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
        UPDATE `tracks` t
           SET t.total_sales = t.total_sales + 1
         WHERE t.id IN (SELECT item_id FROM `order_items`
                         WHERE order_id = NEW.id AND item_type = 'track');

        UPDATE `releases` r
           SET r.total_sales = r.total_sales + 1
         WHERE r.id IN (SELECT item_id FROM `order_items`
                         WHERE order_id = NEW.id AND item_type = 'release');

        UPDATE `artists` a
           SET a.total_sales = a.total_sales + (
                   SELECT COALESCE(SUM(oi.artist_net), 0) FROM `order_items` oi
                    WHERE oi.order_id = NEW.id AND oi.artist_id = a.id
               )
         WHERE a.id IN (SELECT artist_id FROM `order_items`
                         WHERE order_id = NEW.id AND artist_id IS NOT NULL);
    END IF;
END$$
DELIMITER ;

-- Immuabilite de la preuve. Ces deux declencheurs refusent la modification et
-- la suppression quels que soient les droits du compte connecte -- y compris
-- root en local, ou une injection SQL qui aurait franchi tout le reste.
DROP TRIGGER IF EXISTS `payment_events_sans_modification`;
DROP TRIGGER IF EXISTS `payment_events_sans_suppression`;

DELIMITER $$
CREATE TRIGGER `payment_events_sans_modification` BEFORE UPDATE ON `payment_events`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'payment_events est immuable : aucune modification autorisee.';
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `payment_events_sans_suppression` BEFORE DELETE ON `payment_events`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'payment_events est immuable : aucune suppression autorisee.';
END$$
DELIMITER ;

-- DOWN

DROP TRIGGER IF EXISTS `payment_events_sans_modification`;
DROP TRIGGER IF EXISTS `payment_events_sans_suppression`;
DROP TRIGGER IF EXISTS `compter_vente_payee`;
DROP VIEW IF EXISTS `purchases`;

CREATE TABLE IF NOT EXISTS `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_type` enum('track','album') NOT NULL,
  `item_id` int(11) NOT NULL,
  `artist_id` int(11) NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `commission` decimal(8,2) NOT NULL,
  `payment_method` enum('airtel_money','moov_money','ecobank','visa','gimac','wallet') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_fee` decimal(8,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'XAF',
  `download_count` int(11) DEFAULT 0,
  `max_downloads` int(11) DEFAULT 5,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `artist_id` (`artist_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Le declencheur d'origine revient avec sa table : annuler une migration doit
-- rendre le schema tel qu'il etait, declencheurs compris.
DROP TRIGGER IF EXISTS `update_purchase_stats`;

DELIMITER $$
CREATE TRIGGER `update_purchase_stats` AFTER INSERT ON `purchases` FOR EACH ROW BEGIN
    IF NEW.item_type = 'track' THEN
        UPDATE tracks SET total_sales = total_sales + 1 WHERE id = NEW.item_id;
    ELSEIF NEW.item_type = 'album' THEN
        UPDATE albums SET total_sales = total_sales + 1 WHERE id = NEW.item_id;
    END IF;
    UPDATE artists SET total_sales = total_sales + NEW.amount WHERE id = NEW.artist_id;
END$$
DELIMITER ;

DROP TABLE IF EXISTS `invoice_counters`;
DROP TABLE IF EXISTS `payment_events`;
DROP TABLE IF EXISTS `payment_intents`;
DROP TABLE IF EXISTS `entitlements`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `payouts`;
DROP TABLE IF EXISTS `orders`;
