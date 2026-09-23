-- DATA-04 : grille tarifaire administree.
--
-- Les prix Premium vivaient a trois endroits, avec DEUX valeurs
-- contradictoires : config/constants.php disait 2 000 / 20 000 FCFA, tandis que
-- premium.php et premium-payment.php -- les seules pages que le public voit --
-- affichaient 2 500 / 25 000. Les constantes n'etaient lues par personne.
-- La commission etait declaree trois fois et n'entrait dans aucun calcul.
--
-- Un prix ecrit dans le code ne se change pas sans deploiement, et se
-- contredit des qu'il est recopie. Il vit desormais en base.
--
-- LE PLANCHER N'EST PAS UNE FORMALITE
--   Il evite la guerre des prix qui detruirait la valeur percue du catalogue
--   tchadien, et il garantit que la commission couvre les frais mobile money.
--
-- VALEURS DE DEPART -- arbitrees avec le porteur du projet le 23/09/2026 :
--   Premium 2 000 / 20 000 FCFA (l'annuel vaut dix mois).
--   Planchers de la grille reduits de moitie par rapport a la proposition de
--   l'audit, pour laisser les artistes debutants entrer plus bas en prix.

-- UP

CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope` enum('track','release','subscription') NOT NULL,
  -- NULL pour un titre a l'unite : il n'a pas de format de sortie.
  `format` varchar(20) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'XAF',
  `min_price` decimal(8,2) NOT NULL,
  `max_price` decimal(8,2) NOT NULL,
  `suggested` decimal(8,2) NOT NULL,
  -- Part de la plateforme, en pourcentage du prix de vente.
  `commission_rate` decimal(4,2) NOT NULL DEFAULT 15.00,
  `active_from` date NOT NULL,
  `active_to` date DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `portee` (`scope`, `format`, `active_from`),
  KEY `redacteur` (`updated_by`),
  CONSTRAINT `pricing_rules_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Les regles de depart. `INSERT IGNORE` sur une cle stable : rejouer la
-- migration ne doit pas creer de doublon ni ecraser une grille deja ajustee
-- par l'administration.
SET @instruction = (SELECT IF(
    (SELECT COUNT(*) FROM `pricing_rules`) > 0,
    'DO 0',
    "INSERT INTO `pricing_rules`
        (scope, format, currency, min_price, max_price, suggested, commission_rate, active_from)
     VALUES
        ('track',        NULL,             'XAF',   100.00,   1000.00,   300.00, 15.00, '2026-01-01'),
        ('release',      'single',         'XAF',   150.00,   1500.00,   500.00, 15.00, '2026-01-01'),
        ('release',      'maxi_single',    'XAF',   400.00,   2500.00,  1000.00, 15.00, '2026-01-01'),
        ('release',      'ep',             'XAF',   500.00,   3500.00,  1500.00, 15.00, '2026-01-01'),
        ('release',      'album',          'XAF',   750.00,   6000.00,  2500.00, 15.00, '2026-01-01'),
        ('release',      'compilation',    'XAF',   750.00,   8000.00,  3000.00, 20.00, '2026-01-01'),
        ('subscription', 'premium_monthly','XAF',  2000.00,   2000.00,  2000.00,  0.00, '2026-01-01'),
        ('subscription', 'premium_annual', 'XAF', 20000.00,  20000.00, 20000.00,  0.00, '2026-01-01')"
));
PREPARE requete FROM @instruction;
EXECUTE requete;
DEALLOCATE PREPARE requete;

-- DOWN

DROP TABLE IF EXISTS `pricing_rules`;
