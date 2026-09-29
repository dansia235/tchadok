<?php
/**
 * Rapprochement avec les releves des operateurs (PAY-10), en ligne de commande.
 *
 *   php scripts/rapprochement.php                        la veille, toutes passerelles
 *   php scripts/rapprochement.php --date=2026-09-28 [--passerelle=visa]
 *   php scripts/rapprochement.php ecarts                 ecarts ouverts
 *   php scripts/rapprochement.php clore <id> --motif="..."
 *
 * A planifier chaque jour, apres minuit (cron / Planificateur de taches).
 * L'ecran d'administration est admin/rapprochement.php.
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

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $argument, $m)) {
        $options[$m[1]] = $m[2];
    }
}
$commande = isset($argv[1]) && !str_starts_with($argv[1], '--') ? $argv[1] : 'executer';

switch ($commande) {
    case 'executer':
        $date = $options['date'] ?? date('Y-m-d', strtotime('-1 day'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            sortie('  [ERREUR] Date attendue au format AAAA-MM-JJ.');
            exit(1);
        }
        $bilans = isset($options['passerelle'])
            ? [$options['passerelle'] => Rapprochement::executer($options['passerelle'], $date)]
            : Rapprochement::executerTout($date);

        sortie();
        sortie("  Rapprochement du {$date}");
        $erreur = false;
        foreach ($bilans as $code => $b) {
            $erreur = $erreur || $b['statut'] === 'erreur';
            sortie(sprintf('  %-13s %-7s releve %4d ligne(s), Tchadok %4d paiement(s), %d ecart(s)%s',
                $code, $b['statut'], $b['operateur'], $b['plateforme'], $b['ecarts'], $b['erreur'] ? ' -- ' . $b['erreur'] : ''));
        }
        sortie();
        exit($erreur ? 2 : 0);

    case 'ecarts':
        $ouverts = Rapprochement::ouverts();
        sortie();
        foreach ($ouverts as $e) {
            sortie(sprintf('  #%-5d %s %-12s %-27s %-22s %s', $e['id'], $e['statement_date'], $e['gateway'], $e['type'], $e['gateway_ref'] ?? '-', $e['detail']));
        }
        sortie();
        sortie('  ' . count($ouverts) . ' ecart(s) ouvert(s).');
        sortie();
        break;

    case 'clore':
        $id = (int) ($argv[2] ?? 0);
        $motif = trim((string) ($options['motif'] ?? ''));
        if ($id <= 0 || mb_strlen($motif) < 5) {
            sortie('  [ERREUR] Usage : clore <id> --motif="..." (5 caracteres au moins).');
            exit(1);
        }
        if (!Rapprochement::cloturer($id, $motif . ' (en ligne de commande, ' . get_current_user() . ')', null)) {
            sortie('  [ERREUR] Ecart introuvable ou deja clos.');
            exit(1);
        }
        sortie("  [ok] Ecart #{$id} clos.");
        break;

    default:
        sortie('  php scripts/rapprochement.php [--date=AAAA-MM-JJ] [--passerelle=code]');
        sortie('  php scripts/rapprochement.php ecarts');
        sortie('  php scripts/rapprochement.php clore <id> --motif="..."');
        exit(1);
}

exit(0);
