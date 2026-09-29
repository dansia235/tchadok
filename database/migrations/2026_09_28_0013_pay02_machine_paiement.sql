-- PAY-02 : machine a etats du paiement.
--
-- DATA-05 avait pose les tables ; il leur manquait les etats que la realite
-- des operateurs impose :
--
--   orders.expired     l'operateur n'a jamais repondu (abonne qui ignore le
--                      message USSD, callback perdu). Verifie aupres de
--                      l'operateur AVANT de conclure : le callback peut
--                      s'etre egare.
--   orders.review      l'argent a bouge mais quelque chose cloche : montant
--                      du callback different de la commande, ou second
--                      encaissement d'une commande deja payee. Jamais
--                      accepte automatiquement -- un humain tranche.
--   orders.disputed    contestation d'un paiement par carte (chargeback) :
--                      les droits d'acces sont retires.
--
-- Une commande echouee, annulee ou expiree peut etre retentee avec un autre
-- moyen de paiement : chaque essai est une ligne de payment_intents
-- (`attempt`). Une commande payee, en revue, remboursee ou contestee ne
-- l'est plus.
--
-- Colonnes ajoutees a payment_intents :
--   idempotency_key      envoyee a l'operateur a l'initiation : un appel
--                        rejoue (delai reseau) ne debite pas deux fois.
--   redirect_url         page hebergee de l'acquereur (VISA) : la carte est
--                        saisie chez lui, jamais chez nous.
--   amount_reported      montant annonce par l'operateur, conserve pour la
--                        revue quand il differe.
--   completed_at         instant ou la tentative a atteint un etat final.
--   last_checked_at      derniere consultation du statut chez l'operateur.

-- UP

ALTER TABLE `orders`
  MODIFY `status` enum('cart','awaiting_payment','paid','failed','cancelled','expired','review','refunded','disputed')
         NOT NULL DEFAULT 'cart';

ALTER TABLE `payment_intents`
  MODIFY `status` enum('created','pending','succeeded','failed','expired','cancelled','review')
         NOT NULL DEFAULT 'created';

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'payment_intents' AND column_name = 'idempotency_key'),
    'DO 0',
    'ALTER TABLE `payment_intents`
       ADD COLUMN `idempotency_key` char(32) DEFAULT NULL AFTER `gateway_ref`,
       ADD COLUMN `redirect_url` varchar(500) DEFAULT NULL AFTER `msisdn`,
       ADD COLUMN `amount_reported` decimal(10,2) DEFAULT NULL AFTER `amount`,
       ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `expires_at`,
       ADD COLUMN `last_checked_at` datetime DEFAULT NULL AFTER `completed_at`,
       ADD UNIQUE KEY `idempotence` (`idempotency_key`),
       ADD KEY `echeance` (`status`, `expires_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

-- Refuse si des lignes portent les nouveaux etats : MySQL les tronquerait en
-- chaine vide, et une commande payee en revue deviendrait illisible. Hors
-- procedure, SIGNAL n'est pas disponible : la requete sur une table
-- inexistante interrompt l'annulation en nommant la raison.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `orders` WHERE `status` IN ('expired','review','disputed'))
    OR EXISTS(SELECT 1 FROM `payment_intents` WHERE `status` = 'review'),
    'SELECT 1 FROM `annulation_impossible__des_commandes_portent_les_etats_de_PAY02`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

ALTER TABLE `payment_intents`
  DROP INDEX `echeance`,
  DROP INDEX `idempotence`,
  DROP COLUMN `last_checked_at`,
  DROP COLUMN `completed_at`,
  DROP COLUMN `amount_reported`,
  DROP COLUMN `redirect_url`,
  DROP COLUMN `idempotency_key`;
ALTER TABLE `payment_intents`
  MODIFY `status` enum('created','pending','succeeded','failed','expired','cancelled') NOT NULL DEFAULT 'created';
ALTER TABLE `orders`
  MODIFY `status` enum('cart','awaiting_payment','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'cart';
