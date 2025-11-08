<?php
/**
 * Générateur de données complet - Tchadok Platform
 * Génère des données réalistes pour toutes les tables
 */

// Supprimer les avertissements pour avoir un JSON propre
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Configuration directe
define('DB_HOST', 'localhost');
define('DB_NAME', 'tchadok');
define('DB_USER', 'dansia');
define('DB_PASS', 'dansia');

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Noms tchadiens réalistes
$tchadianNames = [
    'first_names' => [
        'male' => ['Abakar', 'Mahamat', 'Hassan', 'Idriss', 'Moussa', 'Adam', 'Abdoulaye', 'Haroun', 'Ali', 'Omar', 'Saleh', 'Youssouf', 'Ibrahim', 'Ahmat', 'Brahim', 'Ousmane', 'Issa', 'Zakaria', 'Abdel', 'Ramadan'],
        'female' => ['Fatima', 'Mounira', 'Achta', 'Khadija', 'Amina', 'Maimouna', 'Halima', 'Mariam', 'Aisha', 'Hawa', 'Zara', 'Hadja', 'Salamata', 'Djamila', 'Fatouma', 'Rahma', 'Nadjia', 'Balkissa', 'Hindou', 'Safa']
    ],
    'last_names' => ['Mahamat', 'Hassan', 'Idriss', 'Ahmat', 'Abakar', 'Moussa', 'Saleh', 'Haroun', 'Adam', 'Ali', 'Youssouf', 'Brahim', 'Ousmane', 'Issa', 'Abdoulaye', 'Omar', 'Zakaria', 'Ibrahim', 'Abdel', 'Ramadan', 'Mitchala', 'Masdongar', 'Assoumane', 'Rimtobaye', 'Sultan', 'Ndong', 'Kelem', 'Ngarlejy', 'Djarma', 'Haggar']
];

// Noms d'artistes et groupes
$artistNames = [
    'Mounira Mitchala', 'H2O Assoumane', 'Clément Masdongar', 'Caleb Rimtobaye', 
    'Maimouna Youssouf', 'Abakar Sultan', 'Sarah Ndong', 'MC Kelem', 'DJ Moussa',
    'Fatima Ngarlejy', 'Hassan Djarma', 'Achta Band', 'Les Sao', 'Sahel Vibes',
    'N\'Djamena Squad', 'Traditional Voices', 'Urban Chad', 'Chari Beats',
    'Desert Symphony', 'Logone Rhythm', 'Kanem Sounds', 'Batha Music',
    'Ouaddaï Groove', 'Tibesti Echo', 'Salamat Harmony'
];

// Genres musicaux
$genres = [
    'Afrobeat', 'Hip Hop', 'R&B/Soul', 'Gospel', 'Traditionnel', 'Jazz Fusion',
    'Pop', 'Reggae', 'Blues', 'Folk', 'Electronic', 'World Music',
    'Acoustic', 'Contemporary', 'Classic', 'Fusion'
];

// Titres d'albums créatifs
$albumTitles = [
    'Renaissance Africaine', 'Révolution Urbaine', 'Rythmes de N\'Djamena', 'Lumière Divine',
    'Héritage Ancestral', 'Jazz Sahélien', 'Voix du Désert', 'Cœur du Sahel',
    'Mélodies du Chari', 'Traditions Vivantes', 'Sons Modernes', 'Harmonie Tchadienne',
    'Échos du Tibesti', 'Rêves de Logone', 'Vibrations Urbaines', 'Musique du Cœur',
    'Chants d\'Espoir', 'Rythmes Sacrés', 'Nouveau Tchad', 'Génération Libre',
    'Couleurs Musicales', 'Passion Africaine', 'Danse des Ancêtres', 'Modern Chad',
    'Fusion Culturelle', 'Beats of Freedom', 'Sacred Rhythms', 'Urban Dreams'
];

// Titres de chansons
$songTitles = [
    'Dounya', 'N\'Djamena City', 'Rythme Ancestral', 'Espoir du Matin', 'Chants d\'Antan',
    'Sahel Dreams', 'Liberté', 'Amour Éternel', 'Danse de la Vie', 'Prière du Soir',
    'Cœur de Lion', 'Soleil Levant', 'Rivière Chari', 'Vent du Désert', 'Étoiles du Sud',
    'Hymne National', 'Fierté Tchadienne', 'Jeunesse Dorée', 'Paix et Unité', 'Renaissance',
    'Mélodie Sacrée', 'Rythme du Tambour', 'Chant des Oiseaux', 'Lune d\'Afrique', 'Sourire d\'Enfant',
    'Marche Triomphale', 'Danse Traditionnelle', 'Modern Beat', 'Love Song', 'Freedom Call',
    'Rising Sun', 'Desert Wind', 'Night Prayer', 'Morning Glory', 'Sweet Melody'
];

// Préfixes téléphoniques tchadiens
$phonePrefix = ['62', '63', '64', '65', '66', '68', '69', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];

function generatePassword($length = 12) {
    return bin2hex(random_bytes($length / 2));
}

function generatePhoneNumber() {
    global $phonePrefix;
    $prefix = $phonePrefix[array_rand($phonePrefix)];
    return $prefix . sprintf('%06d', rand(100000, 999999));
}

function generateTchadianName($gender = null) {
    global $tchadianNames;
    
    if (!$gender) {
        $gender = rand(0, 1) ? 'male' : 'female';
    }
    
    $firstName = $tchadianNames['first_names'][$gender][array_rand($tchadianNames['first_names'][$gender])];
    $lastName = $tchadianNames['last_names'][array_rand($tchadianNames['last_names'])];
    
    return ['first' => $firstName, 'last' => $lastName, 'full' => "$firstName $lastName"];
}

// Génération des utilisateurs
function generateUsers($pdo, $count = 50) {
    $users = [];
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Vérifier si l'admin existe déjà
    $checkAdmin = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkAdmin->execute(['admin@tchadok.td']);
    $existingAdmin = $checkAdmin->fetch();
    
    if (!$existingAdmin) {
        // Créer le compte admin seulement s'il n'existe pas
        $adminName = generateTchadianName('male');
        $adminData = [
            'admin_tchadok',
            'admin@tchadok.td',
            password_hash('12345678', PASSWORD_DEFAULT),
            $adminName['first'],
            $adminName['last'],
            generatePhoneNumber(),
            'Tchad',
            'N\'Djamena',
            1,
            1
        ];
        
        $stmt->execute($adminData);
        $users[] = ['id' => $pdo->lastInsertId()] + array_combine(['username', 'email', 'password', 'first_name', 'last_name', 'phone', 'country', 'city', 'email_verified', 'is_active'], $adminData);
    } else {
        // Utiliser l'admin existant
        $users[] = ['id' => $existingAdmin['id']];
    }
    
    // Utilisateurs normaux
    $cities = ['N\'Djamena', 'Moundou', 'Sarh', 'Abéché', 'Kelo', 'Koumra', 'Pala', 'Am Timan', 'Bongor', 'Doba'];
    
    for ($i = 1; $i < $count; $i++) {
        $gender = rand(0, 1) ? 'male' : 'female';
        $name = generateTchadianName($gender);
        $username = strtolower($name['first'] . '_' . substr($name['last'], 0, 3) . rand(1000, 9999));
        $email = $username . '@example.td';
        
        // Vérifier si l'utilisateur existe déjà
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkUser->execute([$email, $username]);
        $existingUser = $checkUser->fetch();
        
        if (!$existingUser) {
            $userData = [
                $username,
                $email,
                password_hash('12345678', PASSWORD_DEFAULT),
                $name['first'],
                $name['last'],
                generatePhoneNumber(),
                'Tchad',
                $cities[array_rand($cities)],
                rand(0, 1),
                1
            ];
            
            try {
                $stmt->execute($userData);
                $users[] = ['id' => $pdo->lastInsertId()] + array_combine(['username', 'email', 'password', 'first_name', 'last_name', 'phone', 'country', 'city', 'email_verified', 'is_active'], $userData);
            } catch (Exception $e) {
                // Ignorer les doublons et continuer
                continue;
            }
        }
    }
    
    return $users;
}

// Génération des artistes
function generateArtists($pdo, $users, $count = 25) {
    global $artistNames, $genres;
    
    $artists = [];
    $stmt = $pdo->prepare("INSERT INTO artists (user_id, stage_name, real_name, bio, genres, website, facebook, instagram, twitter, youtube, spotify, birth_place, verified, featured, total_streams, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Utiliser tous les utilisateurs comme artistes potentiels
    $artistUsers = array_slice($users, 0, $count);
    
    $cities = ['N\'Djamena', 'Moundou', 'Sarh', 'Abéché', 'Kelo'];
    $recordLabels = ['Tchadok Records', 'Sahel Music', 'Desert Sounds', 'African Vibes', 'Chad Music Group', 'Independent'];
    
    $usedNames = [];
    foreach (array_slice($artistUsers, 0, min($count, count($artistUsers))) as $user) {
        // Vérifier si cet utilisateur a déjà un profil d'artiste
        $checkArtist = $pdo->prepare("SELECT id FROM artists WHERE user_id = ?");
        $checkArtist->execute([$user['id']]);
        if ($checkArtist->fetch()) {
            continue; // Passer cet utilisateur s'il a déjà un profil d'artiste
        }
        
        do {
            $stageName = $artistNames[array_rand($artistNames)];
        } while (in_array($stageName, $usedNames));
        $usedNames[] = $stageName;
        
        $genre = $genres[array_rand($genres)];
        $debutYear = rand(2010, 2024);
        
        $artistData = [
            $user['id'],
            $stageName,
            isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : $stageName,
            "Artiste $genre originaire du Tchad, passionné par la fusion des rythmes traditionnels et modernes.",
            $genre,
            'https://' . strtolower(str_replace(' ', '', $stageName)) . '.td',
            '@' . strtolower(str_replace(' ', '', $stageName)),
            '@' . strtolower(str_replace(' ', '', $stageName)),
            '@' . strtolower(str_replace(' ', '', $stageName)),
            strtolower(str_replace(' ', '', $stageName)),
            strtolower(str_replace(' ', '', $stageName)),
            $cities[array_rand($cities)],
            rand(0, 1),
            rand(0, 1),
            rand(50000, 5000000),
            1
        ];
        
        try {
            $stmt->execute($artistData);
            $artists[] = ['id' => $pdo->lastInsertId()] + array_combine(['user_id', 'stage_name', 'real_name', 'bio', 'genres', 'website', 'facebook', 'instagram', 'twitter', 'youtube', 'spotify', 'birth_place', 'verified', 'featured', 'total_streams', 'is_active'], $artistData);
        } catch (Exception $e) {
            // Ignorer les doublons et continuer
            continue;
        }
    }
    
    return $artists;
}

// Génération des albums
function generateAlbums($pdo, $artists, $count = 40) {
    global $albumTitles, $genres;
    
    $albums = [];
    $stmt = $pdo->prepare("INSERT INTO albums (artist_id, title, description, type, price, release_date, language, total_tracks, total_duration, is_free, is_featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $albumTypes = ['album', 'ep', 'single', 'maxi_single'];
    $currencies = ['XAF', 'USD'];
    
    foreach ($artists as $artist) {
        $albumsForArtist = rand(1, 3);
        
        for ($i = 0; $i < $albumsForArtist && count($albums) < $count; $i++) {
            $title = $albumTitles[array_rand($albumTitles)];
            $albumType = $albumTypes[array_rand($albumTypes)];
            $totalTracks = $albumType === 'Single' ? 1 : ($albumType === 'EP' ? rand(3, 6) : rand(8, 15));
            $releaseDate = date('Y-m-d', strtotime('-' . rand(0, 1460) . ' days'));
            
            $albumData = [
                $artist['id'],
                $title,
                "Album $albumType de {$artist['stage_name']} explorant les thèmes de la culture tchadienne moderne.",
                $albumType,
                rand(500, 2000),
                $releaseDate,
                'fr',
                $totalTracks,
                $totalTracks * rand(180, 300), // Durée approximative
                rand(0, 1),
                rand(0, 1),
                'approved'
            ];
            
            $stmt->execute($albumData);
            $albums[] = ['id' => $pdo->lastInsertId()] + array_combine(['artist_id', 'title', 'description', 'type', 'price', 'release_date', 'language', 'total_tracks', 'total_duration', 'is_free', 'is_featured', 'status'], $albumData);
        }
    }
    
    return $albums;
}

// Génération des pistes
function generateTracks($pdo, $albums, $artists) {
    global $songTitles;
    
    $tracks = [];
    $stmt = $pdo->prepare("INSERT INTO tracks (album_id, artist_id, title, duration, track_number, lyrics, audio_file, is_featured, price, is_free, language, release_date, status, total_streams, total_downloads) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($albums as $album) {
        $artist = array_filter($artists, function($a) use ($album) {
            return $a['id'] === $album['artist_id'];
        });
        $artist = reset($artist);
        
        for ($trackNum = 1; $trackNum <= $album['total_tracks']; $trackNum++) {
            $title = $songTitles[array_rand($songTitles)];
            $duration = rand(120, 360); // 2-6 minutes
            
            $trackData = [
                $album['id'],
                $album['artist_id'],
                $title,
                $duration,
                $trackNum,
                "Paroles de la chanson $title par {$artist['stage_name']}...",
                strtolower(str_replace(' ', '_', $title)) . '.mp3',
                rand(0, 1),
                rand(200, 800),
                rand(0, 1),
                'fr',
                $album['release_date'],
                'approved',
                rand(100, 100000),
                rand(10, 10000)
            ];
            
            $stmt->execute($trackData);
            $tracks[] = ['id' => $pdo->lastInsertId()] + array_combine(['album_id', 'artist_id', 'title', 'duration', 'track_number', 'lyrics', 'audio_file', 'is_featured', 'price', 'is_free', 'language', 'release_date', 'status', 'total_streams', 'total_downloads'], $trackData);
        }
    }
    
    return $tracks;
}

// Génération des playlists
function generatePlaylists($pdo, $users, $tracks, $count = 30) {
    $playlists = [];
    $stmt = $pdo->prepare("INSERT INTO playlists (user_id, name, description, is_public, total_tracks, total_duration) VALUES (?, ?, ?, ?, ?, ?)");
    
    $playlistNames = [
        'Mes Favoris', 'Musique du Matin', 'Soirée Détente', 'Workout Playlist', 'Road Trip',
        'Classics Tchadiens', 'Nouveautés', 'Chill Vibes', 'Party Mix', 'Romantic Songs',
        'Traditional Beats', 'Modern Chad', 'Hip Hop Tchad', 'Gospel Inspirations', 'Jazz Evening'
    ];
    
    foreach (array_slice($users, 0, $count) as $user) {
        $playlistCount = rand(1, 3);
        
        for ($i = 0; $i < $playlistCount; $i++) {
            $title = $playlistNames[array_rand($playlistNames)] . ' ' . rand(1, 100);
            $tracksInPlaylist = rand(5, 20);
            $totalDuration = $tracksInPlaylist * rand(180, 300);
            
            $playlistData = [
                $user['id'],
                $title,
                "Playlist personnalisée créée par un utilisateur Tchadok",
                rand(0, 1),
                $tracksInPlaylist,
                $totalDuration
            ];
            
            $stmt->execute($playlistData);
            $playlistId = $pdo->lastInsertId();
            $playlists[] = ['id' => $playlistId] + array_combine(['user_id', 'name', 'description', 'is_public', 'total_tracks', 'total_duration'], $playlistData);
            
            // Ajouter des pistes à la playlist (seulement si on a des tracks)
            if (!empty($tracks)) {
                $stmtTracks = $pdo->prepare("INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)");
                $selectedTracks = array_rand($tracks, min($tracksInPlaylist, count($tracks)));
                if (!is_array($selectedTracks)) $selectedTracks = [$selectedTracks];
                
                foreach ($selectedTracks as $pos => $trackIndex) {
                    $stmtTracks->execute([$playlistId, $tracks[$trackIndex]['id'], $pos + 1]);
                }
            }
        }
    }
    
    return $playlists;
}

// Génération des émissions radio
function generateRadioShows($pdo, $users, $count = 10) {
    $shows = [];
    $stmt = $pdo->prepare("INSERT INTO radio_shows (host_user_id, title, description, start_time, end_time, days_of_week, genre, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $showNames = [
        'Réveil Musical', 'Soirée Traditionnelle', 'Urban Beats', 'Spécial Artistes',
        'Jazz Evening', 'Gospel Hour', 'Classic Chad', 'New Discoveries',
        'Request Show', 'Weekend Vibes', 'Night Talk', 'Morning Coffee'
    ];
    
    $timeSlots = [
        ['06:00:00', '09:00:00'], ['09:00:00', '12:00:00'], ['12:00:00', '15:00:00'],
        ['15:00:00', '18:00:00'], ['18:00:00', '21:00:00'], ['21:00:00', '00:00:00']
    ];
    
    $daysOptions = ['1,2,3,4,5', '6,7', '1,3,5', '2,4,6', '7'];
    
    foreach (array_slice($users, 0, $count) as $user) {
        $showName = $showNames[array_rand($showNames)];
        $timeSlot = $timeSlots[array_rand($timeSlots)];
        $days = $daysOptions[array_rand($daysOptions)];
        
        $showData = [
            $user['id'],
            $showName,
            "Émission radio spécialisée diffusée sur Tchadok Radio",
            $timeSlot[0],
            $timeSlot[1],
            $days,
            'Variété',
            1
        ];
        
        $stmt->execute($showData);
        $shows[] = ['id' => $pdo->lastInsertId()] + array_combine(['host_user_id', 'title', 'description', 'start_time', 'end_time', 'days_of_week', 'genre', 'is_active'], $showData);
    }
    
    return $shows;
}

// Génération des transactions
function generateTransactions($pdo, $users, $count = 50) {
    $transactions = [];
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, currency, description, reference, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $paymentMethods = ['airtel_money', 'moov_money'];
    $statuses = ['completed', 'pending', 'failed', 'refunded'];
    $descriptions = [
        'Abonnement Premium', 'Achat d\'album', 'Achat de single', 'Don à l\'artiste',
        'Recharge de crédits', 'Achat de playlist', 'Support premium'
    ];
    
    for ($i = 0; $i < $count; $i++) {
        $user = $users[array_rand($users)];
        $method = $paymentMethods[array_rand($paymentMethods)];
        $amount = rand(500, 10000);
        $status = $statuses[array_rand($statuses)];
        
        $transactionId = strtoupper($method === 'airtel_money' ? 'AIRTEL_' : 'MOOV_') . date('YmdHis', strtotime('-' . rand(0, 30) . ' days')) . '_' . rand(1000, 9999);
        
        $transactionData = [
            $user['id'],
            'purchase',
            $amount,
            'XAF',
            $descriptions[array_rand($descriptions)],
            'REF_' . rand(100000, 999999),
            $status
        ];
        
        $stmt->execute($transactionData);
        $transactions[] = ['id' => $pdo->lastInsertId()] + array_combine(['user_id', 'type', 'amount', 'currency', 'description', 'reference', 'status'], $transactionData);
    }
    
    return $transactions;
}

// Génération des articles de blog
function generateBlogPosts($pdo, $users, $count = 20) {
    $posts = [];
    $stmt = $pdo->prepare("INSERT INTO blog_posts (author_user_id, title, content, excerpt, featured_image, category, tags, status, is_featured, views, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $categories = ['Actualités', 'Interviews', 'Critiques', 'Événements', 'Culture', 'Tech'];
    $postTitles = [
        'La Renaissance de la Musique Tchadienne',
        'Interview Exclusive avec Mounira Mitchala',
        'Le Festival de N\'Djamena 2024',
        'Les Nouveaux Talents à Suivre',
        'L\'Impact du Digital sur la Musique Locale',
        'Retour sur le Concert de Clément Masdongar',
        'La Musique Traditionnelle à l\'Ère Moderne',
        'Les Jeunes Artistes Font leur Show',
        'Comment le Streaming Change la Donne',
        'Portrait d\'une Scène Musicale en Mutation'
    ];
    
    foreach (array_slice($users, 0, $count) as $user) {
        $title = $postTitles[array_rand($postTitles)];
        $category = $categories[array_rand($categories)];
        $content = "Contenu détaillé de l'article '$title'. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...";
        
        $postData = [
            $user['id'],
            $title,
            $content,
            substr($content, 0, 150) . '...',
            null,
            $category,
            'musique,tchad,culture',
            'published',
            rand(0, 1),
            rand(50, 5000)
        ];
        
        $stmt->execute($postData);
        $posts[] = ['id' => $pdo->lastInsertId()] + array_combine(['author_user_id', 'title', 'content', 'excerpt', 'featured_image', 'category', 'tags', 'status', 'is_featured', 'views'], $postData);
    }
    
    return $posts;
}

// Exécution de la génération
try {
    $action = $_GET['action'] ?? 'all';
    $response = ['success' => true, 'data' => [], 'message' => ''];
    
    switch ($action) {
        case 'users':
            $count = (int)($_GET['count'] ?? 50);
            $users = generateUsers($pdo, $count);
            $response['data'] = $users;
            $response['message'] = count($users) . " utilisateurs générés";
            break;
            
        case 'clear':
            // Suppression de toutes les données de test
            try {
                $pdo->beginTransaction();
                
                // Supprimer dans l'ordre pour respecter les contraintes de clés étrangères
                $pdo->exec("DELETE FROM playlist_tracks");
                $pdo->exec("DELETE FROM playlists");
                $pdo->exec("DELETE FROM transactions");
                $pdo->exec("DELETE FROM tracks");
                $pdo->exec("DELETE FROM albums");
                $pdo->exec("DELETE FROM artists WHERE user_id > 1"); // Garder l'admin
                $pdo->exec("DELETE FROM users WHERE id > 1"); // Garder l'admin
                
                // Réinitialiser les auto-increment
                $pdo->exec("ALTER TABLE playlist_tracks AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE playlists AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE transactions AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE tracks AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE albums AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE artists AUTO_INCREMENT = 1");
                $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 2"); // Commencer à 2 pour garder l'admin
                
                $pdo->commit();
                $response['message'] = "Toutes les données de test ont été supprimées avec succès";
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'all':
        default:
            // Génération complète
            $pdo->beginTransaction();
            
            try {
                // 1. Utilisateurs (50)
                $users = generateUsers($pdo, 50);
                
                // 2. Artistes (25)
                $artists = generateArtists($pdo, $users, 25);
                
                // 3. Albums (40)
                $albums = generateAlbums($pdo, $artists, 40);
                
                // 4. Pistes (selon albums)
                $tracks = generateTracks($pdo, $albums, $artists);
                
                // 5. Playlists (30)
                $playlists = generatePlaylists($pdo, $users, $tracks, 30);
                
                // 6. Transactions (simulation de 50 paiements)
                $transactions = generateTransactions($pdo, $users, 50);
                
                // Données générées avec succès
                $pdo->commit();
                
                $response['data'] = [
                    'users' => count($users),
                    'artists' => count($artists),
                    'albums' => count($albums),
                    'tracks' => count($tracks),
                    'playlists' => count($playlists),
                    'transactions' => count($transactions)
                ];
                
                $response['message'] = "Génération complète terminée avec succès";
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
?>