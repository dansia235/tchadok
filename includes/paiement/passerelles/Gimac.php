<?php
/**
 * GIMAC -- Groupement Interbancaire Monetique de l'Afrique Centrale.
 *
 * Plateforme d'interoperabilite de la CEMAC : le client paie depuis un
 * portefeuille ou un compte d'un emetteur membre, identifie par son numero de
 * telephone. D'ou l'acceptation des indicatifs des six pays de la zone, et pas
 * seulement du Tchad.
 *
 * Conditions d'adhesion (directe ou via une banque membre) et liste des
 * emetteurs couverts : a confirmer (docs/paiement/contrat-generique.md, §4).
 */

declare(strict_types=1);

final class Gimac extends PasserelleGenerique
{
    /** Tchad, Cameroun, Centrafrique, Guinee equatoriale, Gabon, Congo. */
    private const INDICATIFS_CEMAC = ['235', '237', '236', '240', '241', '242'];

    public function code(): string
    {
        return 'gimac';
    }

    public function libelle(): string
    {
        return 'GIMAC';
    }

    public function parcours(): string
    {
        return 'push';
    }

    public function logo(): string
    {
        return 'assets/images/paiement/gimac.png';
    }

    public function normaliserNumero(string $numero): ?string
    {
        $chiffres = preg_replace('/[\s\-\.()]/', '', $numero) ?? '';

        // Numero local : Tchad par defaut.
        if (preg_match('/^[0-9]{8}$/', $chiffres)) {
            return $chiffres;
        }

        if (preg_match('/^(?:\+|00)?(\d{3})(\d{8,9})$/', $chiffres, $m) && in_array($m[1], self::INDICATIFS_CEMAC, true)) {
            return $m[1] === '235' ? $m[2] : $m[1] . $m[2];
        }

        return null;
    }
}
