-- TAXO-02 / TAXO-03 : taxonomie unifiee.
--
-- `artists.genres` (texte libre multi-valeurs : « Sai, Afrobeat » et
-- « Afrobeat, Sai » faisaient deux groupes) est remplace par `artist_genres`,
-- rattache au referentiel, avec UN genre principal par artiste.
-- La colonne ne se retire que si elle est vide : une valeur existante doit
-- d'abord etre rattachee par l'equipe editoriale
-- (php scripts/taxonomie.php correspondances), jamais automatiquement.
--
-- `genre_proposals` : un artiste PROPOSE un genre ; il n'est cree qu'apres
-- decision motivee de l'administration (TAXO-03).

-- UP

CREATE TABLE IF NOT EXISTS `artist_genres` (
  `artist_id` int(11) NOT NULL,
  `genre_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`artist_id`, `genre_id`),
  KEY `genre` (`genre_id`),
  CONSTRAINT `artist_genres_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `artist_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Un seul genre principal par artiste : colonne generee, unique quand elle
-- n'est pas NULL.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artist_genres' AND column_name = 'primary_key_guard'),
    'DO 0',
    'ALTER TABLE `artist_genres` ADD COLUMN `primary_key_guard` int(11) AS (IF(`is_primary` = 1, `artist_id`, NULL)) PERSISTENT, ADD UNIQUE KEY `un_principal` (`primary_key_guard`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

CREATE TABLE IF NOT EXISTS `genre_proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `genre_id` int(11) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `etat` (`status`, `created_at`),
  CONSTRAINT `genre_proposals_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `genre_proposals_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `genres` (`id`),
  CONSTRAINT `genre_proposals_ibfk_3` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`),
  CONSTRAINT `genre_proposals_ibfk_4` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `decision_proposition_motivee` CHECK (`status` = 'pending' OR CHAR_LENGTH(TRIM(COALESCE(`decision_reason`, ''))) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Retrait de `artists.genres`, seulement si plus aucune valeur n'y attend
-- une correspondance editoriale.
-- En deux temps : MySQL evalue toute l'expression, y compris une lecture de
-- `genres` alors que la colonne n'existe deja plus (migration rejouee).
SET @colonne = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'genres');
SET @restants = 0;
SET @instruction = IF(@colonne > 0, 'SELECT COUNT(*) INTO @restants FROM `artists` WHERE TRIM(COALESCE(`genres`, '''')) <> ''''', 'DO 0');
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;
SET @instruction = IF(@colonne = 0, 'DO 0',
    IF(@restants > 0,
       'SELECT 1 FROM `artists_genres_a_rattacher__lancer_scripts_taxonomie_php_correspondances`',
       'ALTER TABLE `artists` DROP COLUMN `genres`'));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'genres'),
    'DO 0',
    'ALTER TABLE `artists` ADD COLUMN `genres` text DEFAULT NULL'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;
DROP TABLE IF EXISTS `genre_proposals`;
DROP TABLE IF EXISTS `artist_genres`;
