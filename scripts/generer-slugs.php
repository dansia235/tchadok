<?php
/**
 * DATA-03 : remplit les identifiants lisibles (slugs) laisses vides par la
 * migration.
 *
 * Une migration SQL ne peut pas translitterer correctement « Kélou N'Djaména »
 * en « kelou-ndjamena » : elle n'a ni table d'accents ni gestion de l'unicite
 * par suffixe. Ce script le fait, et se relance sans consequence -- il ne
 * touche que les lignes dont le slug est vide.
 *
 * Usage : php scripts/generer-slugs.php [--simulation]
 *
 * --simulation n'ecrit rien et montre les identifiants qui seraient generes.
 * Sur une base de production, on regarde avant d'ecrire.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

$simulation = in_array('--simulation', array_slice($argv, 1), true);

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    fwrite(STDERR, "Base de donnees injoignable.\n");
    exit(1);
}

echo PHP_EOL, '  Identifiants lisibles (slugs)';
echo $simulation ? '  -- SIMULATION, aucune ecriture' : '';
echo PHP_EOL, PHP_EOL;

$tables = [
    'releases' => 'title',
    'tracks'   => 'title',
    'artists'  => 'stage_name',
];

foreach ($tables as $table => $colonne) {
    $existe = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = 'slug'"
    )->fetchColumn();

    if ($existe === 0) {
        printf("  %-10s colonne slug absente : migration DATA-03 non appliquee%s", $table, PHP_EOL);
        continue;
    }

    $lignes = $db->query(
        "SELECT id, `{$colonne}` AS libelle FROM `{$table}` WHERE slug IS NULL OR slug = '' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $mise = $db->prepare("UPDATE `{$table}` SET slug = ? WHERE id = ?");
    foreach ($lignes as $ligne) {
        $slug = Sorties::slug((string) $ligne['libelle'], $table);

        if ($simulation) {
            printf("    #%-6d %-40s -> %s%s", $ligne['id'], mb_substr((string) $ligne['libelle'], 0, 40), $slug, PHP_EOL);
            continue;
        }

        $mise->execute([$slug, $ligne['id']]);
    }

    printf(
        "  %-10s %d identifiant(s) %s%s",
        $table,
        count($lignes),
        $simulation ? 'a generer' : 'genere(s)',
        PHP_EOL
    );
}

echo PHP_EOL;
exit(0);
