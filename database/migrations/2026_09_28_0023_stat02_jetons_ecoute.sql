-- STAT-02 : enregistrement des ecoutes securise.
--
-- Avant : POST /api/stream.php enregistrait une ecoute pour n'importe quel
-- titre publie, avec une duree annoncee par le navigateur (et le pays, la
-- ville). Une boucle suffisait a fabriquer des ecoutes.
--
-- Apres : le serveur emet un JETON D'ECOUTE a l'ouverture d'un titre (acces
-- complet seulement). L'ecoute n'est acceptee qu'avec ce jeton, consomme une
-- seule fois ; le seuil de 30 s se mesure depuis l'heure d'emission, cote
-- serveur. Le jeton n'est garde qu'en empreinte (SHA-256) : une fuite de la
-- table ne permet pas de rejouer.
--
-- `listener_key` identifie l'auditeur pour la deduplication (1 ecoute par
-- auditeur, titre et heure) : « u:<id> » pour un compte, « a:<empreinte> »
-- pour un visiteur (IP tronquee + navigateur, sel quotidien : pas
-- d'identifiant persistant).

-- UP

CREATE TABLE IF NOT EXISTS `listening_sessions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `track_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `listener_key` varchar(80) NOT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'web',
  `issued_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `outcome` varchar(30) DEFAULT NULL,
  `stream_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jeton` (`token_hash`),
  KEY `expiration` (`expires_at`),
  KEY `auditeur` (`listener_key`, `track_id`, `issued_at`),
  CONSTRAINT `listening_sessions_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listening_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'streams' AND column_name = 'listener_key'),
    'DO 0',
    'ALTER TABLE `streams`
       ADD COLUMN `listener_key` varchar(80) DEFAULT NULL AFTER `user_id`,
       ADD COLUMN `session_id` bigint(20) DEFAULT NULL AFTER `source`,
       ADD UNIQUE KEY `session_unique` (`session_id`),
       ADD KEY `deduplication` (`listener_key`, `track_id`, `created_at`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `streams` WHERE `session_id` IS NOT NULL),
    'SELECT 1 FROM `annulation_impossible__des_ecoutes_a_jeton_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

ALTER TABLE `streams`
  DROP KEY `deduplication`,
  DROP KEY `session_unique`,
  DROP COLUMN `session_id`,
  DROP COLUMN `listener_key`;
DROP TABLE IF EXISTS `listening_sessions`;
