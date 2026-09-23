-- DATA-07 : index du catalogue public.
--
-- TOUTES les requetes du catalogue filtrent sur `status` -- et, depuis DATA-06,
-- sur `deleted_at`. Aucune ne disposait d'un index utilisable : chaque page
-- d'accueil parcourait la table des titres en entier. Sur un catalogue de
-- demonstration cela ne se voit pas ; sur dix mille titres, la page d'accueil
-- devient la requete la plus chere du site.
--
-- POURQUOI DES INDEX COMPOSITES, ET DANS CET ORDRE
--   Un index sert de gauche a droite. Les requetes s'ecrivent toutes
--   « WHERE status = 'approved' AND deleted_at IS NULL ORDER BY <date> » :
--   l'egalite d'abord, le tri ensuite. MySQL peut alors filtrer ET trier avec
--   le meme index, sans table temporaire ni tri en memoire.
--
-- CE QUI N'EST PAS FAIT ICI, ET POURQUOI
--   L'audit annoncait un melange de collations a corriger. Verification faite,
--   les 45 tables et les 204 colonnes textuelles sont toutes en
--   `utf8mb4_general_ci` : le schema est homogene. Convertir 45 tables en
--   `utf8mb4_unicode_ci` reecrirait chaque table pour un gain limite au tri des
--   ligatures. La difference n'est pas nulle, mais elle ne justifie pas
--   l'operation aujourd'hui ; elle est notee au plan.

-- UP

-- tracks : filtre du catalogue + tri par date
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'tracks' AND index_name = 'catalogue'),
    'DO 0',
    'ALTER TABLE `tracks` ADD KEY `catalogue` (`status`, `deleted_at`, `created_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- tracks : vues par genre du barometre
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'tracks' AND index_name = 'genre_statut'),
    'DO 0',
    'ALTER TABLE `tracks` ADD KEY `genre_statut` (`genre_id`, `status`, `deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- tracks : classements par ecoutes (ORDER BY total_streams DESC)
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'tracks' AND index_name = 'classement'),
    'DO 0',
    'ALTER TABLE `tracks` ADD KEY `classement` (`status`, `deleted_at`, `total_streams`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- releases : filtre + tri par date de sortie
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'releases' AND index_name = 'catalogue'),
    'DO 0',
    'ALTER TABLE `releases` ADD KEY `catalogue` (`status`, `deleted_at`, `release_date`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- releases : vues par genre
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'releases' AND index_name = 'genre_statut'),
    'DO 0',
    'ALTER TABLE `releases` ADD KEY `genre_statut` (`genre_id`, `status`, `deleted_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- artists : chaque requete du catalogue joint les artistes et exige un compte
-- actif et non retire. Sans index, la jointure filtrait ligne a ligne.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'artists' AND index_name = 'visible'),
    'DO 0',
    'ALTER TABLE `artists` ADD KEY `visible` (`is_active`, `deleted_at`, `total_streams`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- order_items : le tableau de bord artiste somme ses ventes par periode. La
-- jointure part de l'artiste ; sans cet index, elle parcourait toutes les
-- lignes de commande de la plateforme.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'order_items' AND index_name = 'artiste_commande'),
    'DO 0',
    'ALTER TABLE `order_items` ADD KEY `artiste_commande` (`artist_id`, `order_id`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- orders : la meme jointure filtre sur le statut paye et la date de paiement.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'encaissees'),
    'DO 0',
    'ALTER TABLE `orders` ADD KEY `encaissees` (`status`, `paid_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

ALTER TABLE `orders` DROP KEY `encaissees`;
ALTER TABLE `order_items` DROP KEY `artiste_commande`;
ALTER TABLE `artists` DROP KEY `visible`;
ALTER TABLE `releases` DROP KEY `genre_statut`;
ALTER TABLE `releases` DROP KEY `catalogue`;
ALTER TABLE `tracks` DROP KEY `classement`;
ALTER TABLE `tracks` DROP KEY `genre_statut`;
ALTER TABLE `tracks` DROP KEY `catalogue`;
