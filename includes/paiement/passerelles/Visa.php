<?php
/**
 * Carte VISA, via la page hebergee de l'acquereur.
 *
 * Le client saisit sa carte CHEZ l'acquereur : aucun numero, date ni
 * cryptogramme ne transite par Tchadok. C'est ce qui dispense la plateforme de
 * la certification PCI DSS complete. Ne jamais ajouter ici de champ de carte.
 */

declare(strict_types=1);

final class Visa extends PasserelleGenerique
{
    public function code(): string
    {
        return 'visa';
    }

    public function libelle(): string
    {
        return 'Carte VISA';
    }

    public function parcours(): string
    {
        return 'hosted';
    }

    public function logo(): string
    {
        return 'assets/images/paiement/visa.png';
    }

    /**
     * PAY-07 : la carte est le moyen de la diaspora. Elle accepte le franc CFA
     * et le dollar US ; les portefeuilles mobile money restent en XAF.
     */
    public function accepteDevise(string $devise): bool
    {
        return in_array(strtoupper($devise), ['XAF', 'USD'], true);
    }

    /** Pas de versement sortant vers une carte : les artistes sont payes en mobile money. */
    public function verser(string $reference, int $montant, string $msisdn, string $urlCallback, string $cleIdempotence): ResultatPasserelle
    {
        return ResultatPasserelle::erreur('non_supporte', 'Versement sortant indisponible par carte.');
    }

    public function normaliserNumero(string $numero): ?string
    {
        return null;
    }
}
