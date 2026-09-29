<?php
/**
 * Suivi d'une tentative de paiement par la page d'attente du client.
 *
 *   GET ?tentative=<id>  ->  { statut, commande, etat_commande, message, ... }
 *
 * Ne renvoie que les tentatives du membre connecte. Si le callback tarde,
 * Paiements::suivre() interroge l'operateur (au plus toutes les 15 s) ; si le
 * delai est depasse, il expire la tentative sans attendre la tache planifiee.
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

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    $repondre(['erreur' => 'Authentification requise.'], 401);
}

LimiteDebit::appliquer('suivi-paiement');

// La session n'est plus necessaire : on la libere pour ne pas bloquer les
// autres requetes du membre pendant une eventuelle consultation de l'operateur.
session_write_close();

$suivi = Paiements::suivre((int) ($_GET['tentative'] ?? 0), $userId);
if ($suivi === null) {
    $repondre(['erreur' => 'Paiement introuvable.'], 404);
}

$repondre($suivi);
