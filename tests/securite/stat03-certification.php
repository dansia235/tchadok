<?php
/**
 * Tests STAT-03 et STAT-04 : certification anti-fraude, tableau de bord.
 *
 * Criteres du plan :
 *   - un script generant 10 000 ecoutes depuis une IP est detecte et mis en
 *     quarantaine ;
 *   - la levee de quarantaine est journalisee avec son auteur et son motif ;
 *   - le volume certifie est toujours inferieur ou egal au volume brut ;
 *   - un moderateur instruit une anomalie et trace sa decision sans acces
 *     direct a la base.
 *
 * Les ecoutes d'essai sont placees sur un jour passe (2010-2019) tire au
 * hasard, pour ne pas se meler aux ecoutes reelles. Elles sont retirees en fin
 * de test (les certifiees avec leurs titres : c'est la seule suppression que
 * la base accepte).
 *
 * Usage : C:\xampp\php\php.exe tests\securite\stat03-certification.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/certification.php';
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

$db = TchadokDatabase::getInstance()->getConnection();
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
$jour = sprintf('%04d-%02d-%02d', random_int(2010, 2019), random_int(1, 12), random_int(1, 28));
$marque = 'zzst3' . bin2hex(random_bytes(3));

$creerUtilisateur = static function (string $s, ?string $cree = null) use ($db, $marque): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, created_at) VALUES (?, ?, 'x', 'Test', 'Certif', 1, COALESCE(?, NOW()))")
       ->execute([$marque . $s, $marque . $s . '@test.local', $cree]);
    return (int) $db->lastInsertId();
};
$utilisateurs = [];
$utilisateurs[] = $uA = $creerUtilisateur('a');
$utilisateurs[] = $uB = $creerUtilisateur('b');
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uA, 'ZZST3 A ' . $marque]);
$artA = (int) $db->lastInsertId();
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uB, 'ZZST3 B ' . $marque]);
$artB = (int) $db->lastInsertId();
$titres = [];
foreach (['normal', 'rafale', 'ratio', 'comptes', 'surveille', 'nuit'] as $nom) {
    $artiste = $nom === 'surveille' ? $artB : $artA;
    $db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, status) VALUES (?, ?, 'x.mp3', 200, 1, 'approved')")->execute([$artiste, 'ZZST3 ' . $nom]);
    $titres[$nom] = (int) $db->lastInsertId();
}

/** Insertion en masse d'ecoutes brutes : [ip, agent, cle, user, heure, minute]. */
$inserer = static function (int $trackId, array $lignes) use ($db, $jour, $titres, $artA, $artB): void {
    $artiste = $trackId === $titres['surveille'] ? $artB : $artA;
    foreach (array_chunk($lignes, 500) as $paquet) {
        $valeurs = [];
        $params = [];
        foreach ($paquet as [$ip, $agent, $cle, $user, $heure, $minute]) {
            $valeurs[] = '(?, ?, ?, ?, ?, ?, 60, ?)';
            array_push($params, $user, $cle, $trackId, $artiste, $ip, $agent, sprintf('%s %02d:%02d:%02d', $jour, $heure, $minute % 60, random_int(0, 59)));
        }
        $db->prepare('INSERT INTO streams (user_id, listener_key, track_id, artist_id, ip_address, user_agent, duration_played, created_at) VALUES ' . implode(',', $valeurs))->execute($params);
    }
};
$verdicts = static fn (int $trackId): array => $db->query(
    "SELECT v.verdict, COUNT(*) FROM streams s JOIN stream_verdicts v ON v.stream_id = s.id WHERE s.track_id = {$trackId} GROUP BY v.verdict"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$signauxDe = static fn (int $trackId): string => (string) $db->query(
    "SELECT GROUP_CONCAT(DISTINCT v.signals) FROM streams s JOIN stream_verdicts v ON v.stream_id = s.id WHERE s.track_id = {$trackId}"
)->fetchColumn();

try {
    echo "\n=== A. Jeu d'essai du {$jour} ===\n";
    // Ecoutes ordinaires : 20 auditeurs, 20 reseaux, 20 navigateurs.
    $inserer($titres['normal'], array_map(fn($i) => ["10.{$i}.1.5", "Agent {$i}", "a:normal{$i}", null, 8 + $i % 12, $i], range(1, 20)));
    // Un script : 10 000 ecoutes depuis UNE adresse, identifiants d'auditeur varies.
    $inserer($titres['rafale'], array_map(fn($i) => ['203.0.113.7', 'Bot', "a:bot{$i}", null, intdiv($i, 417), $i], range(0, 9999)));
    // 3 auditeurs, 60 ecoutes : 20 par auditeur.
    $inserer($titres['ratio'], array_map(fn($i) => ['198.51.' . ($i % 3) . '.9', 'Agent ratio ' . ($i % 3), 'a:ratio' . ($i % 3), null, 6 + intdiv($i, 4), $i], range(0, 59)));
    // 10 comptes crees dans la meme heure, qui ecoutent le meme titre.
    $rafale = [];
    for ($i = 0; $i < 10; $i++) {
        $utilisateurs[] = $u = $creerUtilisateur('r' . $i, $jour . ' 03:1' . $i . ':00');
        $rafale[] = ["192.0.{$i}.20", "Agent compte {$i}", "u:{$u}", $u, 14, $i];
    }
    $inserer($titres['comptes'], $rafale);
    // L'artiste sur son propre titre.
    $inserer($titres['normal'], [['10.99.1.1', 'Agent artiste', "u:{$uA}", $uA, 15, 0]]);
    // Titre d'un artiste sous surveillance.
    $surveillance = Certification::surveiller('artiste', $artB, 'Pics d\'ecoutes signales (essai)', $uB);
    $inserer($titres['surveille'], [['10.50.1.1', 'Agent', 'a:surv', null, 12, 0]]);
    // Volume nocturne : 110 ecoutes de 110 auditeurs entre 0 h et 5 h.
    $inserer($titres['nuit'], array_map(fn($i) => ['172.' . (16 + $i % 16) . ".{$i}.3", "Agent nuit {$i}", "a:nuit{$i}", null, $i % 5, $i], range(0, 109)));
    // Une ecoute trop recente pour etre jugee (moins de 2 h).
    $db->prepare("INSERT INTO streams (listener_key, track_id, artist_id, ip_address, user_agent, duration_played, created_at) VALUES ('a:recent', ?, ?, '10.1.2.3', 'Agent', 60, NOW() - INTERVAL 10 MINUTE)")
       ->execute([$titres['normal'], $artA]);
    $recente = (int) $db->lastInsertId();
    $brut = (int) $db->query('SELECT COUNT(*) FROM streams WHERE track_id IN (' . implode(',', $titres) . ')')->fetchColumn();
    verif('Jeu d\'essai en place', $brut === 20 + 10000 + 60 + 10 + 1 + 1 + 110 + 1, (string) $brut);
    verif('Surveillance : motif obligatoire', !Certification::surveiller('artiste', $artA, 'x', $uB)['succes'] && $surveillance['succes']);

    echo "\n=== B. Certification ===\n";
    $debut = microtime(true);
    $bilan = Certification::traiter(50000);
    $duree = round(microtime(true) - $debut, 1);
    echo "      ({$bilan['jugees']} ecoute(s) jugee(s) en {$duree} s)\n";
    verif('Ecoutes ordinaires : certifiees', ($verdicts($titres['normal'])['certifiee'] ?? 0) === 20, json_encode($verdicts($titres['normal'])));
    $v = $verdicts($titres['rafale']);
    verif('10 000 ecoutes depuis une IP : toutes en quarantaine', ($v['quarantaine'] ?? 0) === 10000 && count($v) === 1, json_encode($v));
    verif('... signal « rafale depuis une adresse IP »', str_contains($signauxDe($titres['rafale']), 'rafale_ip'), $signauxDe($titres['rafale']));
    verif('Trop d\'ecoutes par auditeur : quarantaine (ratio)', ($verdicts($titres['ratio'])['quarantaine'] ?? 0) === 60 && $signauxDe($titres['ratio']) === 'ratio_auditeurs', $signauxDe($titres['ratio']));
    verif('Comptes crees en rafale : quarantaine', ($verdicts($titres['comptes'])['quarantaine'] ?? 0) === 10 && str_contains($signauxDe($titres['comptes']), 'comptes_en_rafale'), $signauxDe($titres['comptes']));
    verif('Artiste sur son propre titre : exclue', ($verdicts($titres['normal'])['exclue'] ?? 0) === 1);
    verif('Artiste sous surveillance : en revue', ($verdicts($titres['surveille'])['quarantaine'] ?? 0) === 1 && $signauxDe($titres['surveille']) === 'sous_surveillance');
    $v = $verdicts($titres['nuit']);
    verif('Volume nocturne : alerte seulement, ecoutes certifiees', ($v['certifiee'] ?? 0) === 110 && str_contains($signauxDe($titres['nuit']), 'volume_nocturne'), json_encode($v));
    verif('Ecoute de moins de 2 h : pas encore jugee', !$db->query("SELECT 1 FROM stream_verdicts WHERE stream_id = {$recente}")->fetchColumn());
    $certifiees = (int) $db->query('SELECT COUNT(*) FROM streams_certified WHERE track_id IN (' . implode(',', $titres) . ')')->fetchColumn();
    verif('Volume certifie <= volume brut, ecart mesurable', $certifiees === 130 && $certifiees <= $brut, "{$certifiees} / {$brut}");
    $bis = Certification::traiter(50000);
    verif('Relancer : aucune ecoute jugee deux fois', (int) $db->query('SELECT COUNT(*) FROM streams_certified WHERE track_id IN (' . implode(',', $titres) . ')')->fetchColumn() === $certifiees, json_encode($bis));

    echo "\n=== C. Immutabilite ===\n";
    verif('Ecoute certifiee : ni modifiable ni supprimable (base)',
        refuse($db, "UPDATE streams_certified SET track_id = track_id WHERE track_id = {$titres['normal']}")
        && refuse($db, "DELETE FROM streams_certified WHERE track_id = {$titres['normal']}"));
    verif('Verdict automatique definitif (base)', refuse($db, "UPDATE stream_verdicts v JOIN streams s ON s.id = v.stream_id SET v.verdict = 'quarantaine' WHERE s.track_id = {$titres['normal']}"));
    verif('Decision sans motif refusee par la base', refuse($db, "UPDATE stream_verdicts v JOIN streams s ON s.id = v.stream_id SET v.verdict = 'rejetee', v.decided_at = NOW() WHERE s.track_id = {$titres['ratio']}"));

    echo "\n=== D. Decisions humaines ===\n";
    $anomalie = static function (int $trackId): ?array {
        foreach (Certification::anomalies(500) as $a) {
            if ((int) $a['track_id'] === $trackId) {
                return $a;
            }
        }
        return null;
    };
    $aRafale = $anomalie($titres['rafale']);
    verif('Anomalie regroupee : 10 000 ecoutes, 1 adresse, gravite haute', $aRafale && (int) $aRafale['ecoutes'] === 10000 && (int) $aRafale['adresses'] === 1 && Certification::gravite($aRafale) === 'haute', json_encode($aRafale));
    verif('Decision sans motif : refusee', !Certification::decider((string) $aRafale['signals'], $titres['rafale'], $jour, 'rejeter', 'non', $uB)['succes']);
    $r = Certification::decider((string) $aRafale['signals'], $titres['rafale'], $jour, 'rejeter', 'Script identifie : une seule adresse', $uB);
    verif('Rejet motive : 10 000 ecoutes rejetees', $r['succes'] && $r['nombre'] === 10000 && ($verdicts($titres['rafale'])['rejetee'] ?? 0) === 10000, json_encode($r));
    verif('... aucune n\'est certifiee', !$db->query("SELECT 1 FROM streams_certified WHERE track_id = {$titres['rafale']}")->fetchColumn());
    verif('... decision tracee (auteur, motif)', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ecoute.quarantaine_rejetee' AND actor_id = {$uB} AND reason LIKE 'Script identifie%'")->fetchColumn() === 1);
    verif('Seconde decision sur la meme anomalie : refusee', !Certification::decider((string) $aRafale['signals'], $titres['rafale'], $jour, 'valider', 'Changement d\'avis', $uB)['succes']);
    $aRatio = $anomalie($titres['ratio']);
    $r = Certification::decider('ratio_auditeurs', $titres['ratio'], $jour, 'valider', 'Ecoute en boucle verifiee (soiree)', $uB);
    verif('Levee motivee : 60 ecoutes certifiees par revue', $r['succes'] && (int) $db->query("SELECT COUNT(*) FROM streams_certified WHERE track_id = {$titres['ratio']} AND via = 'revue'")->fetchColumn() === 60, json_encode($r));
    verif('... levee tracee avec son auteur et son motif', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ecoute.quarantaine_levee' AND actor_id = {$uB} AND reason LIKE 'Ecoute en boucle%'")->fetchColumn() === 1);
    $sante = Certification::sante(36500);
    verif('Indicateurs : brut >= certifie + exclu + quarantaine + rejete', $sante['brut'] >= $sante['certifiees'] + $sante['exclues'] + $sante['quarantaine'] + $sante['rejetees'], json_encode($sante));
    $idSurveillance = (int) $db->query("SELECT id FROM stream_watchlist WHERE target_type = 'artiste' AND target_id = {$artB} AND lifted_at IS NULL")->fetchColumn();
    verif('Levee de surveillance : motif obligatoire, puis tracee', !Certification::leverSurveillance($idSurveillance, '', $uB)['succes']
        && Certification::leverSurveillance($idSurveillance, 'Pics expliques par un passage radio', $uB)['succes']);

    echo "\n=== E. Tableau de bord ===\n";
    $cookies = (string) tempnam(sys_get_temp_dir(), 'st3');
    $http = static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 60]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps];
    };
    [$code] = $http($base . '/admin/anti-fraude.php');
    verif('Ecran ferme sans session', $code !== 200);
    $db->exec("DELETE FROM login_attempts WHERE identifier = 'admin@tchadok.td'");
    [, $page] = $http($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => 'admin@tchadok.td', 'password' => 'tchadok2026']);
    [$code, $page] = $http($base . '/admin/anti-fraude.php');
    verif('Moderateur : anomalies listees avec le detail des signaux', $code === 200 && str_contains($page, 'ZZST3 comptes') && str_contains($page, 'Comptes crees en rafale'), "HTTP {$code}");
    verif('... indicateurs de sante affiches', str_contains($page, 'Ecoutes brutes') && str_contains($page, 'Part suspecte'));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $signauxComptes = (string) $db->query("SELECT v.signals FROM stream_verdicts v JOIN streams s ON s.id = v.stream_id WHERE s.track_id = {$titres['comptes']} LIMIT 1")->fetchColumn();
    $http($base . '/admin/anti-fraude.php', ['csrf_token' => $m[1] ?? '', 'action' => 'rejeter', 'signaux' => $signauxComptes, 'titre' => $titres['comptes'], 'jour' => $jour, 'motif' => '']);
    verif('Decision sans motif depuis l\'ecran : refusee', ($verdicts($titres['comptes'])['quarantaine'] ?? 0) === 10);
    $http($base . '/admin/anti-fraude.php', ['csrf_token' => $m[1] ?? '', 'action' => 'rejeter', 'signaux' => $signauxComptes, 'titre' => $titres['comptes'], 'jour' => $jour, 'motif' => 'Faux comptes crees ensemble']);
    verif('Decision depuis l\'ecran, sans acces a la base', ($verdicts($titres['comptes'])['rejetee'] ?? 0) === 10);
    [, $page] = $http($base . '/admin-dashboard.php');
    verif('Entree « Anti-fraude » dans la console', str_contains($page, '/admin/anti-fraude.php'));
    @unlink($cookies);

    echo "\n=== F. Alerte et ligne de commande ===\n";
    $sortie = [];
    exec(sprintf('"%s" "%s/scripts/ecoutes.php" sante 36500 2>&1', PHP_BINARY, $racine), $sortie, $code);
    verif('scripts/ecoutes.php sante : part suspecte au-dela du seuil -> code 1 (supervision)', $code === 1 && str_contains(implode(' ', $sortie), 'seuil d\'alerte'), implode(' | ', $sortie));
} finally {
    $liste = implode(',', $titres);
    $db->exec("DELETE FROM streams WHERE track_id IN ({$liste})");
    $db->exec("DELETE FROM stream_watchlist WHERE target_type = 'artiste' AND target_id IN ({$artA}, {$artB})");
    $db->exec("DELETE FROM tracks WHERE id IN ({$liste})");
    $db->exec("DELETE FROM streams_certified WHERE track_id IN ({$liste})");
    $db->exec("DELETE FROM artists WHERE id IN ({$artA}, {$artB})");
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
