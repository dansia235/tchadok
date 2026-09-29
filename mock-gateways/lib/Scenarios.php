<?php
/**
 * Scenarios des simulateurs, pilotes par le numero de telephone ou de carte.
 *
 * Reference pour les developpeurs et les recetteurs :
 * docs/paiement/jeux-de-test.md
 */

declare(strict_types=1);

final class Scenarios
{
    /**
     * Parcours « push » : les huit derniers chiffres du numero sont
     * <indicatif>0000001 a <indicatif>0000010 (66 Airtel, 65 Moov, 62 GIMAC).
     * Tout autre numero valide : succes apres 5 s.
     *
     * Chaque entree : libelle, statut final, code d'echec, delai du premier
     * callback, nombre d'envois, alteration du callback, erreur a l'initiation.
     */
    public const PUSH = [
        1  => ['Succes rapide (2 s)',                         'succeeded', null,                 2,  1, null,        false],
        2  => ['Succes lent (15 s, abonne qui tarde)',        'succeeded', null,                 15, 1, null,        false],
        3  => ['Echec : solde insuffisant',                   'failed',    'insufficient_funds', 3,  1, null,        false],
        4  => ['Echec : code secret errone',                  'failed',    'invalid_pin',        3,  1, null,        false],
        5  => ['Aucun callback (test d\'expiration)',         null,        null,                 0,  0, null,        false],
        6  => ['Annule par l\'abonne',                        'cancelled', null,                 3,  1, null,        false],
        7  => ['Succes, callback envoye trois fois',          'succeeded', null,                 2,  3, null,        false],
        8  => ['Succes, callback a signature invalide',       'succeeded', null,                 2,  1, 'signature', false],
        9  => ['Succes, montant altere dans le callback',     'succeeded', null,                 2,  1, 'montant',   false],
        10 => ['Erreur 500 a l\'initiation',                  null,        null,                 0,  0, null,        true],
    ];

    public const PUSH_DEFAUT = ['Succes (5 s)', 'succeeded', null, 5, 1, null, false];

    /**
     * Parcours « hosted » (VISA) : numeros de test standards de l'industrie,
     * rattaches a aucun compte reel. Tout autre numero valide (Luhn) : succes.
     *
     * Chaque entree : libelle, statut, code d'echec, 3-D Secure, contestation.
     */
    public const CARTES = [
        '4111111111111111' => ['Succes sans 3-D Secure',              'succeeded', null,               false, false],
        '4000000000003220' => ['Succes avec 3-D Secure (123456)',     'succeeded', null,               true,  false],
        '4000000000000002' => ['Refusee par l\'emetteur',             'failed',    'card_declined',    false, false],
        '4000000000009995' => ['Refusee : provision insuffisante',    'failed',    'insufficient_funds', false, false],
        '4000000000000069' => ['Refusee : carte expiree',             'failed',    'expired_card',     false, false],
        '4000000000000127' => ['Refusee : cryptogramme incorrect',    'failed',    'invalid_cvc',      false, false],
        '4000000000000119' => ['Erreur de traitement de l\'acquereur', 'failed',   'processing_error', false, false],
        '4000000000000259' => ['Succes, puis contestation (30 s)',    'succeeded', null,               false, true],
    ];

    /**
     * Versements sortants vers un portefeuille (PAYOUT-04), memes numeros :
     * <indicatif>00000NN.
     *   11  numero invalide : refus immediat (422)
     *   12  compte non enregistre chez l'operateur : echec par callback
     *   13  succes lent (15 s)
     *   14  succes, callback envoye deux fois
     *   autre : succes apres 2 s
     *
     * Chaque entree : libelle, statut final, code d'echec, delai, envois, refus immediat.
     */
    public const VERSEMENTS = [
        11 => ['Numero invalide',                       null,        'invalid_msisdn',    0,  0, true],
        12 => ['Compte non enregistre',                 'failed',    'account_not_found', 2,  1, false],
        13 => ['Succes lent (15 s)',                    'succeeded', null,                15, 1, false],
        14 => ['Succes, callback envoye deux fois',     'succeeded', null,                2,  2, false],
    ];

    public const VERSEMENT_DEFAUT = ['Versement reussi (2 s)', 'succeeded', null, 2, 1, false];

    /** @return array{0:string,1:?string,2:?string,3:int,4:int,5:bool} */
    public static function pourVersement(string $msisdn, ?string $indicatif): array
    {
        $fin = substr(preg_replace('/\D/', '', $msisdn) ?? '', -8);
        if ($indicatif !== null && preg_match('/^' . $indicatif . '0000(\d\d)$/', $fin, $m) && isset(self::VERSEMENTS[(int) $m[1]])) {
            return self::VERSEMENTS[(int) $m[1]];
        }
        return self::VERSEMENT_DEFAUT;
    }

    public const CODE_3DS = '123456';

    /** Delai de la contestation apres le paiement, en secondes. */
    public const DELAI_CONTESTATION = 30;

    /**
     * @return array{0:string,1:?string,2:?string,3:int,4:int,5:?string,6:bool,7:int|string}
     */
    public static function pourNumero(string $msisdn, ?string $indicatif): array
    {
        $fin = substr(preg_replace('/\D/', '', $msisdn) ?? '', -8);
        // 66000007 = indicatif 66, puis 0000, puis le numero de scenario 07.
        if ($indicatif !== null && preg_match('/^' . $indicatif . '0000(\d\d)$/', $fin, $m)) {
            $n = (int) $m[1];
            if (isset(self::PUSH[$n])) {
                return [...self::PUSH[$n], $n];
            }
        }
        return [...self::PUSH_DEFAUT, 'defaut'];
    }

    /**
     * @return array{0:string,1:string,2:?string,3:bool,4:bool}|null null si le numero est invalide
     */
    public static function pourCarte(string $numero): ?array
    {
        $numero = preg_replace('/\D/', '', $numero) ?? '';
        if (isset(self::CARTES[$numero])) {
            return self::CARTES[$numero];
        }
        if (strlen($numero) < 13 || strlen($numero) > 19 || !self::luhn($numero)) {
            return null;
        }
        return ['Succes (carte valide quelconque)', 'succeeded', null, false, false];
    }

    private static function luhn(string $numero): bool
    {
        $somme = 0;
        $double = false;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $c = (int) $numero[$i];
            if ($double) {
                $c *= 2;
                if ($c > 9) {
                    $c -= 9;
                }
            }
            $somme += $c;
            $double = !$double;
        }
        return $somme % 10 === 0;
    }
}
