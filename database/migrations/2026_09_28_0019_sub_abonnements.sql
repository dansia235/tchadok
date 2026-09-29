-- LOT 7 : abonnements Premium.
--
-- AVANT
--   premium-payment.php inserait une ligne `pending` et s'arretait : aucun
--   encaissement, aucune activation, personne ne pouvait devenir Premium. Le
--   statut vivait en session, fige a la connexion.
--
-- PLANS (SUB-01)
--   `subscription_plans` porte le libelle, la duree et l'activation. Le PRIX
--   reste dans la grille administree (`pricing_rules`, DATA-04, portee
--   `subscription`, format = `code` du plan) : une seule source, deja
--   administree et journalisee. Le prix paye est fige sur la commande et sur
--   l'abonnement : un changement de tarif n'affecte pas un abonnement en cours.
--
-- ABONNEMENTS (SUB-02, SUB-03)
--   Une ligne par periode payee, creee a l'ENCAISSEMENT (jamais a l'intention
--   de paiement), rattachee a sa commande. Actif = status 'active' ET
--   start_date <= maintenant < end_date. Un renouvellement commence a la fin
--   de la periode en cours : jamais deux periodes qui se chevauchent.
--   Resiliation : `cancelled_at` ; l'acces reste jusqu'a `end_date`.

-- UP

CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `label` varchar(80) NOT NULL,
  `duration_months` tinyint(4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  CONSTRAINT `subscription_plans_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `duree_positive` CHECK (`duration_months` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `subscription_plans` (`code`, `label`, `duration_months`, `sort_order`) VALUES
  ('premium_monthly', 'Premium mensuel', 1, 10),
  ('premium_annual',  'Premium annuel', 12, 20);

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'subscriptions' AND column_name = 'order_id'),
    'DO 0',
    'ALTER TABLE `subscriptions`
       ADD COLUMN `plan_id` int(11) DEFAULT NULL AFTER `user_id`,
       ADD COLUMN `order_id` int(11) DEFAULT NULL AFTER `plan_id`,
       ADD COLUMN `cancelled_at` datetime DEFAULT NULL AFTER `end_date`,
       ADD COLUMN `reminder_7_sent_at` datetime DEFAULT NULL AFTER `cancelled_at`,
       ADD COLUMN `reminder_1_sent_at` datetime DEFAULT NULL AFTER `reminder_7_sent_at`,
       ADD UNIQUE KEY `commande` (`order_id`),
       ADD KEY `periode` (`user_id`, `status`, `end_date`),
       ADD KEY `echeance` (`status`, `end_date`),
       ADD CONSTRAINT `subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
       ADD CONSTRAINT `subscriptions_commande` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- Les lignes `pending` de l'ancien tunnel n'ont jamais ete payees : elles
-- n'ont ouvert et n'ouvriront aucun droit.
UPDATE `subscriptions` SET `status` = 'failed' WHERE `status` = 'pending' AND `order_id` IS NULL;

-- DOWN

ALTER TABLE `subscriptions`
  DROP FOREIGN KEY `subscriptions_commande`,
  DROP FOREIGN KEY `subscriptions_plan`,
  DROP INDEX `echeance`,
  DROP INDEX `periode`,
  DROP INDEX `commande`,
  DROP COLUMN `reminder_1_sent_at`,
  DROP COLUMN `reminder_7_sent_at`,
  DROP COLUMN `cancelled_at`,
  DROP COLUMN `order_id`,
  DROP COLUMN `plan_id`;
DROP TABLE IF EXISTS `subscription_plans`;
