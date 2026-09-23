<?php
/**
 * API Artistes - NEUTRALISEE (SEC-01)
 *
 * Cette API exposait, SANS AUCUN CONTROLE D'ACCES :
 *   - action=list    : liste des artistes
 *   - action=get     : fiche complete d'un artiste
 *   - action=create  : creation d'un artiste, avec les drapeaux
 *                      "verified" et "featured" fournis par le client
 *   - action=update  : modification d'un artiste
 *   - action=delete  : suppression d'un artiste
 *   - action=stats   : statistiques
 *
 * N'importe qui pouvait donc creer un artiste "verifie" et "mis en
 * avant", ou supprimer le profil d'un artiste existant - avec, par
 * cascade, son catalogue et l'historique attache.
 *
 * Aucun appelant atteignable n'utilisait cette API : l'unique appel
 * existait dans admin/dashboard-tabs/artists.php, repertoire retire en
 * mort (supprime en CLEAN-02).
 *
 * Reecriture prevue : tache SEC-01, seconde etape. Les drapeaux
 * "verified" et "featured" devront relever d'une action dediee,
 * reservee a un role habilite et journalisee (SEC-19), et non d'un
 * champ de formulaire.
 *
 * Le code d'origine reste consultable dans l'historique Git,
 * commit 401eaa5 et anterieurs.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(503);

echo json_encode([
    'success' => false,
    'error' => [
        'code' => 503,
        'message' => "API desactivee. Endpoint en cours de reecriture avec controle d'acces (SEC-01)."
    ]
], JSON_UNESCAPED_UNICODE);
