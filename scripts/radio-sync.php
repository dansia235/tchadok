<?php
/**
 * Synchronise radio_live avec le moteur de stream.
 * Usage: php scripts/radio-sync.php
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/radio-engine.php';

$engine = radioFetchEngineStatus();
if (empty($engine['success'])) {
    fwrite(STDERR, "[radio-sync] Engine indisponible: " . ($engine['error'] ?? 'unknown') . PHP_EOL);
    exit(1);
}

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();
if (!$db) {
    fwrite(STDERR, "[radio-sync] Base indisponible" . PHP_EOL);
    exit(1);
}

if (!tableExists('radio_live')) {
    fwrite(STDERR, "[radio-sync] Table radio_live absente" . PHP_EOL);
    exit(1);
}

$listeners = (int) ($engine['listeners'] ?? 0);
$streamUrl = radioMakeAbsoluteUrl($engine['stream_url'] ?? null);

$rowId = $db->query("SELECT id FROM radio_live ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($rowId) {
    $stmt = $db->prepare("
        UPDATE radio_live
        SET listeners_count = ?, is_live = 1, stream_url = COALESCE(?, stream_url), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$listeners, $streamUrl, $rowId]);
} else {
    $stmt = $db->prepare("
        INSERT INTO radio_live (listeners_count, is_live, stream_url, updated_at)
        VALUES (?, 1, ?, NOW())
    ");
    $stmt->execute([$listeners, $streamUrl]);
}

echo "[radio-sync] OK listeners={$listeners} stream=" . ($streamUrl ?: 'n/a') . PHP_EOL;

