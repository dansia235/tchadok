<?php
/**
 * Lancement d'un paiement par le client (PAY-04, cote navigateur).
 *
 *   POST (JSON) { commande, passerelle, numero? }
 *   -> { succes, erreur?, tentative: { id, statut, redirection?, passerelle } }
 *
 * Membre connecte uniquement, jeton CSRF obligatoire (en-tete X-CSRF-Token,
 * ajoute automatiquement par le correctif fetch() du site). La commande doit
 * appartenir au membre : Paiements::initier() le revérifie en base.
 */

require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/paiement/chargement.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$repondre = static function (array $contenu, int $code = 200): never {
    http_response_code($code);
    echo json_encode($contenu, JSON_UNESCAPED_UNICODE);
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    $repondre(['succes' => false, 'erreur' => 'Methode non autorisee.'], 405);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    $repondre(['succes' => false, 'erreur' => 'Connectez-vous pour payer.'], 401);
}

LimiteDebit::appliquer('paiement');

// MOD-07 : un compte dont l'adresse n'est pas confirmee n'achete pas.
require_once __DIR__ . '/../../includes/comptes.php';
if (!Comptes::emailVerifie($userId)) {
    $repondre(['succes' => false, 'erreur' => 'Confirmez d\'abord votre adresse e-mail.', 'verification' => SITE_URL . '/verifier-email.php'], 403);
}

$entree = json_decode((string) file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($entree)) {
    $entree = $_POST;
}

$commande   = trim((string) ($entree['commande'] ?? ''));
$passerelle = trim((string) ($entree['passerelle'] ?? ''));
$numero     = isset($entree['numero']) ? trim((string) $entree['numero']) : null;
$devise     = strtoupper(trim((string) ($entree['devise'] ?? 'XAF')));

if (!preg_match('/^TCHK-\d{4}-[A-F0-9]{8}$/', $commande) || !FabriquePasserelles::existe($passerelle) || !Devises::existe($devise)) {
    $repondre(['succes' => false, 'erreur' => 'Demande invalide.'], 400);
}

$resultat = Paiements::initier($commande, $userId, $passerelle, $numero, $devise);
$repondre($resultat, $resultat['succes'] ? 200 : 422);
