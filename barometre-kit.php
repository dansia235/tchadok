<?php
/**
 * Fichiers du kit presse d'une edition (CHART-04) : visuel, CSV, communique.
 * Liste fermee de noms : aucun chemin choisi par le client.
 */

require_once 'includes/functions.php';
require_once 'includes/barometre.php';
require_once 'includes/kit-presse.php';

$types = ['top10.png' => 'image/png', 'classement.csv' => 'text/csv; charset=utf-8', 'communique.html' => 'text/html; charset=utf-8'];
$fichier = (string) ($_GET['fichier'] ?? '');
$edition = Barometre::edition((string) ($_GET['edition'] ?? '__aucune__'));
if (!$edition || !isset($types[$fichier])) {
    show404();
}
$chemin = KitPresse::dossier((string) $edition['slug']) . '/' . $fichier;
if (!is_file($chemin)) {
    show404();
}
header('Content-Type: ' . $types[$fichier]);
header('Content-Length: ' . filesize($chemin));
// Une edition arretee ne change plus : mise en cache longue.
header('Cache-Control: public, max-age=86400');
if ($fichier === 'classement.csv') {
    header('Content-Disposition: attachment; filename="barometre-tchadok-' . $edition['slug'] . '.csv"');
}
readfile($chemin);
