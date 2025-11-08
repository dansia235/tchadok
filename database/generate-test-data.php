<?php
/**
 * TCHADOK PLATFORM - GÉNÉRATEUR DE DONNÉES DE TEST
 *
 * Ce script génère des données de test réalistes pour la plateforme :
 * - Artistes tchadiens (réels et fictifs)
 * - Genres musicaux
 * - Albums
 * - Chansons avec liens YouTube
 * - Quelques playlists
 *
 * USAGE: php generate-test-data.php
 */

require_once __DIR__ . '/../includes/functions.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   TCHADOK - GÉNÉRATEUR DE DONNÉES DE TEST                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    echo "✓ Connexion à la base de données réussie\n\n";

    // ========================================
    // 1. GENRES MUSICAUX
    // ========================================
    echo "━━━ CRÉATION DES GENRES MUSICAUX ━━━\n";

    $genres = [
        ['name' => 'Afrobeat', 'slug' => 'afrobeat', 'description' => 'Musique afrobeat moderne avec des influences africaines'],
        ['name' => 'Hip-Hop Tchadien', 'slug' => 'hip-hop-tchadien', 'description' => 'Rap et hip-hop du Tchad'],
        ['name' => 'Coupé-Décalé', 'slug' => 'coupe-decale', 'description' => 'Musique populaire d\'Afrique de l\'Ouest'],
        ['name' => 'Afro-Pop', 'slug' => 'afro-pop', 'description' => 'Pop africaine moderne'],
        ['name' => 'R&B Afro', 'slug' => 'rnb-afro', 'description' => 'R&B avec des influences africaines'],
        ['name' => 'Gospel', 'slug' => 'gospel', 'description' => 'Musique chrétienne africaine'],
        ['name' => 'Traditionnel', 'slug' => 'traditionnel', 'description' => 'Musique traditionnelle tchadienne'],
        ['name' => 'Dancehall', 'slug' => 'dancehall', 'description' => 'Dancehall africain'],
        ['name' => 'Zouk', 'slug' => 'zouk', 'description' => 'Zouk et musique caribéenne'],
        ['name' => 'Afro-Trap', 'slug' => 'afro-trap', 'description' => 'Fusion de trap et musique africaine']
    ];

    $genreIds = [];
    foreach ($genres as $genre) {
        $stmt = $db->prepare("INSERT INTO genres (name, slug, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$genre['name'], $genre['slug'], $genre['description']]);
        $genreIds[$genre['slug']] = $db->lastInsertId() ?: $db->query("SELECT id FROM genres WHERE slug = '{$genre['slug']}'")->fetchColumn();
        echo "  • {$genre['name']} (ID: {$genreIds[$genre['slug']]})\n";
    }

    echo "\n✓ " . count($genres) . " genres créés\n\n";

    // ========================================
    // 2. ARTISTES TCHADIENS
    // ========================================
    echo "━━━ CRÉATION DES ARTISTES ━━━\n";

    $artists = [
        [
            'stage_name' => 'Cleo Grae',
            'real_name' => 'Cleophas Mally',
            'bio' => 'Rappeur tchadien, pionnier du hip-hop au Tchad. Connu pour ses textes engagés et son flow unique.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'Mister You TD',
            'real_name' => 'Mahamat Saleh',
            'bio' => 'Artiste afrobeat et afro-pop. Ses mélodies accrocheuses et son énergie scénique font de lui une star montante.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'Ngariety',
            'real_name' => 'Ngariety Mbairassem',
            'bio' => 'Chanteuse R&B et soul tchadienne. Sa voix douce et ses ballades romantiques touchent le cœur.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'Akon One',
            'real_name' => 'Ahmed Konga',
            'bio' => 'Rappeur et producteur. Mélange le hip-hop avec des sonorités traditionnelles tchadiennes.',
            'country' => 'Tchad',
            'city' => 'Moundou'
        ],
        [
            'stage_name' => 'La Diva du Logone',
            'real_name' => 'Sarah Ndouba',
            'bio' => 'Chanteuse de zouk et afro-pop. Voix puissante et présence scénique captivante.',
            'country' => 'Tchad',
            'city' => 'Sarh'
        ],
        [
            'stage_name' => 'Black Stone',
            'real_name' => 'Ibrahim Moussa',
            'bio' => 'Rappeur afro-trap. Textes percutants sur la réalité urbaine de N\'Djamena.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'DJ Tchadiano',
            'real_name' => 'Youssouf Hassan',
            'bio' => 'DJ et producteur. Créateur de beats afrobeat et coupé-décalé qui font danser tout le Sahel.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'Sister Grace',
            'real_name' => 'Grace Ngartoulao',
            'bio' => 'Chanteuse gospel. Messages d\'espoir et de foi portés par une voix angélique.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ],
        [
            'stage_name' => 'Le Roi du Sahel',
            'real_name' => 'Mahamat Ali',
            'bio' => 'Artiste traditionnel modernisé. Fusionne les instruments traditionnels avec la production moderne.',
            'country' => 'Tchad',
            'city' => 'Abéché'
        ],
        [
            'stage_name' => 'Aminata Star',
            'real_name' => 'Aminata Mahamat',
            'bio' => 'Chanteuse afro-pop et dancehall. Énergique et positive, elle apporte la joie partout.',
            'country' => 'Tchad',
            'city' => 'N\'Djamena'
        ]
    ];

    $artistIds = [];

    foreach ($artists as $index => $artist) {
        // Vérifier si un utilisateur artiste existe déjà
        $stmt = $db->prepare("SELECT id FROM artists WHERE stage_name = ?");
        $stmt->execute([$artist['stage_name']]);
        $existingArtist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingArtist) {
            $artistIds[$artist['stage_name']] = $existingArtist['id'];
            echo "  • {$artist['stage_name']} (déjà existant, ID: {$artistIds[$artist['stage_name']]})\n";
        } else {
            // Créer un utilisateur pour cet artiste
            $username = strtolower(str_replace(' ', '_', $artist['stage_name']));
            $email = $username . '@tchadok.com';
            $passwordHash = hashPassword('artist123');

            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, password_hash, first_name, last_name, country, user_type, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 2, 1, NOW())
            ");

            $nameParts = explode(' ', $artist['real_name']);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : $nameParts[0];

            $stmt->execute([
                $username,
                $email,
                $passwordHash,
                $passwordHash,
                $firstName,
                $lastName,
                $artist['country']
            ]);

            $userId = $db->lastInsertId();

            // Créer le profil artiste
            $stmt = $db->prepare("
                INSERT INTO artists (user_id, stage_name, real_name, bio, country, city, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                $userId,
                $artist['stage_name'],
                $artist['real_name'],
                $artist['bio'],
                $artist['country'],
                $artist['city']
            ]);

            $artistIds[$artist['stage_name']] = $db->lastInsertId();
            echo "  • {$artist['stage_name']} (ID: {$artistIds[$artist['stage_name']]}, User: $email)\n";
        }
    }

    echo "\n✓ " . count($artists) . " artistes créés/vérifiés\n\n";

    // ========================================
    // 3. ALBUMS
    // ========================================
    echo "━━━ CRÉATION DES ALBUMS ━━━\n";

    $albums = [
        [
            'artist' => 'Cleo Grae',
            'title' => 'Sahel Chronicles',
            'slug' => 'sahel-chronicles',
            'release_date' => '2023-06-15',
            'description' => 'Premier album solo explorant les réalités du Sahel à travers le hip-hop.'
        ],
        [
            'artist' => 'Mister You TD',
            'title' => 'African Vibes',
            'slug' => 'african-vibes',
            'release_date' => '2023-08-20',
            'description' => 'Un voyage musical à travers l\'Afrique avec des rythmes afrobeat entraînants.'
        ],
        [
            'artist' => 'Ngariety',
            'title' => 'Cœur d\'Afrique',
            'slug' => 'coeur-dafrique',
            'release_date' => '2023-04-10',
            'description' => 'Ballades romantiques et chansons d\'amour en français et en arabe.'
        ],
        [
            'artist' => 'Black Stone',
            'title' => 'Ndjaména City',
            'slug' => 'ndjamena-city',
            'release_date' => '2023-09-01',
            'description' => 'Les rues de la capitale racontées à travers des beats trap percutants.'
        ],
        [
            'artist' => 'Sister Grace',
            'title' => 'Espoir et Foi',
            'slug' => 'espoir-et-foi',
            'release_date' => '2023-03-25',
            'description' => 'Album gospel inspirant avec des messages d\'espoir et de paix.'
        ],
        [
            'artist' => 'Le Roi du Sahel',
            'title' => 'Traditions Modernisées',
            'slug' => 'traditions-modernisees',
            'release_date' => '2023-07-12',
            'description' => 'Fusion unique entre instruments traditionnels et production contemporaine.'
        ]
    ];

    $albumIds = [];

    foreach ($albums as $album) {
        if (!isset($artistIds[$album['artist']])) continue;

        $stmt = $db->prepare("
            INSERT INTO albums (artist_id, title, slug, release_date, description, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $artistIds[$album['artist']],
            $album['title'],
            $album['slug'],
            $album['release_date'],
            $album['description']
        ]);

        $albumIds[$album['slug']] = $db->lastInsertId();
        echo "  • {$album['title']} par {$album['artist']} (ID: {$albumIds[$album['slug']]})\n";
    }

    echo "\n✓ " . count($albums) . " albums créés\n\n";

    // ========================================
    // 4. CHANSONS
    // ========================================
    echo "━━━ CRÉATION DES CHANSONS ━━━\n";

    $songs = [
        // Cleo Grae - Hip-Hop
        ['artist' => 'Cleo Grae', 'album' => 'sahel-chronicles', 'genre' => 'hip-hop-tchadien', 'title' => 'N\'Djamena Dreams', 'duration' => 245, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Cleo Grae', 'album' => 'sahel-chronicles', 'genre' => 'hip-hop-tchadien', 'title' => 'Sahel Warriors', 'duration' => 198, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Cleo Grae', 'album' => 'sahel-chronicles', 'genre' => 'hip-hop-tchadien', 'title' => 'Flow du Désert', 'duration' => 223, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 1],

        // Mister You TD - Afrobeat
        ['artist' => 'Mister You TD', 'album' => 'african-vibes', 'genre' => 'afrobeat', 'title' => 'Danse Africaine', 'duration' => 212, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Mister You TD', 'album' => 'african-vibes', 'genre' => 'afrobeat', 'title' => 'Mama Africa', 'duration' => 195, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Mister You TD', 'album' => 'african-vibes', 'genre' => 'afrobeat', 'title' => 'Sunshine', 'duration' => 207, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Mister You TD', 'album' => null, 'genre' => 'afro-pop', 'title' => 'Belle Africaine', 'duration' => 189, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // Ngariety - R&B
        ['artist' => 'Ngariety', 'album' => 'coeur-dafrique', 'genre' => 'rnb-afro', 'title' => 'Mon Amour', 'duration' => 234, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Ngariety', 'album' => 'coeur-dafrique', 'genre' => 'rnb-afro', 'title' => 'Sous les Étoiles', 'duration' => 256, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 1],
        ['artist' => 'Ngariety', 'album' => 'coeur-dafrique', 'genre' => 'rnb-afro', 'title' => 'Douce Mélodie', 'duration' => 221, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // Akon One
        ['artist' => 'Akon One', 'album' => null, 'genre' => 'hip-hop-tchadien', 'title' => 'Moundou City', 'duration' => 203, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Akon One', 'album' => null, 'genre' => 'afro-trap', 'title' => 'Trap Tchadien', 'duration' => 187, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // La Diva du Logone
        ['artist' => 'La Diva du Logone', 'album' => null, 'genre' => 'zouk', 'title' => 'Rivière Logone', 'duration' => 241, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'La Diva du Logone', 'album' => null, 'genre' => 'afro-pop', 'title' => 'Sarh la Belle', 'duration' => 218, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // Black Stone
        ['artist' => 'Black Stone', 'album' => 'ndjamena-city', 'genre' => 'afro-trap', 'title' => 'Streets of Chad', 'duration' => 176, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Black Stone', 'album' => 'ndjamena-city', 'genre' => 'afro-trap', 'title' => 'Hustle Daily', 'duration' => 192, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 1],
        ['artist' => 'Black Stone', 'album' => 'ndjamena-city', 'genre' => 'hip-hop-tchadien', 'title' => 'Capital Flow', 'duration' => 201, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // Sister Grace
        ['artist' => 'Sister Grace', 'album' => 'espoir-et-foi', 'genre' => 'gospel', 'title' => 'Alléluia', 'duration' => 267, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Sister Grace', 'album' => 'espoir-et-foi', 'genre' => 'gospel', 'title' => 'Grâce Divine', 'duration' => 289, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Sister Grace', 'album' => 'espoir-et-foi', 'genre' => 'gospel', 'title' => 'Lumière du Monde', 'duration' => 254, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],

        // DJ Tchadiano
        ['artist' => 'DJ Tchadiano', 'album' => null, 'genre' => 'coupe-decale', 'title' => 'Tchadiano Mix Vol.1', 'duration' => 324, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'DJ Tchadiano', 'album' => null, 'genre' => 'afrobeat', 'title' => 'Sahel Beats', 'duration' => 298, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 1],

        // Le Roi du Sahel
        ['artist' => 'Le Roi du Sahel', 'album' => 'traditions-modernisees', 'genre' => 'traditionnel', 'title' => 'Kora Moderne', 'duration' => 276, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Le Roi du Sahel', 'album' => 'traditions-modernisees', 'genre' => 'traditionnel', 'title' => 'Chants Ancestraux', 'duration' => 312, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Le Roi du Sahel', 'album' => 'traditions-modernisees', 'genre' => 'afrobeat', 'title' => 'Tradition Fusion', 'duration' => 248, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 1],

        // Aminata Star
        ['artist' => 'Aminata Star', 'album' => null, 'genre' => 'dancehall', 'title' => 'Dance with Me', 'duration' => 195, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Aminata Star', 'album' => null, 'genre' => 'afro-pop', 'title' => 'Joie de Vivre', 'duration' => 203, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
        ['artist' => 'Aminata Star', 'album' => null, 'genre' => 'dancehall', 'title' => 'Party All Night', 'duration' => 187, 'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'premium' => 0],
    ];

    $songCount = 0;

    foreach ($songs as $song) {
        if (!isset($artistIds[$song['artist']])) continue;

        $albumId = isset($song['album']) && isset($albumIds[$song['album']]) ? $albumIds[$song['album']] : null;
        $genreId = isset($genreIds[$song['genre']]) ? $genreIds[$song['genre']] : null;
        $slug = strtolower(str_replace([' ', '\''], ['-', ''], $song['title']));

        $stmt = $db->prepare("
            INSERT INTO songs (artist_id, album_id, genre_id, title, slug, duration, youtube_url, is_premium, is_active, release_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), NOW())
        ");

        $stmt->execute([
            $artistIds[$song['artist']],
            $albumId,
            $genreId,
            $song['title'],
            $slug,
            $song['duration'],
            $song['youtube'],
            $song['premium']
        ]);

        $songId = $db->lastInsertId();
        $premiumBadge = $song['premium'] ? '⭐' : '';
        echo "  • {$song['title']} par {$song['artist']} $premiumBadge (ID: $songId)\n";

        $songCount++;
    }

    // Mettre à jour le nombre de chansons par album
    $db->exec("
        UPDATE albums a
        SET total_tracks = (SELECT COUNT(*) FROM songs WHERE album_id = a.id)
    ");

    echo "\n✓ $songCount chansons créées\n\n";

    // ========================================
    // 5. STATISTIQUES FINALES
    // ========================================
    echo "━━━ STATISTIQUES ━━━\n";

    $stats = [
        'genres' => $db->query("SELECT COUNT(*) FROM genres")->fetchColumn(),
        'artists' => $db->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'albums' => $db->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
        'songs' => $db->query("SELECT COUNT(*) FROM songs")->fetchColumn(),
        'premium_songs' => $db->query("SELECT COUNT(*) FROM songs WHERE is_premium = 1")->fetchColumn(),
    ];

    echo "  📊 Total des genres: {$stats['genres']}\n";
    echo "  👨‍🎤 Total des artistes: {$stats['artists']}\n";
    echo "  💿 Total des albums: {$stats['albums']}\n";
    echo "  🎵 Total des chansons: {$stats['songs']}\n";
    echo "  ⭐ Chansons premium: {$stats['premium_songs']}\n";

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✓ GÉNÉRATION TERMINÉE AVEC SUCCÈS !                     ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

} catch (Exception $e) {
    echo "\n✗ ERREUR: " . $e->getMessage() . "\n\n";
    exit(1);
}
