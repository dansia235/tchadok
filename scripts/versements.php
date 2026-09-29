<?php
/**
 * Versements et contrat de distribution (LOT 8), en ligne de commande.
 *
 *   php scripts/versements.php verifier
 *       Consulte l'operateur pour les envois sans reponse depuis plus d'une
 *       minute. A planifier toutes les 15 minutes.
 *   php scripts/versements.php solde <artist_id>
 *   php scripts/versements.php controle
 *       Controle global : pour chaque ligne de vente payee, part artiste +
 *       commission = prix paye.
 *
 *   php scripts/versements.php contrat-publier <version> <fichier.txt> --titre="..."
 *       Publie une version du contrat (texte fourni par la direction). Tous
 *       les artistes devront l'accepter avant de publier ou d'etre payes.
 *   php scripts/versements.php contrat-etat
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/paiement/chargement.php';

$db = TchadokDatabase::getInstance()->getConnection();
$options = [];
foreach (array_slice($argv, 2) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $a, $m)) {
        $options[$m[1]] = $m[2];
    }
}

switch ($argv[1] ?? '') {
    case 'verifier':
        printf("[%s] versements : %d envoi(s) conclu(s) apres consultation\n", date('c'), Versements::verifierEnCours());
        break;

    case 'solde':
        foreach (Versements::solde((int) ($argv[2] ?? 0)) as $cle => $valeur) {
            printf("  %-13s %12s FCFA\n", $cle, number_format((float) $valeur, 0, ',', ' '));
        }
        break;

    case 'controle':
        $ecart = (int) $db->query(
            "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.status = 'paid' AND ABS(oi.artist_net + oi.commission - oi.unit_price * oi.quantity) > 0.01"
        )->fetchColumn();
        echo $ecart === 0 ? "  [ok] Part artiste + commission = prix paye, sur toutes les ventes.\n" : "  [ECART] {$ecart} ligne(s) incoherente(s).\n";
        exit($ecart === 0 ? 0 : 1);

    case 'repartition':
        // Apercu de la part des abonnements d'un mois ; la cloture se fait
        // dans admin/remuneration.php (deux regards : calcul, puis decision).
        $mois = (string) ($argv[2] ?? date('Y-m', strtotime(date('Y-m-01') . ' -1 month')));
        $c = RepartitionAbonnements::cloturee($mois) ?? RepartitionAbonnements::calculer($mois);
        printf("  %s : taux %s %%, revenu %s, part %s, reparti %s, non attribue %s FCFA (%d artiste(s))\n",
            $mois, RepartitionAbonnements::pourcent($c['taux']), $c['revenu'], $c['part'], $c['reparti'], $c['non_attribue'], count($c['lignes']));
        break;

    case 'contrat-publier':
        $version = (string) ($argv[2] ?? '');
        $fichier = (string) ($argv[3] ?? '');
        $titre = trim((string) ($options['titre'] ?? 'Contrat de distribution Tchadok'));
        if (!preg_match('/^[A-Za-z0-9._-]{1,20}$/', $version) || !is_file($fichier)) {
            echo "  [ERREUR] Usage : contrat-publier <version> <fichier.txt> --titre=\"...\"\n";
            exit(1);
        }
        $texte = trim((string) file_get_contents($fichier));
        if (mb_strlen($texte) < 200) {
            echo "  [ERREUR] Texte trop court pour un contrat.\n";
            exit(1);
        }
        $db->prepare('INSERT INTO distribution_contracts (version, title, body, published_at) VALUES (?, ?, ?, NOW())')->execute([$version, $titre, $texte]);
        JournalAudit::enregistrer('contrat.publie',['cible_type' => 'contrat_distribution', 'cible_id' => $version, 'raison' => 'Publication en ligne de commande par ' . get_current_user()]);
        echo "  [ok] Version {$version} publiee. Chaque artiste devra l'accepter.\n";
        break;

    case 'contrat-etat':
        $c = Contrats::courant();
        if ($c === null) {
            echo "  [!] Aucun contrat publie : les artistes publient et sont payes SANS contrat accepte. A regler avant la mise en production.\n";
            exit(EnvLoader::isProduction() ? 1 : 0);
        }
        $n = (int) $db->query("SELECT COUNT(*) FROM contract_acceptances WHERE contract_id = {$c['id']}")->fetchColumn();
        $total = (int) $db->query('SELECT COUNT(*) FROM artists WHERE deleted_at IS NULL AND is_active = 1')->fetchColumn();
        echo "  Version {$c['version']} publiee le {$c['published_at']} : {$n} acceptation(s) sur {$total} artiste(s) actif(s).\n";
        break;

    default:
        echo "  php scripts/versements.php verifier | solde <id> | controle | contrat-publier <v> <fichier> --titre=... | contrat-etat\n";
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
