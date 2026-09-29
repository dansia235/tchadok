<?php
/**
 * API d'ecoute - Tchadok Platform
 * Endpoint: /api/stream.php
 *
 * STAT-02 : une ecoute n'est acceptee qu'avec le jeton remis par
 * api/track.php a l'ouverture du titre COMPLET. Le seuil de 30 s, l'usage
 * unique du jeton, l'identite de l'auditeur et la deduplication horaire sont
 * verifies par le serveur (includes/ecoutes.php). `track_id`, `country` et
 * `city` envoyes par le navigateur sont ignores ; la duree annoncee ne peut
 * que reduire ce qui est compte, jamais l'augmenter.
 *
 * POST {jeton, duree}  ->  201 comptee | 200 non comptee (trop courte, deja
 * comptee dans l'heure) | 400 jeton absent | 403 jeton invalide ou d'un autre
 * auditeur | 409 deja transmise | 410 expire.
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/../includes/ecoutes.php';

header('Content-Type: application/json');
// SEC-18 : aucune ouverture entre origines. Cette API agit au nom de la
// personne connectee ; l'ouvrir a un autre site reviendrait a le laisser
// agir sur les comptes de ses visiteurs.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // SEC-18 : plus rien a negocier entre origines.
    http_response_code(405);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // SEC-12 : une ecoute dure au moins quelques dizaines de secondes.
        // Au-dela d'une soixantaine d'appels par minute, ce n'est plus
        // quelqu'un qui ecoute : c'est un script qui gonfle des compteurs.
        LimiteDebit::appliquer('ecoute');
        recordStream();
        exit();
    }

    if ($method === 'GET') {
        http_response_code(501);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Les analytics et recommandations ne sont pas encore disponibles.',
                'code' => 501
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    throw new Exception('Methode non autorisee', 405);
} catch (Exception $e) {
    // SEC-15 : un message ecrit pour le client (400, 404, 405...) reste
    // affiche tel quel ; une panne renvoie une reference, jamais le detail.
    $erreur = GestionErreurs::erreurApi($e, 'api/stream');
    http_response_code($erreur['code']);
    $corps = $erreur['reponse'];
    $corps['error']['timestamp'] = date('c');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
}

function recordStream(): void
{
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $resultat = Ecoutes::enregistrer(
        strtolower(trim((string) ($payload['jeton'] ?? ''))),
        max(0, (int) ($payload['duree'] ?? 0)),
        $userId ?: null
    );

    http_response_code($resultat['code']);
    echo json_encode([
        'success'   => $resultat['code'] < 300,
        'comptee'   => $resultat['comptee'],
        'motif'     => $resultat['motif'],
        'message'   => $resultat['message'],
        'stream_id' => $resultat['stream_id'],
    ], JSON_UNESCAPED_UNICODE);
}
