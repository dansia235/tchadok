-- DATA-08 : structures du referentiel.
--
-- La table `genres` n'etait alimentee par aucun dump : sur une installation
-- neuve elle est VIDE. C'est la cause premiere de l'impossibilite de produire
-- la moindre statistique par genre -- le barometre demande n'avait rien a
-- compter.
--
-- Elle ne portait pas non plus de hierarchie : le barometre s'articule « par
-- genre, par categorie », ce qui suppose un parent. Et aucune table ne decrivait
-- les provinces, pourtant necessaires au barometre regional.
--
-- SEQUENCE DU PLAN, ET POURQUOI ELLE EST AVANCEE ICI
--   Le plan fait dependre DATA-08 de TAXO-01, qui vit au LOT 11. Or une
--   migration de schema appartient au LOT 4, et sans hierarchie le jeu de
--   donnees devrait etre reecrit au LOT 11. Les COLONNES sont donc posees ici ;
--   TAXO-01 garde ce qui reste : les ecrans d'administration (creation,
--   renommage, fusion avec reaffectation, archivage, reordonnancement).
--
-- UN GENRE NE SE SUPPRIME JAMAIS
--   Il est archive, ou fusionne dans un autre (`merged_into`). Supprimer un
--   genre rendrait tout l'historique statistique incoherent : les titres
--   perdraient leur classement passe sans qu'aucune trace n'explique pourquoi.
--   Le `slug` est stable pour que le renommage editorial ne casse pas les URL.

-- UP

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'genres' AND column_name = 'slug'),
    'DO 0',
    "ALTER TABLE `genres`
       ADD COLUMN `parent_id` int(11) DEFAULT NULL AFTER `id`,
       ADD COLUMN `slug` varchar(60) DEFAULT NULL AFTER `name`,
       ADD COLUMN `sort_order` int(11) NOT NULL DEFAULT 0,
       ADD COLUMN `status` enum('active','merged','archived') NOT NULL DEFAULT 'active',
       ADD COLUMN `merged_into` int(11) DEFAULT NULL,
       ADD UNIQUE KEY `slug` (`slug`),
       ADD KEY `parent` (`parent_id`),
       ADD KEY `fusion` (`merged_into`),
       ADD KEY `affichage` (`status`, `sort_order`)"
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Les cles etrangeres sont posees separement : elles echouent si la colonne
-- vient d'etre ajoutee dans la meme instruction sur certaines versions.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.key_column_usage
           WHERE table_schema = DATABASE() AND table_name = 'genres' AND constraint_name = 'genres_parent'),
    'DO 0',
    'ALTER TABLE `genres`
       ADD CONSTRAINT `genres_parent` FOREIGN KEY (`parent_id`) REFERENCES `genres` (`id`) ON DELETE SET NULL,
       ADD CONSTRAINT `genres_fusion` FOREIGN KEY (`merged_into`) REFERENCES `genres` (`id`) ON DELETE SET NULL'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Provinces du Tchad, pour le barometre regional. Une table plutot qu'une
-- chaine libre dans `users.city` : « Ndjamena », « N'Djamena » et « Ndjaména »
-- ne se regroupent pas.
CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `capital` varchar(80) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `name` (`name`),
  KEY `affichage` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rattachement facultatif d'un compte a sa province. `users.city` reste : la
-- ville est plus fine que la province, et les comptes existants la portent.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'region_id'),
    'DO 0',
    'ALTER TABLE `users` ADD COLUMN `region_id` int(11) DEFAULT NULL AFTER `city`, ADD KEY `region` (`region_id`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.key_column_usage
           WHERE table_schema = DATABASE() AND table_name = 'users' AND constraint_name = 'users_region'),
    'DO 0',
    'ALTER TABLE `users` ADD CONSTRAINT `users_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'region_id'),
    'DO 0',
    'ALTER TABLE `artists` ADD COLUMN `region_id` int(11) DEFAULT NULL, ADD KEY `region` (`region_id`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.key_column_usage
           WHERE table_schema = DATABASE() AND table_name = 'artists' AND constraint_name = 'artists_region'),
    'DO 0',
    'ALTER TABLE `artists` ADD CONSTRAINT `artists_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

ALTER TABLE `artists` DROP FOREIGN KEY `artists_region`;
ALTER TABLE `artists` DROP COLUMN `region_id`;
ALTER TABLE `users` DROP FOREIGN KEY `users_region`;
ALTER TABLE `users` DROP COLUMN `region_id`;
DROP TABLE IF EXISTS `regions`;
ALTER TABLE `genres` DROP FOREIGN KEY `genres_parent`;
ALTER TABLE `genres` DROP FOREIGN KEY `genres_fusion`;
ALTER TABLE `genres`
  DROP COLUMN `merged_into`,
  DROP COLUMN `status`,
  DROP COLUMN `sort_order`,
  DROP COLUMN `slug`,
  DROP COLUMN `parent_id`;
