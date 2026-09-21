<?php
/**
 * Flux radio - redirige vers le stream configure
 */

require_once '../../includes/database.php';
require_once '../../includes/radio-engine.php';

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();
$configuredStreamUrl = null;

if ($db) {
    try {
        $stmt = $db->query("SELECT stream_url FROM radio_live ORDER BY id ASC LIMIT 1");
        $configuredStreamUrl = $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        // fallback below
    }
}

$streamUrl = radioGetConfiguredStreamUrl($configuredStreamUrl);

if (!$streamUrl) {
    $engineStatus = radioFetchEngineStatus();
    if (!empty($engineStatus['success']) && !empty($engineStatus['stream_url'])) {
        $streamUrl = $engineStatus['stream_url'];
    }
}

if ($streamUrl) {
    header('Location: ' . $streamUrl, true, 302);
    exit();
}

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "Flux radio non configure. Definissez radio_live.stream_url ou RADIO_STREAM_PUBLIC_URL.";
