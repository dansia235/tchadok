<?php
/**
 * Resolution des passerelles depuis la configuration (PAY-01).
 *
 * Passer des simulateurs aux operateurs reels est un changement de
 * CONFIGURATION, jamais de code : PAYMENT_DRIVER=mock|live et
 * <PASSERELLE>_BASE_URL. La coherence est gardee par CFG-05 (« mock » refuse
 * en production, « live » refuse en local).
 *
 * Une passerelle n'est proposee que si sa configuration est complete : un
 * moyen de paiement affiche mais inutilisable coute une vente et la confiance
 * du client.
 */

declare(strict_types=1);

final class FabriquePasserelles
{
    /** code => [classe, prefixe des variables d'environnement] */
    private const CATALOGUE = [
        'airtel_money' => [AirtelMoney::class, 'AIRTEL'],
        'moov_money'   => [MoovMoney::class, 'MOOV'],
        'visa'         => [Visa::class, 'VISA'],
        'gimac'        => [Gimac::class, 'GIMAC'],
    ];

    /** @var array<string,PasserellePaiement> */
    private static array $instances = [];

    /** @return string[] */
    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function existe(string $code): bool
    {
        return isset(self::CATALOGUE[$code]);
    }

    /**
     * Passerelles utilisables, dans l'ordre d'affichage.
     *
     * @return array<string,PasserellePaiement>
     */
    public static function disponibles(): array
    {
        $liste = [];
        foreach (self::codes() as $code) {
            if (self::configuree($code)) {
                $liste[$code] = self::obtenir($code);
            }
        }
        return $liste;
    }

    public static function configuree(string $code): bool
    {
        if (!self::existe($code)) {
            return false;
        }
        $prefixe = self::CATALOGUE[$code][1];
        foreach (['BASE_URL', 'MERCHANT_ID', 'API_KEY', 'WEBHOOK_SECRET'] as $suffixe) {
            if (trim((string) EnvLoader::get($prefixe . '_' . $suffixe, '')) === '') {
                return false;
            }
        }
        return true;
    }

    public static function obtenir(string $code): PasserellePaiement
    {
        if (!self::existe($code)) {
            throw new InvalidArgumentException('Passerelle inconnue : ' . $code);
        }

        if (!isset(self::$instances[$code])) {
            [$classe, $prefixe] = self::CATALOGUE[$code];
            self::$instances[$code] = new $classe(
                (string) EnvLoader::get($prefixe . '_BASE_URL', ''),
                (string) EnvLoader::get($prefixe . '_MERCHANT_ID', ''),
                (string) EnvLoader::get($prefixe . '_API_KEY', ''),
                (string) EnvLoader::get($prefixe . '_WEBHOOK_SECRET', ''),
                self::modeReel(),
            );
        }

        return self::$instances[$code];
    }

    /**
     * Adresses autorisees a emettre des callbacks pour cette passerelle.
     *
     * Production : la liste declaree par l'operateur (<PREFIXE>_CALLBACK_IPS,
     * adresses ou plages CIDR separees par des virgules). VIDE = TOUT EST
     * REFUSE : un oubli de configuration bloque les encaissements au lieu
     * d'ouvrir la porte, et se voit immediatement dans le journal.
     * Local : la machine elle-meme, d'ou partent les simulateurs.
     *
     * @return string[]
     */
    public static function adressesCallback(string $code): array
    {
        if (!self::existe($code)) {
            return [];
        }
        $declarees = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) EnvLoader::get(self::CATALOGUE[$code][1] . '_CALLBACK_IPS', ''))
        )));

        if ($declarees !== [] || EnvLoader::isProduction()) {
            return $declarees;
        }
        return ['127.0.0.1', '::1'];
    }

    public static function modeReel(): bool
    {
        return strtolower(trim((string) EnvLoader::get('PAYMENT_DRIVER', 'mock'))) === 'live';
    }

    /** Oublie les instances (tests, rechargement de configuration). */
    public static function reinitialiser(): void
    {
        self::$instances = [];
    }
}
