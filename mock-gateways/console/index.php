<?php
/**
 * Console de pilotage des simulateurs de paiement (PAY-09).
 *
 *   http://127.0.0.1:9100   (demarree par scripts\mock-gateways.bat)
 *
 * Observer et provoquer sans toucher a un fichier : transactions des quatre
 * passerelles, callbacks envoyes avec la reponse de Tchadok, actions
 * manuelles (valider, refuser, abandonner, rejouer, mal signer, alterer le
 * montant, contester), reglages globaux et reinitialisation.
 *
 * LOCAL UNIQUEMENT, et protegee meme ici :
 *   - n'accepte que des connexions de la boucle locale ;
 *   - toute action exige un jeton propre a ce poste : sans lui, n'importe quel
 *     site ouvert dans le navigateur pourrait envoyer un formulaire vers
 *     127.0.0.1:9100 et piloter les simulateurs (CSRF sur localhost).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/amorce.php';

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Console reservee a la machine locale.');
}

// Jeton de la console : cree au premier acces, propre a ce poste.
$fichierJeton = Magasin::racine() . '/console-jeton';
if (!is_file($fichierJeton)) {
    file_put_contents($fichierJeton, bin2hex(random_bytes(24)), LOCK_EX);
}
$jeton = trim((string) file_get_contents($fichierJeton));

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// ---------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------
if ($methode === 'POST') {
    $origine = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $origineLocale = $origine === '' || preg_match('#^http://(127\.0\.0\.1|localhost|\[::1\]):9100$#', $origine) === 1;
    if (!$origineLocale || !hash_equals($jeton, (string) ($_POST['jeton'] ?? ''))) {
        http_response_code(403);
        exit('Jeton de console absent ou invalide.');
    }

    $retour = '/';
    $message = '';
    switch ($chemin) {
        case '/action':
            $passerelle = (string) ($_POST['passerelle'] ?? '');
            $id = (string) ($_POST['id'] ?? '');
            $action = (string) ($_POST['action'] ?? '');
            $message = isset(SIMULATEURS[$passerelle]) ? executerAction($passerelle, $id, $action) : 'Passerelle inconnue.';
            $retour = '/t/' . rawurlencode($passerelle) . '/' . rawurlencode($id);
            break;

        case '/reglages':
            $indisponibles = array_values(array_intersect(array_keys(SIMULATEURS), (array) ($_POST['indisponibles'] ?? [])));
            Magasin::enregistrerReglages([
                'facteur_delai' => max(0.0, min(10.0, (float) str_replace(',', '.', (string) ($_POST['facteur_delai'] ?? '1')))),
                'taux_echec'    => max(0, min(100, (int) ($_POST['taux_echec'] ?? 0))),
                'indisponibles' => $indisponibles,
            ]);
            $message = 'Reglages enregistres.';
            break;

        case '/reinitialiser':
            Magasin::reinitialiser();
            $message = 'Simulateurs reinitialises : transactions, file et journal effaces.';
            break;
    }

    header('Location: ' . $retour . ($message !== '' ? (str_contains($retour, '?') ? '&' : '?') . 'm=' . rawurlencode($message) : ''), true, 303);
    exit;
}

/**
 * Applique une action manuelle a une transaction.
 */
function executerAction(string $passerelle, string $id, string $action): string
{
    $t = Magasin::lire($passerelle, $id);
    if (!$t) {
        return 'Transaction inconnue.';
    }
    $enAttente = $t['status'] === 'pending';
    $evenementDe = ($t['kind'] ?? 'payment') === 'disbursement'
        ? ['succeeded' => 'disbursement.succeeded', 'failed' => 'disbursement.failed', 'cancelled' => 'disbursement.failed']
        : ['succeeded' => 'payment.succeeded', 'failed' => 'payment.failed', 'cancelled' => 'payment.cancelled',
           'disputed' => 'payment.disputed', 'refunded' => 'refund.succeeded'];

    $decider = static function (string $statut, ?string $echec, string $libelle) use ($passerelle, $id, $evenementDe): string {
        Magasin::annulerProgrammes($passerelle, $id);
        Magasin::modifier($passerelle, $id, function (array $x) use ($statut, $echec, $libelle): array {
            $x['status'] = $statut;
            $x['failure_code'] = $echec;
            $x['scenario'] = ($x['scenario'] ?? '') . ' -> ' . $libelle . ' (console)';
            return $x;
        });
        Magasin::programmer(['passerelle' => $passerelle, 'id' => $id, 'evenement' => $evenementDe[$statut], 'delai' => 0]);
        return $libelle . ' : callback programme.';
    };

    return match (true) {
        $action === 'valider' && $enAttente   => $decider('succeeded', null, 'Validee'),
        $action === 'refuser' && $enAttente   => $decider('failed', 'insufficient_funds', 'Refusee (solde insuffisant)'),
        $action === 'annuler' && $enAttente   => $decider('cancelled', null, 'Annulee par l\'abonne'),
        $action === 'abandonner' && $enAttente => (function () use ($passerelle, $id): string {
            $n = Magasin::annulerProgrammes($passerelle, $id);
            Magasin::modifier($passerelle, $id, function (array $x): array {
                $x['scenario'] = ($x['scenario'] ?? '') . ' -> abandonnee, aucun callback (console)';
                return $x;
            });
            return "Abandonnee : {$n} callback(s) retire(s) de la file. Tchadok l'expirera apres consultation.";
        })(),
        in_array($action, ['rejouer', 'mal_signe', 'montant_altere'], true) && !$enAttente && isset($evenementDe[$t['status']]) => (function () use ($passerelle, $id, $action, $t, $evenementDe): string {
            Magasin::programmer([
                'passerelle' => $passerelle, 'id' => $id, 'evenement' => $evenementDe[$t['status']], 'delai' => 0,
                'alteration' => ['mal_signe' => 'signature', 'montant_altere' => 'montant'][$action] ?? null,
            ]);
            return 'Callback ' . $evenementDe[$t['status']] . ' programme' . ($action === 'rejouer' ? '' : ' (' . str_replace('_', ' ', $action) . ')') . '.';
        })(),
        $action === 'contester' && $t['status'] === 'succeeded' && $t['flow'] === 'hosted' => (function () use ($passerelle, $id): string {
            Magasin::programmer(['passerelle' => $passerelle, 'id' => $id, 'evenement' => 'payment.disputed', 'delai' => 0, 'statut' => 'disputed']);
            return 'Contestation programmee.';
        })(),
        default => 'Action impossible dans l\'etat actuel de la transaction (' . $t['status'] . ').',
    };
}

// ---------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------
$montant = static fn (array $t): string => ($t['currency'] ?? 'XAF') === 'USD'
    ? number_format((int) $t['amount'] / 100, 2, ',', ' ') . ' $'
    : number_format((int) $t['amount'], 0, ',', ' ') . ' F';
$badge = static fn (string $s): string => '<span class="b b-' . htmlspecialchars($s) . '">' . htmlspecialchars($s) . '</span>';
$champJeton = '<input type="hidden" name="jeton" value="' . $e($jeton) . '">';

$contenu = '';
$titre = 'Transactions';

if (preg_match('#^/t/([a-z_]+)/([A-Z]{3}-[A-F0-9]{16})$#', $chemin, $m) && isset(SIMULATEURS[$m[1]])) {
    [$passerelle, $id] = [$m[1], $m[2]];
    $t = Magasin::lire($passerelle, $id);
    if (!$t) {
        http_response_code(404);
        $contenu = '<p>Transaction inconnue.</p>';
    } else {
        $titre = $id;
        $boutons = '';
        $actions = $t['status'] === 'pending'
            ? ['valider' => 'Valider', 'refuser' => 'Refuser', 'annuler' => 'Annuler (abonne)', 'abandonner' => 'Abandonner (aucun callback)']
            : ['rejouer' => 'Rejouer le callback', 'mal_signe' => 'Callback mal signe', 'montant_altere' => 'Callback au montant altere']
              + ($t['status'] === 'succeeded' && $t['flow'] === 'hosted' ? ['contester' => 'Contester (chargeback)'] : []);
        foreach ($actions as $action => $libelle) {
            $boutons .= '<form method="post" action="/action">' . $champJeton
                . '<input type="hidden" name="passerelle" value="' . $e($passerelle) . '">'
                . '<input type="hidden" name="id" value="' . $e($id) . '">'
                . '<button name="action" value="' . $e($action) . '">' . $e($libelle) . '</button></form>';
        }

        $callbacks = '';
        foreach (Magasin::journalRecent(50, $id) as $c) {
            $callbacks .= '<tr><td>' . $e($c['a'] ?? '') . '</td><td>' . $e($c['evenement'] ?? '') . '</td><td>' . $e($c['alteration'] ?? '') . '</td><td class="http h' . (int) (($c['http'] ?? 0) / 100) . '">' . $e($c['http'] ?? '') . '</td><td>' . $e($c['reponse'] ?? '') . '</td><td>' . $e($c['duree_ms'] ?? '') . ' ms</td></tr>';
        }

        $contenu = '<p><a href="/">&larr; Toutes les transactions</a></p>'
            . '<div class="carte"><h2>' . $e(SIMULATEURS[$passerelle]['libelle']) . ' ' . $badge((string) $t['status']) . '</h2>'
            . '<p><strong>' . $e($montant($t)) . '</strong> — reference Tchadok <code>' . $e($t['reference']) . '</code>'
            . (!empty($t['msisdn']) ? ' — numero <code>' . $e($t['msisdn']) . '</code>' : '') . '</p>'
            . '<p>Scenario : ' . $e($t['scenario'] ?? '-') . '</p>'
            . '<div class="actions">' . $boutons . '</div></div>'
            . '<h3>Callbacks envoyes</h3><table><tr><th>Envoi</th><th>Evenement</th><th>Alteration</th><th>Reponse Tchadok</th><th>Corps</th><th>Duree</th></tr>'
            . ($callbacks ?: '<tr><td colspan="6">Aucun callback envoye.</td></tr>') . '</table>'
            . '<h3>Transaction (stockage du simulateur)</h3><pre>' . $e(json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }
} else {
    $filtre = isset(SIMULATEURS[$_GET['passerelle'] ?? '']) ? (string) $_GET['passerelle'] : null;
    $lignes = '';
    foreach (Magasin::lister($filtre, 150) as $t) {
        $lignes .= '<tr><td><a href="/t/' . $e($t['_passerelle']) . '/' . $e($t['id']) . '"><code>' . $e($t['id']) . '</code></a></td>'
            . '<td>' . $e(SIMULATEURS[$t['_passerelle']]['libelle'] ?? $t['_passerelle']) . '</td>'
            . '<td class="num">' . $e($montant($t)) . '</td><td>' . $badge((string) $t['status']) . '</td>'
            . '<td>' . $e($t['scenario'] ?? '') . '</td><td>' . $e(substr((string) ($t['updated_at'] ?? ''), 0, 19)) . '</td></tr>';
    }

    $flux = '';
    foreach (Magasin::journalRecent(15) as $c) {
        $flux .= '<tr><td>' . $e(substr((string) ($c['a'] ?? ''), 11, 8)) . '</td><td><code>' . $e($c['id'] ?? '') . '</code></td><td>' . $e($c['evenement'] ?? '') . ($c['alteration'] ?? null ? ' [' . $e($c['alteration']) . ']' : '') . '</td><td class="http h' . (int) (($c['http'] ?? 0) / 100) . '">' . $e($c['http'] ?? '') . '</td></tr>';
    }

    $en_retard = 0;
    foreach (glob(Magasin::racine() . '/file/*.json') ?: [] as $f) {
        $en_retard += (float) basename($f) < microtime(true) - 5 ? 1 : 0;
    }

    $reglages = Magasin::reglages();
    $cases = '';
    foreach (SIMULATEURS as $code => $def) {
        $cases .= '<label><input type="checkbox" name="indisponibles[]" value="' . $e($code) . '"'
            . (in_array($code, (array) ($reglages['indisponibles'] ?? []), true) ? ' checked' : '') . '> ' . $e($def['libelle']) . '</label> ';
    }
    $filtres = '<a href="/"' . ($filtre === null ? ' class="actif"' : '') . '>Toutes</a>';
    foreach (SIMULATEURS as $code => $def) {
        $filtres .= ' <a href="/?passerelle=' . $e($code) . '"' . ($filtre === $code ? ' class="actif"' : '') . '>' . $e($def['libelle']) . '</a>';
    }

    $contenu = ($en_retard > 0 ? '<p class="alerte">' . $en_retard . ' callback(s) en retard dans la file : le distributeur est-il demarre ? (fenetre « Tchadok simulateur - callbacks »)</p>' : '')
        . '<div class="grille"><div><p class="filtres">' . $filtres . ' · <a href="?' . $e(http_build_query(['passerelle' => $filtre, 'auto' => 1])) . '">actualisation auto</a></p>'
        . '<table><tr><th>Transaction</th><th>Passerelle</th><th>Montant</th><th>Statut</th><th>Scenario</th><th>Mise a jour</th></tr>'
        . ($lignes ?: '<tr><td colspan="6">Aucune transaction. Lancez un paiement depuis Tchadok.</td></tr>') . '</table></div>'
        . '<aside><div class="carte"><h3>Derniers callbacks</h3><table>' . ($flux ?: '<tr><td>Aucun.</td></tr>') . '</table></div>'
        . '<div class="carte"><h3>Reglages</h3><form method="post" action="/reglages">' . $champJeton
        . '<label>Facteur de delai des callbacks <input name="facteur_delai" value="' . $e($reglages['facteur_delai']) . '" size="4"></label>'
        . '<label>Taux d\'echec aleatoire (%, numeros hors scenarios) <input name="taux_echec" value="' . $e($reglages['taux_echec'] ?? 0) . '" size="4"></label>'
        . '<p>Passerelles en panne (reponse 503) :<br>' . $cases . '</p><button>Enregistrer</button></form></div>'
        . '<div class="carte"><h3>Reinitialisation</h3><form method="post" action="/reinitialiser" onsubmit="return confirm(\'Effacer toutes les transactions simulees ?\')">'
        . $champJeton . '<button class="danger">Tout effacer</button></form>'
        . '<p class="petit">Scenarios : docs/paiement/jeux-de-test.md</p></div></aside></div>';
}

$message = isset($_GET['m']) ? '<p class="message">' . $e($_GET['m']) . '</p>' : '';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . (isset($_GET['auto']) ? '<meta http-equiv="refresh" content="4">' : '')
    . '<title>' . $e($titre) . ' — Console des simulateurs Tchadok</title><style>'
    . ':root{color-scheme:light dark;--fond:#f4f6fa;--carte:#fff;--texte:#1a1f36;--doux:#5b6478;--bord:#dde2ea}'
    . '@media (prefers-color-scheme:dark){:root{--fond:#0b0f17;--carte:#151b27;--texte:#e6e9ef;--doux:#9aa3b2;--bord:#263041}}'
    . 'body{margin:0;font:14px/1.45 system-ui,sans-serif;background:var(--fond);color:var(--texte)}header{background:#1a1f71;color:#fff;padding:12px 16px}'
    . 'header strong{font-size:16px}header span{opacity:.75;margin-left:8px}main{padding:16px;max-width:1400px;margin:0 auto}'
    . '.grille{display:grid;grid-template-columns:1fr 340px;gap:16px}@media(max-width:900px){.grille{grid-template-columns:1fr}}'
    . 'table{width:100%;border-collapse:collapse;background:var(--carte);font-size:13px}th,td{padding:6px 8px;border-bottom:1px solid var(--bord);text-align:left;vertical-align:top}'
    . '.num{text-align:right;white-space:nowrap}.carte{background:var(--carte);border:1px solid var(--bord);border-radius:10px;padding:12px 14px;margin-bottom:16px}'
    . '.carte table{background:none}.b{padding:2px 7px;border-radius:9px;font-size:12px;background:#8883}.b-succeeded{background:#16a34a33}.b-failed,.b-disputed{background:#dc262633}.b-pending{background:#eab30833}.b-cancelled,.b-refunded{background:#64748b33}'
    . '.h2{color:#16a34a}.h4{color:#d97706}.h5,.h0{color:#dc2626}.actions{display:flex;flex-wrap:wrap;gap:8px}.actions form{margin:0}'
    . 'button{padding:7px 12px;border-radius:7px;border:1px solid var(--bord);background:#1a1f71;color:#fff;cursor:pointer}button.danger{background:#b42318}'
    . 'label{display:block;margin:6px 0}input{padding:4px 6px}pre{background:var(--carte);border:1px solid var(--bord);padding:10px;overflow:auto;font-size:12px}'
    . '.message{background:#16a34a22;padding:8px 12px;border-radius:8px}.alerte{background:#dc262622;padding:8px 12px;border-radius:8px}'
    . '.filtres a{margin-right:6px}.filtres a.actif{font-weight:700}.petit{font-size:12px;color:var(--doux)}a{color:inherit}'
    . '</style></head><body><header><strong>Console des simulateurs de paiement</strong><span>LOCAL — aucun argent reel</span></header><main>'
    . $message . '<h1 style="font-size:18px">' . $e($titre) . '</h1>' . $contenu . '</main></body></html>';
