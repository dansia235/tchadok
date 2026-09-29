<?php
/**
 * Abonnements Premium (LOT 7), en ligne de commande.
 *
 *   php scripts/abonnements.php echeances
 *       A planifier chaque jour : clot les periodes echues (le membre perd
 *       ses avantages) et envoie les rappels J-7 et J-1.
 *   php scripts/abonnements.php statut <email|id>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    echo "  [ERREUR] Base injoignable.\n";
    exit(1);
}

switch ($argv[1] ?? '') {
    case 'echeances':
        $bilan = Abonnements::traiterEcheances();
        printf("[%s] abonnements : %d periode(s) close(s), %d rappel(s) envoye(s)\n", date('c'), $bilan['expirees'], $bilan['rappels']);
        break;

    case 'statut':
        $ref = (string) ($argv[2] ?? '');
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ? OR email = ? OR username = ?');
        $stmt->execute([ctype_digit($ref) ? (int) $ref : 0, $ref, $ref]);
        $id = (int) $stmt->fetchColumn();
        if ($id <= 0) {
            echo "  [ERREUR] Compte inconnu.\n";
            exit(1);
        }
        $s = Abonnements::situation($id);
        printf("  Premium : %s%s\n", Abonnements::estPremium($id) ? 'oui' : 'non', $s['fin'] ? ' (jusqu\'au ' . $s['fin'] . ')' : '');
        foreach ($s['historique'] as $l) {
            printf("  %s -> %s  %-10s %s%s\n", $l['start_date'], $l['end_date'], $l['status'], $l['label'], $l['cancelled_at'] ? ' (resilie)' : '');
        }
        break;

    default:
        echo "  php scripts/abonnements.php echeances | statut <compte>\n";
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
