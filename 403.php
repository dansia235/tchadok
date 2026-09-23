<?php
/**
 * Page d'erreur 403 (SEC-16).
 *
 * Servie par Apache (ErrorDocument) et par l'application. Volontairement sans
 * dependance : voir includes/page-erreur.php.
 */

require_once __DIR__ . '/includes/page-erreur.php';

pageErreur(
    403,
    'Acces refuse',
    "Vous n'avez pas les droits necessaires pour voir cette page.",
    "Si vous pensez que c'est une erreur, connectez-vous avec le bon compte.",
    [
        "Revenir a l'accueil" => '',
        'Se connecter'        => 'login.php',
    ]
);
