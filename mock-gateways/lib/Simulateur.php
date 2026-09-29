<?php
/**
 * Serveur HTTP d'un simulateur de passerelle (PAY-03, PAY-05 a PAY-08).
 *
 * Implemente le contrat generique (docs/paiement/contrat-generique.md) :
 *   POST /v1/payments               initiation (push ou hosted)
 *   GET  /v1/payments/{id}          statut
 *   POST /v1/payments/{id}/refund   remboursement
 * et, pour le parcours « hosted » (VISA), la page de l'acquereur :
 *   GET  /hosted/{id}               saisie de la carte
 *   POST /hosted/{id}/submit        traitement
 *   GET|POST /hosted/{id}/3ds       defi 3-D Secure
 *   GET  /hosted/{id}/cancel        abandon par le client
 *
 * Toute requete de l'API exige une signature valide : un mauvais secret, un
 * marchand inconnu ou un horodatage hors fenetre sont rejetes en 401, comme
 * le ferait un operateur. Un simulateur complaisant donnerait une fausse
 * confiance.
 *
 * AUCUN numero de carte n'est conserve, meme ici : seul le libelle du
 * scenario est inscrit dans la transaction.
 */

declare(strict_types=1);

final class Simulateur
{
    private const TOLERANCE = 300;

    /** @param array<string,mixed> $config */
    private function __construct(private readonly array $config)
    {
    }

    public static function servir(string $code): void
    {
        (new self(configurationSimulateur($code)))->router();
    }

    private function router(): void
    {
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        if ($chemin === '/' || $chemin === '/sante') {
            $this->json(200, ['simulateur' => $this->config['libelle'], 'parcours' => $this->config['parcours'], 'etat' => 'ok']);
        }

        // PAYOUT-04 : versements sortants (portefeuilles mobile money seulement).
        if (preg_match('#^/v1/disbursements(?:/([A-Z]{3}-[A-F0-9]{16}))?$#', $chemin, $m)) {
            if ($this->config['parcours'] !== 'push') {
                $this->erreur(404, 'not_found', 'Cet operateur ne fait pas de versement sortant.');
            }
            if (Magasin::indisponible($this->config['code'])) {
                $this->erreur(503, 'service_unavailable', 'Service momentanement indisponible (panne simulee depuis la console).');
            }
            $corps = (string) file_get_contents('php://input');
            $this->authentifier($methode, $chemin, $corps);
            if (($m[1] ?? '') === '' && $methode === 'POST') {
                $this->verser($corps);
            }
            if (($m[1] ?? '') !== '' && $methode === 'GET') {
                $t = Magasin::lire($this->config['code'], $m[1]);
                if (!$t || ($t['kind'] ?? '') !== 'disbursement') {
                    $this->erreur(404, 'not_found', 'Versement inconnu.');
                }
                $this->json(200, $this->vue($t));
            }
            $this->erreur(405, 'invalid_request', 'Methode non prise en charge.');
        }

        // PAY-10 : releve quotidien pour le rapprochement.
        if ($chemin === '/v1/statements' && $methode === 'GET') {
            if (Magasin::indisponible($this->config['code'])) {
                $this->erreur(503, 'service_unavailable', 'Service momentanement indisponible (panne simulee depuis la console).');
            }
            $this->authentifier($methode, $chemin, '');
            $this->releve((string) ($_GET['date'] ?? ''));
        }

        if (preg_match('#^/v1/payments(?:/([A-Z]{3}-[A-F0-9]{16})(/refund)?)?$#', $chemin, $m)) {
            // PAY-09 : panne simulee depuis la console.
            if (Magasin::indisponible($this->config['code'])) {
                $this->erreur(503, 'service_unavailable', 'Service momentanement indisponible (panne simulee depuis la console).');
            }
            $corps = (string) file_get_contents('php://input');
            $this->authentifier($methode, $chemin, $corps);
            $id = $m[1] ?? '';
            $remboursement = ($m[2] ?? '') !== '';

            if ($id === '' && $methode === 'POST') {
                $this->initier($corps);
            }
            if ($id !== '' && !$remboursement && $methode === 'GET') {
                $this->statut($id);
            }
            if ($id !== '' && $remboursement && $methode === 'POST') {
                $this->rembourser($id, $corps);
            }
            $this->erreur(405, 'invalid_request', 'Methode non prise en charge.');
        }

        if ($this->config['parcours'] === 'hosted'
            && preg_match('#^/hosted/([A-Z]{3}-[A-F0-9]{16})(?:/(submit|3ds|cancel))?$#', $chemin, $m)) {
            (new PageHebergee($this->config))->traiter($m[1], $m[2] ?? '', $methode);
        }

        $this->erreur(404, 'not_found', 'Ressource inconnue.');
    }

    // -----------------------------------------------------------------
    // API
    // -----------------------------------------------------------------

    private function authentifier(string $methode, string $chemin, string $corps): void
    {
        $marchand = (string) ($_SERVER['HTTP_X_MERCHANT_ID'] ?? '');
        $horodatage = (string) ($_SERVER['HTTP_X_TIMESTAMP'] ?? '');
        $signature = strtolower((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''));

        if ($marchand === '' || !hash_equals($this->config['marchand'], $marchand)) {
            $this->erreur(401, 'unknown_merchant', 'Marchand inconnu.');
        }
        if (!ctype_digit($horodatage) || abs(time() - (int) $horodatage) > self::TOLERANCE) {
            $this->erreur(401, 'timestamp_out_of_range', 'Horodatage absent ou hors de la fenetre de 300 secondes.');
        }
        $attendue = hash_hmac('sha256', $horodatage . '.' . $methode . '.' . $chemin . '.' . $corps, $this->config['cle']);
        if (!hash_equals($attendue, $signature)) {
            $this->erreur(401, 'invalid_signature', 'Signature de requete invalide.');
        }
    }

    private function initier(string $corps): never
    {
        $demande = json_decode($corps, true);
        if (!is_array($demande)) {
            $this->erreur(400, 'invalid_request', 'Corps JSON attendu.');
        }

        $cle = strtolower((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if ($cle !== '' && ($existant = Magasin::idPourCle($this->config['code'], $cle)) !== null) {
            $transaction = Magasin::lire($this->config['code'], $existant);
            if ($transaction) {
                $this->json(200, $this->vue($transaction));
            }
        }

        // Montant en unites mineures (XAF : francs ; USD : cents). Le dollar
        // n'est accepte que par l'acquereur carte (parcours hosted).
        $devises = $this->config['parcours'] === 'hosted' ? ['XAF', 'USD'] : ['XAF'];
        $montant = $demande['amount'] ?? null;
        if (!is_int($montant) || $montant <= 0 || !in_array($demande['currency'] ?? '', $devises, true)
            || empty($demande['reference']) || empty($demande['callback_url'])
            || ($demande['flow'] ?? '') !== $this->config['parcours']) {
            $this->erreur(422, 'invalid_request', 'Montant, devise (' . implode('/', $devises) . '), reference, parcours ou adresse de notification invalide.');
        }

        $transaction = [
            'id'           => $this->config['id'] . '-' . strtoupper(bin2hex(random_bytes(8))),
            'reference'    => (string) $demande['reference'],
            'amount'       => $montant,
            'currency'     => (string) $demande['currency'],
            'flow'         => $this->config['parcours'],
            'status'       => 'pending',
            'failure_code' => null,
            'callback_url' => (string) $demande['callback_url'],
            'return_url'   => $demande['return_url'] ?? null,
            'description'  => (string) ($demande['description'] ?? ''),
            'created_at'   => date('c'),
            'updated_at'   => date('c'),
        ];

        if ($this->config['parcours'] === 'push') {
            $msisdn = preg_replace('/\D/', '', (string) ($demande['msisdn'] ?? '')) ?? '';
            if (strlen($msisdn) < 8 || strlen($msisdn) > 12) {
                $this->erreur(422, 'invalid_msisdn', 'Numero de telephone invalide.');
            }
            [$libelle, $statut, $echec, $delai, $envois, $alteration, $panne, $numero] = Scenarios::pourNumero($msisdn, $this->config['indicatif']);
            if ($panne) {
                $this->erreur(500, 'internal_error', 'Erreur interne simulee (scenario 10).');
            }
            // PAY-09 : taux d'echec aleatoire, applique aux seuls numeros hors
            // scenarios -- un scenario explicite doit rester reproductible.
            if ($numero === 'defaut' && Magasin::tauxEchec() > 0 && random_int(1, 100) <= Magasin::tauxEchec()) {
                [$libelle, $statut, $echec] = ['Echec aleatoire (console)', 'failed', 'insufficient_funds'];
            }
            $transaction['msisdn'] = $msisdn;
            $transaction['scenario'] = $numero . ' - ' . $libelle;

            Magasin::creer($this->config['code'], $transaction);
            if ($cle !== '') {
                Magasin::retenirCle($this->config['code'], $cle, $transaction['id']);
            }

            // L'abonne confirme sur son telephone : le statut change a
            // l'echeance, puis le callback part. Les envois suivants (scenario
            // 7) rejouent le meme evenement.
            $evenement = ['succeeded' => 'payment.succeeded', 'failed' => 'payment.failed', 'cancelled' => 'payment.cancelled'][$statut] ?? null;
            for ($i = 0; $evenement !== null && $i < $envois; $i++) {
                Magasin::programmer([
                    'passerelle' => $this->config['code'],
                    'id'         => $transaction['id'],
                    'evenement'  => $evenement,
                    'delai'      => $delai + $i,
                    'statut'     => $i === 0 ? $statut : null,
                    'echec'      => $echec,
                    'alteration' => $alteration,
                ]);
            }
        } else {
            if (empty($demande['return_url'])) {
                $this->erreur(422, 'invalid_request', 'return_url obligatoire pour le parcours hosted.');
            }
            $transaction['scenario'] = 'en attente de la saisie de la carte';
            Magasin::creer($this->config['code'], $transaction);
            if ($cle !== '') {
                Magasin::retenirCle($this->config['code'], $cle, $transaction['id']);
            }
        }

        $this->json(201, $this->vue($transaction));
    }

    /**
     * Versement sortant du marchand vers le portefeuille d'un artiste.
     */
    private function verser(string $corps): never
    {
        $demande = json_decode($corps, true);
        $cle = strtolower((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if ($cle !== '' && ($existant = Magasin::idPourCle($this->config['code'], $cle)) !== null && ($t = Magasin::lire($this->config['code'], $existant))) {
            $this->json(200, $this->vue($t));
        }
        $montant = is_array($demande) ? ($demande['amount'] ?? null) : null;
        $msisdn = preg_replace('/\D/', '', (string) ($demande['msisdn'] ?? '')) ?? '';
        if (!is_int($montant) || $montant <= 0 || ($demande['currency'] ?? '') !== 'XAF' || empty($demande['reference']) || empty($demande['callback_url'])) {
            $this->erreur(422, 'invalid_request', 'Montant, devise, reference ou adresse de notification invalide.');
        }
        [$libelle, $statut, $echec, $delai, $envois, $refus] = Scenarios::pourVersement($msisdn, $this->config['indicatif']);
        if ($refus || strlen($msisdn) < 8) {
            $this->erreur(422, 'invalid_msisdn', 'Numero de beneficiaire invalide.');
        }

        $t = [
            'id' => $this->config['id'] . '-' . strtoupper(bin2hex(random_bytes(8))),
            'kind' => 'disbursement',
            'reference' => (string) $demande['reference'], 'amount' => $montant, 'currency' => 'XAF', 'flow' => 'push',
            'msisdn' => $msisdn, 'status' => 'pending', 'failure_code' => null,
            'callback_url' => (string) $demande['callback_url'], 'return_url' => null,
            'description' => (string) ($demande['description'] ?? 'Versement'),
            'scenario' => 'Versement : ' . $libelle, 'created_at' => date('c'), 'updated_at' => date('c'),
        ];
        Magasin::creer($this->config['code'], $t);
        if ($cle !== '') {
            Magasin::retenirCle($this->config['code'], $cle, $t['id']);
        }
        $evenement = $statut === 'succeeded' ? 'disbursement.succeeded' : 'disbursement.failed';
        for ($i = 0; $i < $envois; $i++) {
            Magasin::programmer([
                'passerelle' => $this->config['code'], 'id' => $t['id'], 'evenement' => $evenement,
                'delai' => $delai + $i, 'statut' => $i === 0 ? $statut : null, 'echec' => $echec,
            ]);
        }
        $this->json(201, $this->vue($t));
    }

    private function statut(string $id): never
    {
        $transaction = Magasin::lire($this->config['code'], $id);
        if (!$transaction) {
            $this->erreur(404, 'not_found', 'Transaction inconnue.');
        }
        $this->json(200, $this->vue($transaction));
    }

    private function rembourser(string $id, string $corps): never
    {
        $demande = json_decode($corps, true);
        $refus = null;
        $transaction = Magasin::modifier($this->config['code'], $id, function (array $t) use ($demande, &$refus): array {
            $montant = is_array($demande) && is_int($demande['amount'] ?? null) ? $demande['amount'] : $t['amount'];
            if ($t['status'] !== 'succeeded' || $montant <= 0 || $montant > $t['amount']) {
                $refus = true;
                return $t;
            }
            $t['status'] = 'refunded';
            $t['refunded_amount'] = $montant;
            return $t;
        });

        if (!$transaction) {
            $this->erreur(404, 'not_found', 'Transaction inconnue.');
        }
        if ($refus) {
            $this->erreur(409, 'not_refundable', 'Transaction non remboursable dans son etat actuel.');
        }

        Magasin::programmer(['passerelle' => $this->config['code'], 'id' => $id, 'evenement' => 'refund.succeeded', 'delai' => 1]);
        $this->json(200, ['refund_id' => 'RF' . substr($id, 3), 'status' => 'succeeded'] + $this->vue($transaction));
    }

    /**
     * Releve d'une journee : transactions initiees ce jour-la dont l'argent a
     * bouge (encaissees, remboursees, contestees). Meme format que le statut,
     * pour que le rapprochement compare terme a terme.
     */
    private function releve(string $date): never
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->erreur(422, 'invalid_request', 'Parametre date attendu (AAAA-MM-JJ).');
        }
        $lignes = [];
        foreach (Magasin::lister($this->config['code'], 100000) as $t) {
            // Le releve des ENCAISSEMENTS ; les versements sortants n'y figurent pas.
            if (($t['kind'] ?? 'payment') !== 'disbursement'
                && substr((string) $t['created_at'], 0, 10) === $date && in_array($t['status'], ['succeeded', 'refunded', 'disputed'], true)) {
                $lignes[] = $this->vue($t);
            }
        }
        $this->json(200, ['date' => $date, 'merchant' => $this->config['marchand'], 'count' => count($lignes), 'transactions' => $lignes]);
    }

    /** @return array<string,mixed> */
    private function vue(array $t): array
    {
        return [
            'id'           => $t['id'],
            'reference'    => $t['reference'],
            'amount'       => $t['amount'],
            'currency'     => $t['currency'],
            'status'       => $t['status'],
            'failure_code' => $t['failure_code'],
            'redirect_url' => $t['flow'] === 'hosted' ? $this->config['base'] . '/hosted/' . $t['id'] : null,
            'updated_at'   => $t['updated_at'],
        ];
    }

    public function json(int $code, array $contenu): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(int $code, string $erreur, string $message): never
    {
        $this->json($code, ['error' => ['code' => $erreur, 'message' => $message]]);
    }
}

require_once __DIR__ . '/PageHebergee.php';
