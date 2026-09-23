<?php
/**
 * Collecte des rapports de politique de contenu (SEC-18).
 *
 * La CSP est posee en Report-Only : le navigateur n'empeche rien, mais signale
 * ici ce qu'il aurait bloque. C'est ce qui permet de passer la politique en
 * mode bloquant sans casser le site -- on corrige d'abord ce que les rapports
 * revelent.
 *
 * Le rapport est envoye par le navigateur, sans session ni jeton : la garde
 * CSRF ne peut pas s'appliquer, d'ou l'exemption declaree et journalisee.
 * En contrepartie, l'endpoint est limite en debit, ne lit qu'un corps de
 * taille bornee, n'ecrit que des champs choisis, et ne repond rien.
 */

define('TCHADOK_CSRF_EXEMPT', 'rapport CSP envoye par le navigateur, sans session ni jeton');

require_once '../includes/functions.php';

// Un rapport n'appelle aucune reponse : 204 quoi qu'il arrive.
$repondre = static function (): never {
    if (!headers_sent()) {
        http_response_code(204);
        header('Content-Length: 0');
        header('Cache-Control: no-store');
    }
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    exit;
}

// Un navigateur mal reglé, ou un tiers, pourrait inonder le journal.
LimiteDebit::appliquer('rapport-csp');

$brut = (string) file_get_contents('php://input', false, null, 0, 16384);
$corps = json_decode($brut, true);
if (!is_array($corps)) {
    $repondre();
}

// Deux formats coexistent : csp-report (historique) et reports (Reporting API).
$rapports = [];
if (isset($corps['csp-report']) && is_array($corps['csp-report'])) {
    $rapports[] = $corps['csp-report'];
} else {
    foreach ($corps as $entree) {
        if (is_array($entree) && isset($entree['body']) && is_array($entree['body'])) {
            $rapports[] = $entree['body'];
        }
    }
}

$journal = __DIR__ . '/../storage/logs/csp-report.log';
$tronquer = static fn ($valeur): string => substr(preg_replace('/[\r\n\t]+/', ' ', (string) $valeur), 0, 300);

foreach (array_slice($rapports, 0, 5) as $rapport) {
    $ligne = sprintf(
        "[%s] directive=%s bloque=%s document=%s ip=%s\n",
        date('c'),
        $tronquer($rapport['effective-directive'] ?? $rapport['effectiveDirective'] ?? $rapport['violated-directive'] ?? '-'),
        $tronquer($rapport['blocked-uri'] ?? $rapport['blockedURL'] ?? '-'),
        $tronquer($rapport['document-uri'] ?? $rapport['documentURL'] ?? '-'),
        function_exists('clientIp') ? clientIp() : '-'
    );

    @file_put_contents($journal, $ligne, FILE_APPEND | LOCK_EX);
}

$repondre();
