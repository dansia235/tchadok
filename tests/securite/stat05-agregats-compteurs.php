<?php
/**
 * Tests STAT-05 (agregats journaliers) et STAT-06 (compteurs sans declencheur).
 *
 * Criteres du plan :
 *   STAT-05 - relancer l'agregation d'un jour ne duplique rien ;
 *           - une reconstruction sur 90 jours aboutit et redonne les memes
 *             chiffres ;
 *           - une requete de tableau de bord sur 12 mois s'execute en moins de
 *             200 ms.
 *   STAT-06 - aucun declencheur ne tient plus de compteur ;
 *           - une ecoute non certifiee n'incremente aucun compteur public ;
 *           - purger 1 000 ecoutes frauduleuses et relancer le recalcul fait
 *             baisser les compteurs ;
 *           - un remboursement decremente les ventes ;
 *           - la reconstruction integrale donne exactement les memes valeurs
 *             que l'incremental.
 *
 * Jour d'essai tire au hasard entre 2000 et 2009. Usage :
 *   C:\xampp\php\php.exe tests\securite\stat05-agregats-compteurs.php
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

$db = TchadokDatabase::getInstance()->getConnection();
$jour = sprintf('%04d-%02d-%02d', random_int(2000, 2009), random_int(1, 12), random_int(1, 28));
$marque = 'zzst5' . bin2hex(random_bytes(3));
$utilisateurs = [];
$commandes = [];
$creerUtilisateur = static function (string $s) use ($db, $marque, &$utilisateurs): int {
    // Dates de creation etalees : pas de faux signal « comptes en rafale ».
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, created_at) VALUES (?, ?, 'x', 'Test', 'Agregats', 1, NOW() - INTERVAL ? DAY)")
       ->execute([$marque . $s, $marque . $s . '@test.local', 400 + count($utilisateurs) * 3]);
    return $utilisateurs[] = (int) $db->lastInsertId();
};
$uArtiste = $creerUtilisateur('a');
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uArtiste, 'ZZST5 ' . $marque]);
$artiste = (int) $db->lastInsertId();
$genre = $db->query('SELECT id, parent_id FROM genres WHERE parent_id IS NOT NULL ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: ['id' => null, 'parent_id' => 0];
$db->prepare("INSERT INTO releases (artist_id, title, slug, format, status, genre_id) VALUES (?, ?, ?, 'ep', 'approved', ?)")->execute([$artiste, 'ZZST5 EP', $marque . '-ep', $genre['id']]);
$sortie = (int) $db->lastInsertId();
$titre = static function (string $nom, int $duree) use ($db, $artiste, $sortie, $genre): int {
    $db->prepare("INSERT INTO tracks (artist_id, album_id, release_id, genre_id, title, audio_file, duration, is_free, price, status) VALUES (?, ?, ?, ?, ?, 'x.mp3', ?, 0, 500, 'approved')")
       ->execute([$artiste, $sortie, $sortie, $genre['id'], $nom, $duree]);
    return (int) $db->lastInsertId();
};
$t1 = $titre('ZZST5 un', 200);
$t2 = $titre('ZZST5 deux', 150);

$inserer = static function (int $trackId, array $lignes) use ($db, $artiste, $jour): void {
    foreach (array_chunk($lignes, 500) as $paquet) {
        $v = [];
        $p = [];
        foreach ($paquet as [$ip, $agent, $cle, $user, $duree, $heure, $minute]) {
            $v[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($p, $user, $cle, $trackId, $artiste, $ip, $agent, $duree, sprintf('%s %02d:%02d:00', $jour, $heure, $minute % 60));
        }
        $db->prepare('INSERT INTO streams (user_id, listener_key, track_id, artist_id, ip_address, user_agent, duration_played, created_at) VALUES ' . implode(',', $v))->execute($p);
    }
};
$compteurs = static fn (): array => [
    't1' => $db->query("SELECT total_streams, total_sales FROM tracks WHERE id = {$t1}")->fetch(PDO::FETCH_ASSOC),
    't2' => $db->query("SELECT total_streams, total_sales FROM tracks WHERE id = {$t2}")->fetch(PDO::FETCH_ASSOC),
    'sortie' => $db->query("SELECT total_streams, total_sales, total_tracks, total_duration FROM releases WHERE id = {$sortie}")->fetch(PDO::FETCH_ASSOC),
    'artiste' => $db->query("SELECT total_streams, total_sales, total_earnings FROM artists WHERE id = {$artiste}")->fetch(PDO::FETCH_ASSOC),
];
$empreinte = static fn (): string => md5(json_encode($db->query(
    "SELECT * FROM daily_rollups WHERE day = '{$jour}' AND artist_id = {$artiste} ORDER BY track_id, release_id, source, listener_type, region"
)->fetchAll(PDO::FETCH_ASSOC)));
$payer = static function (string $type, int $id, float $prix, string $heure) use ($db, $artiste, $jour, &$commandes, $creerUtilisateur): int {
    $acheteur = $creerUtilisateur('c' . count($commandes));
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")->execute([Commandes::reference(), $acheteur]);
    $commandes[] = $o = (int) $db->lastInsertId();
    Commandes::ajouterArticle($o, $type, $id, $prix, $artiste, 'ZZST5 achat');
    $db->exec("UPDATE orders SET status = 'paid', paid_at = '{$jour} {$heure}' WHERE id = {$o}");
    return $o;
};

try {
    echo "\n=== A. Jeu d'essai du {$jour} ===\n";
    // t1 : 30 ecoutes ordinaires, 5 trop courtes (journal d'avant STAT-02).
    $inserer($t1, array_map(fn($i) => ["10.{$i}.0.1", "Agent {$i}", "a:t1-{$i}", null, 60, 8 + $i % 12, $i], range(1, 30)));
    $inserer($t1, array_map(fn($i) => ["10.{$i}.9.1", "Agent court {$i}", "a:court-{$i}", null, 0, 9, $i], range(1, 5)));
    // Un abonne et un compte gratuit ecoutent t1.
    $abonne = $creerUtilisateur('ab');
    $gratuit = $creerUtilisateur('gr');
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency, total, paid_at) VALUES (?, ?, 'paid', 'XAF', 2000, ?)")->execute([Commandes::reference(), $abonne, $jour . ' 00:00:00']);
    $commandes[] = $oAbo = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO subscriptions (user_id, order_id, plan_type, amount, status, start_date, end_date) VALUES (?, ?, 'monthly', 2000, 'expired', ?, ? + INTERVAL 1 MONTH)")
       ->execute([$abonne, $oAbo, $jour . ' 00:00:00', $jour . ' 00:00:00']);
    $inserer($t1, [['10.200.0.1', 'Agent abonne', "u:{$abonne}", $abonne, 60, 13, 0], ['10.201.0.1', 'Agent gratuit', "u:{$gratuit}", $gratuit, 60, 14, 0]]);
    // t2 : 1 000 ecoutes d'un reseau de machines que les signaux n'ont pas vu
    // (adresses, navigateurs et auditeurs tous differents) : certifiees a tort.
    $inserer($t2, array_map(fn($i) => ['100.' . intdiv($i, 250) . '.' . ($i % 250) . '.7', "Bot {$i}", "a:bot-{$i}", null, 45, intdiv($i, 42), $i], range(0, 999)));
    // Ventes : un titre, une sortie entiere, un titre rembourse ensuite.
    $payer('track', $t1, 500.0, '10:00:00');
    $payer('release', $sortie, 1000.0, '11:00:00');
    $oRembourse = $payer('track', $t1, 500.0, '12:00:00');

    Certification::traiter(50000);
    $certifiees = (int) $db->query("SELECT COUNT(*) FROM streams_certified WHERE artist_id = {$artiste}")->fetchColumn();
    verif('Certification : 32 + 1 000 certifiees, les 5 trop courtes exclues', $certifiees === 1032, (string) $certifiees);

    echo "\n=== B. Agregats (STAT-05) ===\n";
    $r = Agregats::construireJour($jour);
    verif('Jour agrege : 1 032 ecoutes, 3 ventes', $r['streams'] === 1032 && $r['sales'] === 3, json_encode($r));
    $lignes = (int) $db->query("SELECT COUNT(*) FROM daily_rollups WHERE day = '{$jour}'")->fetchColumn();
    $avant = $empreinte();
    Agregats::construireJour($jour);
    Agregats::construireJour($jour);
    verif('Relancer le jour deux fois : rien de duplique, memes chiffres', (int) $db->query("SELECT COUNT(*) FROM daily_rollups WHERE day = '{$jour}'")->fetchColumn() === $lignes && $empreinte() === $avant);
    $types = $db->query("SELECT listener_type, SUM(streams) FROM daily_rollups WHERE day = '{$jour}' AND track_id = {$t1} AND source <> 'vente' GROUP BY listener_type")->fetchAll(PDO::FETCH_KEY_PAIR);
    verif('Dimension « type d\'auditeur » : 30 visiteurs, 1 abonne, 1 compte', (int) ($types['visiteur'] ?? 0) === 30 && (int) ($types['abonne'] ?? 0) === 1 && (int) ($types['compte'] ?? 0) === 1, json_encode($types));
    $dims = $db->query("SELECT DISTINCT release_id, genre_id, category_id FROM daily_rollups WHERE day = '{$jour}' AND track_id = {$t1}")->fetch(PDO::FETCH_ASSOC);
    verif('Dimensions sortie, genre, categorie renseignees', (int) $dims['release_id'] === $sortie && (int) $dims['genre_id'] === (int) $genre['id'] && (int) $dims['category_id'] === (int) $genre['parent_id'], json_encode($dims));
    $vente = $db->query("SELECT SUM(sales), SUM(revenue) FROM daily_rollups WHERE day = '{$jour}' AND source = 'vente' AND track_id = 0 AND release_id = {$sortie}")->fetch(PDO::FETCH_NUM);
    verif('Achat d\'une sortie entiere : ligne de vente de la sortie', (int) $vente[0] === 1 && (float) $vente[1] === 1000.0, json_encode($vente));

    echo "\n=== C. Compteurs (STAT-06) ===\n";
    verif('Aucun declencheur ne tient plus de compteur',
        (int) $db->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND action_statement LIKE '%total\\_%'")->fetchColumn() === 0);
    Agregats::actualiser();
    $c = $compteurs();
    verif('Titre : ecoutes CERTIFIEES seulement (32, pas les 37 brutes)', (int) $c['t1']['total_streams'] === 32, json_encode($c['t1']));
    verif('Titre : 2 ventes (unites)', (int) $c['t1']['total_sales'] === 2);
    verif('Sortie : 1 032 ecoutes, 1 vente entiere, 2 titres, 350 s', (int) $c['sortie']['total_streams'] === 1032 && (int) $c['sortie']['total_sales'] === 1
        && (int) $c['sortie']['total_tracks'] === 2 && (int) $c['sortie']['total_duration'] === 350, json_encode($c['sortie']));
    verif('Artiste : ventes en UNITES (3), part nette en francs', (int) $c['artiste']['total_sales'] === 3
        && abs((float) $c['artiste']['total_earnings'] - (float) $db->query('SELECT SUM(artist_net) FROM order_items WHERE order_id IN (' . implode(',', $commandes) . ") AND artist_id = {$artiste}")->fetchColumn()) < 0.01, json_encode($c['artiste']));

    $db->prepare("INSERT INTO streams (listener_key, track_id, artist_id, ip_address, duration_played, created_at) VALUES ('a:frais', ?, ?, '10.9.9.9', 60, NOW())")->execute([$t1, $artiste]);
    Agregats::actualiser();
    verif('Ecoute brute non encore certifiee : aucun compteur ne bouge', (int) $compteurs()['t1']['total_streams'] === 32);

    $db->exec("UPDATE orders SET status = 'refunded', refunded_at = NOW() WHERE id = {$oRembourse}");
    $a = Agregats::actualiser();
    verif('Remboursement : le jour est recalcule, la vente decomptee', (int) $compteurs()['t1']['total_sales'] === 1 && $a['jours'] >= 1, json_encode($a));

    $avantPurge = $compteurs();
    $r = Certification::revoquer($t2, $jour, null, 'Reseau de machines identifie apres coup', $uArtiste);
    verif('Revocation de 1 000 ecoutes frauduleuses deja certifiees', $r['succes'] && $r['nombre'] === 1000, json_encode($r));
    verif('... tracee au journal, avec motif', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ecoute.revoquee' AND reason LIKE 'Reseau de machines%'")->fetchColumn() >= 1);
    verif('... historique intact : les 1 000 restent dans streams_certified', (int) $db->query("SELECT COUNT(*) FROM streams_certified WHERE track_id = {$t2}")->fetchColumn() === 1000);
    Agregats::actualiser();
    $apresPurge = $compteurs();
    verif('Recalcul : les compteurs baissent de 1 000 (titre, sortie, artiste)',
        (int) $apresPurge['t2']['total_streams'] === 0
        && (int) $avantPurge['sortie']['total_streams'] - (int) $apresPurge['sortie']['total_streams'] === 1000
        && (int) $avantPurge['artiste']['total_streams'] - (int) $apresPurge['artiste']['total_streams'] === 1000, json_encode([$avantPurge, $apresPurge]));

    $incremental = $compteurs();
    Compteurs::recalculer();
    verif('Reconstruction integrale = incremental', $compteurs() === $incremental, json_encode([$incremental, $compteurs()]));

    Effacement::retirer('tracks', $t2, $uArtiste, 'Essai retrait');
    verif('Retrait d\'un titre : la sortie passe a 1 titre, 200 s', (int) $compteurs()['sortie']['total_tracks'] === 1 && (int) $compteurs()['sortie']['total_duration'] === 200, json_encode($compteurs()['sortie']));
    Effacement::retablir('tracks', $t2, $uArtiste);
    verif('... et revient a 2 titres quand il est remis', (int) $compteurs()['sortie']['total_tracks'] === 2);

    echo "\n=== D. Reconstruction et performance ===\n";
    $avant = $empreinte();
    $debut = microtime(true);
    $n = Agregats::construire(date('Y-m-d', strtotime($jour . ' -89 days')), $jour);
    $duree = microtime(true) - $debut;
    verif(sprintf('Reconstruction sur 90 jours : aboutie (%d jours, %.1f s), memes chiffres', $n, $duree), $n === 90 && $empreinte() === $avant);
    verif('Controle de coherence compteurs / agregats : aucun ecart', Compteurs::controler() === [], implode(' ; ', Compteurs::controler()));

    // Volume d'un an : 365 jours x 200 lignes, sur des identifiants fictifs.
    $fictif = 2000000000;
    $db->beginTransaction();
    $insertion = $db->prepare('INSERT INTO daily_rollups (day, track_id, release_id, artist_id, genre_id, category_id, source, listener_type, streams, listeners, duration_seconds) VALUES '
        . implode(',', array_fill(0, 200, "(?, ?, 0, ?, ?, ?, 'web', 'visiteur', ?, ?, ?)")));
    for ($d = 0; $d < 365; $d++) {
        $p = [];
        $date = date('Y-m-d', strtotime("-{$d} days"));
        for ($i = 0; $i < 200; $i++) {
            array_push($p, $date, $fictif + $i, $fictif + $i % 20, $fictif + $i % 12, $fictif + $i % 4, 10 + $i, 5, 600);
        }
        $insertion->execute($p);
    }
    $db->commit();
    $mesurer = static function (string $sql, array $p) use ($db): float {
        $meilleur = INF;
        for ($k = 0; $k < 3; $k++) {
            $t = microtime(true);
            $s = $db->prepare($sql);
            $s->execute($p);
            $s->fetchAll();
            $meilleur = min($meilleur, microtime(true) - $t);
        }
        return $meilleur * 1000;
    };
    $ms1 = $mesurer("SELECT DATE_FORMAT(day, '%Y-%m') m, SUM(streams), SUM(sales) FROM daily_rollups WHERE artist_id = ? AND day >= CURDATE() - INTERVAL 12 MONTH GROUP BY m", [$fictif + 3]);
    $ms2 = $mesurer("SELECT genre_id, DATE_FORMAT(day, '%Y-%m') m, SUM(streams) FROM daily_rollups WHERE day >= CURDATE() - INTERVAL 12 MONTH GROUP BY genre_id, m", []);
    verif(sprintf('Tableau de bord d\'un artiste sur 12 mois : %.0f ms (< 200 ms)', $ms1), $ms1 < 200);
    verif(sprintf('Barometre par genre sur 12 mois, 73 000 lignes : %.0f ms (< 200 ms)', $ms2), $ms2 < 200);
    $db->exec("DELETE FROM daily_rollups WHERE track_id >= {$fictif}");

    echo "\n=== E. Lignes de commande ===\n";
    $sortie1 = [];
    exec(sprintf('"%s" "%s/scripts/ecoutes.php" controler 2>&1', PHP_BINARY, $racine), $sortie1, $code1);
    verif('scripts/ecoutes.php controler : compteurs conformes', $code1 === 0, implode(' | ', $sortie1));
    $sortie2 = [];
    exec(sprintf('"%s" "%s/scripts/rebuild-counters.php" --du=%s --au=%s 2>&1', PHP_BINARY, $racine, $jour, $jour), $sortie2, $code2);
    verif('scripts/rebuild-counters.php : reconstruction et controle', $code2 === 0 && str_contains(implode(' ', $sortie2), 'conformes'), implode(' | ', $sortie2));
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db->exec("DELETE FROM daily_rollups WHERE track_id >= 2000000000");
    $db->exec("DELETE FROM streams WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM tracks WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM streams_certified WHERE artist_id = {$artiste}");
    $db->exec("DELETE rv FROM stream_revocations rv LEFT JOIN streams_certified sc ON sc.stream_id = rv.stream_id WHERE sc.stream_id IS NULL");
    if ($commandes) {
        $liste = implode(',', $commandes);
        $db->exec("DELETE FROM subscriptions WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM order_items WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec("DELETE FROM releases WHERE id = {$sortie}");
    $db->exec("DELETE FROM daily_rollups WHERE artist_id = {$artiste}");
    Agregats::construireJour($jour);
    $db->exec("DELETE FROM rollup_runs WHERE day = '{$jour}'");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
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
