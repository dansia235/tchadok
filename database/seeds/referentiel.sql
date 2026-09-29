-- ================================================================
-- TCHADOK - DONNEES DE REFERENCE (DATA-08)
-- ================================================================
--
-- A charger sur TOUTE installation, production comprise :
--   php scripts/seed.php referentiel
--
-- Contenu :
--   - categories et genres musicaux (table `genres`, hierarchique) ;
--   - les 23 provinces du Tchad (table `regions`), pour le barometre regional.
--
-- Deja portes par les migrations, donc absents d'ici :
--   - roles et permissions  : migration 0004 (SEC-19) ;
--   - grille tarifaire      : migration 0008 (DATA-04).
--
-- REJOUABLE SANS DANGER
--   Toutes les insertions sont des INSERT IGNORE sur des cles uniques (slug,
--   nom). Relancer le chargement n'ajoute que ce qui manque et n'ECRASE
--   JAMAIS une ligne existante : un genre renomme, recolore ou archive par
--   l'equipe editoriale garde sa version. Un genre ne se supprime jamais
--   (voir la migration 0012) : pour en retirer un, l'archiver ou le fusionner.
--
-- NOMENCLATURE DES GENRES : PROPOSITION A VALIDER
--   Elle reprend le paragraphe 6.3 de l'audit. C'est un acte patrimonial
--   autant que technique : elle doit etre revue par un comite editorial,
--   idealement avec des professionnels du secteur et un ethnomusicologue.
--   Les ajustements se feront ensuite par l'administration de la taxonomie
--   (TAXO-03), pas en modifiant ce fichier sur une base deja chargee.
--
-- Hierarchie : une ligne SANS parent est une CATEGORIE. Un titre se classe
-- toujours dans un genre, jamais directement dans une categorie
-- (getGenresSelectionnables() dans includes/database.php).
-- ================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ----------------------------------------------------------------
-- Categories
-- ----------------------------------------------------------------
INSERT IGNORE INTO `genres`
  (`parent_id`, `name`, `slug`, `name_french`, `description`, `color`, `sort_order`, `is_active`, `status`)
VALUES
  (NULL, 'Patrimoine & traditionnel', 'patrimoine-traditionnel', 'Patrimoine & traditionnel',
   'Musiques des peuples et des terroirs du Tchad, transmises de generation en generation.', '#B45309', 10, 1, 'active'),
  (NULL, 'Musiques urbaines', 'musiques-urbaines', 'Musiques urbaines',
   'Rap, hip-hop et musiques urbaines africaines.', '#7C3AED', 20, 1, 'active'),
  (NULL, 'Variété & world', 'variete-world', 'Variété & world',
   'Variete tchadienne et musiques du monde.', '#0284C7', 30, 1, 'active'),
  (NULL, 'Spirituel', 'spirituel', 'Spirituel',
   'Musiques et chants religieux, chretiens et musulmans.', '#059669', 40, 1, 'active'),
  (NULL, 'Instrumental & jazz', 'instrumental-jazz', 'Instrumental & jazz',
   'Jazz, oeuvres instrumentales, cordes et percussions.', '#CA8A04', 50, 1, 'active'),
  (NULL, 'Jeunesse & éducatif', 'jeunesse-educatif', 'Jeunesse & éducatif',
   'Comptines, contes et chansons pour apprendre.', '#DB2777', 60, 1, 'active');

-- ----------------------------------------------------------------
-- Genres, rattaches a leur categorie par son slug
-- ----------------------------------------------------------------
INSERT IGNORE INTO `genres`
  (`parent_id`, `name`, `slug`, `name_french`, `color`, `sort_order`, `is_active`, `status`)
SELECT c.`id`, v.nom, v.slug, v.nom, c.`color`, v.ordre, 1, 'active'
FROM (
            SELECT 'patrimoine-traditionnel' AS categorie, 'Saï' AS nom, 'sai' AS slug, 10 AS ordre
  UNION ALL SELECT 'patrimoine-traditionnel', 'Kanembou',                 'kanembou',                20
  UNION ALL SELECT 'patrimoine-traditionnel', 'Sara',                     'sara',                    30
  UNION ALL SELECT 'patrimoine-traditionnel', 'Toubou',                   'toubou',                  40
  UNION ALL SELECT 'patrimoine-traditionnel', 'Ouaddaïen',                'ouaddaien',               50
  UNION ALL SELECT 'patrimoine-traditionnel', 'Hadjarai',                 'hadjarai',                60
  UNION ALL SELECT 'patrimoine-traditionnel', 'Griot / chant de louange', 'griot-chant-de-louange',  70
  UNION ALL SELECT 'patrimoine-traditionnel', 'Musique de cour',          'musique-de-cour',         80

  UNION ALL SELECT 'musiques-urbaines',       'Rap tchadien',             'rap-tchadien',            10
  UNION ALL SELECT 'musiques-urbaines',       'Hip-hop',                  'hip-hop',                 20
  UNION ALL SELECT 'musiques-urbaines',       'Trap',                     'trap',                    30
  UNION ALL SELECT 'musiques-urbaines',       'Afrobeats',                'afrobeats',               40
  UNION ALL SELECT 'musiques-urbaines',       'Afropop',                  'afropop',                 50
  UNION ALL SELECT 'musiques-urbaines',       'Coupé-décalé',             'coupe-decale',            60
  UNION ALL SELECT 'musiques-urbaines',       'Ndombolo',                 'ndombolo',                70

  UNION ALL SELECT 'variete-world',           'Variété tchadienne',       'variete-tchadienne',      10
  UNION ALL SELECT 'variete-world',           'Soukous',                  'soukous',                 20
  UNION ALL SELECT 'variete-world',           'Reggae',                   'reggae',                  30
  UNION ALL SELECT 'variete-world',           'Zouk',                     'zouk',                    40
  UNION ALL SELECT 'variete-world',           'World fusion',             'world-fusion',            50

  UNION ALL SELECT 'spirituel',               'Gospel',                   'gospel',                  10
  UNION ALL SELECT 'spirituel',               'Chant chrétien',           'chant-chretien',          20
  UNION ALL SELECT 'spirituel',               'Madh / chant soufi',       'madh-chant-soufi',        30
  UNION ALL SELECT 'spirituel',               'Nasheed',                  'nasheed',                 40

  UNION ALL SELECT 'instrumental-jazz',       'Jazz sahélien',            'jazz-sahelien',           10
  UNION ALL SELECT 'instrumental-jazz',       'Instrumental',             'instrumental',            20
  UNION ALL SELECT 'instrumental-jazz',       'Kora / luth',              'kora-luth',               30
  UNION ALL SELECT 'instrumental-jazz',       'Percussions',              'percussions',             40

  UNION ALL SELECT 'jeunesse-educatif',       'Comptines',                'comptines',               10
  UNION ALL SELECT 'jeunesse-educatif',       'Contes musicaux',          'contes-musicaux',         20
  UNION ALL SELECT 'jeunesse-educatif',       'Chansons pédagogiques',    'chansons-pedagogiques',   30
) AS v
JOIN `genres` c ON c.`slug` = v.categorie AND c.`parent_id` IS NULL;

-- ----------------------------------------------------------------
-- Provinces du Tchad et leur chef-lieu (decoupage de 2018, 23 provinces)
-- ----------------------------------------------------------------
INSERT IGNORE INTO `regions` (`name`, `slug`, `capital`, `sort_order`, `is_active`) VALUES
  ('Barh El Gazel',     'barh-el-gazel',     'Moussoro',      10, 1),
  ('Batha',             'batha',             'Ati',           20, 1),
  ('Borkou',            'borkou',            'Faya-Largeau',  30, 1),
  ('Chari-Baguirmi',    'chari-baguirmi',    'Massenya',      40, 1),
  ('Ennedi-Est',        'ennedi-est',        'Amdjarass',     50, 1),
  ('Ennedi-Ouest',      'ennedi-ouest',      'Fada',          60, 1),
  ('Guéra',             'guera',             'Mongo',         70, 1),
  ('Hadjer-Lamis',      'hadjer-lamis',      'Massakory',     80, 1),
  ('Kanem',             'kanem',             'Mao',           90, 1),
  ('Lac',               'lac',               'Bol',          100, 1),
  ('Logone Occidental', 'logone-occidental', 'Moundou',      110, 1),
  ('Logone Oriental',   'logone-oriental',   'Doba',         120, 1),
  ('Mandoul',           'mandoul',           'Koumra',       130, 1),
  ('Mayo-Kebbi Est',    'mayo-kebbi-est',    'Bongor',       140, 1),
  ('Mayo-Kebbi Ouest',  'mayo-kebbi-ouest',  'Pala',         150, 1),
  ('Moyen-Chari',       'moyen-chari',       'Sarh',         160, 1),
  ('N''Djamena',        'ndjamena',          'N''Djamena',   170, 1),
  ('Ouaddaï',           'ouaddai',           'Abéché',       180, 1),
  ('Salamat',           'salamat',           'Am Timan',     190, 1),
  ('Sila',              'sila',              'Goz Beïda',    200, 1),
  ('Tandjilé',          'tandjile',          'Laï',          210, 1),
  ('Tibesti',           'tibesti',           'Bardaï',       220, 1),
  ('Wadi Fira',         'wadi-fira',         'Biltine',      230, 1);

COMMIT;
