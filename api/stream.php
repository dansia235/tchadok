<?php
/**
 * API de streaming - Tchadok Platform
 * Endpoint: /api/stream.php
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

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

    throw new Exception('Méthode non autorisée', 405);
} catch (Exception $e) {
    // SEC-15 : un message ecrit pour le client (400, 404, 405...) reste
    // affiche tel quel ; une panne renvoie une reference, jamais le detail.
    $erreur = GestionErreurs::erreurApi($e, 'api/stream');
    http_response_code($erreur['code']);
    $corps = $erreur['reponse'];
    $corps['error']['timestamp'] = date('c');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
}

function recordStream() {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $trackId = (int)($payload['track_id'] ?? 0);
    if ($trackId <= 0) {
        throw new Exception('ID du titre requis', 400);
    }

    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance->isConnected() || !tableExists('streams') || !tableExists('tracks')) {
        throw new Exception('Base de données indisponible', 500);
    }

    $db = $dbInstance->getConnection();
    // SEC-08 : pas d'ecoute enregistree sur un titre non publie. Un
    // proprietaire ou un administrateur peut lire un brouillon (verification,
    // moderation) ; ces lectures ne doivent pas alimenter les compteurs
    // publics, que le trigger update_stream_stats incremente a chaque ligne.
    // La securisation complete de cet endpoint releve de STAT-02.
    $stmt = $db->prepare("SELECT id, artist_id, duration FROM tracks WHERE id = ? AND status = 'approved'");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch();

    if (!$track) {
        throw new Exception('Titre introuvable', 404);
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $durationPlayed = max(0, (int)($payload['duration'] ?? 0));
    $trackDuration = (int)($track['duration'] ?? 0);
    $completed = ($trackDuration > 0 && $durationPlayed >= (int)round($trackDuration * 0.6)) ? 1 : 0;

    $source = $payload['source'] ?? 'web';
    $country = $payload['country'] ?? null;
    $city = $payload['city'] ?? null;

    $stmt = $db->prepare("
        INSERT INTO streams (user_id, track_id, artist_id, ip_address, user_agent, country, city, duration_played, completed, source, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId ?: null,
        $trackId,
        (int)$track['artist_id'],
        clientIp(),
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $country,
        $city,
        $durationPlayed,
        $completed,
        $source
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'stream_id' => (int)$db->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
}
