<?php
/**
 * Bascule d'environnement - Tchadok Platform
 *
 * Tache CFG-04.
 *
 *   php scripts/env-switch.php local
 *   php scripts/env-switch.php production
 *   php scripts/env-switch.php --status
 *
 * POURQUOI CE SCRIPT EXISTE
 *
 * Apache ne lit que le fichier nomme exactement ".htaccess". Il ignore
 * totalement ".htaccess.local" et ".htaccess.production". Supprimer
 * ".htaccess.local" lors d'une mise en production ne suffit donc pas :
 * si aucun ".htaccess" n'est en place, le serveur tourne SANS AUCUNE
 * des regles de securite, silencieusement.
 *
 * Ce script genere ".htaccess" depuis la bonne source, et refuse une
 * bascule vers la production tant que des fichiers de developpement
 * sont presents.
 *
 * Execution en ligne de commande uniquement.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Refus d'execution par HTTP
// ---------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ce script ne s'execute qu'en ligne de commande.\n";
    exit(1);
}

$racine = dirname(__DIR__);

// ---------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------
const C_RESET = "\033[0m";
const C_ROUGE = "\033[31m";
const C_VERT  = "\033[32m";
const C_JAUNE = "\033[33m";
const C_BLEU  = "\033[36m";
const C_GRAS  = "\033[1m";

function couleursActives(): bool
{
    static $actives = null;
    if ($actives === null) {
        $actives = (DIRECTORY_SEPARATOR !== '\\')
            || getenv('WT_SESSION') !== false
            || getenv('TERM_PROGRAM') !== false
            || getenv('ANSICON') !== false;
    }
    return $actives;
}

function c(string $couleur, string $texte): string
{
    return couleursActives() ? $couleur . $texte . C_RESET : $texte;
}

function ligne(string $texte = ''): void
{
    echo $texte . PHP_EOL;
}

function titre(string $texte): void
{
    ligne();
    ligne(c(C_GRAS, $texte));
    ligne(str_repeat('-', max(4, mb_strlen($texte))));
}

function ok(string $texte): void      { ligne('  ' . c(C_VERT, '[ok]') . '    ' . $texte); }
function info(string $texte): void    { ligne('  ' . c(C_BLEU, '[info]') . '  ' . $texte); }
function alerte(string $texte): void  { ligne('  ' . c(C_JAUNE, '[!]') . '     ' . $texte); }
function erreur(string $texte): void  { ligne('  ' . c(C_ROUGE, '[ECHEC]') . ' ' . $texte); }

// ---------------------------------------------------------------------
// Definitions
// ---------------------------------------------------------------------
$environnements = [
    'local' => [
        'htaccess' => '.htaccess.local',
        'env'      => '.env.local',
        'interdits' => [],
    ],
    'production' => [
        'htaccess' => '.htaccess.production',
        'env'      => '.env.production',
        // Presents sur un serveur de production = anomalie bloquante
        'interdits' => [
            '.env.local'        => "configuration de developpement : elle prendrait la priorite sur .env.production",
            '.htaccess.local'   => "source Apache de developpement",
            'mock-gateways'     => "simulateurs de paiement : ils ne doivent jamais etre deployes",
            'tests'             => "jeu de tests",
            'database/seeds/demo.sql' => "jeu de demonstration",
        ],
    ],
];

const MARQUEUR = 'storage/.environment';

// Politique des secrets de production : partagee avec le garde-fou applicatif.
// Fichier sans dependance, chargeable meme si la configuration est cassee.
require_once $racine . '/includes/production-secret-policy.php';

// ---------------------------------------------------------------------
// Utilitaires
// ---------------------------------------------------------------------
function chemin(string $racine, string $relatif): string
{
    return $racine . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relatif);
}

function lireEnv(string $chemin): array
{
    if (!is_readable($chemin)) {
        return [];
    }
    $vars = [];
    foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#') {
            continue;
        }
        $p = strpos($l, '=');
        if ($p === false || $p === 0) {
            continue;
        }
        $vars[trim(substr($l, 0, $p))] = trim(trim(substr($l, $p + 1)), "\"'");
    }
    return $vars;
}

function empreinte(string $chemin): string
{
    return is_readable($chemin) ? substr(hash_file('sha256', $chemin), 0, 16) : '';
}

// ---------------------------------------------------------------------
// Etat courant
// ---------------------------------------------------------------------
function etatCourant(string $racine, array $environnements): array
{
    $htaccess = chemin($racine, '.htaccess');
    $etat = [
        'htaccess_present' => is_file($htaccess),
        'htaccess_source'  => null,
        'htaccess_modifie' => false,
        'env_actif'        => null,
        'marqueur'         => null,
    ];

    if ($etat['htaccess_present']) {
        $empreinteActuelle = empreinte($htaccess);
        foreach ($environnements as $nom => $def) {
            $source = chemin($racine, $def['htaccess']);
            if (is_file($source) && empreinte($source) === $empreinteActuelle) {
                $etat['htaccess_source'] = $nom;
                break;
            }
        }
        if ($etat['htaccess_source'] === null) {
            $etat['htaccess_modifie'] = true;
        }
    }

    // Meme ordre de resolution que config/env.php
    foreach (['local', 'production'] as $nom) {
        if (is_file(chemin($racine, $environnements[$nom]['env']))) {
            $etat['env_actif'] = $nom;
            break;
        }
    }

    $marqueur = chemin($racine, MARQUEUR);
    if (is_file($marqueur)) {
        $etat['marqueur'] = json_decode((string) file_get_contents($marqueur), true) ?: null;
    }

    return $etat;
}

// ---------------------------------------------------------------------
// Commande : --status
// ---------------------------------------------------------------------
function afficherStatut(string $racine, array $environnements): int
{
    $etat = etatCourant($racine, $environnements);

    titre('Environnement Tchadok');

    if ($etat['env_actif'] === null) {
        erreur("Aucun fichier d'environnement (.env.local ni .env.production).");
    } else {
        $fichier = $environnements[$etat['env_actif']]['env'];
        ok(sprintf("Configuration : %s (%s)", c(C_GRAS, $etat['env_actif']), $fichier));
    }

    if (!$etat['htaccess_present']) {
        erreur('.htaccess ABSENT. Apache n\'applique aucune regle de securite.');
        info('Corriger avec : php scripts/env-switch.php ' . ($etat['env_actif'] ?? 'local'));
    } elseif ($etat['htaccess_modifie']) {
        alerte('.htaccess present mais ne correspond a aucune source connue.');
        info('Il a ete modifie a la main, ou genere depuis une autre version.');
        info('Reportez vos modifications dans .htaccess.local ou .htaccess.production,');
        info('puis relancez la bascule.');
    } else {
        ok(sprintf('Apache        : .htaccess genere depuis %s', $environnements[$etat['htaccess_source']]['htaccess']));
    }

    if ($etat['env_actif'] !== null && $etat['htaccess_source'] !== null
        && $etat['env_actif'] !== $etat['htaccess_source']) {
        ligne();
        erreur(sprintf(
            'INCOHERENCE : configuration %s mais regles Apache %s.',
            $etat['env_actif'],
            $etat['htaccess_source']
        ));
    }

    if ($etat['marqueur']) {
        info(sprintf(
            'Derniere bascule : %s vers %s, par %s',
            $etat['marqueur']['horodatage'] ?? '?',
            $etat['marqueur']['environnement'] ?? '?',
            $etat['marqueur']['operateur'] ?? '?'
        ));
    }

    // Fichiers de developpement presents alors que la config est production
    if ($etat['env_actif'] === 'production') {
        $trouves = [];
        foreach ($environnements['production']['interdits'] as $rel => $raison) {
            if (file_exists(chemin($racine, $rel))) {
                $trouves[] = $rel;
            }
        }
        if ($trouves) {
            ligne();
            erreur('Fichiers de developpement presents en configuration production :');
            foreach ($trouves as $t) {
                ligne('            - ' . $t);
            }
        }
    }

    ligne();
    return 0;
}

// ---------------------------------------------------------------------
// Commande : bascule
// ---------------------------------------------------------------------
function basculer(string $cible, string $racine, array $environnements): int
{
    $def = $environnements[$cible];

    titre("Bascule vers l'environnement : " . strtoupper($cible));

    $bloquants = [];
    $avertissements = [];

    // 1. Source Apache
    $source = chemin($racine, $def['htaccess']);
    if (!is_file($source)) {
        $bloquants[] = "Source Apache introuvable : {$def['htaccess']}";
    }

    // 2. Fichier d'environnement
    $envPath = chemin($racine, $def['env']);
    if (!is_file($envPath)) {
        $modele = $def['env'] . '.example';
        $bloquants[] = "Fichier d'environnement absent : {$def['env']}"
            . (is_file(chemin($racine, $modele)) ? " (copiez {$modele} et renseignez-le)" : '');
    }

    // 3. Fichiers interdits pour la cible
    foreach ($def['interdits'] as $rel => $raison) {
        if (file_exists(chemin($racine, $rel))) {
            $bloquants[] = sprintf('%s doit etre retire : %s', $rel, $raison);
        }
    }

    // 4. Controles specifiques a la production.
    //    La regle vit dans includes/production-secret-policy.php, partagee
    //    avec le garde-fou applicatif : une seule liste, qui ne peut pas
    //    diverger entre la bascule et l'execution.
    if ($cible === 'production' && is_file($envPath)) {
        $vars = lireEnv($envPath);

        foreach (ProductionSecretPolicy::CLES_SENSIBLES as $cle) {
            if (!array_key_exists($cle, $vars)) {
                $avertissements[] = "Variable absente : {$cle}";
            }
        }

        foreach (ProductionSecretPolicy::violations($vars) as $violation) {
            $bloquants[] = $violation;
        }

        if (($vars['STORAGE_PATH'] ?? '') !== '' && str_starts_with((string) $vars['STORAGE_PATH'], './')) {
            $avertissements[] = "STORAGE_PATH est relatif : le stockage devrait etre hors de la racine web";
        }
    }

    // 5. Restitution
    if ($avertissements) {
        ligne();
        foreach ($avertissements as $a) {
            alerte($a);
        }
    }

    if ($bloquants) {
        ligne();
        erreur(sprintf('Bascule refusee : %d probleme(s) a corriger.', count($bloquants)));
        ligne();
        foreach ($bloquants as $i => $b) {
            ligne(sprintf('   %2d. %s', $i + 1, $b));
        }
        ligne();
        info('Aucune modification effectuee.');
        ligne();
        return 1;
    }

    // 6. Ecriture
    $htaccess = chemin($racine, '.htaccess');

    if (is_file($htaccess)) {
        $etat = etatCourant($racine, $environnements);
        if ($etat['htaccess_modifie']) {
            $sauvegarde = $htaccess . '.remplace-' . date('Ymd-His');
            copy($htaccess, $sauvegarde);
            alerte('.htaccess existant ne correspondait a aucune source : sauvegarde dans');
            ligne('            ' . basename($sauvegarde));
        }
    }

    if (!copy($source, $htaccess)) {
        erreur("Impossible d'ecrire .htaccess. Verifiez les droits.");
        return 1;
    }
    ok(sprintf('.htaccess genere depuis %s', $def['htaccess']));

    // 7. Marqueur
    $dossierMarqueur = dirname(chemin($racine, MARQUEUR));
    if (!is_dir($dossierMarqueur)) {
        @mkdir($dossierMarqueur, 0775, true);
    }
    if (is_dir($dossierMarqueur)) {
        file_put_contents(chemin($racine, MARQUEUR), json_encode([
            'environnement'     => $cible,
            'horodatage'        => date('c'),
            'source_htaccess'   => $def['htaccess'],
            'empreinte'         => empreinte($htaccess),
            'operateur'         => get_current_user(),
            'machine'           => gethostname(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        ok('Marqueur ecrit : ' . MARQUEUR);
    } else {
        alerte('Marqueur non ecrit : ' . MARQUEUR . ' inaccessible en ecriture.');
    }

    ok(sprintf("Configuration : %s", $def['env']));

    ligne();
    if ($cible === 'production') {
        info('Verifiez maintenant :');
        ligne('            - php scripts/env-switch.php --status');
        ligne('            - curl -I https://<domaine>/  (en-tetes de securite)');
        ligne('            - acces refuse a /.env, /config/, /storage/');
        ligne('            - les 5 parcours critiques');
    } else {
        info('Rechargez le site : http://localhost/tchadok');
    }
    ligne();

    return 0;
}

// ---------------------------------------------------------------------
// Point d'entree
// ---------------------------------------------------------------------
$argument = $argv[1] ?? null;

if ($argument === null || in_array($argument, ['-h', '--help', 'help'], true)) {
    ligne();
    ligne(c(C_GRAS, 'env-switch - bascule d\'environnement Tchadok'));
    ligne();
    ligne('  php scripts/env-switch.php local        regles Apache de developpement');
    ligne('  php scripts/env-switch.php production   regles Apache durcies');
    ligne('  php scripts/env-switch.php --status     etat courant et incoherences');
    ligne();
    ligne('Apache ne lit que le fichier ".htaccess". Ce script le genere depuis');
    ligne('.htaccess.local ou .htaccess.production. Sans lui, un deploiement peut');
    ligne('laisser le serveur sans aucune regle de securite, silencieusement.');
    ligne();
    exit(0);
}

if (in_array($argument, ['--status', 'status', '-s'], true)) {
    exit(afficherStatut($racine, $environnements));
}

if (!isset($environnements[$argument])) {
    ligne();
    erreur("Environnement inconnu : {$argument}");
    info('Valeurs acceptees : local, production');
    ligne();
    exit(1);
}

exit(basculer($argument, $racine, $environnements));
