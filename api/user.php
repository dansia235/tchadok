<?php
/**
 * API Utilisateurs - NEUTRALISEE (SEC-01)
 *
 * Cette API exposait, SANS AUCUN CONTROLE D'ACCES :
 *   - action=list    : liste des utilisateurs avec e-mails et telephones
 *   - action=get     : fiche complete d'un utilisateur
 *   - action=create  : creation d'un compte, y compris ADMINISTRATEUR,
 *                      avec le mot de passe en dur "12345678"
 *   - action=update  : modification d'un compte
 *   - action=delete  : suppression d'un compte
 *   - action=bulk    : activation / desactivation / verification / suppression
 *                      EN MASSE
 *
 * Une seule requete HTTP anonyme suffisait donc a prendre le controle
 * complet de la plateforme, ou a detruire la base utilisateurs.
 *
 * Aucun appelant atteignable n'utilisait cette API : les 3 appels
 * existaient dans admin/dashboard-tabs/users.php, repertoire retire en
 * mort qui n'est inclus par aucun point d'entree (supprime en CLEAN-02).
 *
 * Reecriture prevue : tache SEC-01, seconde etape.
 * Prerequis : SEC-09 (CSRF), SEC-19 (roles et journal d'audit),
 *             DATA-06 (suppression logique).
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
