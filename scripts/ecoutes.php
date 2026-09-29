<?php
/**
 * Ecoutes (LOT 9), en ligne de commande.
 *
 *   php scripts/ecoutes.php certifier
 *       Juge les ecoutes de plus de 2 h (STAT-03) : certifiees, exclues ou en
 *       quarantaine. A planifier toutes les 15 minutes.
 *   php scripts/ecoutes.php sante [jours]
 *       Brut, certifie, exclu, quarantaine. Code de sortie 1 si la part en
 *       quarantaine depasse CERTIF_ALERTE_POURCENT (5 %) : a brancher sur la
 *       supervision.
 *   php scripts/ecoutes.php purger
 *       Supprime les jetons d'ecoute expires et inutilises (chaque nuit).
 *   php scripts/ecoutes.php agreger
 *       STAT-05/06 : recalcule les jours touches (ecoutes certifiees, ventes,
 *       remboursements) puis les compteurs concernes. Chaque nuit, apres
 *       « certifier ». Idempotent.
 *   php scripts/ecoutes.php controler
 *       Compare compteurs publics et agregats ; code 1 en cas de derive.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/ecoutes.php';
require_once $racine . '/includes/certification.php';
require_once $racine . '/includes/agregats.php';

switch ($argv[1] ?? '') {
    case 'certifier':
        $b = Certification::traiter();
        printf("[%s] ecoutes jugees : %d (certifiees %d, exclues %d, quarantaine %d, alertes %d)\n",
            date('c'), $b['jugees'], $b['certifiees'], $b['exclues'], $b['quarantaine'], $b['alertes']);
        break;

    case 'sante':
        $jours = max(1, (int) ($argv[2] ?? 30));
        $s = Certification::sante($jours);
        printf("  %d derniers jours : brut %d, certifiees %d, exclues %d, quarantaine %d, rejetees %d, en attente %d\n",
            $jours, $s['brut'], $s['certifiees'], $s['exclues'], $s['quarantaine'], $s['rejetees'], $s['en_attente']);
        printf("  Part en quarantaine ou rejetee : %s %% (seuil d'alerte %s %%)\n", $s['part_quarantaine'], Certification::seuilAlerte());
        exit($s['part_quarantaine'] > Certification::seuilAlerte() ? 1 : 0);

    case 'purger':
        printf("  %d jeton(s) d'ecoute expire(s) supprime(s)\n", Ecoutes::purgerJetons());
        break;

    case 'agreger':
        $r = Agregats::actualiser();
        printf("[%s] agregats : %d jour(s) recalcule(s), %d titre(s) concerne(s)\n", date('c'), $r['jours'], $r['titres']);
        break;

    case 'controler':
        $ecarts = Compteurs::controler();
        echo $ecarts === [] ? "  [ok] Compteurs publics conformes aux agregats.\n" : '  [DERIVE] ' . implode("\n  [DERIVE] ", $ecarts) . "\n";
        exit($ecarts === [] ? 0 : 1);

    default:
        echo "  php scripts/ecoutes.php certifier | sante [jours] | purger | agreger | controler\n";
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
