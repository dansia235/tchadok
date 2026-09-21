<?php
/**
 * Healthcheck moteur radio (Icecast/Liquidsoap)
 */

require_once '../../includes/database.php';
require_once '../../includes/radio-engine.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$dbStreamUrl = null;
if ($db) {
    try {
        $stmt = $db->query("SELECT stream_url FROM radio_live ORDER BY id ASC LIMIT 1");
        $dbStreamUrl = $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        $dbStreamUrl = null;
    }
}

$engineStatus = radioFetchEngineStatus();
$configuredUrl = radioGetConfiguredStreamUrl($dbStreamUrl);
$publicUrl = radioGetPublicStreamUrl($dbStreamUrl);

$payload = [
    'success' => true,
    'timestamp' => time(),
    'engine_enabled' => radioEnvBool('RADIO_ENGINE_ENABLED', false),
    'db_stream_url' => $dbStreamUrl ? radioMakeAbsoluteUrl($dbStreamUrl) : null,
    'configured_stream_url' => $configuredUrl,
    'public_stream_url' => $publicUrl,
    'engine' => [
        'connected' => !empty($engineStatus['success']),
        'name' => $engineStatus['engine'] ?? 'icecast',
        'mount' => $engineStatus['mount'] ?? env('RADIO_ENGINE_MOUNT', '/tchadok.mp3'),
        'listeners' => (int) ($engineStatus['listeners'] ?? 0),
        'error' => $engineStatus['error'] ?? null
    ]
];

if (!empty($engineStatus['now_playing'])) {
    $payload['engine']['now_playing'] = $engineStatus['now_playing'];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);

