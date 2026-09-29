<?php
/**
 * Tests DATA-05 : les tables commerciales.
 *
 * Criteres du plan :
 *   - les migrations creent les six tables avec leurs contraintes ;
 *   - une seconde commande portant le meme `gateway_ref` est rejetee par la
 *     BASE, pas seulement par le code ;
 *   - `payment_events` n'accepte ni UPDATE ni DELETE depuis l'application.
 *
 * Les deux derniers criteres se verifient en essayant reellement l'ecriture
 * interdite et en constatant le refus du serveur. Un test qui se contenterait
 * de lire la definition de la table ne dirait pas si la contrainte mord.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data05-commerce.php
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

/** Execute une ecriture attendue en echec, et renvoie le message du serveur. */
function refus(PDO $db, string $sql, array $parametres = []): string
{
    try {
        $db->prepare($sql)->execute($parametres);
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

$db = TchadokDatabase::getInstance()->getConnection();
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

$nettoyer = static function () use ($db): void {
    $db->exec('DELETE FROM entitlements WHERE user_id BETWEEN 915 AND 916');
    // Suppressions restreintes aux comptes d'essai (915, 916) : une plage
    // d'identifiants seule peut atteindre de vraies commandes (voir data06).
    $db->exec('DELETE p FROM payment_intents p JOIN orders o ON o.id = p.order_id WHERE p.order_id BETWEEN 9501 AND 9599 AND o.user_id BETWEEN 915 AND 916');
    // payment_events refuse le DELETE : les lignes d'essai y restent, ce qui est
    // exactement ce qu'on veut d'un journal de preuve.
    $db->exec('DELETE i FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.order_id BETWEEN 9501 AND 9599 AND o.user_id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM payouts WHERE artist_id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM orders WHERE id BETWEEN 9501 AND 9599 AND user_id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM tracks WHERE id BETWEEN 9501 AND 9599 AND artist_id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM releases WHERE id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM artists WHERE id BETWEEN 915 AND 916');
    // STAT-05 : les agregats du jeu d'essai partent avec lui.
    $db->exec('DELETE FROM daily_rollups WHERE track_id BETWEEN 9501 AND 9599 OR artist_id BETWEEN 915 AND 916');
    $db->exec('DELETE FROM users WHERE id BETWEEN 915 AND 916');
};

$nettoyer();

try {
    echo "\n=== A. Les six tables et leurs contraintes ===\n";
    $tables = [];
    foreach ($db->query(
        "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = DATABASE()"
    ) as $ligne) {
        $tables[$ligne['table_name']] = $ligne['table_type'];
    }

    foreach (['orders', 'order_items', 'entitlements', 'payouts', 'payment_intents', 'payment_events'] as $table) {
        verif("La table `{$table}` existe", ($tables[$table] ?? '') === 'BASE TABLE', $tables[$table] ?? 'absente');
    }
    verif('Le compteur de facture existe', ($tables['invoice_counters'] ?? '') === 'BASE TABLE');
    verif('`purchases` a disparu du schema', !isset($tables['purchases']), $tables['purchases'] ?? 'absente');

    $index = static function (string $table) use ($db): array {
        $uniques = [];
        foreach ($db->query("SHOW INDEX FROM `{$table}`") as $ligne) {
            if ((int) $ligne['Non_unique'] === 0) {
                $uniques[$ligne['Key_name']][] = $ligne['Column_name'];
            }
        }

        return $uniques;
    };

    $uniquesCommandes = $index('orders');
    verif('La reference de commande est unique', ($uniquesCommandes['reference'] ?? []) === ['reference']);
    verif('La reference operateur est unique', ($uniquesCommandes['gateway_ref'] ?? []) === ['gateway_ref']);
    verif('Le numero de facture est unique', ($uniquesCommandes['invoice_number'] ?? []) === ['invoice_number']);
    verif('Une tentative est unique par commande',
        in_array(['order_id', 'attempt'], array_values($index('payment_intents')), true));
    verif('La reference operateur d\'une tentative est unique',
        in_array(['gateway_ref'], array_values($index('payment_intents')), true));
    verif('Un droit ne s\'accorde qu\'une fois par voie',
        in_array(['user_id', 'item_type', 'item_id', 'source'], array_values($index('entitlements')), true));
    // LOT 8 (migration 0020) : versement a la demande, une demande refusee peut
    // etre refaite sur la meme periode. L'unicite par periode est remplacee
    // par « une seule demande en cours par artiste » (Versements::demander) et
    // par les cles d'idempotence de `payout_attempts`.
    $periode = [];
    foreach ($db->query("SHOW INDEX FROM `payouts` WHERE Key_name = 'periode'") as $ligne) {
        $periode[] = $ligne['Column_name'];
    }
    verif('Les versements restent indexes par artiste et periode', $periode === ['artist_id', 'period_start', 'period_end'], implode(',', $periode));
    verif('... sans unicite par periode (une demande refusee se refait)',
        !in_array(['artist_id', 'period_start', 'period_end'], array_values($index('payouts')), true));

    $colonnes = static function (string $table) use ($db): array {
        $liste = [];
        foreach ($db->query("SHOW COLUMNS FROM `{$table}`") as $ligne) {
            $liste[$ligne['Field']] = $ligne;
        }

        return $liste;
    };

    $lignesCommande = $colonnes('order_items');
    verif('Le prix est porte par la ligne de commande', isset($lignesCommande['unit_price']));
    verif('Le taux de commission y est fige aussi', isset($lignesCommande['commission_rate']));
    verif('La part nette de l\'artiste est enregistree', isset($lignesCommande['artist_net']));

    $versements = $colonnes('payouts');
    verif('Le preparateur du versement est identifie', isset($versements['created_by']));
    verif('L\'approbateur aussi', isset($versements['approved_by']));

    $evenements = $colonnes('payment_events');
    verif('Le corps brut de l\'echange est conserve', isset($evenements['payload']));
    verif('La signature et sa validite le sont aussi',
        isset($evenements['signature']) && isset($evenements['signature_valid']));
    verif('Le sens de l\'echange est qualifie',
        isset($evenements['direction']) && str_contains((string) $evenements['direction']['Type'], "'callback'"));

    echo "\n=== B. Jeu d'essai ===\n";
    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (915, ?, ?, ?, ?, ?, 1, 1), (916, ?, ?, ?, ?, ?, 1, 1)'
    )->execute([
        'essai15_acheteur', 'acheteur15@essai.local', password_hash('essai', PASSWORD_BCRYPT), 'Essai', 'Acheteur',
        'essai16_agent', 'agent16@essai.local', password_hash('essai', PASSWORD_BCRYPT), 'Essai', 'Agent',
    ]);
    $db->exec("INSERT INTO artists (id, user_id, stage_name, slug, is_active, total_sales) VALUES (915, 916, 'Essai Commerce', 'essai-commerce', 1, 0)");
    $db->exec("INSERT INTO releases (id, artist_id, title, slug, format, price_bundle, is_free, status, total_sales)
               VALUES (915, 915, 'Sortie de vente', 'sortie-de-vente', 'album', 3000, 0, 'approved', 0)");
    $db->exec("INSERT INTO tracks (id, album_id, release_id, slug, artist_id, title, audio_file, duration, price, is_free, status, total_sales)
               VALUES (9501, 915, 915, 'titre-de-vente', 915, 'Titre de vente', 'assets/audio/essai.mp3', 200, 500, 0, 'approved', 0)");
    verif('Le jeu d\'essai est en place',
        (int) $db->query('SELECT COUNT(*) FROM tracks WHERE id = 9501')->fetchColumn() === 1);

    echo "\n=== C. Le prix est fige a la vente ===\n";
    $db->exec("INSERT INTO orders (id, reference, user_id, status) VALUES (9501, 'TCHK-2026-DATA0501', 915, 'cart')");
    $ajout = Commandes::ajouterArticle(9501, 'track', 9501, 500.0, 915, 'Titre de vente');
    verif('L\'article entre au panier', $ajout['succes'] === true, implode(' ', $ajout['erreurs']));

    $ligne = $db->query('SELECT * FROM order_items WHERE order_id = 9501')->fetch(PDO::FETCH_ASSOC);
    verif('Le prix est recopie sur la ligne', (float) $ligne['unit_price'] === 500.0);
    verif('Le taux de commission du jour est recopie', (float) $ligne['commission_rate'] === 15.0, (string) $ligne['commission_rate']);
    verif('La commission est calculee', (float) $ligne['commission'] === 75.0, (string) $ligne['commission']);
    verif('La part artiste aussi', (float) $ligne['artist_net'] === 425.0, (string) $ligne['artist_net']);

    $commande = $db->query('SELECT * FROM orders WHERE id = 9501')->fetch(PDO::FETCH_ASSOC);
    verif('Les totaux de la commande suivent', (float) $commande['total'] === 500.0, (string) $commande['total']);

    // LE POINT DE LA MANOEUVRE : on change le tarif, l'historique ne bouge pas.
    $idTitre = (int) $db->query("SELECT id FROM pricing_rules WHERE scope = 'track' LIMIT 1")->fetchColumn();
    $tauxInitial = $db->query("SELECT commission_rate FROM pricing_rules WHERE id = {$idTitre}")->fetchColumn();
    $db->exec("UPDATE pricing_rules SET commission_rate = 40 WHERE id = {$idTitre}");
    Tarifs::oublier();

    $ligne = $db->query('SELECT * FROM order_items WHERE order_id = 9501')->fetch(PDO::FETCH_ASSOC);
    verif('Un changement de tarif ne reecrit pas la vente', (float) $ligne['commission_rate'] === 15.0, (string) $ligne['commission_rate']);
    verif('... ni la part promise a l\'artiste', (float) $ligne['artist_net'] === 425.0);

    $db->exec("UPDATE pricing_rules SET commission_rate = {$tauxInitial} WHERE id = {$idTitre}");
    Tarifs::oublier();

    echo "\n=== D. La meme reference operateur, deux fois ===\n";
    $encaissement = Commandes::marquerPayee(9501, 'OPERATEUR-REF-0001', 'airtel_money', 25.0);
    verif('La commande est encaissee', $encaissement['succes'] === true, implode(' ', $encaissement['erreurs']));
    verif('... elle porte un numero de facture',
        (string) $db->query('SELECT invoice_number FROM orders WHERE id = 9501')->fetchColumn() !== '');
    verif('... et la date de paiement',
        $db->query('SELECT paid_at FROM orders WHERE id = 9501')->fetchColumn() !== null);

    // Le rejeu : les operateurs mobile money renvoient le meme evenement.
    $rejeu = Commandes::marquerPayee(9501, 'OPERATEUR-REF-0001', 'airtel_money', 25.0);
    verif('Un callback rejoue ne provoque pas d\'erreur', $rejeu['succes'] === true);
    verif('... et dit qu\'il etait deja traite', $rejeu['deja'] === true);
    verif('... sans creer un second droit',
        (int) $db->query("SELECT COUNT(*) FROM entitlements WHERE user_id = 915 AND item_id = 9501 AND item_type = 'track'")->fetchColumn() === 1);

    // LE CRITERE : c'est la BASE qui refuse, pas le code.
    $db->exec("INSERT INTO orders (id, reference, user_id, status) VALUES (9502, 'TCHK-2026-DATA0502', 915, 'awaiting_payment')");
    $message = refus(
        $db,
        "UPDATE orders SET gateway_ref = 'OPERATEUR-REF-0001' WHERE id = 9502"
    );
    verif('La base refuse une seconde commande avec la meme reference', $message !== '', 'ecriture acceptee !');
    verif('... en le disant explicitement', str_contains($message, 'Duplicate entry') || str_contains($message, '1062'), $message);

    $viaCode = Commandes::marquerPayee(9502, 'OPERATEUR-REF-0001', 'moov_money');
    verif('Le code refuse aussi, avec un message lisible', $viaCode['succes'] === false);
    verif('... qui nomme le probleme', str_contains(implode(' ', $viaCode['erreurs']), 'autre commande'), implode(' ', $viaCode['erreurs']));

    verif('Une reference vide est refusee', Commandes::marquerPayee(9502, '   ', 'moov_money')['succes'] === false);
    verif('Une commande inexistante est refusee', Commandes::marquerPayee(99999, 'REF-X', 'moov_money')['succes'] === false);

    echo "\n=== E. Numerotation de facture sans trou ===\n";
    $numeros = [(string) $db->query('SELECT invoice_number FROM orders WHERE id = 9501')->fetchColumn()];
    for ($i = 3; $i <= 6; $i++) {
        $id = 9500 + $i;
        $db->prepare("INSERT INTO orders (id, reference, user_id, status) VALUES (?, ?, 915, 'awaiting_payment')")
           ->execute([$id, 'TCHK-2026-DATA05' . sprintf('%02d', $i)]);
        Commandes::marquerPayee($id, 'OPERATEUR-REF-' . sprintf('%04d', $i), 'moov_money');
        $numeros[] = (string) $db->query("SELECT invoice_number FROM orders WHERE id = {$id}")->fetchColumn();
    }

    verif('Chaque commande payee recoit un numero', count(array_filter($numeros)) === 5, implode(', ', $numeros));
    verif('Les numeros sont uniques', count(array_unique($numeros)) === 5, implode(', ', $numeros));

    $suite = array_map(static fn (string $n): int => (int) substr($n, -6), $numeros);
    sort($suite);
    verif('La suite est continue, sans trou',
        $suite === range($suite[0], $suite[0] + 4),
        implode(', ', $suite));
    verif('Le numero porte l\'annee', str_starts_with($numeros[0], 'FAC-' . date('Y')), $numeros[0]);

    // Une commande abandonnee ne doit pas consommer de numero.
    $avant = (int) $db->query('SELECT last_number FROM invoice_counters WHERE year = ' . (int) date('Y'))->fetchColumn();
    $db->exec("INSERT INTO orders (id, reference, user_id, status) VALUES (9507, 'TCHK-2026-DATA0507', 915, 'cart')");
    $db->exec("UPDATE orders SET status = 'cancelled' WHERE id = 9507");
    $apres = (int) $db->query('SELECT last_number FROM invoice_counters WHERE year = ' . (int) date('Y'))->fetchColumn();
    verif('Une commande abandonnee ne consomme pas de numero', $avant === $apres, "{$avant} -> {$apres}");

    echo "\n=== F. payment_events est immuable ===\n";
    verif('Un evenement s\'inscrit', Commandes::enregistrerEvenement([
        'order_id'   => 9501,
        'gateway'    => 'airtel_money',
        'direction'  => 'callback',
        'event_type' => 'payment.succeeded',
        'payload'    => ['reference' => 'OPERATEUR-REF-0001', 'status' => 'SUCCESS'],
        'signature'  => 'abc123',
        'signature_valid' => true,
    ]) === true);

    $evenement = $db->query("SELECT * FROM payment_events WHERE order_id = 9501 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('... avec le corps brut de l\'echange', is_array($evenement) && str_contains((string) $evenement['payload'], 'SUCCESS'));
    verif('... et la validite de la signature', is_array($evenement) && (int) $evenement['signature_valid'] === 1);

    $idEvenement = (int) ($evenement['id'] ?? 0);
    $message = refus($db, "UPDATE payment_events SET payload = 'falsifie' WHERE id = ?", [$idEvenement]);
    verif('La modification d\'un evenement est refusee', $message !== '', 'modification acceptee !');
    verif('... avec un message qui dit pourquoi', str_contains($message, 'immuable'), $message);

    $message = refus($db, 'DELETE FROM payment_events WHERE id = ?', [$idEvenement]);
    verif('La suppression d\'un evenement est refusee', $message !== '', 'suppression acceptee !');
    verif('... avec le meme motif', str_contains($message, 'immuable'), $message);

    verif('Et le contenu est intact',
        (string) $db->query("SELECT payload FROM payment_events WHERE id = {$idEvenement}")->fetchColumn() !== 'falsifie');

    echo "\n=== G. Droits d'acces et quota ===\n";
    verif('L\'achat ouvre le droit', Commandes::aLeDroit(915, 'track', 9501) === true);
    verif('Un titre non achete reste ferme', Commandes::aLeDroit(915, 'track', 9502) === false);

    $droit = $db->query("SELECT * FROM entitlements WHERE user_id = 915 AND item_id = 9501 AND item_type = 'track'")->fetch(PDO::FETCH_ASSOC);
    verif('L\'origine du droit est notee', is_array($droit) && $droit['source'] === 'purchase');
    verif('Le quota de telechargement est pose', is_array($droit) && (int) $droit['max_downloads'] === 5);
    verif('La ligne de commande est rattachee', is_array($droit) && $droit['order_item_id'] !== null);

    for ($i = 1; $i <= 5; $i++) {
        $consomme = Commandes::consommerTelechargement(915, 'track', 9501);
        verif("Telechargement {$i} sur 5 accorde", $consomme === true);
    }
    verif('Le sixieme est refuse : quota epuise',
        Commandes::consommerTelechargement(915, 'track', 9501) === false);
    verif('... et le decompte est exact',
        (int) $db->query("SELECT downloads_used FROM entitlements WHERE user_id = 915 AND item_id = 9501 AND item_type = 'track'")->fetchColumn() === 5);

    $db->exec("UPDATE entitlements SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE user_id = 915 AND item_id = 9501");
    verif('Un droit expire ne donne plus acces', Commandes::aLeDroit(915, 'track', 9501) === false);
    $db->exec("UPDATE entitlements SET expires_at = NULL, revoked_at = NOW() WHERE user_id = 915 AND item_id = 9501");
    verif('Un droit revoque non plus', Commandes::aLeDroit(915, 'track', 9501) === false);
    $db->exec("UPDATE entitlements SET revoked_at = NULL WHERE user_id = 915 AND item_id = 9501");

    echo "\n=== H. Le controle d'acces aux medias lit les droits ===\n";
    $acces = $source('includes/media-access.php');
    verif('MediaAccess interroge `entitlements`', str_contains($acces, 'FROM entitlements'));
    verif('... et ne lit plus `purchases`', !str_contains($acces, 'FROM purchases'));
    verif('... en respectant l\'expiration', str_contains($acces, 'expires_at IS NULL OR expires_at > NOW()'));
    verif('... et la revocation', str_contains($acces, 'revoked_at IS NULL'));
    verif('media.php fournit la sortie du titre', str_contains($source('media.php'), 'release_id'));

    echo "\n=== I. Double validation d'un versement ===\n";
    $message = refus(
        $db,
        "INSERT INTO payouts (artist_id, period_start, period_end, gross, commission, net, method, destination, status, created_by, approved_by)
         VALUES (915, '2026-08-01', '2026-08-31', 10000, 1500, 8500, 'airtel_money', 'chiffre', 'approved', 916, 916)"
    );
    verif('Un versement approuve par son preparateur est refuse', $message !== '', 'ecriture acceptee !');
    verif('... par la base elle-meme',
        str_contains($message, 'double_validation') || str_contains($message, 'CONSTRAINT') || str_contains($message, '4025'),
        $message);

    $db->exec(
        "INSERT INTO payouts (artist_id, period_start, period_end, gross, commission, net, method, destination, status, created_by, approved_by, approved_at)
         VALUES (915, '2026-08-01', '2026-08-31', 10000, 1500, 8500, 'airtel_money', 'chiffre', 'approved', 916, 915, NOW())"
    );
    verif('Deux personnes distinctes : accepte',
        (int) $db->query('SELECT COUNT(*) FROM payouts WHERE artist_id = 915')->fetchColumn() === 1);

    // LOT 8 : l'execution est une troisieme main, contrainte en base.
    $message = refus($db, 'UPDATE payouts SET executed_by = approved_by WHERE artist_id = 915');
    verif('Le validateur n\'execute pas le versement (base)', $message !== '', 'ecriture acceptee !');
    $message = refus($db, 'UPDATE payouts SET executed_by = created_by WHERE artist_id = 915');
    verif('Le demandeur n\'execute pas le versement (base)', $message !== '', 'ecriture acceptee !');

    echo "\n=== J. L'histoire comptable resiste a la suppression ===\n";
    $message = refus($db, 'DELETE FROM users WHERE id = 915');
    verif('Supprimer un acheteur ne detruit pas sa commande', $message !== '', 'suppression acceptee !');
    verif('... la commande est toujours la',
        (int) $db->query('SELECT COUNT(*) FROM orders WHERE id = 9501')->fetchColumn() === 1);
    $message = refus($db, 'DELETE FROM artists WHERE id = 915');
    verif('Supprimer un artiste ne detruit pas ses ventes', $message !== '', 'suppression acceptee !');

    echo "\n=== K. Compteurs de vente ===\n";
    // STAT-06 : plus de declencheur ; les compteurs se recalculent depuis les
    // agregats journaliers (vente payee, remboursement deduit).
    require_once $racine . '/includes/agregats.php';
    $jourVente = (string) $db->query('SELECT DATE(paid_at) FROM orders WHERE id = 9501')->fetchColumn();
    $recompter = static function () use ($jourVente): void {
        Agregats::construireJour($jourVente);
        Compteurs::recalculer([9501], [9501], [915]);
    };
    $recompter();
    $ventesTitre = (int) $db->query('SELECT total_sales FROM tracks WHERE id = 9501')->fetchColumn();
    verif('La vente payee a compte une fois', $ventesTitre === 1, (string) $ventesTitre);
    $artiste = $db->query('SELECT total_sales, total_earnings FROM artists WHERE id = 915')->fetch(PDO::FETCH_ASSOC);
    verif('L\'artiste : ventes en unites (1), part nette en francs (425)', (int) $artiste['total_sales'] === 1 && (float) $artiste['total_earnings'] === 425.0, json_encode($artiste));

    $db->exec("UPDATE orders SET status = 'paid' WHERE id = 9501");
    $recompter();
    verif('Un second passage a « paid » ne recompte pas',
        (int) $db->query('SELECT total_sales FROM tracks WHERE id = 9501')->fetchColumn() === 1);

    $declencheurs = [];
    foreach ($db->query("SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = DATABASE()") as $ligne) {
        $declencheurs[] = $ligne['trigger_name'];
    }
    verif('L\'ancien declencheur sur `purchases` a disparu', !in_array('update_purchase_stats', $declencheurs, true));
    verif('STAT-06 : plus de declencheur de compteur de ventes', !in_array('compter_vente_payee', $declencheurs, true));

    echo "\n=== L. `purchases` a disparu, les ecrans lisent les vraies tables ===\n";
    $tablesApres = [];
    foreach ($db->query(
        'SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = DATABASE()'
    ) as $ligne) {
        $tablesApres[$ligne['table_name']] = $ligne['table_type'];
    }

    // includes/payment.php est exclu de la recherche : il ne se charge pas
    // (erreur de syntaxe connue), et LOT 5 le refait entierement.
    $lecteursPurchases = [];
    foreach (glob($racine . '/*.php') ?: [] as $fichier) {
        if (preg_match('/\b(FROM|INTO|UPDATE)\s+`?purchases`?\b/i', (string) file_get_contents($fichier))) {
            $lecteursPurchases[] = basename($fichier);
        }
    }

    verif('La table `purchases` n\'existe plus', !isset($tablesApres['purchases']), $tablesApres['purchases'] ?? '');
    verif('... et rien ne la lit plus',
        $lecteursPurchases === [],
        implode(', ', $lecteursPurchases));

    // Ce que ces ecrans affichaient etait toujours zero : `purchases` n'etait
    // ecrite par personne. Ils lisent desormais les commandes payees.
    verif('Le dashboard artiste lit les lignes de commande',
        str_contains($source('artist-dashboard.php'), 'FROM order_items oi'));
    verif('... et somme la part nette reelle',
        str_contains($source('artist-dashboard.php'), 'SUM(oi.artist_net)'));
    verif('Le dashboard membre compte les commandes payees',
        str_contains($source('user-dashboard.php'), "o.status = 'paid'"));
    // SHOP-06 : le portefeuille lit desormais son propre journal
    // (wallet_transactions), et non plus les commandes.
    verif('Le portefeuille lit son journal', str_contains($source('wallet.php'), 'Portefeuille::mouvements'));

    $revenus = $db->query(
        "SELECT COALESCE(SUM(oi.artist_net), 0) FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE oi.artist_id = 915 AND o.status = 'paid'"
    )->fetchColumn();
    verif('La requete du dashboard artiste rend la part nette', (float) $revenus === 425.0, (string) $revenus);

    $depense = $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = 915 AND status = 'paid'")->fetchColumn();
    verif('Celle du portefeuille rend la depense', (float) $depense > 0.0, (string) $depense);

    foreach (['artist-dashboard.php', 'user-dashboard.php', 'wallet.php'] as $ecran) {
        $r = curl_init('http://localhost/tchadok/' . $ecran);
        curl_setopt_array($r, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
        curl_exec($r);
        $code = (int) curl_getinfo($r, CURLINFO_HTTP_CODE);
        curl_close($r);
        // Sans session, ces ecrans redirigent : 302 prouve que la page s'est
        // chargee -- une erreur SQL sur la vue donnerait 500.
        verif("{$ecran} se charge encore", in_array($code, [200, 302], true), (string) $code);
    }

    echo "\n=== M. Migration ===\n";
    $migration = $source('database/migrations/2026_09_23_0009_data05_tables_commerciales.sql');
    foreach (['orders', 'order_items', 'entitlements', 'payouts', 'payment_intents', 'payment_events'] as $table) {
        verif("La migration cree `{$table}`", str_contains($migration, "CREATE TABLE IF NOT EXISTS `{$table}`"));
    }
    verif('... et sait revenir en arriere', str_contains($migration, '-- DOWN'));
    verif('... en recreant la table `purchases`', str_contains($migration, 'CREATE TABLE IF NOT EXISTS `purchases`'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" status 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('DATA-05 est appliquee', preg_match('/appliquee\s+\S*data05_tables_commerciales/', $texte) === 1, $texte);
    verif('... et le schema est a jour', str_contains($texte, 'Schema a jour'), $texte);
} finally {
    $nettoyer();
    Tarifs::oublier();
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
