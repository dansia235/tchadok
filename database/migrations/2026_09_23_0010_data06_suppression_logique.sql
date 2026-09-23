-- DATA-06 : suppression logique et conservation.
--
-- Aucune table ne portait de `deleted_at`, et les cles etrangeres cascadaient :
-- supprimer un compte detruisait ses ecoutes, ses playlists et -- avant
-- DATA-05 -- son historique d'achat. C'est incompatible avec une obligation de
-- conservation comptable et avec la resolution d'un litige artiste six mois
-- plus tard.
--
-- CE QUE `deleted_at` CHANGE
--   Un contenu retire disparait des pages publiques sans quitter la base. On
--   peut donc repondre a « pourquoi ce titre n'est plus en vente ? » et
--   « combien a-t-il rapporte avant son retrait ? ».
--
-- LE DROIT A L'EFFACEMENT NE S'OPPOSE PAS A LA CONSERVATION COMPTABLE
--   Un membre qui exerce son droit a l'effacement voit ses donnees
--   personnelles remplacees -- nom, courriel, telephone, adresse -- tandis que
--   les ecritures comptables demeurent, sans nom : la loi impose de les garder.
--   L'anonymisation est faite par includes/effacement.php, pas ici : elle doit
--   etre tracee et reversible le temps d'un delai de retractation.

-- UP

-- users
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'deleted_at'),
    'DO 0',
    'ALTER TABLE `users`
       ADD COLUMN `deleted_at` datetime DEFAULT NULL,
       ADD COLUMN `anonymized_at` datetime DEFAULT NULL,
       ADD KEY `retire` (`deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- artists
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'deleted_at'),
    'DO 0',
    'ALTER TABLE `artists` ADD COLUMN `deleted_at` datetime DEFAULT NULL, ADD KEY `retire` (`deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- tracks
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tracks' AND column_name = 'deleted_at'),
    'DO 0',
    'ALTER TABLE `tracks` ADD COLUMN `deleted_at` datetime DEFAULT NULL, ADD KEY `retire` (`deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- releases
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'releases' AND column_name = 'deleted_at'),
    'DO 0',
    'ALTER TABLE `releases` ADD COLUMN `deleted_at` datetime DEFAULT NULL, ADD KEY `retire` (`deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- playlists
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'playlists' AND column_name = 'deleted_at'),
    'DO 0',
    'ALTER TABLE `playlists` ADD COLUMN `deleted_at` datetime DEFAULT NULL, ADD KEY `retire` (`deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- La vue `albums` (DATA-03) expose desormais la colonne, sans quoi un album
-- retire resterait visible partout ou le code lit encore l'ancien nom.
CREATE OR REPLACE VIEW `albums` AS
SELECT
    r.id, r.artist_id, r.title, r.description, r.cover_image, r.genre_id,
    r.format AS `type`,
    COALESCE(r.price_bundle, 0.00) AS price,
    r.release_date, r.language, r.total_tracks, r.total_duration,
    r.is_free, r.is_featured, r.status, r.total_streams, r.total_sales,
    r.deleted_at,
    r.created_at, r.updated_at
FROM `releases` r;

-- Les ecoutes d'un compte anonymise ne doivent plus le designer. La colonne
-- devient facultative : STAT-02 conservera l'ecoute sans son auteur plutot que
-- de detruire la statistique.
SET @instruction = (SELECT IF(
    (SELECT is_nullable FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'streams' AND column_name = 'user_id') = 'YES',
    'DO 0',
    'ALTER TABLE `streams` MODIFY COLUMN `user_id` int(11) DEFAULT NULL'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

DROP VIEW IF EXISTS `albums`;

CREATE OR REPLACE VIEW `albums` AS
SELECT
    r.id, r.artist_id, r.title, r.description, r.cover_image, r.genre_id,
    r.format AS `type`,
    COALESCE(r.price_bundle, 0.00) AS price,
    r.release_date, r.language, r.total_tracks, r.total_duration,
    r.is_free, r.is_featured, r.status, r.total_streams, r.total_sales,
    r.created_at, r.updated_at
FROM `releases` r;

ALTER TABLE `playlists` DROP COLUMN `deleted_at`;
ALTER TABLE `releases` DROP COLUMN `deleted_at`;
ALTER TABLE `tracks` DROP COLUMN `deleted_at`;
ALTER TABLE `artists` DROP COLUMN `deleted_at`;
ALTER TABLE `users` DROP COLUMN `anonymized_at`;
ALTER TABLE `users` DROP COLUMN `deleted_at`;
