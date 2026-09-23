<?php
/**
 * Page d'erreur 404 (SEC-16).
 *
 * Servie par Apache (ErrorDocument) et par l'application. Volontairement sans
 * dependance : voir includes/page-erreur.php.
 */

require_once __DIR__ . '/includes/page-erreur.php';

pageErreur(
    404,
    'Page introuvable',
    "Cette page n'existe pas, ou elle a change d'adresse.",
    "Verifiez le lien, ou repartez de l'accueil pour retrouver la musique.",
    [
        "Revenir a l'accueil"     => '',
        'Parcourir le catalogue'  => 'decouvrir.php',
    ]
);
