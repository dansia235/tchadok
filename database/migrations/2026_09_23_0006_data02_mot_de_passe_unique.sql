-- DATA-02 : une seule colonne de mot de passe.
--
-- La table users portait `password` ET `password_hash`, toutes deux NOT NULL,
-- et l'authentification acceptait l'une OU l'autre. Toute divergence entre les
-- deux creait donc un SECOND MOT DE PASSE VALIDE, permanent : apres le passage
-- de l'ancien admin/update-passwords.php, l'ancien mot de passe restait
-- utilisable par la colonne `password`, sans que personne ne le sache.
--
-- AVANT D'APPLIQUER, sur une base existante :
--     php scripts/analyser-mots-de-passe.php
-- Le rapport indique les comptes dont les deux colonnes divergent. Cette
-- migration fait foi sur `password_hash` : sur un compte divergent, l'ancien
-- mot de passe cesse de fonctionner -- c'est l'objectif -- et la personne
-- devra passer par la recuperation.
--
-- La convergence ne touche que les comptes dont `password_hash` est vide ou
-- illisible : la valeur de `password` y est alors recopiee, pour ne verrouiller
-- personne.

-- UP

-- 1. Convergence : password_hash vide ou non bcrypt, password exploitable.
--
--    Le test d'existence de la colonne est indispensable : sans lui, rejouer
--    cette migration sur une base deja migree echoue sur « Unknown column
--    'password' ». Une migration doit pouvoir etre relancee sans consequence.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'
    ),
    'UPDATE `users` SET `password_hash` = `password`
     WHERE (`password_hash` IS NULL OR `password_hash` = '''' OR `password_hash` NOT LIKE ''$2y$%'')
       AND `password` LIKE ''$2y$%''',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- 2. Suppression de la colonne, si elle existe encore.
--    MySQL ne connait pas DROP COLUMN IF EXISTS : le test passe par
--    information_schema, ce qui rend la migration rejouable.
SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'
    ),
    'ALTER TABLE `users` DROP COLUMN `password`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'
    ),
    'DO 0',
    'ALTER TABLE `users` ADD COLUMN `password` varchar(255) NOT NULL DEFAULT '''' AFTER `email`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Le retour en arriere recopie le hash unique dans les deux colonnes : il
-- restitue la structure, pas les anciens mots de passe divergents, qui ne sont
-- recuperables que par une sauvegarde.
UPDATE `users` SET `password` = `password_hash`;
