-- DATA-03 : l'entite « sortie » et les formats de vente.
--
-- Le modele separait `tracks` et `albums`, ce qui empeche de vendre un single
-- comme un produit : un single n'est pas un album, et n'a rien a faire dans
-- une table qui s'appelle ainsi. L'enumeration `albums.type` contenait deja
-- single / maxi_single / ep / album, sans que rien ne la controle.
--
-- `releases` devient la table des sorties, quel que soit leur format. Les
-- identifiants sont conserves : un album 42 devient la sortie 42, et tous les
-- liens existants restent valides.
--
-- `albums` ne disparait pas pour autant : la table est remplacee par une VUE
-- qui porte les anciens noms de colonnes. Les quatorze lectures existantes
-- continuent de fonctionner sans modification, le temps que le code migre.
-- Les ecritures, elles, passent par `releases` -- une vue filtree n'accepte
-- pas d'insertion fiable.

-- UP

CREATE TABLE IF NOT EXISTS `releases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `genre_id` int(11) DEFAULT NULL,
  -- compilation s'ajoute aux quatre formats existants
  `format` enum('single','maxi_single','ep','album','compilation') NOT NULL DEFAULT 'single',
  `language` varchar(50) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `is_preorder` tinyint(1) NOT NULL DEFAULT 0,
  -- Prix de la sortie complete. NULL = pas de vente groupee.
  `price_bundle` decimal(8,2) DEFAULT NULL,
  -- L'artiste autorise-t-il l'achat titre par titre ?
  `allow_track_buy` tinyint(1) NOT NULL DEFAULT 1,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
  `rejected_reason` varchar(500) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `total_tracks` int(11) NOT NULL DEFAULT 0,
  `total_duration` int(11) NOT NULL DEFAULT 0,
  `total_streams` bigint(20) NOT NULL DEFAULT 0,
  `total_sales` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `artiste` (`artist_id`),
  KEY `genre` (`genre_id`),
  KEY `format_statut` (`format`, `status`),
  CONSTRAINT `releases_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `releases_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`),
  CONSTRAINT `releases_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reprise des albums existants, identifiants conserves. Ne s'execute que si
-- `albums` est encore une table : au second passage, c'est une vue.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'albums' AND table_type = 'BASE TABLE'
    ),
    'INSERT IGNORE INTO `releases`
        (id, artist_id, title, description, cover_image, genre_id, format, language,
         release_date, price_bundle, is_free, is_featured, status, total_tracks,
         total_duration, total_streams, total_sales, created_at, updated_at)
     SELECT id, artist_id, title, description, cover_image, genre_id,
            CASE `type` WHEN ''ep'' THEN ''ep'' WHEN ''single'' THEN ''single''
                        WHEN ''maxi_single'' THEN ''maxi_single'' ELSE ''album'' END,
            language, release_date, NULLIF(price, 0), is_free, is_featured, status,
            total_tracks, total_duration, total_streams, total_sales, created_at, updated_at
     FROM `albums`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Lien des titres vers la sortie. album_id est conserve le temps que le code
-- migre : le retirer maintenant casserait quatorze lectures d'un coup.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'tracks' AND column_name = 'release_id'
    ),
    'DO 0',
    'ALTER TABLE `tracks` ADD COLUMN `release_id` int(11) DEFAULT NULL AFTER `album_id`,
     ADD KEY `sortie` (`release_id`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

UPDATE `tracks` SET `release_id` = `album_id` WHERE `release_id` IS NULL AND `album_id` IS NOT NULL;

-- Identifiants lisibles pour les URL (SEO-02) et les liens artiste.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tracks' AND column_name = 'slug'),
    'DO 0',
    'ALTER TABLE `tracks` ADD COLUMN `slug` varchar(220) DEFAULT NULL, ADD UNIQUE KEY `slug` (`slug`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'slug'),
    'DO 0',
    'ALTER TABLE `artists` ADD COLUMN `slug` varchar(220) DEFAULT NULL, ADD UNIQUE KEY `slug` (`slug`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Une cle etrangere relie encore tracks.album_id a albums : tant qu'elle
-- existe, la table ne peut pas etre remplacee par une vue. Son nom est lu dans
-- information_schema, car il differe d'une installation a l'autre.
SET @contrainte = (
    SELECT constraint_name FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE() AND table_name = 'tracks'
      AND column_name = 'album_id' AND referenced_table_name = 'albums'
    LIMIT 1
);
SET @instruction = IF(
    @contrainte IS NULL,
    'DO 0',
    CONCAT('ALTER TABLE `tracks` DROP FOREIGN KEY `', @contrainte, '`')
);
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- `albums` devient une vue de compatibilite, avec les anciens noms de colonnes.
-- Les EP, albums et compilations y figurent -- ce que le mot « album » designe.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'albums' AND table_type = 'BASE TABLE'
    ),
    'DROP TABLE `albums`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

CREATE OR REPLACE VIEW `albums` AS
SELECT
    r.id, r.artist_id, r.title, r.description, r.cover_image, r.genre_id,
    r.format AS `type`,
    COALESCE(r.price_bundle, 0.00) AS price,
    r.release_date, r.language, r.total_tracks, r.total_duration,
    r.is_free, r.is_featured, r.status, r.total_streams, r.total_sales,
    r.created_at, r.updated_at
FROM `releases` r;

-- DOWN

DROP VIEW IF EXISTS `albums`;

CREATE TABLE IF NOT EXISTS `albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `genre_id` int(11) DEFAULT NULL,
  `type` enum('album','ep','single','maxi_single') DEFAULT 'album',
  `price` decimal(8,2) DEFAULT 0.00,
  `release_date` date DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `total_tracks` int(11) DEFAULT 0,
  `total_duration` int(11) DEFAULT 0,
  `is_free` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `total_streams` bigint(20) DEFAULT 0,
  `total_sales` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `genre_id` (`genre_id`),
  KEY `idx_albums_artist` (`artist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `albums`
    (id, artist_id, title, description, cover_image, genre_id, `type`, price,
     release_date, language, total_tracks, total_duration, is_free, is_featured,
     status, total_streams, total_sales, created_at, updated_at)
SELECT id, artist_id, title, description, cover_image, genre_id,
       CASE format WHEN 'compilation' THEN 'album' ELSE format END,
       COALESCE(price_bundle, 0.00), release_date, language, total_tracks,
       total_duration, is_free, is_featured, status, total_streams, total_sales,
       created_at, updated_at
FROM `releases`
WHERE format IN ('ep', 'album', 'compilation', 'single', 'maxi_single');

DROP TABLE IF EXISTS `releases`;
