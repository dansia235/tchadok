<?php
/**
 * Exploitation des paiements (LOT 5), en ligne de commande.
 *
 *   php scripts/paiements.php expirer
 *       Expire les tentatives sans reponse de l'operateur, APRES l'avoir
 *       interroge (le callback a pu se perdre). A planifier toutes les
 *       5 minutes en production (cron / Planificateur de taches).
 *
 *   php scripts/paiements.php statut <reference-commande>
 *       Etat d'une commande, de ses tentatives et de ses echanges avec les
 *       operateurs. Consulte l'operateur pour une tentative encore ouverte.
 *
 *   php scripts/paiements.php commande-essai <email> [montant]
 *       LOCAL UNIQUEMENT. Cree une commande fictive pour essayer la page de
 *       paiement, en attendant le panier (SHOP-01). Affiche son adresse.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';

function sortie(string $texte = ''): void
{
    echo $texte, PHP_EOL;
}

function echouer(string $texte): never
{
    sortie('  [ERREUR] ' . $texte);
    exit(1);
}

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    echouer('Base de donnees injoignable.');
}

$commande = $argv[1] ?? '';

switch ($commande) {
    case 'expirer':
        $bilan = Paiements::expirerEchues();
        sortie(sprintf('[%s] paiements : %d consultee(s), %d expiree(s)', date('c'), $bilan['verifiees'], $bilan['expirees']));
        break;

    case 'statut':
        $reference = (string) ($argv[2] ?? '');
        $stmt = $db->prepare('SELECT * FROM orders WHERE reference = ?');
        $stmt->execute([$reference]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC) ?: echouer("Commande inconnue : {$reference}");

        foreach ($db->query("SELECT id FROM payment_intents WHERE order_id = {$o['id']} AND status IN ('created','pending')")->fetchAll(PDO::FETCH_COLUMN) as $ouverte) {
            Paiements::verifierStatut((int) $ouverte);
        }
        $stmt->execute([$reference]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC);

        sortie();
        sortie(sprintf('  Commande %s : %s, %s %s, moyen %s, facture %s',
            $o['reference'], $o['status'], number_format((float) $o['total'], 0, ',', ' '), $o['currency'],
            $o['payment_method'] ?? '-', $o['invoice_number'] ?? '-'));
        sortie();
        foreach ($db->query("SELECT * FROM payment_intents WHERE order_id = {$o['id']} ORDER BY attempt")->fetchAll(PDO::FETCH_ASSOC) as $t) {
            sortie(sprintf('  essai %d  %-12s %-10s ref %-22s %s%s',
                $t['attempt'], $t['gateway'], $t['status'], $t['gateway_ref'] ?? '-',
                $t['error_code'] ? 'code ' . $t['error_code'] . ' ' : '',
                $t['amount_reported'] !== null && (float) $t['amount_reported'] !== (float) $t['amount'] ? '(annonce ' . $t['amount_reported'] . ')' : ''));
        }
        sortie();
        foreach ($db->query("SELECT created_at, gateway, direction, event_type, http_status, signature_valid FROM payment_events WHERE order_id = {$o['id']} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $e) {
            sortie(sprintf('  %s  %-12s %-9s %-28s %s %s', $e['created_at'], $e['gateway'], $e['direction'], $e['event_type'],
                $e['http_status'] ?? '', $e['signature_valid'] === null ? '' : ((int) $e['signature_valid'] ? 'signe' : 'REJETE')));
        }
        sortie();
        break;

    case 'commande-essai':
        if (EnvLoader::isProduction() || EnvLoader::environment() !== 'local') {
            echouer('Commande d\'essai refusee hors environnement local.');
        }
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$argv[2] ?? '', $argv[2] ?? '']);
        $userId = (int) ($stmt->fetchColumn() ?: echouer('Compte inconnu : ' . ($argv[2] ?? '')));
        $montant = max(100, (int) ($argv[3] ?? 1500));

        $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')")
           ->execute([Commandes::reference(), $userId]);
        $orderId = (int) $db->lastInsertId();
        Commandes::ajouterArticle($orderId, 'subscription', 1, (float) $montant, null, 'Commande d\'essai (simulateur)');
        $reference = (string) $db->query("SELECT reference FROM orders WHERE id = {$orderId}")->fetchColumn();

        sortie();
        sortie("  Commande d'essai {$reference} : {$montant} FCFA");
        sortie('  ' . rtrim((string) EnvLoader::get('SITE_URL'), '/') . '/paiement.php?commande=' . $reference);
        sortie('  (se connecter avec ce compte ; simulateurs : scripts\\mock-gateways.bat)');
        sortie();
        break;

    default:
        sortie();
        sortie('  php scripts/paiements.php expirer');
        sortie('  php scripts/paiements.php statut <reference-commande>');
        sortie('  php scripts/paiements.php commande-essai <email> [montant]   (local)');
        sortie();
        exit($commande === '' ? 0 : 1);
}

exit(0);
