<?php
/**
 * Tests SEC-19 : roles, permissions et journal d'audit.
 *
 * Verifie les trois criteres du plan :
 *   - un moderateur de catalogue n'atteint pas les ecrans qui ne le regardent
 *     pas, avec un refus prononce par le serveur et non par un menu masque ;
 *   - un versement cree et execute par le meme compte est refuse ;
 *   - chaque action sensible laisse une trace attribuee a son auteur.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec19-roles-audit.php
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

function requete(string $url, ?array $post = null, ?string $cookies = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($cookies !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

function connecter(string $email, string $motDePasse = 'tchadok2026'): string
{
    $cookies = tempnam(sys_get_temp_dir(), 'sec19');
    $page = requete($GLOBALS['base'] . '/login.php', null, $cookies);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    requete($GLOBALS['base'] . '/login.php', ['csrf_token' => $jeton, 'email' => $email, 'password' => $motDePasse], $cookies);

    return $cookies;
}

$db = TchadokDatabase::getInstance()->getConnection();
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');

// Jeu d'essai : les comptes 931 (fan) et 932 (administrateur) de SEC-10.
foreach (explode(";\n", (string) file_get_contents(__DIR__ . '/sec10-nettoyage.sql')) as $instruction) {
    if (trim($instruction) !== '' && !str_starts_with(trim($instruction), '--')) {
        try { $db->exec($instruction); } catch (Throwable $e) { /* sans consequence */ }
    }
}
foreach (explode(";\n", (string) file_get_contents(__DIR__ . '/sec10-fixtures.sql')) as $instruction) {
    if (trim($instruction) !== '' && !str_starts_with(trim($instruction), '--')) {
        try { $db->exec($instruction); } catch (Throwable $e) { /* sans consequence */ }
    }
}

$cookies = [];

try {
    echo "\n=== A. Le modele est en place ===\n";
    $roles = $db->query('SELECT slug FROM roles ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    verif('Les sept roles existent', count($roles) === 7, implode(', ', $roles));
    foreach (['super_admin', 'admin_plateforme', 'responsable_finance', 'moderateur_catalogue', 'editorial', 'support', 'analyste'] as $attendu) {
        verif("Role present : {$attendu}", in_array($attendu, $roles, true));
    }
    $permissions = (int) $db->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
    verif('Le catalogue de permissions est peuple', $permissions >= 20, (string) $permissions);
    $duSuper = (int) $db->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.slug = 'super_admin'")->fetchColumn();
    verif('Le super-administrateur les a toutes', $duSuper === $permissions, "{$duSuper}/{$permissions}");

    echo "\n=== B. Qui peut quoi ===\n";
    // Comptes d'essai : on attribue des roles cibles a l'utilisateur 932.
    $db->exec('DELETE FROM user_roles WHERE user_id IN (931, 932)');
    Autorisations::oublierCache();
    Autorisations::attribuer(932, 'moderateur_catalogue');
    Autorisations::oublierCache();

    verif('Un moderateur peut moderer', Autorisations::peut('catalogue.moderer', 932));
    verif('... mais pas toucher a la finance', !Autorisations::peut('finance.versement.executer', 932));
    verif('... ni changer un tarif', !Autorisations::peut('tarif.modifier', 932));
    verif('... ni lire le journal d\'audit', !Autorisations::peut('journal.lire', 932));
    verif('... ni gerer les roles', !Autorisations::peut('role.gerer', 932));

    Autorisations::attribuer(932, 'analyste');
    Autorisations::oublierCache();
    verif('Les roles se cumulent', Autorisations::peut('statistique.lire', 932) && Autorisations::peut('catalogue.moderer', 932));

    verif('Un compte sans role ne peut rien', !Autorisations::peut('admin.acces', 931));
    verif('... et n\'est pas administrateur', !Autorisations::estAdministrateur(931));
    verif('Un compte avec role est administrateur', Autorisations::estAdministrateur(932));

    verif('Le super-administrateur peut tout', Autorisations::peut('finance.versement.executer', 1) && Autorisations::peut('journal.lire', 1));
    verif('... y compris une permission inconnue du catalogue', Autorisations::peut('permission.ajoutee.demain', 1));
    verif('Personne d\'autre n\'herite d\'une permission inconnue', !Autorisations::peut('permission.ajoutee.demain', 932));

    echo "\n=== C. Le refus vient du serveur ===\n";
    $cookies['moderateur'] = connecter('admin10@essai.local');
    $r = requete($base . '/admin-dashboard.php', null, $cookies['moderateur']);
    verif('Le moderateur entre dans la console', $r['code'] === 200, (string) $r['code']);
    $r = requete($base . '/admin-add-song.php', null, $cookies['moderateur']);
    verif('Ecran d\'edition du catalogue : 403', $r['code'] === 403, (string) $r['code']);
    verif('... avec un message explicite', str_contains($r['corps'], 'Acces refuse'));
    $r = requete($base . '/admin/journal.php', null, $cookies['moderateur']);
    verif('Journal d\'audit : 403', $r['code'] === 403, (string) $r['code']);
    $r = requete($base . '/admin-blog.php', null, $cookies['moderateur']);
    verif('Ecrans editoriaux : 403', $r['code'] === 403, (string) $r['code']);

    $cookies['super'] = connecter('admin@tchadok.td');
    foreach (['/admin-dashboard.php', '/admin/journal.php', '/admin-add-song.php', '/admin-blog.php'] as $chemin) {
        $r = requete($base . $chemin, null, $cookies['super']);
        verif("Le super-administrateur atteint {$chemin}", $r['code'] === 200, (string) $r['code']);
    }

    $r = requete($base . '/admin/journal.php');
    verif('Sans session : pas de journal', $r['code'] !== 200, (string) $r['code']);

    echo "\n=== D. Separation des pouvoirs sur l'argent ===\n";
    verif('Le meme compte ne peut pas creer et executer un versement', !Autorisations::verifierSeparationVersement(7, 7));
    verif('Deux comptes distincts : autorise', Autorisations::verifierSeparationVersement(7, 9));
    verif('Un createur inconnu bloque l\'execution', !Autorisations::verifierSeparationVersement(null, 9));
    $duFinance = $db->query("SELECT p.slug FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = 'responsable_finance'")->fetchAll(PDO::FETCH_COLUMN);
    verif('Le responsable finance a les deux permissions...', in_array('finance.versement.creer', $duFinance, true) && in_array('finance.versement.executer', $duFinance, true));
    verif('... et reste soumis a la regle des deux comptes', !Autorisations::verifierSeparationVersement(3, 3));
    verif('Le responsable finance ne touche pas au catalogue', !in_array('catalogue.editer', $duFinance, true));

    echo "\n=== E. Le journal enregistre ===\n";
    $db->exec('DELETE FROM audit_log');

    $cookies['echec'] = tempnam(sys_get_temp_dir(), 'sec19e');
    $page = requete($base . '/login.php', null, $cookies['echec']);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    requete($base . '/login.php', ['csrf_token' => $jeton, 'email' => 'admin10@essai.local', 'password' => 'faux'], $cookies['echec']);
    $echecs = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'admin.connexion.echec'")->fetchColumn();
    verif('Un echec de connexion administrateur est trace', $echecs === 1, (string) $echecs);

    $cookies['moderateur2'] = connecter('admin10@essai.local');
    $connexions = $db->query("SELECT actor_id, actor_role FROM audit_log WHERE action = 'admin.connexion' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('La connexion est tracee', $connexions !== false);
    verif('... avec son auteur', (int) ($connexions['actor_id'] ?? 0) === 932, (string) ($connexions['actor_id'] ?? ''));
    verif('... et ses roles', str_contains((string) ($connexions['actor_role'] ?? ''), 'moderateur_catalogue'), (string) ($connexions['actor_role'] ?? ''));

    requete($base . '/admin/journal.php', null, $cookies['moderateur2']);
    $refus = $db->query("SELECT target_id FROM audit_log WHERE action = 'autorisation.refus' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('Un refus d\'acces est trace', $refus !== false);
    verif('... avec la permission refusee', ($refus['target_id'] ?? '') === 'journal.lire', (string) ($refus['target_id'] ?? ''));

    Autorisations::attribuer(931, 'support', 1);
    $attribution = $db->query("SELECT actor_id, target_id, after_state FROM audit_log WHERE action = 'role.attribue' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('Une attribution de role est tracee', $attribution !== false);
    verif('... avec le role concerne', str_contains((string) ($attribution['after_state'] ?? ''), 'support'));
    Autorisations::retirer(931, 'support', 1);
    $retrait = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'role.retire'")->fetchColumn();
    verif('Un retrait de role est trace', $retrait === 1, (string) $retrait);

    echo "\n=== F. Le journal ne ment pas ===\n";
    JournalAudit::enregistrer('compte.modifie', [
        'cible_type' => 'utilisateur',
        'cible_id'   => 931,
        'avant'      => ['email' => 'avant@essai.local', 'password_hash' => 'tres-secret', 'reset_token' => 'abc'],
        'apres'      => ['email' => 'apres@essai.local'],
        'acteur'     => 1,
    ]);
    $ligne = $db->query("SELECT before_state, ip_address, user_agent FROM audit_log WHERE action = 'compte.modifie' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('Les valeurs sensibles sont masquees', str_contains((string) $ligne['before_state'], '(masque)'), (string) $ligne['before_state']);
    verif('... et le hash n\'apparait pas', !str_contains((string) $ligne['before_state'], 'tres-secret'));
    verif('L\'adresse est enregistree', !empty($ligne['ip_address']));

    $ecritures = [];
    foreach (['includes/audit.php', 'admin/journal.php'] as $fichier) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        if (preg_match('/(UPDATE|DELETE)\s+(FROM\s+)?`?audit_log/i', $contenu)) {
            $ecritures[] = $fichier;
        }
    }
    verif('Aucun code ne modifie ni ne supprime une entree du journal', $ecritures === [], implode(', ', $ecritures));

    echo "\n=== G. Ecran de consultation ===\n";
    $r = requete($base . '/admin/journal.php', null, $cookies['super']);
    verif('Le journal s\'affiche pour le super-administrateur', $r['code'] === 200, (string) $r['code']);
    verif('Les entrees y figurent', str_contains($r['corps'], 'Connexion a l\'administration') || str_contains($r['corps'], 'Role attribue'));
    verif('Le filtre par action est propose', str_contains($r['corps'], 'name="action"'));
    $r = requete($base . '/admin/journal.php?action=admin.connexion', null, $cookies['super']);
    verif('Le filtrage fonctionne', $r['code'] === 200 && !str_contains($r['corps'], 'Aucune entree'), (string) $r['code']);

    echo "\n=== H. Compatibilite ===\n";
    verif('isAdmin() suit desormais les roles', Autorisations::estAdministrateur(932) && !Autorisations::estAdministrateur(931));
    $creation = (string) file_get_contents($racine . '/scripts/create-admin.php');
    verif('create-admin.php attribue un role', str_contains($creation, 'user_roles'));
    verif('... et echoue si les migrations manquent', str_contains($creation, 'migrate.php up'));
    $fixtures = (string) file_get_contents(__DIR__ . '/sec10-fixtures.sql');
    verif('Le jeu d\'essai attribue un role', str_contains($fixtures, 'user_roles'));
} finally {
    foreach ($cookies as $fichier) {
        @unlink($fichier);
    }
    $db->exec('DELETE FROM audit_log');
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');
    foreach (explode(";\n", (string) file_get_contents(__DIR__ . '/sec10-nettoyage.sql')) as $instruction) {
        // Les lignes de commentaire sont retirees AVANT le test : une
        // instruction precedee d'un commentaire serait sinon ignoree.
        $instruction = preg_replace('/^\s*--.*$/m', '', $instruction);
        if (trim($instruction) !== '') {
            try { $db->exec($instruction); } catch (Throwable $e) { /* sans consequence */ }
        }
    }
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
