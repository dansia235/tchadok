<?php
/**
 * Moov Money (Flooz) Tchad.
 *
 * Suit le contrat generique, a realigner sur la specification remise par Moov
 * a la signature du contrat marchand.
 */

declare(strict_types=1);

final class MoovMoney extends PasserelleGenerique
{
    public function code(): string
    {
        return 'moov_money';
    }

    public function libelle(): string
    {
        return 'Moov Money';
    }

    public function parcours(): string
    {
        return 'push';
    }

    public function logo(): string
    {
        return 'assets/images/paiement/moov-money.png';
    }
}
