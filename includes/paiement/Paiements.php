<?php
/**
 * Machine a etats du paiement (PAY-02).
 *
 * C'est le coeur de la fiabilite financiere. Les operateurs mobile money
 * envoient des callbacks en double, dans le desordre, ou tres en retard ; un
 * abonne peut ignorer le message sur son telephone ; un reseau peut couper
 * entre la demande et la reponse. Chaque cas a ici une issue ecrite.
 *
 * REGLES NON NEGOCIABLES
 *   1. Seul un callback SIGNE, ou une consultation authentifiee aupres de
 *      l'operateur, fait passer une commande en `paid`. Jamais le navigateur
 *      du client (le retour de la page VISA n'est pas une preuve), jamais un
 *      appel d'administration.
 *   2. Idempotence : un doublon est accepte (l'operateur doit cesser de
 *      reessayer) mais ne produit AUCUN effet. Garantie finale : les cles
 *      uniques `gateway_ref` en base.
 *   3. Aucun retour arriere : une tentative reussie ne redevient jamais
 *      echouee, quel que soit l'ordre d'arrivee des callbacks.
 *   4. Verrou (`SELECT ... FOR UPDATE`) sur la tentative et la commande :
 *      deux callbacks simultanes sont traites l'un apres l'autre.
 *   5. Expiration : une tentative sans reponse passe en `expired` apres
 *      PAYMENT_INTENT_TTL secondes -- APRES avoir interroge l'operateur, car
 *      le callback a pu se perdre.
 *   6. Montant : il doit correspondre exactement. Tout ecart, et tout second
 *      encaissement d'une commande deja payee, part en REVUE humaine.
 *
 * Une commande echouee, annulee ou expiree peut etre retentee ; chaque essai
 * est une ligne de payment_intents (`attempt`).
 */

declare(strict_types=1);

final class Paiements
{
    /** Etats de commande depuis lesquels un (nouvel) essai est permis. */
    private const COMMANDE_PAYABLE = ['cart', 'awaiting_payment', 'failed', 'cancelled', 'expired'];

    /** Etats de tentative encore ouverts. */
    private const TENTATIVE_OUVERTE = ['created', 'pending'];

    /** Intervalle minimal entre deux consultations d'une meme tentative. */
    private const INTERVALLE_CONSULTATION = 15;

    /** Delai avant la premiere consultation : laisser au callback le temps d'arriver. */
    private const DELAI_PREMIERE_CONSULTATION = 20;

    // -----------------------------------------------------------------
    // Initiation
    // -----------------------------------------------------------------

    /**
     * Lance un paiement pour une commande du membre.
     *
     * @return array{succes:bool, erreur:?string, tentative:?array{id:int, statut:string, redirection:?string, passerelle:string}}
     */
    public static function initier(string $referenceCommande, int $userId, string $codePasserelle, ?string $numero = null, string $devise = 'XAF'): array
    {
        if (!FabriquePasserelles::configuree($codePasserelle)) {
            return self::refus('Ce moyen de paiement n\'est pas disponible.');
        }
        $passerelle = FabriquePasserelles::obtenir($codePasserelle);

        // PAY-07 : la commande reste en XAF ; la devise est celle du DEBIT.
        $devise = strtoupper($devise);
        if (!Devises::existe($devise) || !$passerelle->accepteDevise($devise)) {
            return self::refus($passerelle->libelle() . ' n\'accepte pas le paiement en ' . $devise . '.');
        }
        // Lu AVANT la transaction : en mode automatique, ce peut etre un appel
        // reseau (quelques secondes au plus) qui ne doit tenir aucun verrou.
        $taux = Devises::taux($devise);
        if ($taux === null) {
            return self::refus('Paiement en ' . $devise . ' momentanement indisponible.');
        }
        $origineTaux = Devises::origine($devise);

        $msisdn = null;
        if ($passerelle->parcours() === 'push') {
            $msisdn = $passerelle->normaliserNumero((string) $numero);
            if ($msisdn === null) {
                return self::refus('Numero de telephone invalide pour ' . $passerelle->libelle() . '.');
            }
        }

        $db = self::base();
        if (!$db) {
            return self::refus('Service momentanement indisponible.');
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare('SELECT * FROM orders WHERE reference = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$referenceCommande, $userId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$commande) {
                $db->rollBack();
                return self::refus('Commande introuvable.');
            }
            if ($commande['status'] === 'paid') {
                $db->rollBack();
                return self::refus('Cette commande est deja payee.');
            }
            if (!in_array($commande['status'], self::COMMANDE_PAYABLE, true)) {
                $db->rollBack();
                return self::refus('Cette commande ne peut plus etre payee. Contactez l\'assistance.');
            }
            if ((float) $commande['total'] <= 0) {
                $db->rollBack();
                return self::refus('Commande vide.');
            }
            if (!$passerelle->accepteDevise((string) $commande['currency'])) {
                $db->rollBack();
                return self::refus($passerelle->libelle() . ' n\'accepte pas la devise ' . $commande['currency'] . '.');
            }

            // Une tentative encore ouverte ?
            $stmt = $db->prepare(
                "SELECT * FROM payment_intents
                  WHERE order_id = ? AND status IN ('created', 'pending') AND expires_at > NOW()
                  ORDER BY attempt DESC LIMIT 1"
            );
            $stmt->execute([$commande['id']]);
            $ouverte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ouverte) {
                // Double clic, ou retour sur la page : on rend la meme tentative,
                // sans nouvelle demande a l'operateur.
                if ($ouverte['gateway'] === $codePasserelle && (string) $ouverte['msisdn'] === (string) $msisdn
                    && $ouverte['currency'] === $devise && $ouverte['status'] === 'pending') {
                    $db->commit();
                    return self::accepte($ouverte);
                }

                if (FabriquePasserelles::existe($ouverte['gateway'])
                    && FabriquePasserelles::obtenir($ouverte['gateway'])->parcours() === 'hosted') {
                    // Page de l'acquereur abandonnee : on la remplace. Si le
                    // client la termine malgre tout, le callback tardif est
                    // traite (encaissement, ou revue si la commande a ete payee
                    // entre-temps) -- l'argent n'est jamais perdu de vue.
                    $db->prepare(
                        "UPDATE payment_intents SET status = 'cancelled', error_code = 'remplacee', completed_at = NOW()
                          WHERE id = ?"
                    )->execute([$ouverte['id']]);
                } else {
                    $db->rollBack();
                    $libelle = FabriquePasserelles::existe($ouverte['gateway'])
                        ? FabriquePasserelles::obtenir($ouverte['gateway'])->libelle() : 'precedent';
                    return self::refus(sprintf(
                        'Un paiement %s est deja en attente de confirmation. Validez-le sur votre telephone, ou reessayez apres son expiration.',
                        $libelle
                    ));
                }
            }

            $stmt = $db->prepare('SELECT COALESCE(MAX(attempt), 0) + 1 FROM payment_intents WHERE order_id = ?');
            $stmt->execute([$commande['id']]);
            $essai = (int) $stmt->fetchColumn();

            // Montant debite, dans la devise choisie, au taux FIGE maintenant :
            // un changement de taux n'affecte jamais une tentative en cours.
            $montant = Devises::convertir((float) $commande['total'], $devise);
            if ($montant === null || $montant <= 0) {
                $db->rollBack();
                return self::refus('Conversion en ' . $devise . ' impossible.');
            }

            $cle = bin2hex(random_bytes(16));
            $db->prepare(
                "INSERT INTO payment_intents
                    (order_id, attempt, gateway, amount, currency, amount_xaf, exchange_rate, rate_source, status, idempotency_key, msisdn, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'created', ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
            )->execute([
                $commande['id'], $essai, $codePasserelle, $montant, $devise, $commande['total'], $taux, $origineTaux,
                $cle, $msisdn, self::dureeDeVie(),
            ]);
            $intentId = (int) $db->lastInsertId();

            $db->prepare("UPDATE orders SET status = 'awaiting_payment', payment_method = ? WHERE id = ?")
               ->execute([$codePasserelle, $commande['id']]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][paiement] initiation impossible : ' . $e->getMessage());
            return self::refus('Paiement impossible pour le moment.');
        }

        // Appel a l'operateur HORS transaction : on ne garde pas de verrou
        // pendant un echange reseau de plusieurs secondes.
        $base = rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE', ''), '/');
        $demande = new DemandePaiement(
            reference: $referenceCommande . '-' . $essai,
            montant: Devises::enUnitesMineures($montant, $devise),
            devise: $devise,
            description: 'Tchadok - commande ' . $referenceCommande,
            urlCallback: $base . '/api/payments/callback.php?passerelle=' . rawurlencode($codePasserelle),
            cleIdempotence: $cle,
            msisdn: $msisdn,
            urlRetour: $base . '/paiement.php?commande=' . rawurlencode($referenceCommande) . '&tentative=' . $intentId,
        );

        $resultat = self::avecJournal($passerelle, (int) $commande['id'], $intentId)->initier($demande);

        if ($resultat->echange && $resultat->reference !== null) {
            $db->prepare(
                "UPDATE payment_intents SET status = 'pending', gateway_ref = ?, redirect_url = ?
                  WHERE id = ? AND status = 'created'"
            )->execute([$resultat->reference, $resultat->urlRedirection, $intentId]);

            // L'operateur peut avoir tranche des l'initiation.
            if (in_array($resultat->statut, ['succeeded', 'failed', 'cancelled'], true)) {
                self::appliquer($intentId, $resultat->statut, $resultat->montant, $resultat->devise, $resultat->reference, $resultat->codeErreur, 'initiation');
            }

            return self::accepte(self::tentative($intentId) ?? []);
        }

        if ($resultat->codeErreur === 'reseau') {
            // On ne sait pas si l'operateur a recu la demande : la tentative
            // reste ouverte. Un callback eventuel la retrouvera par sa
            // reference ; sinon elle expirera.
            $db->prepare("UPDATE payment_intents SET status = 'pending', error_code = 'reseau', error_message = ? WHERE id = ?")
               ->execute([mb_substr((string) $resultat->messageErreur, 0, 500), $intentId]);
            error_log('[Tchadok][paiement] reponse de l\'operateur non recue, tentative ' . $intentId . ' laissee en attente');
            return self::accepte(self::tentative($intentId) ?? []);
        }

        self::clore($intentId, 'failed', $resultat->codeErreur, $resultat->messageErreur);
        return self::refus(self::messageErreur($resultat->codeErreur));
    }

    // -----------------------------------------------------------------
    // Callbacks
    // -----------------------------------------------------------------

    /**
     * Traite un callback d'operateur.
     *
     * @param array<string,string> $entetes noms en minuscules
     * @return array{http:int, resultat:string}
     */
    public static function traiterCallback(string $codePasserelle, array $entetes, string $corps, ?string $ip): array
    {
        if (!FabriquePasserelles::existe($codePasserelle)) {
            Commandes::enregistrerEvenement([
                'gateway' => 'inconnue', 'direction' => 'callback', 'event_type' => 'passerelle_inconnue',
                'payload' => mb_substr($corps, 0, 60000), 'signature_valid' => false, 'ip_address' => $ip,
            ]);
            return ['http' => 404, 'resultat' => 'passerelle_inconnue'];
        }

        $passerelle = FabriquePasserelles::obtenir($codePasserelle);
        $verification = $passerelle->verifierCallback($entetes, $corps);

        // Rattachement, meme pour un callback rejete : la preuve doit pouvoir
        // etre retrouvee depuis la commande.
        $intent = null;
        if ($verification->valide) {
            $intent = self::retrouver($codePasserelle, $verification->referencePasserelle, $verification->referenceTentative);
        } else {
            $brut = json_decode($corps, true);
            $paiement = is_array($brut) && is_array($brut['payment'] ?? null) ? $brut['payment'] : [];
            $intent = self::retrouver(
                $codePasserelle,
                isset($paiement['id']) ? (string) $paiement['id'] : null,
                isset($paiement['reference']) ? (string) $paiement['reference'] : null
            );
        }

        // La preuve AVANT le traitement : si la suite echoue, elle existe.
        $inscrit = Commandes::enregistrerEvenement([
            'order_id'        => $intent['order_id'] ?? null,
            'intent_id'       => $intent['id'] ?? null,
            'gateway'         => $codePasserelle,
            'direction'       => 'callback',
            'event_type'      => $verification->valide ? $verification->evenement : 'rejete:' . $verification->motifRejet,
            'payload'         => mb_substr($corps, 0, 60000),
            'signature'       => $verification->signature !== null ? mb_substr($verification->signature, 0, 255) : null,
            'signature_valid' => $verification->valide,
            'ip_address'      => $ip,
        ]);
        if (!$inscrit) {
            // Sans trace, on ne traite pas : l'operateur reessaiera.
            return ['http' => 500, 'resultat' => 'journal_indisponible'];
        }

        if (!$verification->valide) {
            error_log(sprintf('[Tchadok][paiement][ALERTE] callback %s rejete (%s) depuis %s', $codePasserelle, $verification->motifRejet, $ip ?? '?'));
            return ['http' => 401, 'resultat' => 'signature_rejetee'];
        }

        // LOT 8 : notification d'un versement sortant vers un artiste.
        if (str_starts_with((string) $verification->evenement, 'disbursement.') && class_exists('Versements')) {
            try {
                $issue = Versements::appliquerCallback(
                    $codePasserelle, (string) $verification->referencePasserelle,
                    $verification->evenement === 'disbursement.succeeded' ? 'succeeded' : 'failed',
                    $verification->montant, $verification->codeEchec
                );
            } catch (Throwable $e) {
                error_log('[Tchadok][versement] notification non traitee : ' . $e->getMessage());
                return ['http' => 500, 'resultat' => 'erreur_traitement'];
            }
            return ['http' => 200, 'resultat' => $issue];
        }

        if ($intent === null) {
            error_log(sprintf('[Tchadok][paiement][ALERTE] callback %s pour une tentative inconnue (%s)', $codePasserelle, $verification->referencePasserelle));
            return ['http' => 200, 'resultat' => 'tentative_inconnue'];
        }

        $statut = match ($verification->evenement) {
            'payment.succeeded' => 'succeeded',
            'payment.failed'    => 'failed',
            'payment.cancelled' => 'cancelled',
            'payment.disputed'  => 'disputed',
            default             => null,
        };
        if ($verification->evenement === 'refund.succeeded') {
            // SHOP-07 : l'operateur confirme un remboursement.
            $confirme = class_exists('Remboursements')
                && Remboursements::confirmer($codePasserelle, (string) $verification->referencePasserelle);
            return ['http' => 200, 'resultat' => $confirme ? 'remboursement_confirme' : 'evenement_journalise'];
        }
        if ($statut === null) {
            // Evenements futurs : journalises ci-dessus, sans effet metier.
            return ['http' => 200, 'resultat' => 'evenement_journalise'];
        }

        try {
            $issue = self::appliquer(
                (int) $intent['id'], $statut, $verification->montant, $verification->devise,
                $verification->referencePasserelle, $verification->codeEchec, 'callback'
            );
        } catch (Throwable $e) {
            error_log('[Tchadok][paiement] callback non traite : ' . $e->getMessage());
            return ['http' => 500, 'resultat' => 'erreur_traitement'];
        }

        return ['http' => 200, 'resultat' => $issue];
    }

    // -----------------------------------------------------------------
    // Consultation et expiration
    // -----------------------------------------------------------------

    /**
     * Interroge l'operateur sur une tentative et applique sa reponse.
     */
    public static function verifierStatut(int $intentId): ?string
    {
        $intent = self::tentative($intentId);
        if (!$intent || empty($intent['gateway_ref']) || !FabriquePasserelles::configuree($intent['gateway'])) {
            return null;
        }

        $db = self::base();
        $db?->prepare('UPDATE payment_intents SET last_checked_at = NOW() WHERE id = ?')->execute([$intentId]);

        $passerelle = self::avecJournal(FabriquePasserelles::obtenir($intent['gateway']), (int) $intent['order_id'], $intentId);
        $reponse = $passerelle->statut((string) $intent['gateway_ref']);

        if (!$reponse->echange || $reponse->statut === null) {
            return null;
        }
        if ($reponse->statut !== 'pending') {
            self::appliquer($intentId, $reponse->statut, $reponse->montant, $reponse->devise, $reponse->reference, $reponse->codeErreur, 'consultation');
        }

        return $reponse->statut;
    }

    /**
     * Expire les tentatives echues, apres une derniere consultation.
     *
     * @return array{verifiees:int, expirees:int}
     */
    public static function expirerEchues(int $limite = 200): array
    {
        $db = self::base();
        if (!$db) {
            return ['verifiees' => 0, 'expirees' => 0];
        }

        $stmt = $db->prepare(
            "SELECT id FROM payment_intents
              WHERE status IN ('created', 'pending') AND expires_at <= NOW()
              ORDER BY expires_at LIMIT " . max(1, $limite)
        );
        $stmt->execute();
        $bilan = ['verifiees' => 0, 'expirees' => 0];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            // Le callback a pu se perdre : on demande a l'operateur avant de conclure.
            if (self::verifierStatut($id) !== null) {
                $bilan['verifiees']++;
            }
            $intent = self::tentative($id);
            if ($intent && in_array($intent['status'], self::TENTATIVE_OUVERTE, true)) {
                self::clore($id, 'expired', 'delai_depasse', 'Aucune confirmation de l\'operateur dans le delai imparti.');
                $bilan['expirees']++;
            }
        }

        return $bilan;
    }

    /**
     * Etat d'une tentative pour la page d'attente du client. Consulte
     * l'operateur si le callback tarde, et expire la tentative si son delai
     * est depasse -- sans attendre la tache planifiee.
     *
     * @return array<string,mixed>|null
     */
    public static function suivre(int $intentId, int $userId): ?array
    {
        $intent = self::tentative($intentId);
        if (!$intent || (int) $intent['user_id'] !== $userId) {
            return null;
        }

        if (in_array($intent['status'], self::TENTATIVE_OUVERTE, true)) {
            $age = time() - strtotime((string) $intent['created_at']);
            $derniere = $intent['last_checked_at'] ? time() - strtotime((string) $intent['last_checked_at']) : PHP_INT_MAX;

            if (strtotime((string) $intent['expires_at']) <= time()) {
                self::expirerUne($intentId);
            } elseif ($age >= self::DELAI_PREMIERE_CONSULTATION && $derniere >= self::INTERVALLE_CONSULTATION) {
                self::verifierStatut($intentId);
            }
            $intent = self::tentative($intentId) ?? $intent;
        }

        return [
            'tentative'   => (int) $intent['id'],
            'statut'      => (string) $intent['status'],
            'commande'    => (string) $intent['order_reference'],
            'etat_commande' => (string) $intent['order_status'],
            'passerelle'  => (string) $intent['gateway'],
            'redirection' => $intent['redirect_url'],
            'message'     => self::messageEtat((string) $intent['status'], $intent['error_code']),
            'expire_a'    => (string) $intent['expires_at'],
        ];
    }

    // -----------------------------------------------------------------
    // Transition d'etat
    // -----------------------------------------------------------------

    /**
     * Applique un etat annonce par l'operateur, sous verrou.
     *
     * @return string issue : encaissee, doublon, revue, close, contestee, ignoree
     */
    public static function appliquer(
        int $intentId,
        string $statut,
        ?int $montant,
        ?string $devise,
        ?string $referencePasserelle,
        ?string $codeEchec,
        string $source
    ): string {
        $db = self::base();
        if (!$db) {
            throw new RuntimeException('Base de donnees indisponible.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM payment_intents WHERE id = ? FOR UPDATE');
            $stmt->execute([$intentId]);
            $intent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$intent) {
                $db->rollBack();
                return 'ignoree';
            }

            $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$intent['order_id']]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            $ref = $referencePasserelle ?: (string) $intent['gateway_ref'];
            $issue = match ($statut) {
                'succeeded' => self::appliquerSucces($db, $intent, $commande, $montant, $devise, $ref, $source),
                'failed', 'cancelled' => self::appliquerEchec($db, $intent, $commande, $statut, $codeEchec),
                'disputed'  => self::appliquerContestation($db, $intent, $commande, $source),
                default     => 'ignoree',
            };

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // SHOP-03 : confirmation et facture, APRES la validation. Un echec
        // d'envoi ne remet pas l'encaissement en cause.
        if ($issue === 'encaissee' && class_exists('Factures')) {
            try {
                Factures::envoyer((int) $intent['order_id']);
            } catch (Throwable $e) {
                error_log('[Tchadok][paiement] confirmation non envoyee : ' . $e->getMessage());
            }
        }

        return $issue;
    }

    private static function appliquerSucces(PDO $db, array $intent, array $commande, ?int $montant, ?string $devise, string $ref, string $source): string
    {
        // Deja traitee : aucun second effet. On s'assure seulement que la
        // commande a suivi (reprise apres une panne entre les deux).
        if ($intent['status'] === 'succeeded') {
            if ($commande['status'] !== 'paid' && !in_array($commande['status'], Commandes::ETATS_FIGES, true)) {
                Commandes::marquerPayee((int) $commande['id'], (string) $intent['gateway_ref'], (string) $intent['gateway']);
                self::crediterSiRecharge($db, (int) $commande['id']);
            }
            return 'doublon';
        }
        if ($intent['status'] === 'review') {
            return 'doublon';
        }

        // En unites mineures de la devise de la TENTATIVE : 250 pour 2,50 USD.
        $attendu = Devises::enUnitesMineures((float) $intent['amount'], (string) $intent['currency']);
        $motif = null;
        if ($montant === null || $montant !== $attendu || strtoupper((string) $devise) !== strtoupper((string) $intent['currency'])) {
            $motif = 'montant_divergent';
        } elseif ($commande['status'] === 'paid' && (string) $commande['gateway_ref'] !== $ref) {
            $motif = 'double_encaissement';
        } elseif (in_array($commande['status'], Commandes::ETATS_FIGES, true)) {
            $motif = 'commande_figee';
        }

        if ($motif !== null) {
            $db->prepare(
                "UPDATE payment_intents
                    SET status = 'review', gateway_ref = COALESCE(gateway_ref, ?), amount_reported = ?,
                        error_code = ?, completed_at = NOW()
                  WHERE id = ?"
            )->execute([$ref ?: null, $montant, $motif, $intent['id']]);

            if ($motif === 'montant_divergent' && $commande['status'] !== 'paid') {
                $db->prepare("UPDATE orders SET status = 'review' WHERE id = ?")->execute([$commande['id']]);
            }

            error_log(sprintf(
                '[Tchadok][paiement][ALERTE] %s : tentative %d, commande %s, attendu %d %s, annonce %s %s (source : %s)',
                $motif, $intent['id'], $commande['reference'], $attendu, $intent['currency'],
                $montant === null ? '?' : (string) $montant, $devise ?? '?', $source
            ));
            return 'revue';
        }

        $db->prepare(
            "UPDATE payment_intents
                SET status = 'succeeded', gateway_ref = COALESCE(gateway_ref, ?), amount_reported = ?,
                    error_code = NULL, error_message = NULL, completed_at = NOW()
              WHERE id = ?"
        )->execute([$ref, $montant, $intent['id']]);

        $encaissement = Commandes::marquerPayee((int) $commande['id'], $ref, (string) $intent['gateway']);
        if (!$encaissement['succes']) {
            throw new RuntimeException('Encaissement refuse : ' . implode(' ', $encaissement['erreurs']));
        }
        self::crediterSiRecharge($db, (int) $commande['id']);

        return 'encaissee';
    }

    /**
     * SHOP-06 : une recharge du portefeuille encaissee credite le solde, dans
     * la meme transaction. Idempotent : un callback rejoue ne credite pas
     * deux fois.
     */
    private static function crediterSiRecharge(PDO $db, int $orderId): void
    {
        if (class_exists('Portefeuille') && Portefeuille::estRecharge($db, $orderId)) {
            Portefeuille::crediterRecharge($db, $orderId);
        }
        // LOT 7 : un abonnement ne s'active QU'A l'encaissement, jamais a
        // l'intention de paiement. Idempotent.
        if (class_exists('Abonnements')) {
            Abonnements::activer($db, $orderId);
        }
    }

    private static function appliquerEchec(PDO $db, array $intent, array $commande, string $statut, ?string $codeEchec): string
    {
        // Jamais de retour arriere : un echec annonce apres une reussite (ordre
        // d'arrivee inverse) est ignore.
        if (!in_array($intent['status'], self::TENTATIVE_OUVERTE, true)) {
            return 'doublon';
        }

        $db->prepare(
            'UPDATE payment_intents SET status = ?, error_code = ?, completed_at = NOW() WHERE id = ?'
        )->execute([$statut, $codeEchec, $intent['id']]);

        self::suivreCommande($db, $intent, $commande, $statut);
        return 'close';
    }

    private static function appliquerContestation(PDO $db, array $intent, array $commande, string $source): string
    {
        if ($intent['status'] !== 'succeeded' || $commande['status'] !== 'paid'
            || (string) $commande['gateway_ref'] !== (string) $intent['gateway_ref']) {
            return $commande['status'] === 'disputed' ? 'doublon' : 'ignoree';
        }

        Commandes::contester((int) $commande['id'], 'Contestation du paiement par le porteur de la carte (' . $source . ')');
        error_log(sprintf('[Tchadok][paiement][ALERTE] contestation : commande %s, droits d\'acces retires', $commande['reference']));
        return 'contestee';
    }

    /**
     * La commande reflete l'issue de sa DERNIERE tentative, et seulement si
     * elle attendait un paiement.
     */
    private static function suivreCommande(PDO $db, array $intent, array $commande, string $statut): void
    {
        if ($commande['status'] !== 'awaiting_payment') {
            return;
        }

        $stmt = $db->prepare('SELECT MAX(attempt) FROM payment_intents WHERE order_id = ?');
        $stmt->execute([$intent['order_id']]);
        if ((int) $stmt->fetchColumn() !== (int) $intent['attempt']) {
            return;
        }

        $etat = ['failed' => 'failed', 'cancelled' => 'cancelled', 'expired' => 'expired'][$statut] ?? null;
        if ($etat !== null) {
            $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$etat, $commande['id']]);
        }
    }

    /**
     * Clot une tentative ouverte (echec d'initiation, expiration).
     */
    private static function clore(int $intentId, string $statut, ?string $code, ?string $message): void
    {
        $db = self::base();
        if (!$db) {
            return;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM payment_intents WHERE id = ? FOR UPDATE');
            $stmt->execute([$intentId]);
            $intent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($intent && in_array($intent['status'], self::TENTATIVE_OUVERTE, true)) {
                $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
                $stmt->execute([$intent['order_id']]);
                $commande = $stmt->fetch(PDO::FETCH_ASSOC);

                $db->prepare(
                    'UPDATE payment_intents SET status = ?, error_code = ?, error_message = ?, completed_at = NOW() WHERE id = ?'
                )->execute([$statut, $code, $message !== null ? mb_substr($message, 0, 500) : null, $intentId]);

                if ($commande) {
                    self::suivreCommande($db, $intent, $commande, $statut);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][paiement] cloture impossible : ' . $e->getMessage());
        }
    }

    private static function expirerUne(int $intentId): void
    {
        self::verifierStatut($intentId);
        $intent = self::tentative($intentId);
        if ($intent && in_array($intent['status'], self::TENTATIVE_OUVERTE, true)) {
            self::clore($intentId, 'expired', 'delai_depasse', 'Aucune confirmation de l\'operateur dans le delai imparti.');
        }
    }

    // -----------------------------------------------------------------
    // Outils
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function tentative(int $intentId): ?array
    {
        $db = self::base();
        if (!$db) {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT pi.*, o.reference AS order_reference, o.status AS order_status, o.user_id
               FROM payment_intents pi JOIN orders o ON o.id = pi.order_id
              WHERE pi.id = ?'
        );
        $stmt->execute([$intentId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    /**
     * Retrouve une tentative par la reference de l'operateur, ou a defaut par
     * sa reference Tchadok (« TCHK-2026-XXXXXXXX-2 ») -- indispensable quand
     * la reponse a l'initiation s'est perdue et que l'on ne connait pas
     * encore la reference de l'operateur.
     *
     * @return array<string,mixed>|null
     */
    private static function retrouver(string $codePasserelle, ?string $referencePasserelle, ?string $referenceTentative): ?array
    {
        $db = self::base();
        if (!$db) {
            return null;
        }

        if ($referencePasserelle !== null && $referencePasserelle !== '') {
            $stmt = $db->prepare('SELECT * FROM payment_intents WHERE gateway_ref = ? AND gateway = ?');
            $stmt->execute([$referencePasserelle, $codePasserelle]);
            if ($ligne = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $ligne;
            }
        }

        if ($referenceTentative !== null && preg_match('/^(.+)-(\d+)$/', $referenceTentative, $m)) {
            $stmt = $db->prepare(
                'SELECT pi.* FROM payment_intents pi JOIN orders o ON o.id = pi.order_id
                  WHERE o.reference = ? AND pi.attempt = ? AND pi.gateway = ?'
            );
            $stmt->execute([$m[1], (int) $m[2], $codePasserelle]);
            if ($ligne = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $ligne;
            }
        }

        return null;
    }

    private static function avecJournal(PasserellePaiement $passerelle, int $orderId, int $intentId): PasserellePaiement
    {
        $code = $passerelle->code();
        $passerelle->journaliserAvec(static function (array $echange) use ($code, $orderId, $intentId): void {
            Commandes::enregistrerEvenement($echange + [
                'gateway'    => $code,
                'order_id'   => $orderId,
                'intent_id'  => $intentId,
                'ip_address' => '',
            ]);
        });

        return $passerelle;
    }

    private static function dureeDeVie(): int
    {
        return max(60, (int) EnvLoader::get('PAYMENT_INTENT_TTL', 900));
    }

    public static function messageEtat(string $statut, ?string $code = null): string
    {
        return match ($statut) {
            'created', 'pending' => 'En attente de votre confirmation.',
            'succeeded'          => 'Paiement confirme. Merci !',
            'review'             => 'Paiement recu, en cours de verification par notre equipe.',
            'expired'            => 'Le delai de confirmation est depasse. Vous pouvez reessayer.',
            'cancelled'          => $code === 'remplacee' ? 'Tentative remplacee par une nouvelle.' : 'Paiement annule.',
            'failed'             => self::messageErreur($code),
            default              => 'Etat inconnu.',
        };
    }

    private static function messageErreur(?string $code): string
    {
        return match ($code) {
            'insufficient_funds' => 'Solde insuffisant.',
            'invalid_pin'        => 'Code secret incorrect.',
            'card_declined'      => 'Carte refusee par votre banque.',
            'expired_card'       => 'Carte expiree.',
            'invalid_cvc'        => 'Cryptogramme incorrect.',
            'invalid_msisdn'     => 'Numero de telephone non reconnu par l\'operateur.',
            'reseau', 'internal_error', 'processing_error' => 'L\'operateur ne repond pas. Reessayez dans quelques instants.',
            'service_unavailable' => 'Ce moyen de paiement est momentanement indisponible. Choisissez-en un autre ou reessayez plus tard.',
            default              => 'Le paiement n\'a pas abouti.',
        };
    }

    /** @return array{succes:false, erreur:string, tentative:null} */
    private static function refus(string $message): array
    {
        return ['succes' => false, 'erreur' => $message, 'tentative' => null];
    }

    private static function accepte(array $intent): array
    {
        return [
            'succes'    => true,
            'erreur'    => null,
            'tentative' => [
                'id'          => (int) ($intent['id'] ?? 0),
                'statut'      => (string) ($intent['status'] ?? 'pending'),
                'redirection' => $intent['redirect_url'] ?? null,
                'passerelle'  => (string) ($intent['gateway'] ?? ''),
            ],
        ];
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
