<?php
/**
 * API Radio Metadata - Tchadok Platform
 * Metadonnees basees sur la base de donnees
 */

require_once '../../includes/database.php';
require_once '../../includes/radio-engine.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['message' => 'Connexion base indisponible']
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$live = getRadioLiveInfo();
$currentTrack = $live['current_track'] ?? null;
$currentShow = $live['current_show'] ?? null;
$engineStatus = radioFetchEngineStatus();
$preferEngineStream = radioEnvBool('RADIO_ENGINE_PREFER_STREAM', true);
$configuredPublicStream = radioGetPublicStreamUrl($live['stream_url'] ?? null);
$useEngineListenUrl = radioEnvBool('RADIO_ENGINE_USE_STATUS_LISTENURL', false);

if (!empty($engineStatus['success'])) {
    $live['is_live'] = true;
    $live['listeners_count'] = (int) ($engineStatus['listeners'] ?? $live['listeners_count']);

    // Par defaut, on privilegie l'URL publique configuree (env/DB) pour eviter
    // les cas ou listenurl d'Icecast pointe vers un hostname interne/non resolu.
    if ($useEngineListenUrl && !empty($engineStatus['stream_url']) && ($preferEngineStream || empty($configuredPublicStream))) {
        $live['stream_url'] = $engineStatus['stream_url'];
    } else {
        $live['stream_url'] = $configuredPublicStream;
    }

    if (!$currentTrack && !empty($engineStatus['now_playing'])) {
        $currentTrack = [
            'id' => null,
            'title' => $engineStatus['now_playing']['title'] ?? 'Direct Radio',
            'artist' => $engineStatus['now_playing']['artist'] ?? 'Tchadok Radio',
            'duration' => 0,
            'audio_file' => null,
            'cover_image' => null
        ];
    }
}

if (empty($live['stream_url'])) {
    if (!empty($engineStatus['stream_url']) && $useEngineListenUrl) {
        $live['stream_url'] = $engineStatus['stream_url'];
    } else {
        $live['stream_url'] = radioGetPublicStreamUrl(null);
    }
}

if (!$currentShow) {
    $currentShow = findCurrentShow($db);
}

$history = getRecentStreams(3);
$upcomingTracks = getTrendingTracks(3);

$nextTrack = null;
if ($currentTrack) {
    $stmt = $db->prepare("
        SELECT t.id, t.title, ar.stage_name AS artist, al.cover_image AS cover
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        WHERE t.id <> ?
        ORDER BY t.total_streams DESC, t.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $currentTrack['id']]);
    $nextTrack = $stmt->fetch();
}

$stats = [
    'listeners' => (int) ($live['listeners_count'] ?? 0),
    'total_tracks_today' => getTodayStreamsCount($db)
];

echo json_encode([
    'success' => true,
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s'),
    'station' => [
        'name' => 'Tchadok Radio',
        'tagline' => 'Musique tchadienne en continu',
        'is_live' => (bool) ($live['is_live'] ?? false),
        'stream_url' => $live['stream_url'] ?? null
    ],
    'current_track' => $currentTrack,
    'next_track' => $nextTrack,
    'current_show' => $currentShow,
    'stats' => $stats,
    'history' => $history,
    'upcoming' => $upcomingTracks,
    'engine' => [
        'enabled' => radioEnvBool('RADIO_ENGINE_ENABLED', false),
        'source' => $engineStatus['engine'] ?? 'database',
        'status' => !empty($engineStatus['success']) ? 'connected' : 'fallback',
        'message' => !empty($engineStatus['success']) ? 'Moteur de stream actif' : ($engineStatus['error'] ?? 'Metadonnees DB')
    ]
], JSON_UNESCAPED_UNICODE);

function findCurrentShow($db) {
    if (!tableExists('radio_shows')) return null;
    $stmt = $db->query("
        SELECT id, title, host_name, start_time, end_time
        FROM radio_shows
        WHERE status = 'active'
        ORDER BY start_time ASC
    ");
    $shows = $stmt->fetchAll();
    if (!$shows) return null;

    $now = time();
    $today = date('Y-m-d');
    foreach ($shows as $show) {
        if (!$show['start_time'] || !$show['end_time']) {
            continue;
        }
        $startTs = strtotime($today . ' ' . $show['start_time']);
        $endTs = strtotime($today . ' ' . $show['end_time']);
        if ($endTs <= $startTs) {
            $endTs = strtotime('+1 day', $endTs);
        }
        if ($now >= $startTs && $now < $endTs) {
            return [
                'id' => (int) $show['id'],
                'title' => $show['title'],
                'host' => $show['host_name'] ?: 'Tchadok Radio',
                'start_time' => $show['start_time'],
                'end_time' => $show['end_time']
            ];
        }
    }
    return null;
}

function getTodayStreamsCount($db) {
    if (!tableExists('streams')) return 0;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM streams WHERE DATE(created_at) = CURDATE()");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
