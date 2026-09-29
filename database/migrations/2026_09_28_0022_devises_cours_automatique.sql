-- Cours du dollar automatique (decision du 28/09/2026).
--
-- Le taux USD -> XAF se lit dans une API gratuite, sans cle :
--   1. open.er-api.com (ExchangeRate-API, acces libre) : cours USD/XAF direct ;
--   2. a defaut, api.frankfurter.app (cours de la BCE) : USD/EUR, multiplie par
--      la parite FIXE du franc CFA (1 EUR = 655,957 XAF, garantie par le Tresor
--      francais) -- le resultat est donc exact, pas une approximation.
-- Sans connexion Internet (ou reponse aberrante), le taux de secours s'applique :
-- 600 FCFA (DEVISE_USD_SECOURS).
--
-- Chaque cours obtenu devient une ligne de l'historique (qui reste immuable),
-- avec sa SOURCE : on sait, pour tout paiement, d'ou venait le taux fige.

-- UP

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'exchange_rates' AND column_name = 'source'),
    'DO 0',
    'ALTER TABLE `exchange_rates`
       ADD COLUMN `source` enum(''manuel'',''api'') NOT NULL DEFAULT ''manuel'' AFTER `xaf_per_unit`,
       ADD KEY `par_source` (`currency`, `source`, `active_from`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payment_intents' AND column_name = 'rate_source'),
    'DO 0',
    'ALTER TABLE `payment_intents` ADD COLUMN `rate_source` enum(''manuel'',''api'',''secours'') DEFAULT NULL AFTER `exchange_rate`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `exchange_rates` WHERE `source` = 'api'),
    'SELECT 1 FROM `annulation_impossible__des_cours_automatiques_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

ALTER TABLE `payment_intents` DROP COLUMN `rate_source`;
ALTER TABLE `exchange_rates` DROP KEY `par_source`, DROP COLUMN `source`;
