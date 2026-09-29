<?php
/**
 * Tests LOT 10 : classements arretes, vues, barometre public, kit presse,
 * API, methodologie, certifications (CHART-01 a CHART-06).
 *
 * Criteres du plan :
 *   CHART-01 - un classement arrete ne change plus, meme si des ecoutes en
 *              quarantaine sont levees ensuite (elles comptent pour la periode
 *              suivante) ; archives consultables par edition, avec permalien.
 *   CHART-02 - chaque vue se recoupe avec les totaux nationaux.
 *   CHART-03 - URL stable, apercu WhatsApp / Facebook (balises og), page
 *              legere.
 *   CHART-04 - une edition genere son kit sans intervention manuelle.
 *   CHART-05 - methodologie publiee, datee, versionnee, liee depuis chaque
 *              classement.
 *   CHART-06 - un titre franchissant un seuil est certifie automatiquement,
 *              avec notification a l'artiste.
 *
 * Deux semaines consecutives tirees au hasard entre 1995 et 1999. Usage :
 *   C:\xampp\php\php.exe tests\barometre\chart-barometre.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';
require_once $racine . '/includes/certification.php';
require_once $racine . '/includes/agregats.php';
require_once $racine . '/includes/barometre.php';
require_once $racine . '/includes/kit-presse.php';
if (!preg_match('/local|test|dev/', (string) EnvLoader::get('DB_DATABASE', ''))) {
    fwrite(STDERR, "Refus : base non locale.\n");
    exit(1);
}

$ok = 0;
$ko = 0;
function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    $condition ? $ok++ : $ko++;
    echo $condition ? "  OK  {$libelle}\n" : "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
}
function refuse(PDO $db, string $sql): bool
{
    try {
        $db->exec($sql);
        return false;
    } catch (Throwable $e) {
        return true;
    }
}
function http(string $url, array $entetes = []): array
{
    $h = curl_init($url);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $entetes]);
    $t = microtime(true);
    $corps = (string) curl_exec($h);
    return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_CONTENT_TYPE), microtime(true) - $t];
}

$db = TchadokDatabase::getInstance()->getConnection();
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
$lundi = date('Y-m-d', strtotime('monday this week', strtotime(sprintf('%04d-%02d-%02d', random_int(1995, 1999), random_int(1, 11), random_int(1, 20)))));
$lundi2 = date('Y-m-d', strtotime($lundi . ' +7 days'));
[, , $slug1] = Barometre::periode('weekly', $lundi);
[, , $slug2] = Barometre::periode('weekly', $lundi2);
$marque = 'zzchart' . bin2hex(random_bytes(3));
$utilisateurs = [];
$commandes = [];
$creerUtilisateur = static function (string $s) use ($db, $marque, &$utilisateurs): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, created_at) VALUES (?, ?, 'x', 'Test', 'Chart', 1, NOW() - INTERVAL ? DAY)")
       ->execute([$marque . $s, $marque . $s . '@test.local', 500 + count($utilisateurs) * 5]);
    return $utilisateurs[] = (int) $db->lastInsertId();
};
$genre = $db->query('SELECT id, parent_id FROM genres WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$artistes = [];
$sorties = [];
$titres = [];
// A est arrive sur la plateforme 3 mois avant la semaine testee : artiste « nouveau ».
foreach (['A' => ['single', date('Y-m-d', strtotime($lundi . ' -3 months'))], 'B' => ['album', '1990-01-01'], 'C' => ['album', '1990-01-01']] as $nom => [$format, $arrivee]) {
    $u = $creerUtilisateur(strtolower($nom));
    $db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$u, "ZZCHART {$nom} {$marque}"]);
    $artistes[$nom] = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO releases (artist_id, title, slug, format, status, genre_id) VALUES (?, ?, ?, ?, 'approved', ?)")
       ->execute([$artistes[$nom], "ZZCHART sortie {$nom}", "{$marque}-{$nom}", $format, $genre['id']]);
    $sorties[$nom] = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO tracks (artist_id, album_id, release_id, genre_id, title, audio_file, duration, is_free, price, status, created_at) VALUES (?, ?, ?, ?, ?, 'x.mp3', 200, 0, 500, 'approved', ?)")
       ->execute([$artistes[$nom], $sorties[$nom], $sorties[$nom], $genre['id'], "ZZCHART titre {$nom}", $arrivee]);
    $titres[$nom] = (int) $db->lastInsertId();
}
// Un titre non publie avec des ecoutes : ne concourt pas.
$db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, status, created_at) VALUES (?, 'ZZCHART brouillon', 'x.mp3', 200, 'draft', '1990-01-01')")->execute([$artistes['C']]);
$titres['brouillon'] = (int) $db->lastInsertId();

$n = 0;
$ecouter = static function (int $trackId, string $jour, int $fois, int $auditeurs, ?int $user = null) use ($db, &$n, $artistes, $titres): void {
    $artiste = (int) $db->query("SELECT artist_id FROM tracks WHERE id = {$trackId}")->fetchColumn();
    for ($i = 0; $i < $fois; $i++) {
        $n++;
        $db->prepare('INSERT INTO streams (user_id, listener_key, track_id, artist_id, ip_address, user_agent, duration_played, created_at) VALUES (?, ?, ?, ?, ?, ?, 60, ?)')
           ->execute([$user, $user ? "u:{$user}" : 'a:ch' . ($i % $auditeurs) . '-' . $trackId, $trackId, $artiste, '10.' . ($n % 250) . '.' . intdiv($n, 250) . '.8', 'Agent ' . $n,
               sprintf('%s %02d:%02d:00', $jour, 8 + $i % 12, $i % 60)]);
    }
};
$payer = static function (string $type, int $id, int $artiste, string $quand) use ($db, &$commandes, $creerUtilisateur): int {
    $acheteur = $creerUtilisateur('c' . count($commandes));
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $acheteur]);
    $commandes[] = $o = (int) $db->lastInsertId();
    Commandes::ajouterArticle($o, $type, $id, $type === 'track' ? 500.0 : 1500.0, $artiste, 'ZZCHART achat');
    $db->exec("UPDATE orders SET status = 'paid', paid_at = '{$quand}' WHERE id = {$o}");
    return $o;
};
$entrees = static fn (string $slug, string $classement): array => array_map(
    fn($e) => ['id' => (int) $e['item_id'], 'rang' => (int) $e['rank'], 'valeur' => (int) $e['value'], 'precedent' => $e['previous_rank'], 'meilleur' => (int) $e['peak_rank'], 'periodes' => (int) $e['periods_on_chart'], 'nouveau' => (int) $e['is_new']],
    Barometre::entrees((int) Barometre::edition($slug)['id'], $classement)
);
$rangDe = static function (array $liste, int $id): ?int {
    foreach ($liste as $l) {
        if ($l['id'] === $id) {
            return $l['rang'];
        }
    }
    return null;
};

try {
    echo "\n=== A. Jeu d'essai : semaines {$slug1} et {$slug2} ===\n";
    $j1 = date('Y-m-d', strtotime($lundi . ' +2 days'));
    $ecouter($titres['A'], $j1, 30, 30);
    $ecouter($titres['B'], $j1, 20, 10);   // 20 ecoutes, 10 auditeurs
    $ecouter($titres['C'], $j1, 20, 20);   // 20 ecoutes, 20 auditeurs : devant B a egalite
    $ecouter($titres['brouillon'], $j1, 50, 50);
    // Une ecoute de C par un compte sous surveillance : en quarantaine.
    $surveille = $creerUtilisateur('s');
    Certification::surveiller('compte', $surveille, 'Compte a verifier (essai barometre)', $surveille);
    $ecouter($titres['C'], $j1, 1, 1, $surveille);
    $payer('track', $titres['A'], $artistes['A'], $j1 . ' 10:00:00');
    $payer('release', $sorties['B'], $artistes['B'], $j1 . ' 11:00:00');
    $j2 = date('Y-m-d', strtotime($lundi2 . ' +1 day'));
    $ecouter($titres['B'], $j2, 40, 40);
    $ecouter($titres['A'], $j2, 10, 10);
    $rembourse = $payer('track', $titres['C'], $artistes['C'], $j2 . ' 12:00:00');
    $db->exec("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = {$rembourse}");
    Certification::traiter(100000);
    verif('Ecoutes certifiees, sauf le compte surveille', (int) $db->query("SELECT COUNT(*) FROM stream_verdicts v JOIN streams s ON s.id = v.stream_id WHERE s.user_id = {$surveille} AND v.verdict = 'quarantaine'")->fetchColumn() === 1);

    echo "\n=== B. Arrete (CHART-01) ===\n";
    try {
        Barometre::arreter('weekly', date('Y-m-d'));
        $refuse = false;
    } catch (RuntimeException $e) {
        $refuse = str_contains($e->getMessage(), 'non consolidee');
    }
    verif('Semaine en cours : arrete refuse (48 h de consolidation)', $refuse);
    $r = Barometre::arreter('weekly', $lundi);
    // 30 + 20 + 20 + les 50 du brouillon (certifiees : elles comptent au total et pour l'artiste).
    verif("Edition {$slug1} arretee", $r['slug'] === $slug1 && $r['ecoutes'] === 120 && $r['ventes'] === 2, json_encode($r));
    $top = $entrees($slug1, 'titres');
    verif('Top titres : A (30), puis C devant B a egalite (plus d\'auditeurs)', array_column($top, 'id') === [$titres['A'], $titres['C'], $titres['B']], json_encode($top));
    verif('... le titre non publie ne concourt pas', $rangDe($top, $titres['brouillon']) === null);
    verif('... nouvelles entrees, sans rang precedent, meilleur rang = rang', $top[0]['nouveau'] === 1 && $top[0]['precedent'] === null && $top[0]['meilleur'] === 1 && $top[0]['periodes'] === 1);
    verif('Ventes separees des ecoutes : Top titres (ventes) = A', array_column($entrees($slug1, 'titres_ventes'), 'id') === [$titres['A']]);
    verif('Sorties par format : le single ne concourt pas contre les albums',
        array_column($entrees($slug1, 'sorties_single'), 'id') === [$sorties['A']] && array_column($entrees($slug1, 'sorties_album'), 'id') === [$sorties['C'], $sorties['B']]);
    verif('Ventes de sorties : B', array_column($entrees($slug1, 'sorties_ventes'), 'id') === [$sorties['B']]);
    verif('Top artistes : C (70, brouillon compris), A (30), B (20)', array_column($entrees($slug1, 'artistes'), 'id') === [$artistes['C'], $artistes['A'], $artistes['B']]);
    try {
        Barometre::arreter('weekly', $lundi);
        $double = true;
    } catch (RuntimeException $e) {
        $double = false;
    }
    verif('Second arrete de la meme semaine : refuse', !$double);
    $edition1 = Barometre::edition($slug1);
    verif('Edition figee (base) : ni modification ni suppression',
        refuse($db, "UPDATE chart_entries SET value = 999 WHERE edition_id = {$edition1['id']}")
        && refuse($db, "UPDATE chart_editions SET streams_total = 1 WHERE id = {$edition1['id']}")
        && refuse($db, "DELETE FROM chart_entries WHERE edition_id = {$edition1['id']}")
        && refuse($db, "DELETE FROM chart_editions WHERE id = {$edition1['id']}"));

    // La quarantaine est levee APRES l'arrete de la semaine 1.
    sleep(1);
    $signaux = (string) $db->query("SELECT v.signals FROM stream_verdicts v JOIN streams s ON s.id = v.stream_id WHERE s.user_id = {$surveille}")->fetchColumn();
    Certification::decider($signaux, $titres['C'], $j1, 'valider', 'Compte verifie apres coup', $surveille);
    verif('Quarantaine levee apres l\'arrete : l\'edition arretee ne change pas', $entrees($slug1, 'titres') === $top);
    $r2 = Barometre::arreter('weekly', $lundi2);
    $top2 = $entrees($slug2, 'titres');
    verif('... l\'ecoute levee compte pour la semaine suivante', $rangDe($top2, $titres['C']) !== null
        && $top2[array_search($titres['C'], array_column($top2, 'id'), true)]['valeur'] === 1, json_encode($top2));
    verif('Semaine 2 : B premier, rang precedent 3, montee de 2', $top2[0]['id'] === $titres['B'] && (int) $top2[0]['precedent'] === 3 && Barometre::evolution(['is_new' => 0, 'previous_rank' => 3, 'rank' => 1]) === '+2');
    $a = $top2[array_search($titres['A'], array_column($top2, 'id'), true)];
    verif('... A redescend : rang precedent 1, meilleur rang conserve (1), 2 semaines classees', (int) $a['precedent'] === 1 && $a['meilleur'] === 1 && $a['periodes'] === 2 && $a['nouveau'] === 0, json_encode($a));
    verif('Vente remboursee avant l\'arrete : absente', $entrees($slug2, 'titres_ventes') === [] && $r2['ventes'] === 0);
    $r3 = Barometre::arreter('monthly', $lundi);
    verif('Edition mensuelle arretee', str_starts_with($r3['slug'], substr($lundi, 0, 4)) && Barometre::edition($r3['slug'])['period_type'] === 'monthly');
    verif('Archives par edition', in_array($slug1, array_column(Barometre::archives('weekly', 500), 'slug'), true));

    echo "\n=== C. Vues analytiques (CHART-02) ===\n";
    Agregats::construire($lundi, date('Y-m-d', strtotime($lundi2 . ' +6 days')));
    $fin1 = date('Y-m-d', strtotime($lundi . ' +6 days'));
    foreach (['genre', 'categorie', 'format', 'region'] as $dim) {
        $v = Barometre::vue($dim, $lundi, $fin1);
        $somme = array_sum(array_column($v['lignes'], 'ecoutes'));
        $ventes = array_sum(array_column($v['lignes'], 'ventes'));
        verif("Vue par {$dim} : se recoupe avec le total national", $somme === $v['total']['ecoutes'] && $ventes === $v['total']['ventes'] && $somme > 0, "{$somme} / {$v['total']['ecoutes']}");
    }
    $formats = array_column(Barometre::vue('format', $lundi, $fin1)['lignes'], 'ecoutes', 'cle');
    verif('Vue par format : single 30, album 41 (quarantaine levee comprise)', (int) ($formats['single'] ?? 0) === 30 && (int) ($formats['album'] ?? 0) === 41, json_encode($formats));
    verif('Vue par region : tout est « non localise » (geolocalisation absente, rien d\'invente)', count(Barometre::vue('region', $lundi, $fin1)['lignes']) === 1);
    $indice = Barometre::indiceDecouverte($lundi, $fin1);
    verif('Indice de decouverte : part des ecoutes d\'artistes arrives depuis moins de 12 mois (A : 30 / 121, brouillon compris)', abs($indice - round(100 * 30 / 121, 1)) < 0.05, (string) $indice);

    echo "\n=== D. Kit presse et exports (CHART-04) ===\n";
    $kits = KitPresse::genererManquants();
    $dossier = KitPresse::dossier($slug1);
    $image = @getimagesize($dossier . '/top10.png');
    verif('Kits generes sans intervention pour chaque edition arretee', $kits >= 3 && Barometre::edition($slug1)['kit_generated_at'] !== null);
    verif('Visuel Top 10 : PNG 1080 x 1350', $image && $image[0] === 1080 && $image[1] === 1350, json_encode($image));
    $csv = (string) file_get_contents($dossier . '/classement.csv');
    verif('CSV : methodologie et date d\'arrete, tous les classements', str_contains($csv, 'methodologie v1') && str_contains($csv, 'titres;1;') && str_contains($csv, 'sorties_album'));
    verif('Communique genere avec le n°1 et les chiffres cles', str_contains((string) file_get_contents($dossier . '/communique.html'), 'ZZCHART titre A'));

    echo "\n=== E. Barometre public (CHART-03, CHART-05) ===\n";
    [$code, $page, , $duree] = http($base . '/barometre.php?edition=' . $slug1 . '&classement=titres');
    verif('Permalien d\'edition : page publique', $code === 200 && str_contains($page, 'ZZCHART titre A') && str_contains($page, 'data-rang="1"'), "HTTP {$code}");
    verif('Apercu WhatsApp / Facebook : og:image = visuel du Top 10, og:title explicite',
        str_contains($page, 'property="og:image" content="' . $base . '/barometre-kit.php?edition=' . $slug1 . '&amp;fichier=top10.png"') && str_contains($page, 'property="og:title" content="Barometre Tchadok - Top titres (ecoutes)'));
    verif('Donnees structurees JSON-LD (ItemList)', str_contains($page, '"@type":"ItemList"'));
    verif('Lien vers la methodologie depuis le classement', str_contains($page, '/methodologie.php'));
    verif(sprintf('Page legere : %d Ko de HTML, servie en %.2f s', strlen($page) / 1024, $duree), strlen($page) < 150 * 1024 && $duree < 1.5);
    [, $page] = http($base . '/barometre.php?edition=' . $slug1 . '&classement=titres&genre=' . $genre['id']);
    verif('Filtre par genre : rang national conserve', str_contains($page, 'rang affiche reste le rang national') && str_contains($page, 'ZZCHART titre A'));
    [, $page] = http($base . '/barometre.php?edition=' . $slug2 . '&classement=titres');
    verif('Evolution affichee (+2, nouveau / retour)', str_contains($page, '+2'));
    [$code] = http($base . '/barometre.php?edition=1990-S01');
    verif('Edition inconnue : 404', $code === 404);
    [$code, $corps, $type] = http($base . '/barometre-kit.php?edition=' . $slug1 . '&fichier=top10.png');
    verif('Visuel servi en image/png', $code === 200 && str_starts_with($type, 'image/png') && str_starts_with($corps, "\x89PNG"));
    [$code] = http($base . '/barometre-kit.php?edition=' . $slug1 . '&fichier=..%2F..%2F.env.local');
    verif('Kit : aucun autre fichier accessible', $code === 404);
    [$code, $page] = http($base . '/methodologie.php');
    verif('Methodologie publiee, datee, versionnee, seuils de certification', $code === 200 && str_contains($page, 'data-version-methodologie="1"') && str_contains($page, '28 septembre 2026') && str_contains($page, 'Diamant'));

    echo "\n=== F. API publique (CHART-04) ===\n";
    [$code] = http($base . '/api/barometre.php?edition=' . $slug1);
    verif('Sans cle : 401', $code === 401);
    exec(sprintf('"%s" "%s/scripts/barometre.php" cle-creer "%s" api@test.local 2 2>&1', PHP_BINARY, $racine, 'Media ' . $marque), $sortie);
    preg_match('/(tbk_[a-f0-9]{40})/', implode("\n", $sortie), $m);
    $cle = $m[1] ?? '';
    [$code, $corps] = http($base . '/api/barometre.php?edition=' . $slug1 . '&classement=titres', ['X-Api-Key: ' . $cle]);
    $json = json_decode($corps, true);
    verif('Avec cle : classement, attribution obligatoire, methodologie', $code === 200 && ($json['entrees'][0]['titre'] ?? '') === 'ZZCHART titre A'
        && str_contains((string) ($json['attribution'] ?? ''), 'Barometre Tchadok') && !empty($json['methodologie']), $corps);
    http($base . '/api/barometre.php?edition=' . $slug1, ['X-Api-Key: ' . $cle]);
    [$code] = http($base . '/api/barometre.php?edition=' . $slug1, ['X-Api-Key: ' . $cle]);
    verif('Quota quotidien (2) depasse : 429', $code === 429);
    verif('La cle n\'est conservee qu\'en empreinte', (int) $db->query('SELECT COUNT(*) FROM api_keys WHERE key_hash = ' . $db->quote(hash('sha256', $cle)))->fetchColumn() === 1
        && (int) $db->query('SELECT COUNT(*) FROM api_keys WHERE key_hash = ' . $db->quote($cle))->fetchColumn() === 0);

    echo "\n=== G. Certifications (CHART-06) ===\n";
    $or = CertificationsTchadok::seuils()['ecoutes']['or'];
    $db->exec("UPDATE tracks SET total_streams = {$or} + 10 WHERE id = {$titres['A']}");
    $journal = $racine . '/storage/logs/mail.log';
    $taille = is_file($journal) ? filesize($journal) : 0;
    CertificationsTchadok::decerner();
    $certif = $db->query("SELECT * FROM certifications WHERE track_id = {$titres['A']}")->fetch(PDO::FETCH_ASSOC);
    verif('Seuil Or franchi : certification decernee automatiquement', $certif && $certif['level'] === 'or' && $certif['basis'] === 'ecoutes' && (int) $certif['value_at_award'] === $or + 10, json_encode($certif));
    verif('... artiste notifie', $certif['notified_at'] !== null && str_contains((string) file_get_contents($journal, false, null, $taille), 'ZZCHART titre A'));
    CertificationsTchadok::decerner();
    verif('... une seule fois', (int) $db->query("SELECT COUNT(*) FROM certifications WHERE track_id = {$titres['A']}")->fetchColumn() === 1);
    verif('... definitive (base)', refuse($db, "UPDATE certifications SET level = 'diamant' WHERE id = {$certif['id']}") && refuse($db, "DELETE FROM certifications WHERE id = {$certif['id']}"));
    [$code, $page] = http($base . '/certification.php?id=' . $certif['id']);
    verif('Attestation publique, imprimable', $code === 200 && str_contains($page, 'Certifie Or') && str_contains($page, 'ZZCHART titre A'));
    [, $page] = http($base . '/barometre.php?edition=' . $slug1 . '&classement=titres');
    verif('Badge de certification sur le classement', str_contains($page, 'Certifie Or (ecoutes certifiees)'));
} finally {
    $slugs = [$slug1, $slug2];
    $idsTitres = implode(',', $titres);
    $db->exec("DELETE FROM streams WHERE track_id IN ({$idsTitres})");
    $db->exec("DELETE FROM stream_watchlist WHERE target_type = 'compte' AND target_id IN (" . implode(',', $utilisateurs) . ')');
    if ($commandes) {
        $liste = implode(',', $commandes);
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec("DELETE FROM tracks WHERE id IN ({$idsTitres})");
    $db->exec('DELETE FROM releases WHERE id IN (' . implode(',', $sorties) . ')');
    $db->exec('DELETE FROM artists WHERE id IN (' . implode(',', $artistes) . ')');
    $db->exec("DELETE FROM streams_certified WHERE track_id IN ({$idsTitres})");
    $db->exec("DELETE FROM certifications WHERE track_id IN ({$idsTitres})");
    // Objets disparus : leurs entrees, puis les editions vides, se suppriment.
    $editions = $db->query("SELECT id, slug FROM chart_editions WHERE period_start BETWEEN '1995-01-01' AND '1999-12-31'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($editions as $id => $s) {
        try {
            $db->exec("DELETE FROM chart_entries WHERE edition_id = {$id}");
            $db->exec("DELETE FROM chart_editions WHERE id = {$id}");
            foreach (glob(KitPresse::dossier($s) . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir(KitPresse::dossier($s));
        } catch (Throwable $e) {
            echo "  (edition {$s} conservee : elle porte des objets reels)\n";
        }
    }
    $db->exec("DELETE FROM daily_rollups WHERE day BETWEEN '{$lundi}' AND '" . date('Y-m-d', strtotime($lundi2 . ' +6 days')) . "'");
    $db->exec("DELETE FROM rollup_runs WHERE day BETWEEN '{$lundi}' AND '" . date('Y-m-d', strtotime($lundi2 . ' +6 days')) . "'");
    $db->exec("DELETE FROM api_keys WHERE owner = " . $db->quote('Media ' . $marque));
    foreach ($utilisateurs as $u) {
        try {
            $db->exec("DELETE FROM users WHERE id = {$u}");
        } catch (Throwable $e) {
            $db->exec("UPDATE users SET is_active = 0 WHERE id = {$u}");
        }
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
