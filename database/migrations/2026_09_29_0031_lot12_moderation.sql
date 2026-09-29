-- LOT 12 : moderation et onboarding artiste.
--
-- MOD-07  verification de l'e-mail : jeton (empreinte), expiration, renvoi.
-- MOD-01  machine a etats du contenu, IMPOSEE PAR LA BASE :
--           draft -> pending -> approved | rejected ; rejected -> pending ;
--           pending -> draft (retrait de la soumission) ;
--           approved -> offline -> approved (retrait / remise en ligne).
--         Toute autre transition est refusee, d'ou qu'elle vienne. Chaque
--         transition est journalisee (content_transitions, immuable).
-- MOD-02  file de moderation : une revue par soumission, affectation, grille.
-- MOD-03  signalements : categories, priorite, retrait provisoire, delai de
--         reponse, decision motivee, contre-notification.
-- MOD-04  controles automatiques au depot : caracteristiques audio,
--         empreinte du fichier (doublons exacts), resultats traces.
-- MOD-06  dossier artiste : etapes, niveau (decouverte, verifie,
--         partenaire), pieces chiffrees hors racine web.

-- UP

-- MOD-07 / MOD-06 : verifications du compte.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'email_verified_at'),
    'DO 0',
    'ALTER TABLE `users`
       ADD COLUMN `email_verified_at` datetime DEFAULT NULL AFTER `email_verified`,
       ADD COLUMN `verification_expires_at` datetime DEFAULT NULL AFTER `verification_token`,
       ADD COLUMN `verification_sent_at` datetime DEFAULT NULL AFTER `verification_expires_at`,
       ADD COLUMN `phone_verified_at` datetime DEFAULT NULL AFTER `phone`,
       ADD COLUMN `phone_code_hash` char(64) DEFAULT NULL AFTER `phone_verified_at`,
       ADD COLUMN `phone_code_expires_at` datetime DEFAULT NULL AFTER `phone_code_hash`'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;
UPDATE `users` SET `email_verified_at` = COALESCE(`email_verified_at`, `created_at`) WHERE `email_verified` = 1;

-- MOD-01 : etat « hors ligne » (retire par l'administration, reversible).
ALTER TABLE `tracks` MODIFY `status` enum('draft','pending','approved','rejected','offline') DEFAULT 'draft';
ALTER TABLE `releases` MODIFY `status` enum('draft','pending','approved','rejected','offline') NOT NULL DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS `content_transitions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `content_type` enum('track','release') NOT NULL,
  `content_id` int(11) NOT NULL,
  `from_status` varchar(20) NOT NULL,
  `to_status` varchar(20) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contenu` (`content_type`, `content_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `transitions_sans_modification`;
DELIMITER //
CREATE TRIGGER `transitions_sans_modification` BEFORE UPDATE ON `content_transitions` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content_transitions est un journal : aucune modification.';
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `transitions_sans_suppression`;
DELIMITER //
CREATE TRIGGER `transitions_sans_suppression` BEFORE DELETE ON `content_transitions` FOR EACH ROW
BEGIN
    IF (OLD.content_type = 'track' AND EXISTS (SELECT 1 FROM `tracks` WHERE `id` = OLD.content_id))
       OR (OLD.content_type = 'release' AND EXISTS (SELECT 1 FROM `releases` WHERE `id` = OLD.content_id)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content_transitions est un journal : aucune suppression.';
    END IF;
END//
DELIMITER ;

-- Graphe des transitions, pour les titres et les sorties.
DROP TRIGGER IF EXISTS `titre_transition_autorisee`;
DELIMITER //
CREATE TRIGGER `titre_transition_autorisee` BEFORE UPDATE ON `tracks` FOR EACH ROW
BEGIN
    IF NOT (NEW.status <=> OLD.status) AND NOT (
           (OLD.status = 'draft' AND NEW.status = 'pending')
        OR (OLD.status = 'pending' AND NEW.status IN ('approved', 'rejected', 'draft'))
        OR (OLD.status = 'rejected' AND NEW.status = 'pending')
        OR (OLD.status = 'approved' AND NEW.status = 'offline')
        OR (OLD.status = 'offline' AND NEW.status = 'approved')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transition de statut non autorisee pour un titre.';
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `sortie_transition_autorisee`;
DELIMITER //
CREATE TRIGGER `sortie_transition_autorisee` BEFORE UPDATE ON `releases` FOR EACH ROW
BEGIN
    IF NOT (NEW.status <=> OLD.status) AND NOT (
           (OLD.status = 'draft' AND NEW.status = 'pending')
        OR (OLD.status = 'pending' AND NEW.status IN ('approved', 'rejected', 'draft'))
        OR (OLD.status = 'rejected' AND NEW.status = 'pending')
        OR (OLD.status = 'approved' AND NEW.status = 'offline')
        OR (OLD.status = 'offline' AND NEW.status = 'approved')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transition de statut non autorisee pour une sortie.';
    END IF;
END//
DELIMITER ;

-- MOD-02 : une revue par soumission.
CREATE TABLE IF NOT EXISTS `moderation_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` enum('track','release') NOT NULL,
  `content_id` int(11) NOT NULL,
  `artist_id` int(11) NOT NULL,
  `submitted_at` datetime NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `decision` enum('approved','rejected','correction') DEFAULT NULL,
  `reason_code` varchar(40) DEFAULT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `checklist` varchar(500) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file` (`decision`, `submitted_at`),
  KEY `contenu` (`content_type`, `content_id`),
  CONSTRAINT `moderation_reviews_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `moderation_reviews_ibfk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refus_motive` CHECK (`decision` IS NULL OR `decision` = 'approved' OR CHAR_LENGTH(TRIM(COALESCE(`reason`, ''))) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- MOD-04 : caracteristiques lues dans le fichier, empreinte, controles.
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tracks' AND column_name = 'audio_sha256'),
    'DO 0',
    'ALTER TABLE `tracks`
       ADD COLUMN `audio_sha256` char(64) DEFAULT NULL AFTER `audio_file`,
       ADD COLUMN `audio_bitrate` int(11) DEFAULT NULL AFTER `audio_sha256`,
       ADD COLUMN `audio_sample_rate` int(11) DEFAULT NULL AFTER `audio_bitrate`,
       ADD COLUMN `audio_channels` tinyint(4) DEFAULT NULL AFTER `audio_sample_rate`,
       ADD COLUMN `credits` varchar(500) DEFAULT NULL AFTER `lyrics`,
       ADD KEY `empreinte_audio` (`audio_sha256`)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

CREATE TABLE IF NOT EXISTS `content_checks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `check_code` varchar(40) NOT NULL,
  `result` enum('ok','refus','signal','non_verifie') NOT NULL,
  `detail` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `titre` (`track_id`),
  CONSTRAINT `content_checks_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- MOD-03 : signalements (la table existait, ni lue ni ecrite).
SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reports' AND column_name = 'category'),
    'DO 0',
    'ALTER TABLE `reports`
       ADD COLUMN `category` enum(''droit_auteur'',''inapproprie'',''spam'',''faux_profil'') NOT NULL DEFAULT ''inapproprie'' AFTER `reported_id`,
       ADD COLUMN `priority` tinyint(4) NOT NULL DEFAULT 2 AFTER `category`,
       ADD COLUMN `claimant_name` varchar(150) DEFAULT NULL AFTER `description`,
       ADD COLUMN `claimant_contact` varchar(190) DEFAULT NULL AFTER `claimant_name`,
       ADD COLUMN `takedown_at` datetime DEFAULT NULL,
       ADD COLUMN `response_deadline` datetime DEFAULT NULL,
       ADD COLUMN `counter_notice` text DEFAULT NULL,
       ADD COLUMN `counter_notice_at` datetime DEFAULT NULL,
       ADD COLUMN `decision` enum(''maintenu'',''retire'',''rejete'') DEFAULT NULL,
       ADD COLUMN `decision_reason` varchar(1000) DEFAULT NULL,
       ADD COLUMN `decided_by` int(11) DEFAULT NULL,
       ADD COLUMN `decided_at` datetime DEFAULT NULL,
       ADD KEY `file` (`status`, `priority`, `created_at`),
       ADD CONSTRAINT `signalement_decision_motivee` CHECK (`decision` IS NULL OR CHAR_LENGTH(TRIM(COALESCE(`decision_reason`, ''''))) >= 5)'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;
ALTER TABLE `reports` MODIFY `reported_type` enum('track','album','release','artist','user','comment','post') NOT NULL;

-- MOD-06 : dossier artiste.
CREATE TABLE IF NOT EXISTS `artist_dossiers` (
  `artist_id` int(11) NOT NULL,
  `level` enum('aucun','decouverte','verifie','partenaire') NOT NULL DEFAULT 'aucun',
  `status` enum('brouillon','soumis','valide','a_completer','refuse') NOT NULL DEFAULT 'brouillon',
  `identity_doc` varchar(100) DEFAULT NULL,
  `selfie_doc` varchar(100) DEFAULT NULL,
  `identity_verified_at` datetime DEFAULT NULL,
  `identity_verified_by` int(11) DEFAULT NULL,
  `rights_declared_at` datetime DEFAULT NULL,
  `fiscal_regime` varchar(60) DEFAULT NULL,
  `fiscal_id` varchar(60) DEFAULT NULL,
  `negotiated_commission` decimal(5,2) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decision_reason` varchar(1000) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`artist_id`),
  KEY `etat` (`status`, `submitted_at`),
  CONSTRAINT `artist_dossiers_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `artist_dossiers_ibfk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dossier_decision_motivee` CHECK (`status` NOT IN ('a_completer', 'refuse') OR CHAR_LENGTH(TRIM(COALESCE(`decision_reason`, ''))) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `permissions` (`slug`, `domaine`, `libelle`)
VALUES ('artiste.valider', 'catalogue', 'Valider les dossiers artistes et consulter les pieces d''identite');
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.slug = 'artiste.valider'
 WHERE r.slug IN ('super_admin', 'moderateur_catalogue');

-- DOWN

SET @instruction = (SELECT IF(
    EXISTS(SELECT 1 FROM `content_transitions`) OR EXISTS(SELECT 1 FROM `moderation_reviews`) OR EXISTS(SELECT 1 FROM `artist_dossiers`)
    OR EXISTS(SELECT 1 FROM `tracks` WHERE `status` = 'offline') OR EXISTS(SELECT 1 FROM `releases` WHERE `status` = 'offline'),
    'SELECT 1 FROM `annulation_impossible__des_donnees_de_moderation_existent`',
    'DO 0'
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

DELETE rp FROM `role_permissions` rp JOIN `permissions` p ON p.id = rp.permission_id WHERE p.slug = 'artiste.valider';
DELETE FROM `permissions` WHERE `slug` = 'artiste.valider';
DROP TABLE IF EXISTS `artist_dossiers`;
DROP TABLE IF EXISTS `content_checks`;
DROP TABLE IF EXISTS `moderation_reviews`;
DROP TRIGGER IF EXISTS `sortie_transition_autorisee`;
DROP TRIGGER IF EXISTS `titre_transition_autorisee`;
DROP TRIGGER IF EXISTS `transitions_sans_suppression`;
DROP TRIGGER IF EXISTS `transitions_sans_modification`;
DROP TABLE IF EXISTS `content_transitions`;
ALTER TABLE `tracks` MODIFY `status` enum('draft','pending','approved','rejected') DEFAULT 'draft';
ALTER TABLE `releases` MODIFY `status` enum('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft';
ALTER TABLE `tracks` DROP KEY `empreinte_audio`, DROP COLUMN `credits`, DROP COLUMN `audio_channels`, DROP COLUMN `audio_sample_rate`, DROP COLUMN `audio_bitrate`, DROP COLUMN `audio_sha256`;
ALTER TABLE `reports` DROP CONSTRAINT `signalement_decision_motivee`, DROP KEY `file`,
  DROP COLUMN `decided_at`, DROP COLUMN `decided_by`, DROP COLUMN `decision_reason`, DROP COLUMN `decision`,
  DROP COLUMN `counter_notice_at`, DROP COLUMN `counter_notice`, DROP COLUMN `response_deadline`, DROP COLUMN `takedown_at`,
  DROP COLUMN `claimant_contact`, DROP COLUMN `claimant_name`, DROP COLUMN `priority`, DROP COLUMN `category`;
ALTER TABLE `users` DROP COLUMN `phone_code_expires_at`, DROP COLUMN `phone_code_hash`, DROP COLUMN `phone_verified_at`,
  DROP COLUMN `verification_sent_at`, DROP COLUMN `verification_expires_at`, DROP COLUMN `email_verified_at`;
