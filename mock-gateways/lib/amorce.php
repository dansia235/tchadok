<?php
/**
 * Amorce commune des simulateurs de paiement (PAY-03).
 *
 * LOCAL UNIQUEMENT. Les simulateurs lisent le .env.local du projet (memes
 * identifiants marchands et memes secrets que l'application) et refusent de
 * demarrer ailleurs qu'en environnement local. Ils ecoutent sur 127.0.0.1,
 * jamais sur toutes les interfaces : le lanceur l'impose.
 *
 * Ils n'utilisent PAS le code de signature de l'application : une erreur
 * symetrique (meme bogue des deux cotes) passerait sinon inapercue. Le contrat
 * (docs/paiement/contrat-generique.md) est la seule chose partagee.
 */

declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/env.php';

if (EnvLoader::environment() !== 'local' || EnvLoader::isProduction()) {
    fwrite(STDERR, "Simulateurs de paiement refuses hors environnement local.\n");
    exit(1);
}

require_once __DIR__ . '/Magasin.php';
require_once __DIR__ . '/Scenarios.php';
require_once __DIR__ . '/Simulateur.php';

const SIMULATEURS = [
    'airtel_money' => ['prefixe' => 'AIRTEL', 'libelle' => 'Airtel Money', 'parcours' => 'push',   'id' => 'AIR', 'indicatif' => '66'],
    'moov_money'   => ['prefixe' => 'MOOV',   'libelle' => 'Moov Money',   'parcours' => 'push',   'id' => 'MOV', 'indicatif' => '65'],
    'visa'         => ['prefixe' => 'VISA',   'libelle' => 'VISA',         'parcours' => 'hosted', 'id' => 'VIS', 'indicatif' => null],
    'gimac'        => ['prefixe' => 'GIMAC',  'libelle' => 'GIMAC',        'parcours' => 'push',   'id' => 'GIM', 'indicatif' => '62'],
];

/**
 * Configuration d'un simulateur, lue dans .env.local.
 *
 * @return array{code:string, libelle:string, parcours:string, id:string, indicatif:?string, base:string, marchand:string, cle:string, secret:string}
 */
function configurationSimulateur(string $code): array
{
    $def = SIMULATEURS[$code] ?? null;
    if ($def === null) {
        throw new InvalidArgumentException('Simulateur inconnu : ' . $code);
    }

    return [
        'code'      => $code,
        'libelle'   => $def['libelle'],
        'parcours'  => $def['parcours'],
        'id'        => $def['id'],
        'indicatif' => $def['indicatif'],
        'base'      => rtrim((string) EnvLoader::get($def['prefixe'] . '_BASE_URL', ''), '/'),
        'marchand'  => (string) EnvLoader::get($def['prefixe'] . '_MERCHANT_ID', ''),
        'cle'       => (string) EnvLoader::get($def['prefixe'] . '_API_KEY', ''),
        'secret'    => (string) EnvLoader::get($def['prefixe'] . '_WEBHOOK_SECRET', ''),
    ];
}
