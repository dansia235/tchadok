<?php
/**
 * Portefeuille (SHOP-06), en ligne de commande.
 *
 *   php scripts/portefeuille.php verifier
 *       Compare chaque solde (cache users.wallet_balance) a son journal.
 *       Code de sortie 1 en cas d'ecart : a planifier chaque nuit.
 *   php scripts/portefeuille.php solde <email|id>
 *   php scripts/portefeuille.php ajuster <email|id> <montant> --motif="..."
 *       Correction motivee (montant negatif pour debiter), tracee au journal
 *       d'audit. Le journal n'est jamais modifie : on ajoute une correction.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';

function sortie(string $texte = ''): void
{
    echo $texte, PHP_EOL;
}

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    sortie('  [ERREUR] Base injoignable.');
    exit(1);
}

$trouver = static function (string $ref) use ($db): int {
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? OR email = ? OR username = ? LIMIT 1');
    $stmt->execute([ctype_digit($ref) ? (int) $ref : 0, $ref, $ref]);
    $id = (int) $stmt->fetchColumn();
    if ($id <= 0) {
        sortie("  [ERREUR] Compte inconnu : {$ref}");
        exit(1);
    }
    return $id;
};

$motif = '';
foreach (array_slice($argv, 2) as $a) {
    if (preg_match('/^--motif=(.+)$/s', $a, $m)) {
        $motif = trim($m[1]);
    }
}

switch ($argv[1] ?? '') {
    case 'verifier':
        $ecarts = Portefeuille::verifier();
        if ($ecarts === []) {
            sortie('  [ok] Tous les soldes concordent avec leur journal.');
            exit(0);
        }
        foreach ($ecarts as $e) {
            sortie(sprintf('  [ECART] compte %d : cache %.2f, journal %.2f', $e['user_id'], $e['cache'], $e['journal']));
        }
        error_log('[Tchadok][portefeuille][ALERTE] ' . count($ecarts) . ' solde(s) divergent de leur journal');
        exit(1);

    case 'solde':
        $id = $trouver((string) ($argv[2] ?? ''));
        sortie(sprintf('  Solde : %s FCFA', number_format(Portefeuille::solde($id), 0, ',', ' ')));
        foreach (array_slice(Portefeuille::mouvements($id, 20), 0, 20) as $m) {
            sortie(sprintf('  %s  %-10s %10.2f  -> %10.2f  %s', $m['created_at'], $m['type'], $m['amount'], $m['balance_after'], $m['reference']));
        }
        break;

    case 'ajuster':
        $id = $trouver((string) ($argv[2] ?? ''));
        $montant = (float) ($argv[3] ?? 0);
        if ($montant == 0.0 || mb_strlen($motif) < 5) {
            sortie('  [ERREUR] Usage : ajuster <compte> <montant> --motif="..." (5 caracteres au moins).');
            exit(1);
        }
        if (!Portefeuille::ajuster($id, $montant, $motif . ' (en ligne de commande, ' . get_current_user() . ')', null)) {
            sortie('  [ERREUR] Ajustement refuse (solde insuffisant pour un debit ?).');
            exit(1);
        }
        sortie(sprintf('  [ok] Nouveau solde : %s FCFA', number_format(Portefeuille::solde($id), 0, ',', ' ')));
        break;

    default:
        sortie('  php scripts/portefeuille.php verifier | solde <compte> | ajuster <compte> <montant> --motif="..."');
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
