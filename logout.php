<?php
/**
 * Page de déconnexion - Tchadok Platform
 */

// Démarrer la session AVANT d'inclure functions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer la page de référence avant de charger functions.php
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

// Si pas de referer ou si c'est logout.php lui-même, aller à l'accueil
if (empty($redirect_url) || strpos($redirect_url, 'logout.php') !== false) {
    require_once 'includes/functions.php';
    $redirect_url = SITE_URL . '/index.php';
} else {
    require_once 'includes/functions.php';
}

// Détruire toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Empêcher la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirection vers la page précédente
header('Location: ' . $redirect_url);
exit();
