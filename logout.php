<?php
/**
 * Page de déconnexion - Tchadok Platform
 */

require_once 'includes/functions.php';

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruire la session
session_destroy();

// Message de confirmation et redirection
setFlashMessage(FLASH_INFO, 'Vous avez été déconnecté avec succès. À bientôt sur Tchadok !');
redirect(SITE_URL . '/');
?>