<?php
/**
 * Dashboard Admin - Redirection vers la console unifiee
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

header('Location: ' . SITE_URL . '/admin-dashboard.php');
exit;
?>

