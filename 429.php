<?php
/**
 * Page d'erreur 429 (SEC-16).
 *
 * La limitation applicative (SEC-12) rend sa propre reponse, avec le delai
 * d'attente. Cette page couvre les refus prononces par le serveur web
 * lui-meme, en amont de PHP.
 *
 * Il n'y a pas de page 419 : le refus CSRF repond 403 (voir SEC-09, Apache ne
 * connait pas le code 419 et le transforme en 500).
 */

require_once __DIR__ . '/includes/page-erreur.php';

pageErreur(
    429,
    'Trop de requetes',
    "Trop de requetes ont ete envoyees depuis cet appareil en peu de temps.",
    "Patientez quelques minutes avant de reessayer.",
    [
        "Revenir a l'accueil" => '',
    ]
);
