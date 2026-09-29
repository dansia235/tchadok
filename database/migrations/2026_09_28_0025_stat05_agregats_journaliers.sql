-- STAT-05 : agregats journaliers.
--
-- Une ligne par jour et par combinaison des dimensions du barometre : titre,
-- sortie, artiste, genre, categorie, region, source, type d'auditeur. Les
-- ecoutes viennent UNIQUEMENT de `streams_certified` (STAT-03) ; les ventes,
-- des lignes de commandes PAYEES (un remboursement fait sortir la vente au
-- recalcul du jour).
--
-- Les tableaux de bord lisent cette table, jamais `streams`. Elle se
-- reconstruit a l'identique pour n'importe quel jour (includes/agregats.php) :
-- relancer un jour deja traite ne duplique rien.
--
-- Dimensions absentes : 0 pour un identifiant, '' pour un texte (la cle
-- primaire ne peut pas porter de NULL). `region` reste vide tant que la
-- geolocalisation serveur n'est pas en place (STAT-02). Les ventes portent
-- source = 'vente' et listener_type = '-'. `listeners` (auditeurs uniques)
-- n'est pas additif d'une ligne a l'autre.

-- UP

CREATE TABLE IF NOT EXISTS `daily_rollups` (
  `day` date NOT NULL,
  `track_id` int(11) NOT NULL DEFAULT 0,
  `release_id` int(11) NOT NULL DEFAULT 0,
  `artist_id` int(11) NOT NULL DEFAULT 0,
  `genre_id` int(11) NOT NULL DEFAULT 0,
  `category_id` int(11) NOT NULL DEFAULT 0,
  `region` varchar(60) NOT NULL DEFAULT '',
  `source` varchar(20) NOT NULL DEFAULT '',
  `listener_type` enum('abonne','compte','visiteur','-') NOT NULL DEFAULT '-',
  `streams` int(11) NOT NULL DEFAULT 0,
  `listeners` int(11) NOT NULL DEFAULT 0,
  `duration_seconds` bigint(20) NOT NULL DEFAULT 0,
  `sales` int(11) NOT NULL DEFAULT 0,
  `revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `artist_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`day`, `track_id`, `release_id`, `artist_id`, `genre_id`, `category_id`, `region`, `source`, `listener_type`),
  KEY `artiste` (`artist_id`, `day`),
  KEY `titre` (`track_id`, `day`),
  KEY `sortie` (`release_id`, `day`),
  KEY `genre` (`genre_id`, `day`),
  KEY `categorie` (`category_id`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Journal des calculs : quel jour, quand, avec quels totaux. Sert a savoir
-- quels jours recalculer apres un remboursement ou une decision de
-- quarantaine.
CREATE TABLE IF NOT EXISTS `rollup_runs` (
  `day` date NOT NULL,
  `built_at` datetime NOT NULL,
  `streams` int(11) NOT NULL DEFAULT 0,
  `sales` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reperer vite les ecoutes certifiees apres le dernier calcul d'un jour.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'streams_certified' AND index_name = 'certification'),
    'DO 0',
    'ALTER TABLE `streams_certified` ADD KEY `certification` (`certified_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'streams_certified' AND index_name = 'certification'),
    'ALTER TABLE `streams_certified` DROP KEY `certification`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;
DROP TABLE IF EXISTS `rollup_runs`;
DROP TABLE IF EXISTS `daily_rollups`;
