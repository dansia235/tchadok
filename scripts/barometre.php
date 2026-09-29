<?php
/**
 * Barometre (LOT 10), en ligne de commande.
 *
 *   php scripts/barometre.php arreter
 *       Arrete les editions echues (hebdomadaire, mensuelle, annuelle, 48 h
 *       apres la fin de periode) et produit leur kit presse. Chaque jour,
 *       apres « ecoutes.php agreger ». Idempotent.
 *   php scripts/barometre.php certifier
 *       Decerne les certifications Or / Platine / Diamant atteintes et
 *       previent les artistes. Chaque nuit, apres « arreter ».
 *   php scripts/barometre.php editions [weekly|monthly|yearly]
 *   php scripts/barometre.php cle-creer "Nom du media" contact@exemple.td [quota]
 *       Cle d'API publique ; affichee UNE fois, conservee en empreinte.
 *   php scripts/barometre.php cle-revoquer <id>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/barometre.php';
require_once $racine . '/includes/kit-presse.php';

$db = TchadokDatabase::getInstance()->getConnection();

switch ($argv[1] ?? '') {
    case 'arreter':
        $slugs = Barometre::arreterEchues();
        $kits = KitPresse::genererManquants();
        printf("[%s] barometre : %d edition(s) arretee(s)%s, %d kit(s) presse genere(s)\n", date('c'), count($slugs), $slugs ? ' (' . implode(', ', $slugs) . ')' : '', $kits);
        break;

    case 'certifier':
        printf("[%s] certifications decernees : %d\n", date('c'), CertificationsTchadok::decerner());
        break;

    case 'editions':
        foreach (Barometre::archives($argv[2] ?? 'weekly', 20) as $e) {
            printf("  %-10s %s  %s ecoutes, %s ventes, arrete le %s%s\n", $e['slug'], Barometre::libellePeriode($e),
                number_format((float) $e['streams_total'], 0, ',', ' '), number_format((float) $e['sales_total'], 0, ',', ' '), $e['arrete_at'],
                $e['kit_generated_at'] ? '' : ' (kit a generer)');
        }
        break;

    case 'cle-creer':
        $proprietaire = trim((string) ($argv[2] ?? ''));
        $contact = trim((string) ($argv[3] ?? ''));
        $quota = max(1, (int) ($argv[4] ?? 1000));
        if (mb_strlen($proprietaire) < 2 || !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            echo "  [ERREUR] Usage : cle-creer \"Nom\" contact@exemple.td [quota quotidien]\n";
            exit(1);
        }
        $cle = 'tbk_' . bin2hex(random_bytes(20));
        $db->prepare('INSERT INTO api_keys (key_hash, owner, contact, daily_quota) VALUES (?, ?, ?, ?)')->execute([hash('sha256', $cle), $proprietaire, $contact, $quota]);
        echo "  Cle creee pour {$proprietaire} ({$quota} appels/jour). A transmettre maintenant, elle ne sera plus affichee :\n\n  {$cle}\n\n";
        break;

    case 'cle-revoquer':
        $stmt = $db->prepare('UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL');
        $stmt->execute([(int) ($argv[2] ?? 0)]);
        echo $stmt->rowCount() === 1 ? "  [ok] Cle revoquee.\n" : "  [ERREUR] Cle introuvable ou deja revoquee.\n";
        exit($stmt->rowCount() === 1 ? 0 : 1);

    default:
        echo "  php scripts/barometre.php arreter | certifier | editions [type] | cle-creer \"Nom\" contact [quota] | cle-revoquer <id>\n";
        exit(($argv[1] ?? '') === '' ? 0 : 1);
}

exit(0);
