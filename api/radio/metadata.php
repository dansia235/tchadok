<?php
/**
 * API Radio Metadata - Tchadok Platform
 * Fournit les métadonnées actuelles de la radio
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $pdo = null;
}

// Playlist radio simulée
$radioPlaylist = [
    [
        'id' => 1,
        'title' => 'Dounya',
        'artist' => 'Mounira Mitchala',
        'album' => 'Renaissance Africaine',
        'duration' => 255,
        'genre' => 'Soul/R&B',
        'year' => 2024,
        'cover' => '/assets/images/albums/mounira_dounya.jpg'
    ],
    [
        'id' => 2,
        'title' => 'N\'Djamena City',
        'artist' => 'H2O Assoumane',
        'album' => 'Révolution Urbaine',
        'duration' => 198,
        'genre' => 'Hip Hop',
        'year' => 2024,
        'cover' => '/assets/images/albums/h2o_ndjamena.jpg'
    ],
    [
        'id' => 3,
        'title' => 'Rythme Ancestral',
        'artist' => 'Clément Masdongar',
        'album' => 'Rythmes de N\'Djamena',
        'duration' => 234,
        'genre' => 'Afrobeat',
        'year' => 2023,
        'cover' => '/assets/images/albums/clement_rythmes.jpg'
    ],
    [
        'id' => 4,
        'title' => 'Espoir du Matin',
        'artist' => 'Caleb Rimtobaye',
        'album' => 'Lumière Divine',
        'duration' => 287,
        'genre' => 'Gospel',
        'year' => 2024,
        'cover' => '/assets/images/albums/caleb_lumiere.jpg'
    ],
    [
        'id' => 5,
        'title' => 'Chants d\'Antan',
        'artist' => 'Maimouna Youssouf',
        'album' => 'Héritage Ancestral',
        'duration' => 312,
        'genre' => 'Traditionnel',
        'year' => 2023,
        'cover' => '/assets/images/albums/maimouna_heritage.jpg'
    ],
    [
        'id' => 6,
        'title' => 'Jazz Sahélien',
        'artist' => 'Abakar Sultan',
        'album' => 'Jazz Sahélien',
        'duration' => 276,
        'genre' => 'Jazz Fusion',
        'year' => 2024,
        'cover' => '/assets/images/albums/abakar_jazz.jpg'
    ]
];

// Émissions radio
$radioShows = [
    [
        'id' => 1,
        'title' => 'Réveil Musical',
        'host' => 'Abakar Mahamat',
        'description' => 'Commencez la journée avec les hits du moment',
        'start_time' => '06:00',
        'end_time' => '09:00',
        'avatar' => '/assets/images/hosts/abakar_mahamat.jpg'
    ],
    [
        'id' => 2,
        'title' => 'Soirée Traditionnelle',
        'host' => 'DJ Moussa',
        'description' => 'Découvrez les sons authentiques du Tchad',
        'start_time' => '19:00',
        'end_time' => '21:00',
        'avatar' => '/assets/images/hosts/dj_moussa.jpg'
    ],
    [
        'id' => 3,
        'title' => 'Urban Beats',
        'host' => 'MC Kelem',
        'description' => 'Le meilleur du rap et hip-hop tchadien',
        'start_time' => '21:00',
        'end_time' => '23:00',
        'avatar' => '/assets/images/hosts/mc_kelem.jpg'
    ],
    [
        'id' => 4,
        'title' => 'Spécial Artistes',
        'host' => 'Sarah Ndong',
        'description' => 'Interviews exclusives et coulisses',
        'start_time' => '14:00',
        'end_time' => '16:00',
        'avatar' => '/assets/images/hosts/sarah_ndong.jpg'
    ]
];

// Calcule la track et l'émission actuelles
$currentTime = time();
$currentHour = date('H');
$totalPlaylistDuration = array_sum(array_column($radioPlaylist, 'duration'));
$currentPosition = $currentTime % $totalPlaylistDuration;

// Trouve la track actuelle
$elapsedTime = 0;
$currentTrack = $radioPlaylist[0];
$trackProgress = 0;
$nextTrack = $radioPlaylist[1];

foreach ($radioPlaylist as $index => $track) {
    if ($currentPosition >= $elapsedTime && $currentPosition < $elapsedTime + $track['duration']) {
        $currentTrack = $track;
        $trackProgress = $currentPosition - $elapsedTime;
        $nextTrack = $radioPlaylist[($index + 1) % count($radioPlaylist)];
        break;
    }
    $elapsedTime += $track['duration'];
}

// Trouve l'émission actuelle
$currentShow = null;
foreach ($radioShows as $show) {
    $startHour = (int)substr($show['start_time'], 0, 2);
    $endHour = (int)substr($show['end_time'], 0, 2);
    
    if ($endHour < $startHour) { // Émission qui traverse minuit
        if ($currentHour >= $startHour || $currentHour < $endHour) {
            $currentShow = $show;
            break;
        }
    } else {
        if ($currentHour >= $startHour && $currentHour < $endHour) {
            $currentShow = $show;
            break;
        }
    }
}

// Génère des statistiques en temps réel
$listeners = rand(200, 350);
$peakListeners = rand(400, 600);

// Met à jour la base de données si disponible
if ($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE radio_live SET current_track_id = ?, listeners_count = ?, updated_at = NOW()");
        $stmt->execute([$currentTrack['id'], $listeners]);
    } catch (Exception $e) {
        // Continue sans DB
    }
}

// Prépare la réponse
$response = [
    'success' => true,
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s'),
    'station' => [
        'name' => 'Tchadok Radio',
        'tagline' => '24/7 Musique Tchadienne',
        'frequency' => '101.5 FM',
        'website' => 'https://tchadok.td',
        'is_live' => true
    ],
    'current_track' => [
        'id' => $currentTrack['id'],
        'title' => $currentTrack['title'],
        'artist' => $currentTrack['artist'],
        'album' => $currentTrack['album'],
        'duration' => $currentTrack['duration'],
        'progress' => $trackProgress,
        'remaining' => $currentTrack['duration'] - $trackProgress,
        'genre' => $currentTrack['genre'],
        'year' => $currentTrack['year'],
        'cover_url' => $currentTrack['cover'],
        'percentage' => round(($trackProgress / $currentTrack['duration']) * 100, 1)
    ],
    'next_track' => [
        'id' => $nextTrack['id'],
        'title' => $nextTrack['title'],
        'artist' => $nextTrack['artist'],
        'album' => $nextTrack['album'],
        'duration' => $nextTrack['duration'],
        'cover_url' => $nextTrack['cover']
    ],
    'current_show' => $currentShow,
    'stats' => [
        'listeners' => $listeners,
        'peak_today' => $peakListeners,
        'total_tracks_today' => rand(150, 200),
        'uptime' => '99.8%'
    ],
    'history' => array_slice($radioPlaylist, -3, 3), // 3 dernières tracks
    'upcoming' => array_slice($radioPlaylist, 0, 3)  // 3 prochaines tracks
];

// Log de l'activité
$logEntry = date('Y-m-d H:i:s') . " - Metadata Request: {$currentTrack['title']} - Listeners: {$listeners}\n";
@file_put_contents('../../logs/radio_metadata.log', $logEntry, FILE_APPEND | LOCK_EX);

// Retourne la réponse JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>