<?php
/**
 * Déconnexion administrateur - Tchadok Platform
 */

session_start();
session_destroy();
header('Location: login.php');
exit;
?>