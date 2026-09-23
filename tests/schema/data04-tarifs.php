<?php
/**
 * Tests DATA-04 : grille tarifaire administree.
 *
 * Criteres du plan :
 *   - aucun prix n'est ecrit en dur dans le code ;
 *   - un changement de tarif en base est visible sans deploiement ;
 *   - un prix hors grille soumis par un artiste est refuse.
 *
 * Les deux derniers criteres se verifient sur le site en fonctionnement : on
 * change le tarif en base et on relit la page publique, puis on soumet un prix
 * hors grille par le VRAI formulaire artiste, avec une session ouverte. Un test
 * qui se contenterait d'appeler la fonction de validation ne dirait pas si le
 * formulaire l'appelle.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data04-tarifs.php
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

function jeton(string $html): string
{
    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}

/** Texte de la page, balises et entites resorbees : les prix s'y lisent. */
function texte(string $html): string
{
    $sans = preg_replace('/<[^>]+>/', ' ', $html) ?? '';

    return (string) preg_replace('/\s+/u', ' ', html_entity_decode($sans, ENT_QUOTES, 'UTF-8'));
}

$db = TchadokDatabase::getInstance()->getConnection();
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

$grilleInitiale = $db->query('SELECT * FROM pricing_rules ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');
$db->exec('DELETE FROM tracks WHERE id BETWEEN 9401 AND 9499');
$db->exec('DELETE FROM artists WHERE id = 914');
$db->exec('DELETE FROM users WHERE id = 914');

try {
    echo "\n=== A. La grille existe en base ===\n";
    $colonnes = [];
    foreach ($db->query('SHOW COLUMNS FROM pricing_rules') as $ligne) {
        $colonnes[$ligne['Field']] = $ligne;
    }
    verif('La table `pricing_rules` existe', $colonnes !== []);
    foreach (['scope', 'format', 'currency', 'min_price', 'max_price', 'suggested', 'commission_rate', 'active_from', 'active_to'] as $champ) {
        verif("... colonne `{$champ}`", isset($colonnes[$champ]));
    }
    verif('La portee couvre titre, sortie et abonnement',
        isset($colonnes['scope'])
        && str_contains((string) $colonnes['scope']['Type'], "'track'")
        && str_contains((string) $colonnes['scope']['Type'], "'release'")
        && str_contains((string) $colonnes['scope']['Type'], "'subscription'"));
    verif('Un titre a l\'unite n\'a pas de format', isset($colonnes['format']) && $colonnes['format']['Null'] === 'YES');
    verif('La modification est attribuee', isset($colonnes['updated_by']));

    $regles = (int) $db->query('SELECT COUNT(*) FROM pricing_rules')->fetchColumn();
    verif('Les huit regles de depart sont enregistrees', $regles === 8, (string) $regles);

    echo "\n=== B. Plus aucun prix ecrit dans le code ===\n";
    verif('PREMIUM_MONTHLY n\'existe plus', !defined('PREMIUM_MONTHLY'));
    verif('PREMIUM_ANNUAL n\'existe plus', !defined('PREMIUM_ANNUAL'));
    verif('DEFAULT_COMMISSION_RATE n\'existe plus', !defined('DEFAULT_COMMISSION_RATE'));
    verif('Les bornes de forme de SEC-17 demeurent', defined('PRIX_MINIMUM') && defined('PRIX_PAS'));

    $constantes = $source('config/constants.php');
    verif('config/constants.php ne fixe plus le tarif Premium',
        !preg_match("/define\('PREMIUM_(MONTHLY|ANNUAL)'/", $constantes));
    verif('... ni le taux de commission',
        !preg_match("/define\('DEFAULT_COMMISSION_RATE'/", $constantes));

    $paiement = $source('config/payment.php');
    verif('config/payment.php ne declare plus de commission',
        !preg_match("/'commission_rate'\s*=>\s*[\d.]+/", $paiement));
    verif('... et delegue le calcul a la grille',
        str_contains($paiement, 'Tarifs::commission'));

    foreach (['premium.php', 'premium-payment.php'] as $fichier) {
        $contenu = $source($fichier);
        verif("{$fichier} lit le tarif dans la grille", str_contains($contenu, 'Tarifs::abonnement'));
        verif("{$fichier} n'ecrit plus de montant",
            !preg_match("/'price'\s*=>\s*\d+/", $contenu),
            $fichier);
    }
    verif('premium.php calcule l\'economie annuelle', str_contains($source('premium.php'), 'Tarifs::economieAnnuelle'));

    $depot = $source('upload.php');
    verif('upload.php n\'annonce plus de bornes inventees',
        !preg_match('/min="500"\s+max="10000"/', $depot) && !str_contains($depot, 'Prix entre 500 et 10 000 FCFA'));
    verif('... et valide le prix (il se contentait d\'un transtypage)',
        str_contains($depot, 'Tarifs::valider'));

    foreach (['artist-add-song.php', 'admin-add-song.php', 'artist-add-album.php', 'admin-add-album.php'] as $fichier) {
        verif("{$fichier} valide contre la grille", str_contains($source($fichier), 'Tarifs::valider'));
        verif("{$fichier} affiche les bornes reelles", str_contains($source($fichier), 'Tarifs::indication'));
    }

    echo "\n=== C. Le tarif Premium, une seule valeur ===\n";
    verif('Mensuel : 2 000 FCFA', Tarifs::abonnement('monthly') === 2000.0, (string) Tarifs::abonnement('monthly'));
    verif('Annuel : 20 000 FCFA', Tarifs::abonnement('yearly') === 20000.0, (string) Tarifs::abonnement('yearly'));
    verif('L\'annuel vaut dix mois', Tarifs::economieAnnuelle() === 4000.0, (string) Tarifs::economieAnnuelle());

    $page = requete($base . '/premium.php');
    $lisible = texte($page['corps']);
    verif('La page Premium repond', $page['code'] === 200, (string) $page['code']);
    verif('... et affiche 2 000 FCFA', str_contains($lisible, '2 000 FCFA'));
    verif('... et 20 000 FCFA', str_contains($lisible, '20 000 FCFA'));
    verif('... et l\'economie calculee', str_contains($lisible, 'Économisez 4 000 FCFA'));
    verif('Les anciens montants ont disparu',
        !str_contains($lisible, '2 500 FCFA') && !str_contains($lisible, '25 000 FCFA'));

    echo "\n=== D. Un changement en base, sans deploiement ===\n";
    $db->exec("UPDATE pricing_rules SET suggested = 3000, min_price = 3000, max_price = 3000 WHERE scope = 'subscription' AND format = 'premium_monthly'");
    $lisible = texte(requete($base . '/premium.php')['corps']);
    verif('Le nouveau tarif s\'affiche aussitot', str_contains($lisible, '3 000 FCFA'), 'la page sert encore l\'ancien prix');
    verif('... et l\'economie suit', str_contains($lisible, 'Économisez 16 000 FCFA'), 'economie non recalculee');

    $db->exec("UPDATE pricing_rules SET suggested = 2000, min_price = 2000, max_price = 2000 WHERE scope = 'subscription' AND format = 'premium_monthly'");
    Tarifs::oublier();
    verif('Retour a la valeur arbitree', Tarifs::abonnement('monthly') === 2000.0);

    echo "\n=== E. Les bornes s'appliquent ===\n";
    verif('Gratuit reste permis', Tarifs::valider(0, 'track')['valide'] === true);
    verif('Un prix negatif est refuse', Tarifs::valider(-500, 'track')['valide'] === false);
    verif('Un prix hors grille de 50 est refuse', Tarifs::valider(175, 'track')['valide'] === false);
    verif('Sous le plancher du titre : refuse', Tarifs::valider(50, 'track')['valide'] === false);
    $refus = Tarifs::valider(50, 'track');
    verif('... avec le montant plancher annonce', str_contains($refus['message'], '100 FCFA'), $refus['message']);
    verif('... et la raison du plancher', str_contains($refus['message'], 'valeur du catalogue'), $refus['message']);
    verif('Au plancher : accepte', Tarifs::valider(100, 'track')['valide'] === true);
    verif('Au-dela du plafond du titre : refuse', Tarifs::valider(2000, 'track')['valide'] === false);
    verif('Au plafond : accepte', Tarifs::valider(1000, 'track')['valide'] === true);

    verif('Album sous son plancher : refuse', Tarifs::valider(500, 'release', 'album')['valide'] === false);
    verif('Album au plancher : accepte', Tarifs::valider(750, 'release', 'album')['valide'] === true);
    verif('Album au-dela de son plafond : refuse', Tarifs::valider(6500, 'release', 'album')['valide'] === false);
    verif('Le single a des bornes propres',
        Tarifs::valider(150, 'release', 'single')['valide'] === true
        && Tarifs::valider(100, 'release', 'single')['valide'] === false);
    // Un format inconnu ne doit pas echapper aux bornes : il herite de
    // l'enveloppe des sorties -- plancher le plus bas, plafond le plus haut.
    $enveloppe = Tarifs::regle('release', 'mixtape');
    verif('Un format inconnu reste borne', $enveloppe !== null);
    verif('... par le plancher le plus bas des sorties', ($enveloppe['min'] ?? 0.0) === 150.0, (string) ($enveloppe['min'] ?? 0));
    verif('... et le plafond le plus haut', ($enveloppe['max'] ?? 0.0) === 8000.0, (string) ($enveloppe['max'] ?? 0));
    verif('Un prix sous cette enveloppe est refuse', Tarifs::valider(100, 'release', 'mixtape')['valide'] === false);

    echo "\n=== F. Refus par le vrai formulaire artiste ===\n";
    $motDePasse = 'Tarifs-2026!aa';
    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (914, ?, ?, ?, ?, ?, 1, 1)'
    )->execute(['essai14_artiste', 'artiste14@essai.local', password_hash($motDePasse, PASSWORD_BCRYPT), 'Essai', 'Tarifs']);
    $db->exec("INSERT INTO artists (id, user_id, stage_name, slug, is_active) VALUES (914, 914, 'Essai Tarifs', 'essai-tarifs', 1)");

    $cookies = tempnam(sys_get_temp_dir(), 'data04');
    $page = requete($base . '/login.php', null, $cookies);
    requete($base . '/login.php', [
        'csrf_token' => jeton($page['corps']),
        'email'      => 'artiste14@essai.local',
        'password'   => $motDePasse,
    ], $cookies);
    $formulaire = requete($base . '/artist-add-song.php', null, $cookies);
    verif('L\'artiste d\'essai atteint son formulaire', $formulaire['code'] === 200, (string) $formulaire['code']);
    verif('... ou les bornes de la grille sont affichees',
        str_contains(texte($formulaire['corps']), 'Entre 100 et 1 000 FCFA'),
        substr(texte($formulaire['corps']), 0, 120));

    $envoi = requete($base . '/artist-add-song.php', [
        'csrf_token' => jeton($formulaire['corps']),
        'title'      => 'Titre hors grille',
        'price'      => '50',
        'duration'   => '180',
    ], $cookies);
    $reponse = texte($envoi['corps']);
    verif('Un prix sous le plancher est refuse par le formulaire', str_contains($reponse, 'Le prix minimum est de 100 FCFA'), substr($reponse, 0, 200));
    verif('... et rien n\'est enregistre',
        (int) $db->query('SELECT COUNT(*) FROM tracks WHERE artist_id = 914')->fetchColumn() === 0);

    $envoi = requete($base . '/artist-add-song.php', [
        'csrf_token' => jeton($formulaire['corps']),
        'title'      => 'Titre trop cher',
        'price'      => '9000',
        'duration'   => '180',
    ], $cookies);
    verif('Un prix au-dessus du plafond est refuse aussi',
        str_contains(texte($envoi['corps']), 'Le prix maximum est de 1 000 FCFA'));

    // Un prix dans la grille franchit le controle de prix : l'erreur suivante
    // porte sur le fichier audio, preuve que le prix est passe.
    $envoi = requete($base . '/artist-add-song.php', [
        'csrf_token' => jeton($formulaire['corps']),
        'title'      => 'Titre dans la grille',
        'price'      => '300',
        'duration'   => '180',
    ], $cookies);
    $reponse = texte($envoi['corps']);
    verif('Un prix dans la grille franchit le controle',
        !str_contains($reponse, 'Le prix minimum') && !str_contains($reponse, 'Le prix maximum'),
        substr($reponse, 0, 200));
    @unlink($cookies);

    echo "\n=== G. Commission ===\n";
    verif('Le taux du titre est celui de la grille', Tarifs::tauxCommission('track') === 15.0);
    verif('Commission de 15 % sur 1 000 FCFA', Tarifs::commission(1000.0) === 150.0, (string) Tarifs::commission(1000.0));
    verif('L\'artiste touche le reste', Tarifs::partArtiste(1000.0) === 850.0, (string) Tarifs::partArtiste(1000.0));
    verif('Une compilation se commissionne a 20 %', Tarifs::tauxCommission('release', 'compilation') === 20.0);
    verif('Un abonnement ne porte pas de commission artiste', Tarifs::tauxCommission('subscription', 'premium_monthly') === 0.0);
    verif('La commission est arrondie au franc', Tarifs::commission(333.0) === 50.0, (string) Tarifs::commission(333.0));

    echo "\n=== H. L'ecran d'administration ===\n";
    $ecran = $source('admin/tarifs.php');
    verif('L\'ecran exige la permission tarif.modifier', str_contains($ecran, "Autorisations::exiger('tarif.modifier')"));
    verif('... protege le formulaire par un jeton', str_contains($ecran, 'csrfField()'));
    verif('... et passe par Tarifs::enregistrer', str_contains($ecran, 'Tarifs::enregistrer'));
    verif('Un visiteur anonyme est renvoye a la connexion',
        requete($base . '/admin/tarifs.php')['code'] === 302);
    verif('La console propose l\'entree sous condition de permission',
        str_contains($source('admin-dashboard.php'), "Autorisations::peut('tarif.modifier')"));

    $idTitre = (int) $db->query("SELECT id FROM pricing_rules WHERE scope = 'track' LIMIT 1")->fetchColumn();
    verif('Un plafond sous le plancher est refuse',
        Tarifs::enregistrer($idTitre, ['min_price' => 500, 'max_price' => 200, 'suggested' => 300, 'commission_rate' => 15])['succes'] === false);
    verif('Un suggere hors bornes est refuse',
        Tarifs::enregistrer($idTitre, ['min_price' => 100, 'max_price' => 1000, 'suggested' => 2000, 'commission_rate' => 15])['succes'] === false);
    verif('Une commission de 80 % est refusee',
        Tarifs::enregistrer($idTitre, ['min_price' => 100, 'max_price' => 1000, 'suggested' => 300, 'commission_rate' => 80])['succes'] === false);
    verif('Une regle inexistante est refusee',
        Tarifs::enregistrer(99999, ['min_price' => 100, 'max_price' => 1000, 'suggested' => 300, 'commission_rate' => 15])['succes'] === false);

    $db->exec("DELETE FROM audit_log WHERE action = 'tarif.modifie' AND target_id = '{$idTitre}'");
    $resultat = Tarifs::enregistrer($idTitre, ['min_price' => 200, 'max_price' => 900, 'suggested' => 400, 'commission_rate' => 12], 914);
    verif('Une modification coherente est acceptee', $resultat['succes'] === true, implode(' ', $resultat['erreurs']));
    verif('... et s\'applique immediatement (cache vide)', Tarifs::valider(150, 'track')['valide'] === false);
    verif('... le nouveau plancher est accepte', Tarifs::valider(200, 'track')['valide'] === true);

    $trace = $db->query(
        "SELECT action, target_type, before_state, after_state FROM audit_log
         WHERE action = 'tarif.modifie' AND target_id = '{$idTitre}' ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    verif('Le changement de tarif est journalise', is_array($trace) && $trace['target_type'] === 'tarif');
    verif('... avec la valeur precedente', is_array($trace) && str_contains((string) $trace['before_state'], '100'));
    verif('... et la nouvelle', is_array($trace) && str_contains((string) $trace['after_state'], '200'));

    echo "\n=== I. Mise en cache et panne ===\n";
    Tarifs::oublier();
    $premiere = Tarifs::grille();
    $db->exec("UPDATE pricing_rules SET suggested = 777 WHERE id = {$idTitre}");
    verif('La grille est lue une seule fois par requete', Tarifs::grille() === $premiere);
    Tarifs::oublier();
    verif('... et relue apres oubli', Tarifs::grille() !== $premiere);
    verif('Des valeurs de secours existent si la base manque',
        str_contains($source('includes/tarifs.php'), 'SECOURS'));

    echo "\n=== J. Migration ===\n";
    $migration = $source('database/migrations/2026_09_23_0008_data04_tarifs.sql');
    verif('La migration cree la table', str_contains($migration, 'CREATE TABLE IF NOT EXISTS `pricing_rules`'));
    verif('... sait revenir en arriere', str_contains($migration, '-- DOWN'));
    verif('... et n\'ecrase pas une grille deja ajustee',
        str_contains($migration, 'COUNT(*) FROM `pricing_rules`) > 0'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" status 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('DATA-04 est appliquee', preg_match('/appliquee\s+\S*data04_tarifs/', $texte) === 1, $texte);
    verif('... et le schema est a jour', str_contains($texte, 'Schema a jour'), $texte);
} finally {
    // La grille est un reglage de production : on la remet exactement en etat.
    $db->exec('DELETE FROM tracks WHERE artist_id = 914');
    $db->exec('DELETE FROM artists WHERE id = 914');
    $db->exec('DELETE FROM users WHERE id = 914');
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');

    $remise = $db->prepare(
        'UPDATE pricing_rules
         SET min_price = ?, max_price = ?, suggested = ?, commission_rate = ?, updated_by = ?
         WHERE id = ?'
    );
    foreach ($grilleInitiale as $ligne) {
        $remise->execute([
            $ligne['min_price'], $ligne['max_price'], $ligne['suggested'],
            $ligne['commission_rate'], $ligne['updated_by'], $ligne['id'],
        ]);
    }
    $db->exec("DELETE FROM audit_log WHERE action = 'tarif.modifie' AND actor_id = 914");
    Tarifs::oublier();
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
