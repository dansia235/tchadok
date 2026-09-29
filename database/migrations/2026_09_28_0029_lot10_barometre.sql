-- LOT 10 : classements, barometre, certifications.
--
-- EDITIONS (CHART-01)
--   Un classement est ARRETE (hebdomadaire, mensuel, annuel) 48 h apres la
--   fin de sa periode, puis IMMUABLE : c'est ce qui le rend citable. Classements
--   separes : titres (ecoutes), titres (ventes), sorties par format, sorties
--   (ventes), artistes. Chaque entree porte rang, rang precedent, semaines de
--   presence, meilleur rang, nouvelle entree.
--   Suppression permise seulement pour une entree dont l'objet n'existe plus
--   du tout (jeux d'essai), et pour une edition vide.
--
-- CERTIFICATIONS (CHART-06)
--   Paliers Or, Platine, Diamant, sur les ecoutes certifiees et sur les
--   ventes, seuils publies et datables. Une certification decernee est
--   definitive.
--
-- API PUBLIQUE (CHART-04)
--   Lecture seule, cles (conservees en empreinte), quota quotidien.
--
-- L'ancienne table `charts` (jamais alimentee, aucun code ne la lit) est
-- remplacee.

-- UP

CREATE TABLE IF NOT EXISTS `chart_editions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_type` enum('weekly','monthly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `slug` varchar(20) NOT NULL,
  `methodology_version` varchar(10) NOT NULL,
  `streams_total` bigint(20) NOT NULL DEFAULT 0,
  `sales_total` int(11) NOT NULL DEFAULT 0,
  `arrete_at` datetime NOT NULL,
  `kit_generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `edition` (`period_type`, `period_start`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `chart_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `edition_id` int(11) NOT NULL,
  `chart` enum('titres','titres_ventes','sorties_single','sorties_maxi_single','sorties_ep','sorties_album','sorties_compilation','sorties_ventes','artistes') NOT NULL,
  `rank` int(11) NOT NULL,
  `item_type` enum('track','release','artist') NOT NULL,
  `item_id` int(11) NOT NULL,
  `value` bigint(20) NOT NULL,
  `listeners` int(11) NOT NULL DEFAULT 0,
  `previous_rank` int(11) DEFAULT NULL,
  `peak_rank` int(11) NOT NULL,
  `periods_on_chart` int(11) NOT NULL DEFAULT 1,
  `is_new` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rang` (`edition_id`, `chart`, `rank`),
  UNIQUE KEY `objet` (`edition_id`, `chart`, `item_id`),
  KEY `historique` (`chart`, `item_id`),
  CONSTRAINT `chart_entries_ibfk_1` FOREIGN KEY (`edition_id`) REFERENCES `chart_editions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `edition_figee`;
DELIMITER //
CREATE TRIGGER `edition_figee` BEFORE UPDATE ON `chart_editions` FOR EACH ROW
BEGIN
    IF NOT (NEW.period_type = OLD.period_type AND NEW.period_start = OLD.period_start AND NEW.period_end = OLD.period_end
            AND NEW.slug = OLD.slug AND NEW.streams_total = OLD.streams_total AND NEW.sales_total = OLD.sales_total
            AND NEW.arrete_at = OLD.arrete_at AND NEW.methodology_version = OLD.methodology_version) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Classement arrete : il ne change plus.';
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `edition_sans_suppression`;
DELIMITER //
CREATE TRIGGER `edition_sans_suppression` BEFORE DELETE ON `chart_editions` FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM `chart_entries` WHERE `edition_id` = OLD.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Classement arrete : aucune suppression.';
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `entree_figee`;
DELIMITER //
CREATE TRIGGER `entree_figee` BEFORE UPDATE ON `chart_entries` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Classement arrete : il ne change plus.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `entree_sans_suppression`;
DELIMITER //
CREATE TRIGGER `entree_sans_suppression` BEFORE DELETE ON `chart_entries` FOR EACH ROW
BEGIN
    IF (OLD.item_type = 'track' AND EXISTS (SELECT 1 FROM `tracks` WHERE `id` = OLD.item_id))
       OR (OLD.item_type = 'release' AND EXISTS (SELECT 1 FROM `releases` WHERE `id` = OLD.item_id))
       OR (OLD.item_type = 'artist' AND EXISTS (SELECT 1 FROM `artists` WHERE `id` = OLD.item_id)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Classement arrete : aucune suppression.';
    END IF;
END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `certification_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `basis` enum('ecoutes','ventes') NOT NULL,
  `level` enum('or','platine','diamant') NOT NULL,
  `threshold` int(11) NOT NULL,
  `effective_from` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `palier` (`basis`, `level`, `effective_from`),
  CONSTRAINT `seuil_positif` CHECK (`threshold` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seuils proposes le 28/09/2026 (a valider par la direction), a l'echelle du
-- marche tchadien : ecoutes certifiees cumulees, ventes cumulees.
INSERT IGNORE INTO `certification_levels` (`basis`, `level`, `threshold`, `effective_from`) VALUES
  ('ecoutes', 'or', 50000, '2026-09-01'), ('ecoutes', 'platine', 100000, '2026-09-01'), ('ecoutes', 'diamant', 250000, '2026-09-01'),
  ('ventes', 'or', 1000, '2026-09-01'), ('ventes', 'platine', 2500, '2026-09-01'), ('ventes', 'diamant', 5000, '2026-09-01');

CREATE TABLE IF NOT EXISTS `certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `basis` enum('ecoutes','ventes') NOT NULL,
  `level` enum('or','platine','diamant') NOT NULL,
  `threshold` int(11) NOT NULL,
  `value_at_award` int(11) NOT NULL,
  `awarded_at` datetime NOT NULL,
  `notified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `palier` (`track_id`, `basis`, `level`),
  KEY `recentes` (`awarded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `certification_definitive`;
DELIMITER //
CREATE TRIGGER `certification_definitive` BEFORE UPDATE ON `certifications` FOR EACH ROW
BEGIN
    IF NOT (NEW.track_id = OLD.track_id AND NEW.basis = OLD.basis AND NEW.level = OLD.level AND NEW.threshold = OLD.threshold
            AND NEW.value_at_award = OLD.value_at_award AND NEW.awarded_at = OLD.awarded_at) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certification decernee : definitive.';
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `certification_sans_suppression`;
DELIMITER //
CREATE TRIGGER `certification_sans_suppression` BEFORE DELETE ON `certifications` FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM `tracks` WHERE `id` = OLD.track_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certification decernee : definitive.';
    END IF;
END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_hash` char(64) NOT NULL,
  `owner` varchar(150) NOT NULL,
  `contact` varchar(190) NOT NULL,
  `daily_quota` int(11) NOT NULL DEFAULT 1000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `api_key_usage` (
  `key_id` int(11) NOT NULL,
  `day` date NOT NULL,
  `calls` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`key_id`, `day`),
  CONSTRAINT `api_key_usage_ibfk_1` FOREIGN KEY (`key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ancienne table, jamais alimentee.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'charts'),
    'DROP TABLE `charts`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `chart_editions`) OR EXISTS(SELECT 1 FROM `certifications`),
    'SELECT 1 FROM `annulation_impossible__des_classements_ou_certifications_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

CREATE TABLE IF NOT EXISTS `charts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chart_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `item_type` enum('track','album','artist') NOT NULL,
  `item_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `streams_count` bigint(20) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `chart_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chart_date_type` (`chart_date`, `chart_type`, `item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `api_key_usage`;
DROP TABLE IF EXISTS `api_keys`;
DROP TRIGGER IF EXISTS `certification_sans_suppression`;
DROP TRIGGER IF EXISTS `certification_definitive`;
DROP TABLE IF EXISTS `certifications`;
DROP TABLE IF EXISTS `certification_levels`;
DROP TRIGGER IF EXISTS `entree_sans_suppression`;
DROP TRIGGER IF EXISTS `entree_figee`;
DROP TRIGGER IF EXISTS `edition_sans_suppression`;
DROP TRIGGER IF EXISTS `edition_figee`;
DROP TABLE IF EXISTS `chart_entries`;
DROP TABLE IF EXISTS `chart_editions`;
