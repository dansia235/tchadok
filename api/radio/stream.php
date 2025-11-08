<?php
/**
 * API Radio Stream - Tchadok Platform
 * Simule un flux radio en direct avec métadonnées
 */

header('Content-Type: audio/mpeg');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // Mode déconnecté si pas de DB
    $pdo = null;
}

// Playlist simulée pour la radio
$radioPlaylist = [
    [
        'id' => 1,
        'title' => 'Dounya',
        'artist' => 'Mounira Mitchala',
        'duration' => 255,
        'file' => 'demo_track_1.mp3'
    ],
    [
        'id' => 2,
        'title' => 'N\'Djamena City',
        'artist' => 'H2O Assoumane',
        'duration' => 198,
        'file' => 'demo_track_2.mp3'
    ],
    [
        'id' => 3,
        'title' => 'Rythme Ancestral',
        'artist' => 'Clément Masdongar',
        'duration' => 234,
        'file' => 'demo_track_3.mp3'
    ],
    [
        'id' => 4,
        'title' => 'Espoir du Matin',
        'artist' => 'Caleb Rimtobaye',
        'duration' => 287,
        'file' => 'demo_track_4.mp3'
    ],
    [
        'id' => 5,
        'title' => 'Chants d\'Antan',
        'artist' => 'Maimouna Youssouf',
        'duration' => 312,
        'file' => 'demo_track_5.mp3'
    ],
    [
        'id' => 6,
        'title' => 'Jazz Sahélien',
        'artist' => 'Abakar Sultan',
        'duration' => 276,
        'file' => 'demo_track_6.mp3'
    ]
];

// Détermine la track actuelle basée sur l'heure
$currentTime = time();
$totalPlaylistDuration = array_sum(array_column($radioPlaylist, 'duration'));
$currentPosition = $currentTime % $totalPlaylistDuration;

$elapsedTime = 0;
$currentTrack = $radioPlaylist[0];
$trackProgress = 0;

foreach ($radioPlaylist as $track) {
    if ($currentPosition >= $elapsedTime && $currentPosition < $elapsedTime + $track['duration']) {
        $currentTrack = $track;
        $trackProgress = $currentPosition - $elapsedTime;
        break;
    }
    $elapsedTime += $track['duration'];
}

// Met à jour la base de données si disponible
if ($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE radio_live SET current_track_id = ?, listeners_count = ?, updated_at = NOW() WHERE id = 1");
        $listeners = rand(200, 350); // Simule des auditeurs
        $stmt->execute([$currentTrack['id'], $listeners]);
    } catch (Exception $e) {
        // Ignore les erreurs DB en mode stream
    }
}

// Log de l'activité radio
$logEntry = date('Y-m-d H:i:s') . " - Radio Stream: {$currentTrack['title']} by {$currentTrack['artist']} - Progress: {$trackProgress}s\n";
@file_put_contents('../../logs/radio.log', $logEntry, FILE_APPEND | LOCK_EX);

// Headers pour le streaming audio
header('Accept-Ranges: bytes');
header('Content-Length: ' . (1024 * 1024 * 10)); // 10MB simulé

// Génère un flux audio factice
function generateAudioStream($duration = 3600) {
    $chunkSize = 8192;
    $totalBytes = $duration * 44100 * 2; // 44.1kHz, 16-bit
    $sentBytes = 0;
    
    while ($sentBytes < $totalBytes && connection_status() == 0) {
        // Génère des données audio aléatoires (silence avec bruit blanc léger)
        $audioData = '';
        for ($i = 0; $i < $chunkSize; $i++) {
            $audioData .= chr(rand(0, 15)); // Très faible niveau audio
        }
        
        echo $audioData;
        flush();
        
        $sentBytes += $chunkSize;
        usleep(185000); // ~185ms pour simuler le tempo audio réel
        
        // Vérifie si le client est toujours connecté
        if (connection_aborted()) {
            break;
        }
    }
}

// Démarre le flux
generateAudioStream();
?>