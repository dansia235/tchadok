<?php
/**
 * Panier (SHOP-01), pour les boutons « Ajouter au panier ».
 *
 *   GET                                   -> { nombre, total }
 *   POST (JSON) { action: ajouter|retirer, type: track|release, id }
 *                                         -> { succes, message, nombre }
 *
 * Ouvert aux visiteurs : leur panier vit en session et rejoint leur compte a
 * la connexion. Jeton CSRF obligatoire sur POST (garde SEC-09 ; l'en-tete est
 * ajoute par le correctif fetch() du site). Aucun prix n'est accepte du
 * client : il est lu en base.
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/paiement/chargement.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$repondre = static function (array $contenu, int $code = 200): never {
    http_response_code($code);
    echo json_encode($contenu, JSON_UNESCAPED_UNICODE);
    exit;
};

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;
$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($methode === 'GET') {
    $repondre(['nombre' => Panier::compter($userId)]);
}
if ($methode !== 'POST') {
    header('Allow: GET, POST');
    $repondre(['succes' => false, 'message' => 'Methode non autorisee.'], 405);
}

$entree = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($entree)) {
    $entree = $_POST;
}
$action = (string) ($entree['action'] ?? '');
$type = (string) ($entree['type'] ?? '');
$id = (int) ($entree['id'] ?? 0);

if (!in_array($type, Panier::TYPES, true) || $id <= 0) {
    $repondre(['succes' => false, 'message' => 'Article invalide.'], 400);
}

if ($action === 'ajouter') {
    $resultat = Panier::ajouter($userId, $type, $id);
} elseif ($action === 'retirer') {
    $resultat = ['succes' => Panier::retirer($userId, $type, $id), 'message' => 'Article retire.'];
} else {
    $repondre(['succes' => false, 'message' => 'Action inconnue.'], 400);
}

$repondre($resultat + ['nombre' => Panier::compter($userId)], $resultat['succes'] ? 200 : 422);
