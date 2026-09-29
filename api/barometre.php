<?php
/**
 * API publique du barometre (CHART-04), lecture seule.
 *
 *   GET /api/barometre.php?edition=<slug>|&periode=weekly&classement=titres
 *   En-tete : X-Api-Key: <cle>   (ou ?cle=<cle>)
 *
 * Cle obligatoire (scripts/barometre.php cle-creer), quota quotidien par cle
 * (429 au-dela), attribution OBLIGATOIRE fournie dans chaque reponse : mieux
 * vaut etre la source citee que la source copiee.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/barometre.php';

header('Content-Type: application/json; charset=utf-8');
$repondre = static function (int $code, array $corps): never {
    http_response_code($code);
    echo json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $repondre(405, ['erreur' => 'Lecture seule.']);
}

$db = TchadokDatabase::getInstance()->getConnection();
$cle = (string) ($_SERVER['HTTP_X_API_KEY'] ?? $_GET['cle'] ?? '');
$stmt = $db->prepare('SELECT * FROM api_keys WHERE key_hash = ? AND revoked_at IS NULL');
$stmt->execute([hash('sha256', $cle)]);
$compte = $stmt->fetch(PDO::FETCH_ASSOC);
if ($cle === '' || !$compte) {
    $repondre(401, ['erreur' => 'Cle d\'API absente ou invalide. Demande : contact Tchadok.']);
}
$db->prepare('INSERT INTO api_key_usage (key_id, day, calls) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE calls = calls + 1')->execute([$compte['id']]);
$stmt = $db->prepare('SELECT calls FROM api_key_usage WHERE key_id = ? AND day = CURDATE()');
$stmt->execute([$compte['id']]);
$appels = (int) $stmt->fetchColumn();
header('X-Quota-Limite: ' . (int) $compte['daily_quota']);
header('X-Quota-Restant: ' . max(0, (int) $compte['daily_quota'] - $appels));
if ($appels > (int) $compte['daily_quota']) {
    header('Retry-After: ' . (strtotime('tomorrow') - time()));
    $repondre(429, ['erreur' => 'Quota quotidien atteint.']);
}

$type = array_key_exists($_GET['periode'] ?? '', Barometre::PERIODES) ? (string) $_GET['periode'] : 'weekly';
$classement = array_key_exists($_GET['classement'] ?? '', Barometre::CLASSEMENTS) ? (string) $_GET['classement'] : 'titres';
$slug = preg_match('/^[0-9]{4}(-S[0-9]{2}|-[0-9]{2})?$/', (string) ($_GET['edition'] ?? '')) ? (string) $_GET['edition'] : null;
$edition = Barometre::edition($slug, $type);
if (!$edition) {
    $repondre(404, ['erreur' => 'Edition introuvable.']);
}
$repondre(200, [
    'attribution' => 'Source : Barometre Tchadok, ' . Barometre::libellePeriode($edition) . ', arrete le ' . date('d/m/Y', strtotime((string) $edition['arrete_at'])) . '. Mention obligatoire.',
    'methodologie' => SITE_URL . '/methodologie.php',
    'permalien' => SITE_URL . '/barometre.php?edition=' . rawurlencode((string) $edition['slug']) . '&classement=' . $classement,
    'edition' => [
        'slug' => $edition['slug'], 'periode' => $edition['period_type'], 'debut' => $edition['period_start'], 'fin' => $edition['period_end'],
        'arrete_le' => $edition['arrete_at'], 'version_methodologie' => $edition['methodology_version'],
        'ecoutes_certifiees' => (int) $edition['streams_total'], 'ventes' => (int) $edition['sales_total'],
    ],
    'classement' => $classement,
    'mesure' => Barometre::CLASSEMENTS[$classement]['mesure'],
    'entrees' => array_map(static fn ($e) => [
        'rang' => (int) $e['rank'], 'rang_precedent' => $e['previous_rank'] !== null ? (int) $e['previous_rank'] : null,
        'evolution' => Barometre::evolution($e), 'titre' => $e['titre'], 'artiste' => $e['artiste'],
        'valeur' => (int) $e['value'], 'meilleur_rang' => (int) $e['peak_rank'], 'periodes_classees' => (int) $e['periods_on_chart'],
    ], Barometre::entrees((int) $edition['id'], $classement)),
]);
