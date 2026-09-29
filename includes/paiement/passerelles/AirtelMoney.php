<?php
/**
 * Airtel Money Tchad.
 *
 * Suit le contrat generique. A la reception de la documentation Airtel Africa
 * (OAuth2 client_credentials, en-tetes X-Country / X-Currency, statuts TS/TF/
 * TIP), c'est ici -- et seulement ici -- que se fera le realignement.
 */

declare(strict_types=1);

final class AirtelMoney extends PasserelleGenerique
{
    public function code(): string
    {
        return 'airtel_money';
    }

    public function libelle(): string
    {
        return 'Airtel Money';
    }

    public function parcours(): string
    {
        return 'push';
    }

    public function logo(): string
    {
        return 'assets/images/paiement/airtel-money.png';
    }
}
