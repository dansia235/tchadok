<?php
/**
 * Page d'erreur 500 (SEC-16).
 *
 * Dernier filet : le gestionnaire de SEC-15 rend deja sa propre page, avec une
 * reference. Celle-ci sert quand PHP ne s'execute plus du tout (erreur de
 * demarrage, memoire epuisee) -- raison de plus pour qu'elle ne depende de
 * rien : ni configuration, ni session, ni base de donnees.
 */

require_once __DIR__ . '/includes/page-erreur.php';

pageErreur(
    500,
    'Une erreur est survenue',
    "Le probleme vient de notre cote, pas de votre action. Il a ete enregistre.",
    "Reessayez dans un instant. Si cela se reproduit, ecrivez-nous depuis la page de contact.",
    [
        "Revenir a l'accueil" => '',
        'Nous ecrire'         => 'contact.php',
    ]
);
