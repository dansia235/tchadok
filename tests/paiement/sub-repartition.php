<?php
/**
 * Tests : part des artistes dans les abonnements (decision du 28/09/2026).
 *
 *   - le pourcentage se regle depuis la console, avec historique, motif et
 *     preavis ; il ne touche jamais un mois deja reparti ;
 *   - le revenu d'un mois est etale au prorata des jours (annuel compris),
 *     abonnements payes seulement ;
 *   - repartition centree sur l'abonne : ses ecoutes seulement, ecoutes
 *     courtes, ecoutes de soi-meme et ecoutes hors abonnement exclues ;
 *   - arrondi au franc sans perte : lignes = reparti, reparti + non attribue
 *     = part ;
 *   - la cloture credite le solde des artistes (circuit des versements),
 *     une seule fois, et se fige en base.
 *
 * Le mois de test est pris AVANT le lancement (2000-2019) et tire au hasard
 * parmi les mois libres : une repartition cloturee ne se supprime pas, elle
 * reste donc en base avec son taux de test, sans effet sur les mois reels.
 * Les deux artistes d'essai restent aussi, desactives (ajustements immuables).
 *
 * Usage : C:\xampp\php\php.exe tests\paiement\sub-repartition.php
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

// Mois de test libre, avant le lancement.
$clos = array_flip($db->query('SELECT period FROM subscription_distributions')->fetchAll(PDO::FETCH_COLUMN));
do {
    $mois = sprintf('%04d-%02d', random_int(2000, 2019), random_int(1, 12));
} while (isset($clos[$mois]));
$debut = $mois . '-01 00:00:00';
$suivant = date('Y-m-d H:i:s', strtotime($debut . ' +1 month'));
$joursMois = (int) date('t', strtotime($debut));
$joursAnnee = (int) ((strtotime($debut . ' +1 year') - strtotime($debut)) / 86400);
$dansLeMois = static fn (int $jour, int $heure = 12): string => sprintf('%s-%02d %02d:00:00', $mois, $jour, $heure);

$marque = 'zzrep' . bin2hex(random_bytes(3));
$creer = static function (string $s) use ($db, $marque): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active) VALUES (?, ?, 'x', 'Test', 'Repartition', 1)")
       ->execute([$marque . $s, $marque . $s . '@test.local']);
    return (int) $db->lastInsertId();
};
$uA = $creer('a');
$uB = $creer('b');
[$s1, $s2, $s3, $s4, $s5, $finance] = [$creer('s1'), $creer('s2'), $creer('s3'), $creer('s4'), $creer('s5'), $creer('f')];
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uA, 'ZZREP A ' . $marque]);
$artA = (int) $db->lastInsertId();
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uB, 'ZZREP B ' . $marque]);
$artB = (int) $db->lastInsertId();
// L'abonne s3 est l'artiste C : ses ecoutes de lui-meme ne comptent pas.
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$s3, 'ZZREP C ' . $marque]);
$artC = (int) $db->lastInsertId();
$titres = [];
foreach ([$artA, $artB, $artC] as $a) {
    $db->prepare("INSERT INTO tracks (artist_id, title, audio_file, duration, is_free, price, status) VALUES (?, 'ZZREP', 'x.mp3', 200, 0, 500, 'approved')")->execute([$a]);
    $titres[$a] = (int) $db->lastInsertId();
}
$commandes = [];
$abonner = static function (int $user, float $montant, string $du, string $au, string $etatCommande = 'paid') use ($db, &$commandes): void {
    $db->prepare("INSERT INTO orders (reference, user_id, status, currency, total, paid_at) VALUES (?, ?, ?, 'XAF', ?, ?)")
       ->execute([Commandes::reference(), $user, $etatCommande, $montant, $du]);
    $commandes[] = $orderId = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO subscriptions (user_id, order_id, plan_type, amount, currency, status, start_date, end_date) VALUES (?, ?, ?, ?, 'XAF', 'expired', ?, ?)")
       ->execute([$user, $orderId, $montant > 5000 ? 'yearly' : 'monthly', $montant, $du, $au]);
};
$ecouter = static function (int $user, int $artiste, int $fois, int $secondes = 60, int $jour = 10) use ($db, $titres): void {
    for ($i = 0; $i < $fois; $i++) {
        $db->prepare('INSERT INTO streams (user_id, track_id, artist_id, duration_played, created_at) VALUES (?, ?, ?, ?, ?)')
           ->execute([$user, $titres[$artiste], $artiste, $secondes, sprintf('%s-%02d 12:%02d:00', substr((string) $GLOBALS['mois'], 0, 7), $jour, $i)]);
    }
};

try {
    echo "\n=== A. Pourcentage administre ===\n";
    verif('Taux propose en vigueur : 70 %', RepartitionAbonnements::taux() === 70.0, (string) RepartitionAbonnements::taux());
    verif('Avant toute decision : 0 %', RepartitionAbonnements::taux('2026-08') === 0.0);
    $prochain = date('Y-m', strtotime(date('Y-m-01') . ' +1 month'));
    verif('Mois passe : refuse', !RepartitionAbonnements::definirTaux(75, '2026-01', 'Revalorisation', $finance)['succes']);
    verif('Hors bornes : refuse', !RepartitionAbonnements::definirTaux(150, $prochain, 'Revalorisation', $finance)['succes']);
    verif('Sans motif : refuse', !RepartitionAbonnements::definirTaux(75, $prochain, 'ok', $finance)['succes']);
    $r = RepartitionAbonnements::definirTaux(60, date('Y-m'), 'Baisse immediate', $finance);
    verif('Baisse dans le mois en cours : refusee (preavis)', !$r['succes'] && str_contains($r['message'], 'preavis'), $r['message']);

    // Chemin de succes, annule ensuite : il ne doit pas changer le taux reel.
    $journal = $racine . '/storage/logs/mail.log';
    $tailleAvant = is_file($journal) ? filesize($journal) : 0;
    $db->beginTransaction();
    $r = RepartitionAbonnements::definirTaux(75, $prochain, 'Revalorisation de test', $finance);
    verif('Hausse programmee le mois prochain', $r['succes'] && RepartitionAbonnements::taux($prochain) === 75.0 && RepartitionAbonnements::taux() === 70.0, $r['message']);
    verif('... l\'ancien taux reste dans l\'historique', count(array_filter(RepartitionAbonnements::historique(), fn($h) => (float) $h['artist_rate'] === 70.0)) >= 1);
    verif('... les artistes sont prevenus', str_contains((string) file_get_contents($journal, false, null, $tailleAvant), 'passe de 70 % a 75 %'));
    $db->rollBack();
    verif('(annule : le taux reel reste 70 %)', RepartitionAbonnements::taux($prochain) === 70.0);

    $premier = (int) $db->query("SELECT id FROM revenue_share_settings ORDER BY id LIMIT 1")->fetchColumn();
    verif('Historique des taux : ni modifiable ni supprimable (base)',
        refuse($db, "UPDATE revenue_share_settings SET artist_rate = 99 WHERE id = {$premier}") && refuse($db, "DELETE FROM revenue_share_settings WHERE id = {$premier}"));
    verif('Taux sans motif ou hors debut de mois : refuse par la base',
        refuse($db, "INSERT INTO revenue_share_settings (artist_rate, effective_from, reason) VALUES (50, '2031-01-15', 'Motif valable')")
        && refuse($db, "INSERT INTO revenue_share_settings (artist_rate, effective_from, reason) VALUES (50, '2031-01-01', '')"));

    echo "\n=== B. Calcul du mois {$mois} ===\n";
    // Taux de test pour ce mois ancien, BORNE au mois suivant par un taux nul :
    // un taux vaut jusqu'au suivant, et sans borne il s'appliquerait a tous les
    // mois jusqu'a la premiere vraie decision. Les deux lignes restent en base.
    $db->prepare("INSERT INTO revenue_share_settings (artist_rate, effective_from, reason) VALUES (70, ?, ?)")->execute([$mois . '-01', 'Taux du test de repartition ' . $marque]);
    $db->prepare("INSERT INTO revenue_share_settings (artist_rate, effective_from, reason) VALUES (0, ?, ?)")->execute([substr($suivant, 0, 10), 'Fin du taux du test de repartition ' . $marque]);
    $unAn = date('Y-m-d H:i:s', strtotime($debut . ' +1 year'));
    $abonner($s1, 2000, $debut, $suivant);            // mensuel, tout le mois
    $abonner($s2, 20000, $debut, $unAn);              // annuel commence ce mois
    $abonner($s3, 2000, $debut, $suivant);            // artiste C, n'ecoute que lui-meme
    $abonner($s4, 2000, $debut, $suivant, 'refunded'); // rembourse : exclu
    $ecouter($s1, $artA, 3);
    $ecouter($s1, $artB, 1);
    $ecouter($s1, $artA, 2, 10);                      // trop courtes
    $ecouter($s2, $artB, 1);
    $ecouter($s3, $artC, 5);                          // soi-meme
    $ecouter($s4, $artA, 4);                          // abonnement rembourse
    $ecouter($s5, $artA, 6);                          // non abonne
    // STAT-03 : seules les ecoutes CERTIFIEES comptent. Les comptes d'essai
    // sont dates a des heures distinctes, sinon la creation en serie des
    // tests declencherait le signal « comptes crees en rafale ».
    foreach ([$s1, $s2, $s3, $s4, $s5] as $i => $u) {
        $db->exec("UPDATE users SET created_at = '" . $mois . "-01 00:00:00' - INTERVAL " . (10 + $i * 3) . " HOUR WHERE id = {$u}");
    }
    Certification::traiter(100000);
    verif('Ecoutes passees par la certification (courtes et de soi-meme exclues)',
        (int) $db->query('SELECT COUNT(*) FROM streams_certified WHERE track_id IN (' . implode(',', $titres) . ')')->fetchColumn() === 3 + 1 + 1 + 4 + 6,
        (string) $db->query('SELECT COUNT(*) FROM streams_certified WHERE track_id IN (' . implode(',', $titres) . ')')->fetchColumn());

    $c = RepartitionAbonnements::calculer($mois);
    $r2 = 20000 * $joursMois / $joursAnnee;
    $partBrute = 0.7 * (4000 + $r2);
    $montant = static function (int $artiste) use ($c): float {
        foreach ($c['lignes'] as $l) {
            if ((int) $l['artist_id'] === $artiste) {
                return (float) $l['montant'];
            }
        }
        return 0.0;
    };
    verif('Revenu : mensuels entiers + annuel au prorata des jours, rembourse exclu', abs($c['revenu'] - (4000 + $r2)) < 0.02, json_encode([$c['revenu'], 4000 + $r2]));
    verif('Part artistes = revenu x 70 %, au franc', $c['part'] === round($partBrute), json_encode([$c['part'], $partBrute]));
    verif('Abonnes payes comptes : 3 (le rembourse exclu)', $c['abonnes'] === 3, (string) $c['abonnes']);
    verif('Auditeurs retenus : 2 (l\'artiste qui s\'ecoute lui-meme exclu)', $c['auditeurs'] === 2, (string) $c['auditeurs']);
    verif('Ecoutes retenues : 5 (courtes, de soi, hors abonnement exclues)', $c['ecoutes'] === 5, (string) $c['ecoutes']);
    verif('Artiste A : 3/4 de la part de s1 = 1 050 FCFA', abs($montant($artA) - 1050) <= 1, (string) $montant($artA));
    verif('Artiste B : 1/4 de s1 + toute la part de s2', abs($montant($artB) - (350 + 0.7 * $r2)) <= 1, json_encode([$montant($artB), 350 + 0.7 * $r2]));
    verif('Artiste C : rien (seulement ses propres ecoutes)', $montant($artC) === 0.0);
    verif('Part de s3 (sans ecoute retenue) : non attribuee', abs($c['non_attribue'] - 1400) <= 1, (string) $c['non_attribue']);
    verif('Arrondi sans perte : lignes = reparti, reparti + non attribue = part',
        array_sum(array_column($c['lignes'], 'montant')) === $c['reparti'] && $c['reparti'] + $c['non_attribue'] === $c['part']);
    verif('Montants en francs entiers', array_filter($c['lignes'], fn($l) => floor($l['montant']) !== $l['montant']) === []);

    echo "\n=== C. Cloture ===\n";
    verif('Mois en cours : cloture refusee', !RepartitionAbonnements::cloturer(date('Y-m'), $finance)['succes']);
    $soldeAvant = Versements::solde($artA)['ajustements'];
    $r = RepartitionAbonnements::cloturer($mois, $finance);
    verif('Mois echu cloture', $r['succes'], $r['message']);
    verif('... le solde de l\'artiste A est credite', abs(Versements::solde($artA)['ajustements'] - $soldeAvant - $montant($artA)) < 0.01);
    $motif = (string) $db->query("SELECT reason FROM artist_adjustments WHERE artist_id = {$artB} ORDER BY id DESC LIMIT 1")->fetchColumn();
    verif('... ajustement motive (mois, ecoutes, taux)', str_contains($motif, 'Abonnements Premium') && str_contains($motif, '70 %'), $motif);
    verif('... chaque ligne reliee a son ajustement', (int) $db->query("SELECT COUNT(*) FROM subscription_distribution_lines l JOIN subscription_distributions d ON d.id = l.distribution_id WHERE d.period = '{$mois}' AND l.adjustment_id IS NULL AND l.amount > 0")->fetchColumn() === 0);
    verif('Seconde cloture : refusee', !RepartitionAbonnements::cloturer($mois, $finance)['succes']);
    $fige = RepartitionAbonnements::cloturee($mois);
    $ecouter($s1, $artB, 20);
    verif('Apres cloture, de nouvelles ecoutes ne changent rien au mois fige', $fige !== null && RepartitionAbonnements::cloturee($mois)['reparti'] === $c['reparti']);
    verif('Repartition cloturee : ni modifiable ni supprimable (base)',
        refuse($db, "UPDATE subscription_distributions SET pool = 0 WHERE period = '{$mois}'")
        && refuse($db, "DELETE FROM subscription_distribution_lines WHERE artist_id = {$artA}")
        && refuse($db, "UPDATE subscription_distribution_lines SET amount = 1 WHERE artist_id = {$artA}"));
    verif('Un mois reparti ne peut plus changer de taux', $mois < RepartitionAbonnements::premierMoisModifiable());

    echo "\n=== D. Ecrans ===\n";
    $cookies = (string) tempnam(sys_get_temp_dir(), 'rep');
    $http = static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE) ?: 0, (string) curl_exec($h), $h];
    };
    $h = curl_init($base . '/admin/remuneration.php');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true]);
    curl_exec($h);
    verif('Ecran ferme sans session', (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE) !== 200);
    $db->exec("DELETE FROM login_attempts WHERE identifier = 'admin@tchadok.td'");
    [, $page] = $http($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $http($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => 'admin@tchadok.td', 'password' => 'tchadok2026']);
    [, $page] = $http($base . '/admin/remuneration.php');
    verif('Console : pourcentage affiche (70 %) et part plateforme (30 %)', str_contains($page, 'data-taux-artistes>70 %') && str_contains($page, '30 %'));
    verif('... part artiste des ventes affichee (85 % sur un titre)', str_contains($page, '85 %'));
    [, $page] = $http($base . '/admin/remuneration.php?mois=' . $mois);
    verif('... mois cloture affiche fige, avec ses artistes', str_contains($page, 'Mois cloture') && str_contains($page, 'ZZREP A ' . $marque));
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    [, $page] = $http($base . '/admin/remuneration.php', ['csrf_token' => $m[1] ?? '', 'action' => 'taux', 'taux' => '80', 'mois' => $prochain, 'motif' => '']);
    verif('... changement sans motif refuse par l\'ecran', str_contains($page, 'Motif obligatoire') && RepartitionAbonnements::taux($prochain) === 70.0);
    [, $page] = $http($base . '/admin-dashboard.php');
    verif('Entree « Remuneration des artistes » dans la console', str_contains($page, '/admin/remuneration.php'));
    [, $page] = $http($base . '/admin/tarifs.php');
    verif('Grille tarifaire : les abonnements renvoient au pourcentage administre', str_contains($page, 'Part artistes : 70 %'));
    @unlink($cookies);
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $utilisateurs = implode(',', [$uA, $uB, $s1, $s2, $s3, $s4, $s5, $finance]);
    $db->exec("DELETE FROM streams WHERE user_id IN ({$utilisateurs})");
    if ($commandes) {
        $liste = implode(',', array_map('intval', $commandes));
        $db->exec("DELETE FROM subscriptions WHERE order_id IN ({$liste})");
        $db->exec("DELETE FROM orders WHERE id IN ({$liste})");
    }
    $db->exec('DELETE FROM tracks WHERE id IN (' . implode(',', $titres) . ')');
    // Ecoutes certifiees : supprimables seulement une fois leur titre disparu.
    $db->exec('DELETE FROM streams_certified WHERE track_id IN (' . implode(',', $titres) . ')');
    $db->exec("UPDATE artists SET is_active = 0, deleted_at = NOW() WHERE id IN ({$artA}, {$artB}, {$artC})");
    foreach (explode(',', $utilisateurs) as $u) {
        try {
            $db->exec("DELETE FROM users WHERE id = {$u}");
        } catch (Throwable $e) {
            $db->exec("UPDATE users SET is_active = 0 WHERE id = {$u}");
        }
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
