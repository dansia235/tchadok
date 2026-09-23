-- SEC-19 : roles, permissions et journal d'audit.
--
-- Avant : isAdmin() est un booleen. La colonne admins.permissions contient
-- '["all"]' et n'est jamais lue. Tout administrateur peut donc tout faire, y
-- compris valider de l'argent, et rien n'est trace : impossible de savoir qui
-- a approuve un titre, valide un versement ou supprime un compte.
--
-- Apres : des permissions nommees, portees par des roles, et un journal en
-- ecriture seule.
--
-- Sur la separation des pouvoirs : creer un versement et l'executer sont deux
-- permissions distinctes, et le code refuse en plus qu'une meme personne fasse
-- les deux sur le meme versement. Le controle est technique, pas seulement
-- procedural.

-- UP

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `domaine` varchar(40) NOT NULL,
  `libelle` varchar(160) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `domaine` (`domaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `attribue_par` int(11) DEFAULT NULL,
  `attribue_le` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Journal d'audit. L'application n'y fait que des INSERT : aucune ligne n'est
-- modifiee ni supprimee par le code. En production, le compte MySQL applicatif
-- ne doit avoir ni UPDATE ni DELETE sur cette table -- voir la procedure VPS.
--
-- ON DELETE SET NULL sur l'acteur : supprimer un compte ne doit pas effacer la
-- trace de ce qu'il a fait.
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(120) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `target_type` varchar(60) DEFAULT NULL,
  `target_id` varchar(60) DEFAULT NULL,
  `before_state` text DEFAULT NULL,
  `after_state` text DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `action_date` (`action`, `created_at`),
  KEY `acteur_date` (`actor_id`, `created_at`),
  KEY `cible` (`target_type`, `target_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Les sept roles. INSERT IGNORE : rejouer la migration ne duplique rien.
INSERT IGNORE INTO `roles` (`slug`, `nom`, `description`) VALUES
('super_admin',          'Super-administrateur',     'Toutes les permissions, y compris la gestion des roles. Une a deux personnes nommees.'),
('admin_plateforme',     'Administrateur plateforme','Configuration, taxonomie, tarifs, mise en avant. Ni finance ni suppression de compte.'),
('responsable_finance',  'Responsable finance',      'Transactions, versements, remboursements, rapports. Aucune modification de contenu.'),
('moderateur_catalogue', 'Moderateur catalogue',     'File de moderation et signalements. Ni finance ni configuration.'),
('editorial',            'Editorial',                'Blog, playlists, radio, podcasts, mises en avant. Statistiques en lecture.'),
('support',              'Support',                  'Lecture des comptes, reinitialisation de mot de passe a la demande. Aucune suppression.'),
('analyste',             'Analyste',                 'Lecture seule des tableaux de bord et des exports.');

INSERT IGNORE INTO `permissions` (`slug`, `domaine`, `libelle`) VALUES
('admin.acces',                     'admin',       'Acceder a la console d''administration'),
('configuration.gerer',             'admin',       'Modifier la configuration de la plateforme'),
('role.gerer',                      'admin',       'Attribuer et retirer les roles'),
('journal.lire',                    'admin',       'Consulter le journal d''audit'),
('catalogue.moderer',               'catalogue',   'Approuver ou rejeter un contenu'),
('catalogue.editer',                'catalogue',   'Creer et modifier titres et albums'),
('catalogue.supprimer',             'catalogue',   'Supprimer un contenu du catalogue'),
('signalement.traiter',             'catalogue',   'Traiter les signalements'),
('taxonomie.gerer',                 'catalogue',   'Gerer genres et categories'),
('tarif.modifier',                  'catalogue',   'Modifier prix et taux de commission'),
('editorial.gerer',                 'editorial',   'Gerer blog, playlists, radio et podcasts'),
('editorial.mise-en-avant',         'editorial',   'Choisir les mises en avant'),
('finance.transaction.lire',        'finance',     'Consulter les transactions'),
('finance.versement.creer',         'finance',     'Preparer un versement aux artistes'),
('finance.versement.executer',      'finance',     'Executer un versement prepare'),
('finance.remboursement.executer',  'finance',     'Rembourser un achat'),
('finance.rapport.lire',            'finance',     'Consulter les rapports financiers'),
('compte.lire',                     'compte',      'Consulter les comptes utilisateurs'),
('compte.modifier',                 'compte',      'Modifier un compte utilisateur'),
('compte.supprimer',                'compte',      'Supprimer un compte utilisateur'),
('compte.reinitialiser-mot-de-passe','compte',     'Reinitialiser le mot de passe d''un compte'),
('ticket.traiter',                  'compte',      'Traiter les demandes de support'),
('statistique.lire',                'mesure',      'Consulter les statistiques'),
('export.effectuer',                'mesure',      'Exporter des donnees');

-- Le super-administrateur recoit tout. Le code lui accorde egalement les
-- permissions ajoutees plus tard : sans cela, creer une permission la rendrait
-- inaccessible a tout le monde, y compris au seul role cense pouvoir tout
-- faire.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.slug = 'super_admin';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'admin_plateforme' AND p.slug IN (
    'admin.acces', 'configuration.gerer', 'taxonomie.gerer', 'tarif.modifier',
    'catalogue.editer', 'catalogue.moderer', 'catalogue.supprimer',
    'editorial.gerer', 'editorial.mise-en-avant',
    'compte.lire', 'statistique.lire', 'export.effectuer'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'responsable_finance' AND p.slug IN (
    'admin.acces', 'finance.transaction.lire', 'finance.versement.creer',
    'finance.versement.executer', 'finance.remboursement.executer',
    'finance.rapport.lire', 'compte.lire', 'statistique.lire', 'export.effectuer'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'moderateur_catalogue' AND p.slug IN (
    'admin.acces', 'catalogue.moderer', 'signalement.traiter', 'statistique.lire'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'editorial' AND p.slug IN (
    'admin.acces', 'editorial.gerer', 'editorial.mise-en-avant', 'statistique.lire'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'support' AND p.slug IN (
    'admin.acces', 'compte.lire', 'compte.reinitialiser-mot-de-passe', 'ticket.traiter'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'analyste' AND p.slug IN (
    'admin.acces', 'statistique.lire', 'export.effectuer'
);

-- Reprise de l'existant : chaque ligne de `admins` recoit le role equivalent.
-- Personne ne perd l'acces au moment de la migration.
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT a.user_id, r.id
FROM `admins` a
JOIN `roles` r ON r.slug = CASE a.role
    WHEN 'super_admin' THEN 'super_admin'
    WHEN 'moderator'   THEN 'moderateur_catalogue'
    ELSE 'admin_plateforme'
END
WHERE a.user_id IS NOT NULL;

-- DOWN

DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
