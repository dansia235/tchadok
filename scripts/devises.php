<?php
/**
 * Taux de change (PAY-07), en ligne de commande, en attendant l'ecran
 * d'administration (DASH-09).
 *
 *   php scripts/devises.php liste
 *   php scripts/devises.php actualiser
 *       Redemande le cours aux API gratuites (mode automatique, par defaut).
 *       A planifier toutes les 6 heures : les pages n'ont alors presque
 *       jamais a attendre le reseau.
 *   php scripts/devises.php definir USD 610 --raison="Alignement sur le cours BEAC du 01/10"
 *       Mode manuel seulement (DEVISES_COURS=manuel).
 *
 * Un taux n'est jamais modifie : chaque changement ajoute une ligne datee,
 * motivee, et inscrite au journal d'audit. Les tentatives deja lancees
 * gardent le taux qu'elles ont fige.
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
    sortie('  [ERREUR] Base de donnees injoignable.');
    exit(1);
}

$raison = '';
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--raison=(.+)$/s', $argument, $m)) {
        $raison = trim($m[1]);
    }
}

switch ($argv[1] ?? '') {
    case 'liste':
        sortie();
        foreach ($db->query('SELECT currency, xaf_per_unit, active_from, reason FROM exchange_rates ORDER BY currency, active_from DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC) as $l) {
            sortie(sprintf('  %s  1 %s = %s XAF   %s', $l['active_from'], $l['currency'],
                rtrim(rtrim($l['xaf_per_unit'], '0'), '.'), $l['reason'] ?? ''));
        }
        sortie();
        sortie('  Mode : ' . (Devises::mode() === 'auto' ? 'automatique (API gratuite, secours hors connexion)' : 'manuel (DEVISES_COURS=manuel)'));
        foreach (array_keys(Devises::DECIMALES) as $devise) {
            if ($devise !== Devises::REFERENCE) {
                $taux = Devises::taux($devise);
                sortie(sprintf('  En vigueur : 1 %s = %s XAF (%s ; secours %s)', $devise,
                    $taux === null ? 'AUCUN TAUX (paiement impossible)' : (string) $taux,
                    Devises::origine($devise) ?? '-', Devises::secours($devise) === null ? 'aucun'
                        : rtrim(rtrim(number_format((float) Devises::secours($devise), 4, '.', ''), '0'), '.')));
            }
        }
        sortie();
        break;

    case 'actualiser':
        $echecs = 0;
        foreach (array_keys(Devises::DECIMALES) as $devise) {
            if ($devise === Devises::REFERENCE) {
                continue;
            }
            $obtenu = Devises::actualiser($devise);
            if ($obtenu === null) {
                $echecs++;
                sortie(sprintf('  [!] %s : API injoignable ou reponse aberrante. Taux applique : %s XAF (%s).',
                    $devise, (string) Devises::taux($devise), Devises::origine($devise)));
            } else {
                sortie(sprintf('  [ok] 1 %s = %s XAF (%s)', $devise, $obtenu['taux'], $obtenu['source']));
            }
        }
        exit($echecs === 0 ? 0 : 2);

    case 'definir':
        $devise = strtoupper((string) ($argv[2] ?? ''));
        $valeur = (string) ($argv[3] ?? '');
        if (!Devises::existe($devise) || $devise === Devises::REFERENCE || !is_numeric($valeur) || (float) $valeur <= 0) {
            sortie('  [ERREUR] Usage : definir <devise> <XAF pour une unite> --raison="..."');
            exit(1);
        }
        if ($raison === '') {
            sortie('  [ERREUR] Motif obligatoire (--raison="...") : il figure au journal d\'audit.');
            exit(1);
        }
        $avant = Devises::taux($devise);
        if (!Devises::definir($devise, (float) $valeur, $raison . ' (en ligne de commande, ' . get_current_user() . ')')) {
            sortie('  [ERREUR] Taux refuse.');
            exit(1);
        }
        sortie(sprintf('  [ok] 1 %s = %s XAF (avant : %s). Les paiements deja lances gardent leur taux.', $devise, $valeur, $avant ?? 'aucun'));
        if (Devises::mode() === 'auto') {
            sortie('  [!] Mode automatique : ce taux est archive mais NE S\'APPLIQUE PAS. Le cours vient de l\'API (secours : DEVISE_' . $devise . '_SECOURS).');
            sortie('      Pour l\'imposer : DEVISES_COURS=manuel dans le fichier d\'environnement.');
        }
        break;

    default:
        sortie('  php scripts/devises.php liste');
        sortie('  php scripts/devises.php actualiser');
        sortie('  php scripts/devises.php definir USD 610 --raison="..."');
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
