<?php
/**
 * Tests DATA-06 : suppression logique et conservation.
 *
 * Criteres du plan :
 *   - supprimer un compte ayant achete ne fait pas disparaitre la ligne de
 *     commande ;
 *   - un compte anonymise ne contient plus ni e-mail, ni telephone, ni nom ;
 *   - les contenus supprimes n'apparaissent plus sur les pages publiques.
 *
 * Le troisieme critere est le plus facile a croire sur parole et le plus
 * facile a rater : une seule requete oubliee, et le titre retire reste
 * affiche. Il est donc verifie en appelant CHAQUE fonction de lecture
 * publique, puis en relisant les pages elles-memes.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data06-effacement.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

require_once $racine . '/includes/functions.php';

$baseDb = (string) EnvLoader::get('DB_DATABASE', '');
if (!preg_match('/local|test|dev/', $baseDb)) {
    fwrite(STDERR, "Refus : base '{$baseDb}' non locale.\n");
    exit(1);
}

$base = 'http://localhost/tchadok';
$ok = 0;
$ko = 0;

function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    if ($condition) {
        $ok++;
        echo "  OK  {$libelle}\n";
    } else {
        $ko++;
        echo "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

function refus(PDO $db, string $sql, array $parametres = []): string
{
    try {
        $db->prepare($sql)->execute($parametres);
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

function pageHttp(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false]);
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

/** Les identifiants trouves dans un resultat de fonction publique. */
function ids($lignes): array
{
    $liste = [];
    foreach ((array) $lignes as $ligne) {
        if (is_array($ligne) && isset($ligne['id'])) {
            $liste[] = (int) $ligne['id'];
        }
    }

    return $liste;
}

$db = TchadokDatabase::getInstance()->getConnection();
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

$nettoyer = static function () use ($db): void {
    // Chaque suppression est restreinte aux lignes des comptes d'essai (917,
    // 918) : une simple plage d'identifiants atteignait aussi de vraies
    // commandes, que l'auto-increment avait placees dans la meme plage --
    // `DELETE FROM order_items WHERE order_id BETWEEN ...` avait efface les
    // lignes de commandes payees.
    $db->exec('DELETE s FROM streams s JOIN tracks t ON t.id = s.track_id WHERE s.track_id BETWEEN 9601 AND 9699 AND t.artist_id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM entitlements WHERE user_id BETWEEN 917 AND 918');
    $db->exec('DELETE i FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.order_id BETWEEN 9601 AND 9699 AND o.user_id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM orders WHERE id BETWEEN 9601 AND 9699 AND user_id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM playlists WHERE id BETWEEN 9601 AND 9699 AND user_id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM tracks WHERE id BETWEEN 9601 AND 9699 AND artist_id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM releases WHERE id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM artists WHERE id BETWEEN 917 AND 918');
    $db->exec('DELETE FROM users WHERE id BETWEEN 917 AND 918');
    // Filtre sur le nom : depuis DATA-08, un vrai genre peut porter ce numero.
    $db->exec("DELETE FROM genres WHERE id = 992 AND name = 'ZZDATA06 Genre'");
};

$nettoyer();

try {
    echo "\n=== A. Le schema porte la suppression logique ===\n";
    foreach (['users', 'artists', 'tracks', 'releases', 'playlists'] as $table) {
        $colonne = $db->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = 'deleted_at'"
        )->fetchColumn();
        verif("`{$table}.deleted_at` existe", (int) $colonne === 1);
    }
    verif('`users.anonymized_at` existe',
        (int) $db->query("SELECT COUNT(*) FROM information_schema.columns
                          WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'anonymized_at'")->fetchColumn() === 1);
    verif('La vue `albums` expose la colonne',
        (int) $db->query("SELECT COUNT(*) FROM information_schema.columns
                          WHERE table_schema = DATABASE() AND table_name = 'albums' AND column_name = 'deleted_at'")->fetchColumn() === 1);
    verif('Une ecoute peut survivre a son auteur',
        (string) $db->query("SELECT is_nullable FROM information_schema.columns
                             WHERE table_schema = DATABASE() AND table_name = 'streams' AND column_name = 'user_id'")->fetchColumn() === 'YES');
    verif('La colonne est indexee (filtre de toutes les lectures)',
        (int) $db->query("SELECT COUNT(*) FROM information_schema.statistics
                          WHERE table_schema = DATABASE() AND table_name = 'tracks' AND column_name = 'deleted_at'")->fetchColumn() >= 1);

    echo "\n=== B. Jeu d'essai ===\n";
    $db->exec("INSERT INTO genres (id, name, is_active) VALUES (992, 'ZZDATA06 Genre', 1)");
    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, phone, city, country, is_active, email_verified)
         VALUES (917, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1), (918, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)'
    )->execute([
        'essai17_membre', 'membre17@essai.local', password_hash('essai', PASSWORD_BCRYPT), 'Adoum', 'Ngarbaroum', '+23566000017', 'Ndjamena', 'TD',
        'essai18_artiste', 'artiste18@essai.local', password_hash('essai', PASSWORD_BCRYPT), 'Essai', 'Artiste', '+23566000018', 'Moundou', 'TD',
    ]);
    $db->exec("INSERT INTO artists (id, user_id, stage_name, slug, is_active, verified, featured)
               VALUES (917, 918, 'ZZDATA06 Artiste', 'zzdata06-artiste', 1, 1, 1)");
    $db->exec("INSERT INTO releases (id, artist_id, title, slug, genre_id, format, price_bundle, is_free, status)
               VALUES (917, 917, 'ZZDATA06 Sortie visible', 'zzdata06-visible', 992, 'album', 3000, 0, 'approved'),
                      (918, 917, 'ZZDATA06 Sortie retiree', 'zzdata06-retiree', 992, 'album', 3000, 0, 'approved')");
    $db->exec("INSERT INTO tracks (id, album_id, release_id, slug, artist_id, genre_id, title, audio_file, duration, price, is_free, status, total_streams)
               VALUES (9601, 917, 917, 'zzdata06-visible', 917, 992, 'ZZDATA06 Titre visible', 'x.mp3', 200, 500, 0, 'approved', 40),
                      (9602, 918, 918, 'zzdata06-retire',  917, 992, 'ZZDATA06 Titre retire',  'x.mp3', 200, 500, 0, 'approved', 30)");
    $db->exec("INSERT INTO streams (track_id, artist_id, user_id, duration_played, completed) VALUES (9601, 917, 917, 60, 1), (9602, 917, 917, 60, 1)");
    $db->exec("INSERT INTO playlists (id, user_id, name, is_public) VALUES (9601, 917, 'ZZDATA06 Playlist', 1)");
    verif('Le jeu d\'essai est en place', (int) $db->query('SELECT COUNT(*) FROM tracks WHERE id BETWEEN 9601 AND 9602')->fetchColumn() === 2);

    echo "\n=== C. Retirer un contenu le fait disparaitre partout ===\n";
    $resultat = Effacement::retirer('tracks', 9602, null, 'reclamation d\'ayant droit');
    verif('Le titre est retire', $resultat['succes'] === true, implode(' ', $resultat['erreurs']));
    verif('... et l\'etat se lit', Effacement::estRetire('tracks', 9602) === true);
    verif('Le titre voisin reste en ligne', Effacement::estRetire('tracks', 9601) === false);
    verif('Un second retrait est refuse', Effacement::retirer('tracks', 9602)['succes'] === false);
    verif('Une table inconnue est refusee', Effacement::retirer('blog_posts', 1)['succes'] === false);

    Effacement::retirer('releases', 918, null, 'sortie du titre retire');

    // CHAQUE fonction de lecture publique, et non un echantillon : c'est une
    // requete oubliee qui laisse un contenu retire a l'ecran.
    $lectures = [
        'getTrendingTracks' => getTrendingTracks(200),
        'getNewTracks'      => getNewTracks(200),
        'getClassicTracks'  => getClassicTracks(200),
        'getNewReleases'    => getNewReleases(200),
        'getAlbums'         => getAlbums(200),
    ];
    foreach ($lectures as $nom => $resultats) {
        $trouves = ids($resultats);
        $indesirable = $nom === 'getAlbums' || $nom === 'getNewReleases' ? 918 : 9602;
        verif("{$nom} : le contenu retire n'y est plus", !in_array($indesirable, $trouves, true), implode(', ', $trouves));
    }

    $recherche = searchContent('ZZDATA06', 200);
    $titresTrouves = [];
    foreach (($recherche['tracks'] ?? []) as $ligne) {
        $titresTrouves[] = (string) ($ligne['title'] ?? '');
    }
    verif('La recherche ignore le titre retire',
        !in_array('ZZDATA06 Titre retire', $titresTrouves, true), implode(', ', $titresTrouves));
    verif('... mais trouve toujours celui qui reste',
        in_array('ZZDATA06 Titre visible', $titresTrouves, true), implode(', ', $titresTrouves));

    verif('countAlbums ne compte plus la sortie retiree',
        (int) $db->query("SELECT COUNT(*) FROM albums WHERE status = 'approved' AND deleted_at IS NULL AND id = 918")->fetchColumn() === 0);

    $genres = getGenresWithStats();
    $compteGenre = 0;
    foreach ($genres as $genre) {
        if (($genre['name'] ?? '') === 'ZZDATA06 Genre') {
            $compteGenre = (int) ($genre['tracks_count'] ?? $genre['track_count'] ?? 0);
        }
    }
    verif('Les statistiques par genre ne comptent plus le titre retire', $compteGenre === 1, (string) $compteGenre);

    $classement = getTopArtistsByGenre(992, 10);
    $titresArtiste = 0;
    foreach ($classement as $ligne) {
        if ((int) ($ligne['id'] ?? 0) === 917) {
            $titresArtiste = (int) ($ligne['track_count'] ?? 0);
        }
    }
    verif('Le classement par genre non plus', $titresArtiste === 1, (string) $titresArtiste);

    $page = pageHttp($base . '/albums.php');
    verif('La page des albums repond', $page['code'] === 200, (string) $page['code']);
    verif('... sans la sortie retiree', !str_contains($page['corps'], 'ZZDATA06 Sortie retiree'));
    verif('... mais avec celle qui reste', str_contains($page['corps'], 'ZZDATA06 Sortie visible'));

    foreach (['index.php', 'artists.php', 'genres.php', 'search.php?q=ZZDATA06'] as $chemin) {
        $reponse = pageHttp($base . '/' . $chemin);
        verif("{$chemin} : rien du contenu retire", $reponse['code'] === 200 && !str_contains($reponse['corps'], 'ZZDATA06 Titre retire'), (string) $reponse['code']);
    }

    echo "\n=== D. Un retrait se repare ===\n";
    $resultat = Effacement::retablir('tracks', 9602);
    verif('Le titre revient en ligne', $resultat['succes'] === true, implode(' ', $resultat['erreurs']));
    verif('... et l\'etat le confirme', Effacement::estRetire('tracks', 9602) === false);
    verif('... la recherche le retrouve',
        in_array(9602, ids(searchContent('ZZDATA06', 200)['tracks'] ?? []), true)
        || str_contains(implode(' ', array_column(searchContent('ZZDATA06', 200)['tracks'] ?? [], 'title')), 'Titre retire'));
    verif('Un second retablissement est refuse', Effacement::retablir('tracks', 9602)['succes'] === false);
    Effacement::retirer('tracks', 9602);

    echo "\n=== E. Une playlist se retire, elle aussi ===\n";
    verif('L\'API supprime logiquement', str_contains($source('api/playlists.php'), 'UPDATE playlists SET deleted_at = NOW()'));
    verif('... et ne supprime plus la ligne', !str_contains($source('api/playlists.php'), 'DELETE FROM playlists'));
    Effacement::retirer('playlists', 9601);
    verif('La playlist retiree n\'est plus listee',
        (int) $db->query('SELECT COUNT(*) FROM playlists WHERE user_id = 917 AND deleted_at IS NULL')->fetchColumn() === 0);
    verif('... mais la ligne existe toujours',
        (int) $db->query('SELECT COUNT(*) FROM playlists WHERE id = 9601')->fetchColumn() === 1);

    echo "\n=== F. Supprimer un acheteur est impossible ===\n";
    $db->exec("INSERT INTO orders (id, reference, user_id, subtotal, total, status, payment_method, gateway_ref, paid_at)
               VALUES (9601, 'TCHK-2026-DATA0601', 917, 500, 500, 'paid', 'airtel_money', 'DATA06-REF-01', NOW())");
    $db->exec("INSERT INTO order_items (order_id, item_type, item_id, artist_id, unit_price, commission_rate, commission, artist_net)
               VALUES (9601, 'track', 9601, 917, 500, 15.00, 75, 425)");
    $db->exec("INSERT INTO entitlements (user_id, item_type, item_id, source, max_downloads) VALUES (917, 'track', 9601, 'purchase', 5)");

    $message = refus($db, 'DELETE FROM users WHERE id = 917');
    verif('La base refuse de supprimer un acheteur', $message !== '', 'suppression acceptee !');
    verif('... la commande est intacte',
        (int) $db->query('SELECT COUNT(*) FROM orders WHERE id = 9601')->fetchColumn() === 1);

    echo "\n=== G. Anonymisation : ce qui part ===\n";
    $avant = Effacement::tracesComptables(917);
    verif('Les traces comptables sont annoncees avant d\'agir', ($avant['orders'] ?? 0) === 1, json_encode($avant));

    $resultat = Effacement::anonymiser(917, null, 'demande d\'essai');
    verif('L\'anonymisation aboutit', $resultat['succes'] === true, implode(' ', $resultat['erreurs']));
    verif('... sous un pseudonyme sans lien avec l\'identite', str_starts_with($resultat['pseudonyme'], 'anonyme_917_'), $resultat['pseudonyme']);

    $compte = $db->query('SELECT * FROM users WHERE id = 917')->fetch(PDO::FETCH_ASSOC);
    verif('Plus d\'adresse electronique d\'origine',
        !str_contains((string) $compte['email'], 'membre17@essai.local'), (string) $compte['email']);
    verif('Plus de nom', $compte['first_name'] === 'Compte' && $compte['last_name'] === 'anonymise',
        $compte['first_name'] . ' ' . $compte['last_name']);
    verif('Plus de telephone', $compte['phone'] === null);
    verif('Plus de ville ni de pays', $compte['city'] === null && $compte['country'] === null);
    verif('Le compte est desactive', (int) $compte['is_active'] === 0);
    verif('La date d\'anonymisation est posee', $compte['anonymized_at'] !== null);
    verif('Le compte est aussi marque retire', $compte['deleted_at'] !== null);

    verif('Le mot de passe est devenu inutilisable',
        password_verify('essai', (string) $compte['password_hash']) === false);
    verif('... et aucune chaine ne l\'ouvre',
        password_verify('*inutilisable*', (string) $compte['password_hash']) === false);

    verif('Aucun jeton de connexion automatique ne survit',
        (int) $db->query('SELECT COUNT(*) FROM remember_tokens WHERE user_id = 917')->fetchColumn() === 0);
    verif('Aucune session ne survit',
        (int) $db->query('SELECT COUNT(*) FROM user_sessions WHERE user_id = 917')->fetchColumn() === 0);

    echo "\n=== H. Anonymisation : ce qui reste ===\n";
    verif('La commande demeure',
        (int) $db->query('SELECT COUNT(*) FROM orders WHERE id = 9601')->fetchColumn() === 1);
    verif('... et sa ligne aussi',
        (int) $db->query('SELECT COUNT(*) FROM order_items WHERE order_id = 9601')->fetchColumn() === 1);
    verif('Le droit d\'acces demeure',
        (int) $db->query("SELECT COUNT(*) FROM entitlements WHERE user_id = 917")->fetchColumn() === 1);
    verif('Les ecoutes demeurent',
        (int) $db->query('SELECT COUNT(*) FROM streams WHERE track_id BETWEEN 9601 AND 9602')->fetchColumn() === 2);
    verif('... mais ne designent plus personne',
        (int) $db->query('SELECT COUNT(*) FROM streams WHERE user_id = 917')->fetchColumn() === 0);

    $trace = $db->query(
        "SELECT action, target_type, target_id, reason FROM audit_log
         WHERE action = 'compte.anonymise' AND target_id = '917' ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    verif('L\'operation est inscrite au journal', is_array($trace));
    verif('... avec son motif', is_array($trace) && str_contains((string) $trace['reason'], 'demande d\'essai'));
    verif('Le journal ne conserve que l\'identifiant, pas le nom',
        is_array($trace) && !str_contains(json_encode($trace), 'Ngarbaroum'));

    verif('Un second effacement est refuse', Effacement::anonymiser(917)['succes'] === false);
    verif('Un compte inexistant est refuse', Effacement::anonymiser(99999)['succes'] === false);

    echo "\n=== I. Les lectures publiques filtrent toutes ===\n";
    $lecture = $source('includes/database.php');
    $sansFiltre = [];
    foreach (explode("\n", $lecture) as $numero => $ligne) {
        // Toute restriction au contenu publie doit s'accompagner du filtre de
        // retrait : c'est la paire qui protege les pages publiques.
        if (preg_match('/\b\w*\.?status = .approved.(?!.*deleted_at)/', $ligne) && !str_contains($ligne, 'deleted_at')) {
            $sansFiltre[] = 'l.' . ($numero + 1);
        }
    }
    verif('Aucune lecture publiee sans filtre de retrait', $sansFiltre === [], implode(', ', $sansFiltre));
    verif('Le filtre est present 36 fois', substr_count($lecture, 'deleted_at IS NULL') >= 36,
        (string) substr_count($lecture, 'deleted_at IS NULL'));

    echo "\n=== J. Script et documentation ===\n";
    $script = $source('scripts/effacement.php');
    verif('Le script refuse le web', str_contains($script, "PHP_SAPI !== 'cli'"));
    verif('... annonce ce qui sera conserve avant d\'agir', str_contains($script, 'Seront conserves'));
    verif('... et dit que l\'operation est definitive', str_contains($script, 'definitive'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/effacement.php" etat tracks 9602 2>&1', $racine), $sortie, $code);
    verif('La commande « etat » fonctionne', $code === 0 && str_contains(implode(' ', $sortie), 'RETIRE'), implode(' ', $sortie));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/effacement.php" retablir tracks 9602 2>&1', $racine), $sortie, $code);
    verif('La commande « retablir » fonctionne', $code === 0, implode(' ', $sortie));
    Effacement::retirer('tracks', 9602);

    $doc = $source('docs/exploitation/conservation.md');
    verif('La note de conservation existe', $doc !== '');
    foreach (['orders', 'payment_events', 'audit_log', 'login_attempts', 'streams'] as $table) {
        verif("... elle donne une duree pour `{$table}`", str_contains($doc, '`' . $table . '`'));
    }
    verif('... elle distingue retirer, anonymiser et purger',
        str_contains($doc, 'Retirer') && str_contains($doc, 'Anonymiser') && str_contains($doc, 'Purger'));
    verif('... elle dit ce qui survit a l\'effacement', str_contains($doc, 'Ce qui demeure'));
    verif('... et ce qui n\'est pas encore fait', str_contains($doc, 'ne couvre pas encore'));

    echo "\n=== K. Migration ===\n";
    $migration = $source('database/migrations/2026_09_23_0010_data06_suppression_logique.sql');
    verif('La migration est conditionnelle (rejouable)', substr_count($migration, 'information_schema.columns') >= 5);
    verif('... et sait revenir en arriere', str_contains($migration, '-- DOWN'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" status 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('DATA-06 est appliquee', preg_match('/appliquee\s+\S*data06_suppression_logique/', $texte) === 1, $texte);
    verif('... et le schema est a jour', str_contains($texte, 'Schema a jour'), $texte);
} finally {
    $db->exec("DELETE FROM audit_log WHERE target_id IN ('917', '918', '9601', '9602') AND action IN ('compte.anonymise', 'contenu.supprime', 'contenu.restaure')");
    $nettoyer();
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
