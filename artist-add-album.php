<?php
/**
 * Ancien parcours de publication, remplace par publier.php (MOD-05) : un seul
 * parcours, les memes regles pour tous. Conserve pour ne pas casser les
 * liens existants.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/publier.php'));
}

// PAYOUT-05 : aucune publication sans acceptation du contrat en vigueur.
require_once __DIR__ . '/includes/paiement/chargement.php';
$stmt = TchadokDatabase::getInstance()->getConnection()->prepare('SELECT id FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([(int) $_SESSION['user_id']]);
$artistId = (int) $stmt->fetchColumn();
if ($artistId > 0 && Contrats::acceptationRequise($artistId)) {
    redirect(SITE_URL . '/contrat.php?retour=' . urlencode('/' . basename(__FILE__)));
}

header('Location: ' . SITE_URL . '/publier.php', true, 301);
exit;