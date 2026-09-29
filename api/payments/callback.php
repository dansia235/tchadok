<?php
/**
 * Reception des callbacks des operateurs de paiement (PAY-04).
 *
 *   POST /api/payments/callback.php?passerelle=airtel_money|moov_money|visa|gimac
 *
 * L'operateur n'a ni session ni jeton : la garde CSRF (SEC-09) ne peut pas
 * s'appliquer. C'est la seule exemption legitime du site, et elle est
 * compensee par :
 *   - la signature HMAC du corps brut, verifiee en temps constant ;
 *   - la fenetre d'horodatage (300 s), contre le rejeu ;
 *   - la liste des adresses emettrices de l'operateur ;
 *   - l'inscription de chaque appel dans payment_events AVANT traitement.
 *
 * Codes de reponse :
 *   200  traite, ou doublon, ou tentative inconnue -- l'operateur cesse
 *   401  signature rejetee
 *   403  adresse non autorisee
 *   404  passerelle inconnue
 *   405  methode autre que POST
 *   413  corps trop volumineux
 *   500  echec de traitement -- l'operateur reessaiera
 * Jamais 200 sur un traitement echoue.
 */

define('TCHADOK_CSRF_EXEMPT', 'callback operateur de paiement, signe HMAC (PAY-04)');

require_once '../../includes/functions.php';
require_once '../../includes/paiement/chargement.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$repondre = static function (int $code, string $resultat): never {
    http_response_code($code);
    echo json_encode(['resultat' => $resultat]);
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    $repondre(405, 'methode_non_autorisee');
}

$passerelle = (string) ($_GET['passerelle'] ?? '');
$ip = clientIp();

$autorisees = FabriquePasserelles::adressesCallback($passerelle);
if (FabriquePasserelles::existe($passerelle) && !adresseCorrespond($ip, $autorisees)) {
    Commandes::enregistrerEvenement([
        'gateway' => $passerelle, 'direction' => 'callback', 'event_type' => 'rejete:adresse_non_autorisee',
        'payload' => mb_substr((string) file_get_contents('php://input', false, null, 0, 65536), 0, 60000),
        'signature_valid' => false, 'ip_address' => $ip,
    ]);
    error_log(sprintf('[Tchadok][paiement][ALERTE] callback %s depuis une adresse non autorisee : %s', $passerelle, $ip));
    $repondre(403, 'adresse_non_autorisee');
}

// Corps brut, lu AVANT toute interpretation : la signature porte sur ces octets.
$corps = (string) file_get_contents('php://input', false, null, 0, 65537);
if (strlen($corps) > 65536) {
    $repondre(413, 'corps_trop_volumineux');
}

$entetes = [];
foreach ($_SERVER as $cle => $valeur) {
    if (str_starts_with($cle, 'HTTP_')) {
        $entetes[strtolower(str_replace('_', '-', substr($cle, 5)))] = (string) $valeur;
    }
}

$issue = Paiements::traiterCallback($passerelle, $entetes, $corps, $ip);
$repondre($issue['http'], $issue['resultat']);
