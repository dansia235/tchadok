<?php
/**
 * Distributeur des callbacks simules (PAY-03).
 *
 * Processus a part : le serveur integre de PHP ne traite qu'une requete a la
 * fois et ne sait pas « rappeler plus tard ». Le distributeur lit la file
 * (mock-gateways/storage/file/), applique le changement d'etat a l'echeance --
 * l'instant ou l'abonne valide sur son telephone -- puis envoie le callback
 * signe a l'application et note sa reponse.
 *
 *   php mock-gateways/distributeur.php            boucle continue
 *   php mock-gateways/distributeur.php --une-fois traite les echus et s'arrete
 *
 * Alterations de test (scenarios 8 et 9) :
 *   signature  callback signe avec un mauvais secret -- l'application doit le rejeter
 *   montant    montant augmente de 1 FCFA -- l'application doit le mettre en revue
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/lib/amorce.php';

$uneFois = in_array('--une-fois', $argv, true);

if (!$uneFois) {
    fwrite(STDOUT, "Distributeur de callbacks demarre (Ctrl+C pour arreter).\n");
}

do {
    foreach (array_keys(Magasin::echus()) as $chemin) {
        // S'approprier l'envoi par un renommage : c'est atomique, un seul
        // distributeur peut y parvenir. (unlink ne suffit pas sous Windows :
        // deux suppressions concurrentes d'un fichier encore ouvert peuvent
        // toutes deux « reussir », et le callback partait deux fois.) L'envoi
        // quitte la file AVANT d'etre execute : un plantage ne le rejoue pas.
        $pris = $chemin . '.' . getmypid() . '.encours';
        if (!@rename($chemin, $pris)) {
            continue;
        }
        $envoi = json_decode((string) file_get_contents($pris), true);
        @unlink($pris);
        if (is_array($envoi)) {
            distribuer($envoi);
        }
    }
    if (!$uneFois) {
        usleep(250000);
    }
} while (!$uneFois);

function distribuer(array $envoi): void
{
    $code = (string) ($envoi['passerelle'] ?? '');
    if (!isset(SIMULATEURS[$code])) {
        return;
    }
    $config = configurationSimulateur($code);

    $transaction = Magasin::modifier($code, (string) $envoi['id'], function (array $t) use ($envoi): array {
        if (!empty($envoi['statut'])) {
            $t['status'] = $envoi['statut'];
            if (array_key_exists('echec', $envoi)) {
                $t['failure_code'] = $envoi['echec'];
            }
        }
        return $t;
    });
    if (!$transaction) {
        return;
    }

    $montant = (int) $transaction['amount'];
    if (($envoi['alteration'] ?? null) === 'montant') {
        $montant++;
    }

    $idEvenement = 'evt_' . bin2hex(random_bytes(8));
    $corps = json_encode([
        'event'      => (string) $envoi['evenement'],
        'event_id'   => $idEvenement,
        'created_at' => date('c'),
        // PAYOUT-04 : un versement sortant est notifie sous la cle « disbursement ».
        (($transaction['kind'] ?? 'payment') === 'disbursement' ? 'disbursement' : 'payment') => [
            'id'           => $transaction['id'],
            'reference'    => $transaction['reference'],
            'amount'       => $montant,
            'currency'     => $transaction['currency'],
            'status'       => $transaction['status'],
            'failure_code' => $transaction['failure_code'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $secret = ($envoi['alteration'] ?? null) === 'signature' ? 'secret-errone-' . $config['secret'] : $config['secret'];
    $horodatage = (string) time();
    $signature = 't=' . $horodatage . ',v1=' . hash_hmac('sha256', $horodatage . '.' . $corps, $secret);

    $debut = microtime(true);
    $curl = curl_init((string) $transaction['callback_url']);
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corps,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Signature: ' . $signature, 'X-Event-Id: ' . $idEvenement],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $reponse = curl_exec($curl);
    $http = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $erreur = $reponse === false ? curl_error($curl) : null;

    $ligne = [
        'passerelle' => $code,
        'id'         => $transaction['id'],
        'evenement'  => $envoi['evenement'],
        'alteration' => $envoi['alteration'] ?? null,
        'http'       => $http,
        'reponse'    => $erreur ?? mb_substr((string) $reponse, 0, 300),
        'duree_ms'   => (int) round((microtime(true) - $debut) * 1000),
    ];
    Magasin::journaliser($ligne);

    fwrite(STDOUT, sprintf("%s %-12s %s %-18s -> %d %s\n",
        date('H:i:s'), $code, $transaction['id'], $envoi['evenement'] . ($ligne['alteration'] ? ' [' . $ligne['alteration'] . ']' : ''),
        $http, $erreur ?? ''));
}
