<?php
/**
 * Export du schema (DATA-01, point 5).
 *
 * mysqldump produit un fichier truffe de commentaires conditionnels
 * (/*!50003 ... *\/) et de reglages de session, penible a relire et fragile a
 * rejouer. Ce script interroge la base et ecrit un schema propre :
 *
 *   - tables      CREATE TABLE IF NOT EXISTS, sans AUTO_INCREMENT courant ;
 *   - vues        DROP VIEW IF EXISTS puis CREATE VIEW, sans DEFINER ;
 *   - declencheurs DROP TRIGGER IF EXISTS puis CREATE TRIGGER, sans DEFINER.
 *
 * Le fichier obtenu est donc REJOUABLE : l'appliquer sur une base deja a jour
 * ne produit rien. C'est ce qui permet de s'en servir comme premiere
 * migration, et comme export de reference regenere apres chaque changement.
 *
 * Le DEFINER est retire volontairement : il fige un utilisateur MySQL
 * (root@localhost) qui n'existera pas sur le serveur de production.
 *
 * Usage : php scripts/export-schema.php [chemin/de/sortie.sql]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/config/env.php';
require_once $racine . '/includes/database.php';

$sortie = $argv[1] ?? ($racine . '/database/tchadok.sql');

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    fwrite(STDERR, "Base de donnees injoignable.\n");
    exit(1);
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$base = (string) EnvLoader::get('DB_DATABASE', '');

$sansDefiner = static fn (string $sql): string => preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', $sql) ?? $sql;

// --- Tables ----------------------------------------------------------
$tables = $db->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
     ORDER BY table_name"
)->fetchAll(PDO::FETCH_COLUMN);

$morceaux = [];
$morceaux[] = "-- Schema Tchadok\n--\n-- Fichier GENERE par scripts/export-schema.php : ne pas modifier a la main.\n"
    . "-- Le schema fait foi dans database/migrations/ ; ce fichier n'en est que\n"
    . "-- la photographie, regeneree apres chaque migration (DATA-01).\n--\n"
    . '-- Genere le ' . date('d/m/Y \a H\hi') . " depuis la base {$base}.\n";

$morceaux[] = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n";

foreach ($tables as $table) {
    if ($table === 'schema_migrations') {
        continue; // registre du systeme de migrations, cree par le script
    }

    $creation = (string) $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC)['Create Table'];
    $creation = preg_replace('/\s+AUTO_INCREMENT=\d+/', '', $creation) ?? $creation;
    $creation = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $creation) ?? $creation;

    $morceaux[] = "-- Table `{$table}`\n{$creation};\n";
}

// --- Vues ------------------------------------------------------------
$vues = $db->query(
    'SELECT table_name FROM information_schema.views WHERE table_schema = DATABASE() ORDER BY table_name'
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($vues as $vue) {
    $creation = (string) $db->query("SHOW CREATE VIEW `{$vue}`")->fetch(PDO::FETCH_ASSOC)['Create View'];
    $creation = $sansDefiner($creation);
    $creation = preg_replace('/\s*SQL SECURITY \w+\s*/', ' ', $creation) ?? $creation;

    $morceaux[] = "-- Vue `{$vue}`\nDROP VIEW IF EXISTS `{$vue}`;\n{$creation};\n";
}

// --- Declencheurs ----------------------------------------------------
$declencheurs = $db->query(
    'SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = DATABASE() ORDER BY trigger_name'
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($declencheurs as $declencheur) {
    $ligne = $db->query("SHOW CREATE TRIGGER `{$declencheur}`")->fetch(PDO::FETCH_ASSOC);
    $creation = $sansDefiner((string) $ligne['SQL Original Statement']);

    // Le corps contient des points-virgules : delimiteur temporaire.
    $morceaux[] = "-- Declencheur `{$declencheur}`\n"
        . "DROP TRIGGER IF EXISTS `{$declencheur}`;\n"
        . "DELIMITER \$\$\n{$creation}\$\$\nDELIMITER ;\n";
}

$morceaux[] = "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($sortie, implode("\n", $morceaux));

printf(
    "Schema ecrit dans %s : %d table(s), %d vue(s), %d declencheur(s).\n",
    $sortie,
    count($tables) - 1,
    count($vues),
    count($declencheurs)
);
