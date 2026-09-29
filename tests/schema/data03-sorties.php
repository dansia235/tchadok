<?php
/**
 * Tests DATA-03 : l'entite « sortie » et les formats de vente.
 *
 * Criteres d'acceptation du plan :
 *   - une sortie declaree « album » avec un seul titre est refusee, avec un
 *     message explicite ;
 *   - un maxi single sans prix de sortie complete est refuse ;
 *   - les albums existants sont migres sans perte et les pages publiques
 *     continuent de fonctionner.
 *
 * Les regles sont verifiees a deux niveaux : sans base (verifierComposition,
 * le coeur reutilisable par la future API Android) et sur la base reelle
 * (changerStatut, le chemin qu'empruntent les formulaires).
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data03-sorties.php
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

/** Le message d'erreur contient-il l'explication attendue ? */
function messageContient(array $erreurs, string $attendu): bool
{
    foreach ($erreurs as $message) {
        if (str_contains(mb_strtolower($message, 'UTF-8'), mb_strtolower($attendu, 'UTF-8'))) {
            return true;
        }
    }

    return false;
}

function pageHttp(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

$db = TchadokDatabase::getInstance()->getConnection();
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

// Menage prealable : les identifiants reserves peuvent rester d'un essai
// interrompu.
$db->exec('DELETE FROM tracks WHERE id BETWEEN 9301 AND 9399');
$db->exec('DELETE FROM releases WHERE id BETWEEN 901 AND 999');
$db->exec('DELETE FROM artists WHERE id BETWEEN 913 AND 914');
$db->exec('DELETE FROM users WHERE id BETWEEN 913 AND 914');

try {
    echo "\n=== A. Le schema connait les sorties ===\n";

    $colonnes = [];
    foreach ($db->query('SHOW COLUMNS FROM releases') as $ligne) {
        $colonnes[$ligne['Field']] = $ligne;
    }
    verif('La table `releases` existe', $colonnes !== []);
    verif('... avec un format', isset($colonnes['format']));
    foreach (['single', 'maxi_single', 'ep', 'album', 'compilation'] as $format) {
        verif(
            "... qui accepte « {$format} »",
            isset($colonnes['format']) && str_contains((string) $colonnes['format']['Type'], "'{$format}'")
        );
    }
    verif('Le prix de la sortie complete peut etre vide (sortie gratuite)',
        isset($colonnes['price_bundle']) && $colonnes['price_bundle']['Null'] === 'YES');
    verif('L\'achat au titre est configurable', isset($colonnes['allow_track_buy']));
    verif('La precommande est prevue', isset($colonnes['is_preorder']));
    verif('Le motif de refus est conserve', isset($colonnes['rejected_reason']));
    verif('Le moderateur est identifie', isset($colonnes['reviewed_by']));
    verif('Le statut couvre les quatre etats',
        isset($colonnes['status'])
        && str_contains((string) $colonnes['status']['Type'], "'draft'")
        && str_contains((string) $colonnes['status']['Type'], "'rejected'"));

    $index = [];
    foreach ($db->query('SHOW INDEX FROM releases') as $ligne) {
        if ($ligne['Column_name'] === 'slug') {
            $index[] = (int) $ligne['Non_unique'];
        }
    }
    verif('Le slug d\'une sortie est unique', $index === [0], json_encode($index));

    $tracks = [];
    foreach ($db->query('SHOW COLUMNS FROM tracks') as $ligne) {
        $tracks[$ligne['Field']] = $ligne;
    }
    verif('`tracks.release_id` rattache un titre a sa sortie', isset($tracks['release_id']));
    verif('`tracks.slug` existe (URL lisibles)', isset($tracks['slug']));
    verif('`tracks.album_id` subsiste le temps que le code migre', isset($tracks['album_id']));

    $artistes = $db->query("SHOW COLUMNS FROM artists LIKE 'slug'")->fetchAll();
    verif('`artists.slug` existe', $artistes !== []);

    echo "\n=== B. `albums` devient une vue de compatibilite ===\n";
    $type = (string) $db->query(
        "SELECT table_type FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'albums'"
    )->fetchColumn();
    verif('`albums` n\'est plus une table de base', $type !== 'BASE TABLE', $type);
    verif('`albums` est une vue', $type === 'VIEW', $type);

    $vue = [];
    foreach ($db->query('SHOW COLUMNS FROM albums') as $ligne) {
        $vue[] = $ligne['Field'];
    }
    verif('La vue garde l\'ancien nom `type`', in_array('type', $vue, true));
    verif('La vue garde l\'ancien nom `price`', in_array('price', $vue, true));
    verif('La vue n\'expose pas les nouveaux noms', !in_array('format', $vue, true) && !in_array('price_bundle', $vue, true));

    $fk = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE() AND table_name = 'tracks'
           AND column_name = 'album_id' AND referenced_table_name IS NOT NULL"
    )->fetchColumn();
    verif('Plus de cle etrangere vers l\'ancienne table', $fk === 0, (string) $fk);

    echo "\n=== C. Les regles de format, sans base ===\n";
    // Un single : un ou deux titres, prix de sortie facultatif.
    verif('Un single d\'un titre passe', Sorties::verifierComposition('single', 1, null, 500.0) === []);
    verif('Un single de deux titres passe', Sorties::verifierComposition('single', 2, null, 1000.0) === []);
    verif('Un single de trois titres est refuse', Sorties::verifierComposition('single', 3, null, 1500.0) !== []);

    // CRITERE : « album » a un seul titre, refuse avec un message explicite.
    $erreurs = Sorties::verifierComposition('album', 1, 3000.0, 500.0);
    verif('Un album d\'un seul titre est refuse', $erreurs !== []);
    verif('... et le message dit combien de titres il faut', messageContient($erreurs, 'au moins 8 titres'), implode(' | ', $erreurs));
    verif('... et rappelle le nombre fourni', messageContient($erreurs, 'en a 1'), implode(' | ', $erreurs));
    verif('... et propose de changer de format', messageContient($erreurs, 'changez de format'), implode(' | ', $erreurs));

    // CRITERE : maxi single sans prix de sortie complete, refuse.
    $erreurs = Sorties::verifierComposition('maxi_single', 3, null, 1500.0);
    verif('Un maxi single sans prix de sortie est refuse', $erreurs !== []);
    verif('... avec un message explicite', messageContient($erreurs, 'prix pour la sortie complete'), implode(' | ', $erreurs));
    verif('Le meme maxi single gratuit passe', Sorties::verifierComposition('maxi_single', 3, null, 0.0, true) === []);
    verif('L\'enregistrement refuse deja le maxi single sans prix',
        Sorties::validerEnregistrement('maxi_single', null, false) !== []);
    verif('L\'enregistrement laisse passer le single sans prix',
        Sorties::validerEnregistrement('single', null, false) === []);

    // La vente groupee doit valoir quelque chose.
    verif('Un maxi single vendu au prix des titres est refuse',
        Sorties::verifierComposition('maxi_single', 3, 1500.0, 1500.0) !== []);
    verif('... a -10 %, il passe', Sorties::verifierComposition('maxi_single', 3, 1350.0, 1500.0) === []);
    verif('Un album a -10 % est refuse (15 % attendus)',
        Sorties::verifierComposition('album', 8, 3600.0, 4000.0) !== []);
    verif('... a -15 %, il passe', Sorties::verifierComposition('album', 8, 3400.0, 4000.0) === []);
    $erreurs = Sorties::verifierComposition('album', 8, 4000.0, 4000.0);
    verif('... et le message chiffre le plafond', messageContient($erreurs, '3 400'), implode(' | ', $erreurs));

    // Compilation : plusieurs artistes, par definition.
    verif('Une compilation d\'un seul artiste est refusee',
        messageContient(Sorties::verifierComposition('compilation', 8, 3400.0, 4000.0, false, 1), 'plusieurs artistes'));
    verif('... a plusieurs artistes, elle passe',
        Sorties::verifierComposition('compilation', 8, 3400.0, 4000.0, false, 3) === []);

    verif('Un format inconnu est refuse', Sorties::verifierComposition('mixtape', 8, 3400.0, 4000.0) !== []);
    verif('... des l\'enregistrement', Sorties::validerEnregistrement('mixtape', 3000.0, false) !== []);
    verif('Le rappel de composition est affichable', str_contains(Sorties::attendu('album'), '8 titres ou plus'), Sorties::attendu('album'));
    verif('... et mentionne le prix obligatoire', str_contains(Sorties::attendu('ep'), 'prix de la sortie obligatoire'));
    verif('Chaque format a un libelle lisible', Sorties::libelle('maxi_single') === 'Maxi single');

    echo "\n=== D. Les memes regles sur la base ===\n";
    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (913, ?, ?, ?, ?, ?, 1, 1)'
    )->execute(['essai13_artiste', 'artiste13@essai.local', password_hash('tchadok2026', PASSWORD_BCRYPT), 'Essai', 'Artiste']);
    $db->exec("INSERT INTO artists (id, user_id, stage_name, slug, is_active) VALUES (913, 913, 'Essai Sorties', 'essai-sorties', 1)");

    $creerSortie = static function (int $id, string $format, ?float $bundle, string $titre) use ($db): void {
        $db->prepare(
            'INSERT INTO releases (id, artist_id, title, slug, format, price_bundle, is_free, status)
             VALUES (?, 913, ?, ?, ?, ?, 0, ?)'
        )->execute([$id, $titre, 'essai-' . $id, $format, $bundle, 'draft']);
    };
    $ajouterTitres = static function (int $releaseId, int $nombre, float $prix, int $premierId) use ($db): void {
        for ($i = 0; $i < $nombre; $i++) {
            $db->prepare(
                'INSERT INTO tracks (id, album_id, release_id, slug, artist_id, title, audio_file, duration, price, is_free, status)
                 VALUES (?, ?, ?, ?, 913, ?, ?, 180, ?, 0, ?)'
            )->execute([
                $premierId + $i, $releaseId, $releaseId, 'essai-titre-' . ($premierId + $i),
                'Titre d\'essai ' . ($premierId + $i), 'assets/audio/essai.mp3', $prix, 'approved',
            ]);
        }
    };

    // Un « album » d'un seul titre, par le chemin reel des formulaires.
    $creerSortie(901, 'album', 3000.0, 'Album d\'essai');
    $ajouterTitres(901, 1, 500.0, 9301);
    $resultat = Sorties::changerStatut(901, 'pending');
    verif('Un album d\'un titre ne part pas en moderation', $resultat['succes'] === false);
    verif('... avec un message explicite', messageContient($resultat['erreurs'], 'au moins 8 titres'), implode(' | ', $resultat['erreurs']));
    verif('... et le statut reste brouillon',
        $db->query('SELECT status FROM releases WHERE id = 901')->fetchColumn() === 'draft');

    // Une fois complet, mais vendu trop cher, il reste bloque.
    $ajouterTitres(901, 7, 500.0, 9302);
    $db->exec('UPDATE releases SET price_bundle = 3800 WHERE id = 901');
    $resultat = Sorties::changerStatut(901, 'pending');
    verif('Un album sans remise suffisante est refuse', $resultat['succes'] === false, implode(' | ', $resultat['erreurs']));

    $db->exec('UPDATE releases SET price_bundle = 3000 WHERE id = 901');
    $resultat = Sorties::changerStatut(901, 'pending');
    verif('Un album complet et remise part en moderation', $resultat['succes'] === true, implode(' | ', $resultat['erreurs']));
    verif('... et le statut est enregistre',
        $db->query('SELECT status FROM releases WHERE id = 901')->fetchColumn() === 'pending');

    // Maxi single sans prix de sortie complete : refuse en base aussi.
    $creerSortie(902, 'maxi_single', null, 'Maxi d\'essai');
    $ajouterTitres(902, 3, 500.0, 9311);
    $resultat = Sorties::changerStatut(902, 'pending');
    verif('Un maxi single sans prix de sortie est refuse en base', $resultat['succes'] === false);
    verif('... avec le message attendu', messageContient($resultat['erreurs'], 'prix pour la sortie complete'), implode(' | ', $resultat['erreurs']));

    verif('Un statut inconnu est refuse', Sorties::changerStatut(902, 'publie')['succes'] === false);
    verif('Une sortie inexistante est refusee', Sorties::changerStatut(999, 'pending')['succes'] === false);

    // Le refus, lui, n'exige aucune composition : on rejette aussi un brouillon.
    $resultat = Sorties::changerStatut(902, 'rejected', 913, 'Prix manquant');
    verif('Un brouillon incomplet peut etre rejete', $resultat['succes'] === true, implode(' | ', $resultat['erreurs']));
    verif('... et le motif est conserve',
        $db->query('SELECT rejected_reason FROM releases WHERE id = 902')->fetchColumn() === 'Prix manquant');

    echo "\n=== E. Migration sans perte et vue exploitable ===\n";
    $migration = $source('database/migrations/2026_09_23_0007_data03_sorties.sql');
    verif('La migration reprend les albums existants', str_contains($migration, 'INSERT IGNORE INTO `releases`'));
    verif('... en conservant les identifiants', preg_match('/INSERT IGNORE INTO `releases`\s*\r?\n\s*\(id,/', $migration) === 1);
    verif('... et les compteurs (streams, ventes)',
        str_contains($migration, 'total_streams, total_sales'));
    verif('... et rattache les titres a leur sortie',
        str_contains($migration, 'UPDATE `tracks` SET `release_id` = `album_id`'));
    verif('Elle sait revenir en arriere', str_contains($migration, '-- DOWN'));
    verif('... en recreant la table `albums`', str_contains($migration, 'CREATE TABLE IF NOT EXISTS `albums`'));
    verif('Elle est rejouable (aucune suppression inconditionnelle)',
        !preg_match('/^\s*DROP TABLE `albums`;/m', $migration));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" verify 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('Les migrations appliquees sont intactes', $code === 0, $texte);

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" status 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('DATA-03 figure parmi les migrations appliquees',
        preg_match('/appliquee\s+\S*data03_sorties/', $texte) === 1, $texte);
    verif('... et le schema est a jour', str_contains($texte, 'Schema a jour'), $texte);

    // La vue nourrit les fonctions publiques telles quelles.
    // MOD-01 : pas de brouillon publie directement ; la base impose la soumission.
    $db->exec("UPDATE releases SET status = 'pending' WHERE id = 901");
    $db->exec("UPDATE releases SET status = 'approved' WHERE id = 901");
    $vueLigne = $db->query('SELECT * FROM albums WHERE id = 901')->fetch(PDO::FETCH_ASSOC);
    verif('La vue expose la sortie', is_array($vueLigne) && $vueLigne['title'] === 'Album d\'essai');
    verif('... avec le format sous l\'ancien nom', is_array($vueLigne) && $vueLigne['type'] === 'album');
    verif('... et le prix sous l\'ancien nom', is_array($vueLigne) && (float) $vueLigne['price'] === 3000.0);

    $albums = getAlbums(12);
    $titres = array_column($albums, 'title');
    verif('getAlbums() retrouve la sortie', in_array('Album d\'essai', $titres, true), implode(', ', $titres));
    verif('countAlbums() la compte', countAlbums() >= 1);

    $page = pageHttp($base . '/albums.php');
    verif('La page publique des albums repond', $page['code'] === 200, (string) $page['code']);
    verif('... et affiche la sortie migree', str_contains($page['corps'], 'Album d&#039;essai') || str_contains($page['corps'], 'Album d\'essai'));

    foreach (['index.php', 'artists.php', 'genres.php', 'decouvrir.php', 'search.php'] as $page) {
        $reponse = pageHttp($base . '/' . $page);
        verif("La page {$page} repond toujours", $reponse['code'] === 200, (string) $reponse['code']);
    }

    echo "\n=== F. Le code ecrit des sorties, plus des albums ===\n";
    $ecritures = [];
    foreach (glob($racine . '/*.php') ?: [] as $fichier) {
        if (preg_match('/(INSERT INTO|UPDATE|DELETE FROM)\s+albums\b/i', (string) file_get_contents($fichier))) {
            $ecritures[] = basename($fichier);
        }
    }
    verif('Aucune ecriture dans la vue `albums`', $ecritures === [], implode(', ', $ecritures));

    // Depuis MOD-05, les artistes publient par un parcours unique
    // (publier.php + includes/publication.php) ; les anciennes pages
    // ne sont plus que des redirections.
    $publication = $source('includes/publication.php');
    foreach (['admin-add-album.php'] as $fichier) {
        verif("{$fichier} enregistre une sortie", str_contains($source($fichier), 'INSERT INTO releases'));
        verif("{$fichier} verifie le format cote serveur", str_contains($source($fichier), 'Sorties::validerEnregistrement'));
        verif("{$fichier} cree un brouillon", str_contains($source($fichier), "'draft'"));
    }
    verif('La console passe par le controle de composition',
        str_contains($source('admin-add-album.php'), 'Sorties::changerStatut'));
    verif('Le parcours artiste enregistre une sortie en brouillon',
        str_contains($publication, "INSERT INTO releases (artist_id, title, slug, format, is_free, status) VALUES (?, ?, ?, ?, ?, 'draft')"));
    verif('Le parcours artiste verifie le format cote serveur',
        str_contains($publication, 'Sorties::formatConnu') && str_contains($publication, 'Sorties::validerPublication'));
    foreach (['upload.php', 'artist-add-song.php', 'artist-add-album.php'] as $fichier) {
        $contenu = $source($fichier);
        verif("{$fichier} redirige vers le parcours unique", str_contains($contenu, 'publier.php') && !str_contains($contenu, 'INSERT INTO'));
    }

    foreach (['includes/publication.php' => "Sorties::slug(\$nom, 'tracks')", 'admin-add-song.php' => "Sorties::slug(\$title, 'tracks')"] as $fichier => $slug) {
        $contenu = $source($fichier);
        verif("{$fichier} rattache le titre a sa sortie", str_contains($contenu, '(album_id, release_id, slug,'));
        verif("{$fichier} calcule le slug du titre", str_contains($contenu, $slug));
    }

    echo "\n=== G. Identifiants lisibles ===\n";
    verif('Les accents sont translitteres', Sorties::normaliser('Kélou Fêté') === 'kelou-fete', Sorties::normaliser('Kélou Fêté'));
    verif('La ponctuation disparait', Sorties::normaliser('Sahel #1 — édition !') === 'sahel-1-edition', Sorties::normaliser('Sahel #1 — édition !'));
    verif('Une ligature est developpee', Sorties::normaliser('Cœur') === 'coeur', Sorties::normaliser('Cœur'));
    verif('Un titre vide donne un slug de repli', Sorties::slug('---', 'releases') === 'sortie');

    $creerSortie(903, 'single', null, 'Doublon d\'essai');
    $db->exec("UPDATE releases SET slug = 'doublon-d-essai' WHERE id = 903");
    verif('Un titre deja pris recoit un suffixe',
        Sorties::slug('Doublon d\'essai', 'releases') === 'doublon-d-essai-2',
        Sorties::slug('Doublon d\'essai', 'releases'));
    verif('... sauf pour la sortie elle-meme',
        Sorties::slug('Doublon d\'essai', 'releases', 903) === 'doublon-d-essai');

    $db->exec('UPDATE releases SET slug = NULL WHERE id = 903');

    // La simulation montre sans ecrire : sur une base de production, on regarde
    // avant d'agir.
    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/generer-slugs.php" --simulation 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('La simulation annonce qu\'elle n\'ecrit rien', str_contains($texte, 'SIMULATION'), $texte);
    verif('... et montre l\'identifiant a venir', str_contains($texte, 'doublon-d-essai'), $texte);
    verif('... sans rien ecrire',
        $db->query('SELECT slug FROM releases WHERE id = 903')->fetchColumn() === null);

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/generer-slugs.php" 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('Le script de rattrapage s\'execute', $code === 0, $texte);
    verif('... et remplit le slug manquant',
        $db->query('SELECT slug FROM releases WHERE id = 903')->fetchColumn() === 'doublon-d-essai',
        (string) $db->query('SELECT slug FROM releases WHERE id = 903')->fetchColumn());
    verif('Le script refuse le web', str_contains($source('scripts/generer-slugs.php'), "PHP_SAPI !== 'cli'"));
} finally {
    $db->exec('DELETE FROM tracks WHERE id BETWEEN 9301 AND 9399');
    $db->exec('DELETE FROM releases WHERE id BETWEEN 901 AND 999');
    $db->exec('DELETE FROM artists WHERE id BETWEEN 913 AND 914');
    $db->exec('DELETE FROM users WHERE id BETWEEN 913 AND 914');
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
