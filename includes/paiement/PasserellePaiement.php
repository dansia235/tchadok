<?php
/**
 * Interface commune aux passerelles de paiement (PAY-01).
 *
 * Ce que le reste de l'application sait d'un operateur tient ici. Ajouter une
 * passerelle demande un adaptateur et une entree de configuration, rien
 * d'autre ; realigner un adaptateur sur la specification officielle d'un
 * partenaire ne touche ni la machine a etats, ni le tunnel, ni les tests.
 *
 * Contrat en vigueur : docs/paiement/contrat-generique.md
 */

declare(strict_types=1);

interface PasserellePaiement
{
    /** Identifiant stable : airtel_money, moov_money, visa, gimac. */
    public function code(): string;

    /** Nom affiche au client. */
    public function libelle(): string;

    /** « push » (confirmation sur le telephone) ou « hosted » (page de l'acquereur). */
    public function parcours(): string;

    /** Chemin du logo, relatif a la racine du site. */
    public function logo(): string;

    public function accepteDevise(string $devise): bool;

    /**
     * Normalise un numero de telephone pour cette passerelle, ou null s'il
     * n'est pas acceptable. Sans objet pour un parcours « hosted ».
     */
    public function normaliserNumero(string $numero): ?string;

    public function initier(DemandePaiement $demande): ResultatPasserelle;

    public function statut(string $referencePasserelle): ResultatPasserelle;

    /**
     * Verifie et decode un callback. Ne leve jamais d'exception : un callback
     * illisible ou mal signe est un resultat (rejete), pas une panne.
     *
     * @param array<string,string> $entetes noms en minuscules
     */
    public function verifierCallback(array $entetes, string $corps): ResultatCallback;

    public function rembourser(string $referencePasserelle, int $montant, string $motif): ResultatPasserelle;

    /**
     * Releve des transactions d'une journee (PAY-10), ou null si l'operateur
     * ne l'a pas fourni. Chaque ligne : reference (operateur), tentative
     * (reference Tchadok), montant (unites mineures), devise, statut.
     *
     * @return array<int,array{reference:string, tentative:?string, montant:int, devise:string, statut:string}>|null
     */
    public function releve(string $date): ?array;

    /**
     * Versement sortant vers le portefeuille d'un artiste (PAYOUT-03).
     * Montant en unites mineures (XAF). La cle d'idempotence garantit qu'un
     * appel rejoue ne verse pas deux fois.
     */
    public function verser(string $reference, int $montant, string $msisdn, string $urlCallback, string $cleIdempotence): ResultatPasserelle;

    public function statutVersement(string $referencePasserelle): ResultatPasserelle;

    /**
     * Branche la journalisation des echanges (payment_events). Recoit un
     * tableau : direction, event_type, http_status, payload.
     */
    public function journaliserAvec(?Closure $journal): void;
}

/**
 * Demande de paiement, telle qu'envoyee a l'operateur.
 */
final class DemandePaiement
{
    public function __construct(
        public readonly string $reference,
        public readonly int $montant,
        public readonly string $devise,
        public readonly string $description,
        public readonly string $urlCallback,
        public readonly string $cleIdempotence,
        public readonly ?string $msisdn = null,
        public readonly ?string $urlRetour = null,
    ) {
    }
}

/**
 * Reponse d'une passerelle a une initiation, une consultation ou un
 * remboursement.
 *
 * `echange` dit si l'operateur a repondu de facon exploitable ; `statut` est
 * l'etat du paiement selon lui (pending, succeeded, failed, cancelled,
 * disputed, refunded). Une erreur reseau donne echange = false et statut
 * null : on ne sait PAS ce qui s'est passe -- a ne jamais confondre avec un
 * refus.
 */
final class ResultatPasserelle
{
    public function __construct(
        public readonly bool $echange,
        public readonly ?string $statut = null,
        public readonly ?string $reference = null,
        public readonly ?int $montant = null,
        public readonly ?string $devise = null,
        public readonly ?string $urlRedirection = null,
        public readonly ?string $codeErreur = null,
        public readonly ?string $messageErreur = null,
        public readonly ?int $httpStatus = null,
    ) {
    }

    public static function erreur(string $code, string $message, ?int $httpStatus = null): self
    {
        return new self(false, null, null, null, null, null, $code, $message, $httpStatus);
    }
}

/**
 * Callback verifie et decode.
 */
final class ResultatCallback
{
    public function __construct(
        public readonly bool $valide,
        public readonly ?string $motifRejet = null,
        public readonly ?string $evenement = null,
        public readonly ?string $idEvenement = null,
        public readonly ?string $referencePasserelle = null,
        public readonly ?string $referenceTentative = null,
        public readonly ?string $statut = null,
        public readonly ?int $montant = null,
        public readonly ?string $devise = null,
        public readonly ?string $codeEchec = null,
        public readonly ?string $signature = null,
    ) {
    }

    public static function rejete(string $motif, ?string $signature = null): self
    {
        return new self(false, $motif, null, null, null, null, null, null, null, null, $signature);
    }
}
