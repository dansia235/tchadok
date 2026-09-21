<?php
/**
 * Genere automatiquement la playlist rotation.m3u pour Liquidsoap.
 *
 * Usage:
 *   php scripts/generate-rotation.php
 *   php scripts/generate-rotation.php --shuffle
 *   php scripts/generate-rotation.php --source=infra/stream/media --output=infra/stream/liquidsoap/rotation.m3u
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "[generate-rotation] Impossible de resoudre la racine du projet." . PHP_EOL);
    exit(1);
}

$options = getopt('', ['source::', 'output::', 'prefix::', 'extensions::', 'shuffle']);

$sourceDir = $options['source'] ?? 'infra/stream/media';
$outputFile = $options['output'] ?? 'infra/stream/liquidsoap/rotation.m3u';
$containerPrefix = $options['prefix'] ?? '/opt/radio/media';
$extensions = $options['extensions'] ?? 'mp3,wav,m4a,ogg';
$shuffle = array_key_exists('shuffle', $options);

$sourcePath = normalizePath($root, $sourceDir);
$outputPath = normalizePath($root, $outputFile);
$sourceReal = realpath($sourcePath);

if ($sourceReal === false || !is_dir($sourceReal)) {
    fwrite(STDERR, "[generate-rotation] Dossier source introuvable: {$sourcePath}" . PHP_EOL);
    exit(1);
}

$allowedExtensions = array_filter(array_map(
    static fn(string $ext): string => strtolower(trim($ext)),
    explode(',', (string) $extensions)
));

if (!$allowedExtensions) {
    fwrite(STDERR, "[generate-rotation] Aucune extension valide fournie." . PHP_EOL);
    exit(1);
}

$tracks = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceReal, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }

    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, $allowedExtensions, true)) {
        continue;
    }

    $absolute = normalizeSlash($fileInfo->getPathname());
    $relative = ltrim(substr($absolute, strlen(normalizeSlash($sourceReal))), '/');
    if ($relative === '') {
        continue;
    }

    $tracks[] = rtrim(normalizeSlash($containerPrefix), '/') . '/' . $relative;
}

$tracks = array_values(array_unique($tracks));
sort($tracks, SORT_NATURAL | SORT_FLAG_CASE);

if ($shuffle && count($tracks) > 1) {
    shuffle($tracks);
}

if (!$tracks) {
    fwrite(STDERR, "[generate-rotation] Aucun fichier audio trouve dans {$sourceReal}" . PHP_EOL);
    exit(1);
}

$outputDir = dirname($outputPath);
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "[generate-rotation] Impossible de creer le dossier: {$outputDir}" . PHP_EOL);
    exit(1);
}

$lines = [];
$lines[] = '# Rotation generee automatiquement';
$lines[] = '# Source: ' . normalizeSlash($sourceReal);
$lines[] = '# Date: ' . date('Y-m-d H:i:s');
$lines[] = '# Fichiers: ' . count($tracks);
$lines[] = '';
foreach ($tracks as $track) {
    $lines[] = $track;
}
$content = implode(PHP_EOL, $lines) . PHP_EOL;

if (file_put_contents($outputPath, $content) === false) {
    fwrite(STDERR, "[generate-rotation] Echec ecriture: {$outputPath}" . PHP_EOL);
    exit(1);
}

echo "[generate-rotation] OK: " . count($tracks) . " piste(s) -> " . normalizeSlash($outputPath) . PHP_EOL;

function normalizePath(string $root, string $path): string {
    $clean = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
    if ($clean === '') {
        return $root;
    }
    if (preg_match('/^[a-zA-Z]:\\\\/', $clean) === 1 || str_starts_with($clean, DIRECTORY_SEPARATOR)) {
        return $clean;
    }
    return $root . DIRECTORY_SEPARATOR . $clean;
}

function normalizeSlash(string $path): string {
    return str_replace('\\', '/', $path);
}

