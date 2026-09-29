-- STAT-06 : suivi du comptage.
--
-- `counted_at` : quand les compteurs publics ont ete recalcules pour ce jour.
-- Un jour reconstruit depuis (built_at plus recent), par la tache de nuit ou a
-- la main, voit ses compteurs recalcules au passage suivant.

-- UP

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'rollup_runs' AND column_name = 'counted_at'),
    'DO 0',
    'ALTER TABLE `rollup_runs` ADD COLUMN `counted_at` datetime DEFAULT NULL AFTER `built_at`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

ALTER TABLE `rollup_runs` DROP COLUMN `counted_at`;
