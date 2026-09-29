<?php
/**
 * Chargement de la couche paiement (LOT 5).
 *
 *   require_once __DIR__ . '/includes/paiement/chargement.php';
 *
 * Suppose includes/functions.php deja charge (EnvLoader, base, Commandes).
 */

declare(strict_types=1);

require_once __DIR__ . '/../commandes.php';
require_once __DIR__ . '/Devises.php';
require_once __DIR__ . '/PasserellePaiement.php';
require_once __DIR__ . '/PasserelleGenerique.php';
require_once __DIR__ . '/passerelles/AirtelMoney.php';
require_once __DIR__ . '/passerelles/MoovMoney.php';
require_once __DIR__ . '/passerelles/Visa.php';
require_once __DIR__ . '/passerelles/Gimac.php';
require_once __DIR__ . '/FabriquePasserelles.php';
require_once __DIR__ . '/Paiements.php';
require_once __DIR__ . '/Rapprochement.php';
require_once __DIR__ . '/Remboursements.php';
require_once __DIR__ . '/Portefeuille.php';
require_once __DIR__ . '/Contrats.php';
require_once __DIR__ . '/Versements.php';
require_once __DIR__ . '/Repartition.php';
require_once __DIR__ . '/../factures.php';
require_once __DIR__ . '/../panier.php';
