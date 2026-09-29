<?php
/**
 * Reconstruction integrale des agregats et des compteurs (STAT-06).
 *
 *   php scripts/rebuild-counters.php [--du=AAAA-MM-JJ] [--au=AAAA-MM-JJ]
 *
 * Sans plage : du premier jour d'activite (ecoute certifiee ou vente payee) a
 * aujourd'hui. Recalcule les agregats de la plage, puis TOUS les compteurs
 * publics, et verifie qu'ils concordent. Executable a tout moment : apres
 * une correction de donnees, une purge de fraude, ou la migration 0026.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/agregats.php';

$options = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(du|au)=(\d{4}-\d{2}-\d{2})$/', $a, $m)) {
        $options[$m[1]] = $m[2];
    }
}
$db = TchadokDatabase::getInstance()->getConnection();
$premier = $db->query(
    "SELECT LEAST(COALESCE((SELECT MIN(DATE(listened_at)) FROM streams_certified), CURDATE()),
                  COALESCE((SELECT MIN(DATE(paid_at)) FROM orders WHERE status = 'paid'), CURDATE()))"
)->fetchColumn();
$du = $options['du'] ?? (string) $premier;
$au = $options['au'] ?? date('Y-m-d');

$debut = microtime(true);
$jours = Agregats::construire($du, $au);
Compteurs::recalculer();
$ecarts = Compteurs::controler();
printf("  Agregats reconstruits du %s au %s (%d jour(s)), compteurs recalcules en %.1f s.\n", $du, $au, $jours, microtime(true) - $debut);
echo $ecarts === [] ? "  [ok] Compteurs conformes aux agregats.\n" : '  [DERIVE] ' . implode("\n  [DERIVE] ", $ecarts) . "\n";
exit($ecarts === [] ? 0 : 1);
