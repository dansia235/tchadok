<?php
/**
 * Implementation du contrat generique (PAY-01).
 *
 * Contrat : docs/paiement/contrat-generique.md. Les quatre adaptateurs en
 * heritent tant que la documentation officielle des partenaires n'est pas
 * obtenue ; le jour ou elle l'est, l'adaptateur concerne redefinit les
 * methodes qui different, sans toucher au reste.
 *
 * CE QUI COMPTE ICI
 *   - La cle d'API ne circule jamais : elle signe la requete.
 *   - Delais explicites (connexion 5 s, total 20 s) : sans eux, un operateur
 *     lent bloque un processus Apache indefiniment.
 *   - Verification TLS active, et HTTPS exige en mode « live ».
 *   - Une erreur reseau n'est PAS un refus : le resultat le dit (echange =
 *     false), et la machine a etats laisse alors la tentative en attente,
 *     a verifier plus tard, au lieu de la declarer echouee.
 *   - Aucune donnee de carte ne passe par ici : le parcours « hosted »
 *     n'echange que des references.
 */

declare(strict_types=1);

abstract class PasserelleGenerique implements PasserellePaiement
{
    /** Ecart d'horloge tolere, en secondes, sur les requetes et les callbacks. */
    public const TOLERANCE_HORLOGE = 300;

    private const DELAI_CONNEXION = 5;
    private const DELAI_TOTAL = 20;

    private ?Closure $journal = null;

    public function __construct(
        protected readonly string $urlBase,
        protected readonly string $idMarchand,
        protected readonly string $cleApi,
        protected readonly string $secretCallback,
        protected readonly bool $modeReel = false,
    ) {
    }

    public function accepteDevise(string $devise): bool
    {
        return strtoupper($devise) === 'XAF';
    }

    /**
     * Par defaut, numero tchadien : 8 chiffres, prefixe 235 facultatif.
     */
    public function normaliserNumero(string $numero): ?string
    {
        $chiffres = preg_replace('/[\s\-\.()]/', '', $numero) ?? '';
        if (preg_match('/^(?:\+?235)?([0-9]{8})$/', $chiffres, $m)) {
            return $m[1];
        }
        return null;
    }

    public function journaliserAvec(?Closure $journal): void
    {
        $this->journal = $journal;
    }

    public function initier(DemandePaiement $demande): ResultatPasserelle
    {
        $corps = [
            'reference'    => $demande->reference,
            'amount'       => $demande->montant,
            'currency'     => $demande->devise,
            'flow'         => $this->parcours(),
            'msisdn'       => $this->parcours() === 'push' ? $demande->msisdn : null,
            'description'  => $demande->description,
            'callback_url' => $demande->urlCallback,
            'return_url'   => $this->parcours() === 'hosted' ? $demande->urlRetour : null,
        ];

        [$code, $reponse, $erreur] = $this->requete('POST', '/v1/payments', $corps, 'initiation', [
            'Idempotency-Key' => $demande->cleIdempotence,
        ]);

        return $this->interpreter($code, $reponse, $erreur);
    }

    public function statut(string $referencePasserelle): ResultatPasserelle
    {
        [$code, $reponse, $erreur] = $this->requete(
            'GET',
            '/v1/payments/' . rawurlencode($referencePasserelle),
            null,
            'consultation'
        );

        return $this->interpreter($code, $reponse, $erreur);
    }

    public function rembourser(string $referencePasserelle, int $montant, string $motif): ResultatPasserelle
    {
        [$code, $reponse, $erreur] = $this->requete(
            'POST',
            '/v1/payments/' . rawurlencode($referencePasserelle) . '/refund',
            ['amount' => $montant, 'reason' => mb_substr($motif, 0, 200)],
            'remboursement'
        );

        return $this->interpreter($code, $reponse, $erreur);
    }

    public function verser(string $reference, int $montant, string $msisdn, string $urlCallback, string $cleIdempotence): ResultatPasserelle
    {
        [$code, $reponse, $erreur] = $this->requete('POST', '/v1/disbursements', [
            'reference'    => $reference,
            'amount'       => $montant,
            'currency'     => 'XAF',
            'msisdn'       => $msisdn,
            'description'  => 'Versement Tchadok',
            'callback_url' => $urlCallback,
        ], 'versement', ['Idempotency-Key' => $cleIdempotence]);

        return $this->interpreter($code, $reponse, $erreur);
    }

    public function statutVersement(string $referencePasserelle): ResultatPasserelle
    {
        [$code, $reponse, $erreur] = $this->requete('GET', '/v1/disbursements/' . rawurlencode($referencePasserelle), null, 'statut_versement');

        return $this->interpreter($code, $reponse, $erreur);
    }

    public function releve(string $date): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        [$code, $reponse, $erreur] = $this->requete('GET', '/v1/statements?date=' . $date, null, 'releve');
        if ($erreur !== null || $code !== 200 || !is_array($reponse['transactions'] ?? null)) {
            return null;
        }

        $lignes = [];
        foreach ($reponse['transactions'] as $t) {
            if (!is_array($t) || empty($t['id']) || !isset($t['amount'], $t['currency'], $t['status'])) {
                continue;
            }
            $lignes[] = [
                'reference' => (string) $t['id'],
                'tentative' => isset($t['reference']) ? (string) $t['reference'] : null,
                'montant'   => (int) $t['amount'],
                'devise'    => strtoupper((string) $t['currency']),
                'statut'    => (string) $t['status'],
            ];
        }
        return $lignes;
    }

    public function verifierCallback(array $entetes, string $corps): ResultatCallback
    {
        $signature = (string) ($entetes['x-signature'] ?? '');
        if ($signature === '') {
            return ResultatCallback::rejete('signature_absente');
        }

        $parties = [];
        foreach (explode(',', $signature) as $morceau) {
            [$cle, $valeur] = array_pad(explode('=', trim($morceau), 2), 2, '');
            $parties[$cle] = $valeur;
        }
        $horodatage = $parties['t'] ?? '';
        $empreinte = $parties['v1'] ?? '';

        if (!ctype_digit($horodatage) || $empreinte === '') {
            return ResultatCallback::rejete('signature_illisible', $signature);
        }
        if (abs(time() - (int) $horodatage) > self::TOLERANCE_HORLOGE) {
            return ResultatCallback::rejete('horodatage_hors_tolerance', $signature);
        }

        $attendue = hash_hmac('sha256', $horodatage . '.' . $corps, $this->secretCallback);
        if (!hash_equals($attendue, strtolower($empreinte))) {
            return ResultatCallback::rejete('signature_invalide', $signature);
        }

        $donnees = json_decode($corps, true);
        // Paiement entrant (« payment ») ou versement sortant (« disbursement »).
        $paiement = is_array($donnees) ? ($donnees['payment'] ?? $donnees['disbursement'] ?? null) : null;
        if (!is_array($donnees) || !is_array($paiement) || empty($paiement['id']) || empty($donnees['event'])) {
            return ResultatCallback::rejete('corps_illisible', $signature);
        }

        return new ResultatCallback(
            valide: true,
            evenement: (string) $donnees['event'],
            idEvenement: isset($donnees['event_id']) ? (string) $donnees['event_id'] : null,
            referencePasserelle: (string) $paiement['id'],
            referenceTentative: isset($paiement['reference']) ? (string) $paiement['reference'] : null,
            statut: isset($paiement['status']) ? (string) $paiement['status'] : null,
            montant: isset($paiement['amount']) && is_numeric($paiement['amount']) ? (int) $paiement['amount'] : null,
            devise: isset($paiement['currency']) ? strtoupper((string) $paiement['currency']) : null,
            codeEchec: isset($paiement['failure_code']) ? (string) $paiement['failure_code'] : null,
            signature: $signature,
        );
    }

    // -----------------------------------------------------------------
    // Transport
    // -----------------------------------------------------------------

    /**
     * @return array{0:int, 1:?array, 2:?string} code HTTP (0 si aucun), reponse decodee, erreur reseau
     */
    private function requete(string $methode, string $chemin, ?array $corps, string $nature, array $enTetesSup = []): array
    {
        if ($this->urlBase === '' || $this->idMarchand === '' || $this->cleApi === '') {
            return [0, null, 'configuration_incomplete'];
        }
        if ($this->modeReel && !str_starts_with(strtolower($this->urlBase), 'https://')) {
            return [0, null, 'https_obligatoire'];
        }

        $json = $corps === null ? '' : (string) json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $horodatage = (string) time();
        $cheminSigne = (string) parse_url(rtrim($this->urlBase, '/') . $chemin, PHP_URL_PATH);
        $signature = hash_hmac('sha256', $horodatage . '.' . $methode . '.' . $cheminSigne . '.' . $json, $this->cleApi);

        $enTetes = [
            'Accept: application/json',
            'X-Merchant-Id: ' . $this->idMarchand,
            'X-Timestamp: ' . $horodatage,
            'X-Signature: ' . $signature,
        ];
        if ($json !== '') {
            $enTetes[] = 'Content-Type: application/json';
        }
        foreach ($enTetesSup as $nom => $valeur) {
            $enTetes[] = $nom . ': ' . $valeur;
        }

        $this->journaliser('request', $nature, null, $methode . ' ' . $chemin . ($json !== '' ? ' ' . $json : ''));

        $curl = curl_init(rtrim($this->urlBase, '/') . $chemin);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $enTetes,
            CURLOPT_CONNECTTIMEOUT => self::DELAI_CONNEXION,
            CURLOPT_TIMEOUT        => self::DELAI_TOTAL,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($json !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }

        $brut = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erreurReseau = $brut === false ? (curl_error($curl) ?: 'erreur_reseau') : null;

        if ($erreurReseau !== null) {
            $this->journaliser('response', $nature, null, 'ERREUR RESEAU : ' . $erreurReseau);
            return [0, null, $erreurReseau];
        }

        $this->journaliser('response', $nature, $code, (string) $brut);
        $decode = json_decode((string) $brut, true);

        return [$code, is_array($decode) ? $decode : null, null];
    }

    private function interpreter(int $code, ?array $reponse, ?string $erreurReseau): ResultatPasserelle
    {
        if ($erreurReseau !== null) {
            return ResultatPasserelle::erreur('reseau', $erreurReseau);
        }

        if ($code < 200 || $code >= 300 || $reponse === null) {
            $erreur = is_array($reponse['error'] ?? null) ? $reponse['error'] : [];
            return ResultatPasserelle::erreur(
                (string) ($erreur['code'] ?? 'http_' . $code),
                (string) ($erreur['message'] ?? 'Reponse inattendue de l\'operateur.'),
                $code
            );
        }

        return new ResultatPasserelle(
            echange: true,
            statut: isset($reponse['status']) ? (string) $reponse['status'] : null,
            reference: isset($reponse['id']) ? (string) $reponse['id'] : (isset($reponse['refund_id']) ? (string) $reponse['refund_id'] : null),
            montant: isset($reponse['amount']) && is_numeric($reponse['amount']) ? (int) $reponse['amount'] : null,
            devise: isset($reponse['currency']) ? strtoupper((string) $reponse['currency']) : null,
            urlRedirection: isset($reponse['redirect_url']) ? (string) $reponse['redirect_url'] : null,
            codeErreur: isset($reponse['failure_code']) ? (string) $reponse['failure_code'] : null,
            httpStatus: $code,
        );
    }

    private function journaliser(string $direction, string $nature, ?int $code, string $contenu): void
    {
        if ($this->journal !== null) {
            ($this->journal)([
                'direction'   => $direction,
                'event_type'  => $nature,
                'http_status' => $code,
                'payload'     => mb_substr($contenu, 0, 60000),
            ]);
        }
    }
}
