<?php
/**
 * Page de déconnexion - Tchadok Platform
 */

require_once 'includes/functions.php';

// La session est déjà démarrée par functions.php

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

// Redirection vers la page d'accueil
header('Location: ' . SITE_URL . '/index.php');
exit();
?>