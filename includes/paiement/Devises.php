<?php
/**
 * Devises de paiement (PAY-07).
 *
 * Decision du 28/09/2026 : la diaspora paie en dollar US, par carte. Tout le
 * reste de la plateforme compte en francs CFA : le dollar n'existe qu'au
 * moment du paiement, converti au taux en vigueur, et ce taux est FIGE sur la
 * tentative (payment_intents.exchange_rate, amount_xaf).
 *
 * Montants echanges avec les operateurs : en UNITES MINEURES de la devise --
 * le franc CFA n'a pas de subdivision (1 500 XAF -> 1500), le dollar se
 * compte en cents (2,50 USD -> 250). Jamais de nombre a virgule sur le fil.
 *
 * COURS AUTOMATIQUE (decision du 28/09/2026, mode par defaut)
 *   Le cours vient d'une API gratuite, sans cle :
 *     1. open.er-api.com : cours USD/XAF direct ;
 *     2. a defaut, api.frankfurter.app (BCE) : USD/EUR x 655,957, parite FIXE
 *        du franc CFA -- exact, pas une approximation.
 *   Il est redemande toutes les DEVISES_RAFRAICHIR_HEURES (6 h) au plus, et
 *   reste valable DEVISES_VALIDITE_HEURES (24 h : les API publient un cours
 *   par jour). Sans connexion, reponse aberrante ou cours perime : taux de
 *   secours DEVISE_USD_SECOURS, 600 FCFA. Un echec n'est pas retente avant
 *   15 minutes : hors ligne, une page ne doit pas attendre le reseau a
 *   chaque affichage.
 *   Le dernier cours et le dernier echec vivent dans storage/cache (memo) ;
 *   chaque cours obtenu entre aussi dans l'historique immuable
 *   `exchange_rates`, source « api ». La tentative de paiement fige le taux
 *   ET sa provenance (payment_intents.rate_source).
 *
 * MODE MANUEL (DEVISES_COURS=manuel)
 *   Comportement d'origine : le dernier taux saisi par scripts/devises.php.
 */

declare(strict_types=1);

final class Devises
{
    /** Devises acceptees et nombre de decimales. */
    public const DECIMALES = ['XAF' => 0, 'USD' => 2];

    public const REFERENCE = 'XAF';

    /** 1 EUR = 655,957 XAF : parite fixe du franc CFA. */
    public const PARITE_EUR = 655.957;

    /** Delai avant de retenter une API qui n'a pas repondu. */
    private const PAUSE_APRES_ECHEC = 900;

    /** @var array<string,float|null> */
    private static array $cache = [];

    /** @var array<string,string> provenance du taux lu : api, secours, manuel */
    private static array $origines = [];

    /** Source injectee par les tests : fn(string $devise): ?array{taux, source}. */
    private static $sourceForcee = null;
    private static ?int $rafraichirForce = null;
    private static ?int $validiteForcee = null;

    public static function existe(string $devise): bool
    {
        return isset(self::DECIMALES[strtoupper($devise)]);
    }

    /**
     * Nombre de francs CFA pour une unite de la devise, taux en vigueur.
     * Mode automatique : cours de l'API, ou secours (600 pour l'USD).
     * Mode manuel : dernier taux saisi. null si aucun taux n'existe : on
     * refuse alors de payer dans cette devise plutot que d'inventer un cours.
     */
    public static function taux(string $devise): ?float
    {
        $devise = strtoupper($devise);
        if ($devise === self::REFERENCE) {
            return 1.0;
        }
        if (array_key_exists($devise, self::$cache)) {
            return self::$cache[$devise];
        }
        if (!self::existe($devise)) {
            return self::$cache[$devise] = null;
        }
        if (self::mode() === 'auto') {
            return self::$cache[$devise] = self::tauxAutomatique($devise);
        }

        self::$origines[$devise] = 'manuel';
        $taux = null;
        try {
            $db = TchadokDatabase::getInstance()->getConnection();
            if ($db) {
                $stmt = $db->prepare(
                    'SELECT xaf_per_unit FROM exchange_rates
                      WHERE currency = ? AND active_from <= NOW()
                      ORDER BY active_from DESC, id DESC LIMIT 1'
                );
                $stmt->execute([$devise]);
                $valeur = $stmt->fetchColumn();
                $taux = $valeur === false ? null : (float) $valeur;
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][devises] taux illisible : ' . $e->getMessage());
        }

        return self::$cache[$devise] = ($taux !== null && $taux > 0 ? $taux : null);
    }

    /**
     * Convertit un montant XAF dans la devise, ARRONDI A L'UNITE MINEURE
     * SUPERIEURE : la plateforme ne percoit jamais moins que le prix en francs,
     * et l'ecart ne depasse pas un cent.
     */
    public static function convertir(float $montantXaf, string $devise): ?float
    {
        $devise = strtoupper($devise);
        $taux = self::taux($devise);
        if ($taux === null || !self::existe($devise)) {
            return null;
        }
        $facteur = 10 ** self::DECIMALES[$devise];
        // round() avant ceil() : 1500 / 600 * 100 vaut 250.00000000000003 en
        // virgule flottante, et ceil() en ferait 251.
        return ceil(round($montantXaf / $taux * $facteur, 6)) / $facteur;
    }

    public static function enUnitesMineures(float $montant, string $devise): int
    {
        return (int) round($montant * 10 ** (self::DECIMALES[strtoupper($devise)] ?? 0));
    }

    public static function formater(float $montant, string $devise): string
    {
        $devise = strtoupper($devise);
        return match ($devise) {
            'USD'   => number_format($montant, 2, ',', ' ') . ' $ US',
            'XAF'   => number_format($montant, 0, ',', ' ') . ' FCFA',
            default => number_format($montant, self::DECIMALES[$devise] ?? 2, ',', ' ') . ' ' . $devise,
        };
    }

    /**
     * Definit un nouveau taux. L'historique n'est jamais reecrit : c'est une
     * ligne de plus, datee, motivee et tracee au journal d'audit.
     */
    public static function definir(string $devise, float $xafParUnite, string $raison, ?int $auteur = null): bool
    {
        $devise = strtoupper($devise);
        if ($devise === self::REFERENCE || !self::existe($devise) || $xafParUnite <= 0 || trim($raison) === '') {
            return false;
        }

        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return false;
        }

        $avant = self::taux($devise);
        $db->prepare('INSERT INTO exchange_rates (currency, xaf_per_unit, active_from, reason, set_by) VALUES (?, ?, NOW(), ?, ?)')
           ->execute([$devise, $xafParUnite, mb_substr($raison, 0, 300), $auteur]);
        unset(self::$cache[$devise]);

        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('tarif.modifie', [
                'cible_type' => 'taux_de_change',
                'cible_id'   => $devise,
                'avant'      => ['xaf_par_unite' => $avant],
                'apres'      => ['xaf_par_unite' => $xafParUnite],
                'raison'     => $raison,
                'acteur'     => $auteur,
            ]);
        }

        return true;
    }

    public static function oublier(): void
    {
        self::$cache = [];
        self::$origines = [];
    }

    // -----------------------------------------------------------------
    // Cours automatique
    // -----------------------------------------------------------------

    /** 'auto' (API, secours 600) ou 'manuel' (scripts/devises.php). */
    public static function mode(): string
    {
        return strtolower((string) EnvLoader::get('DEVISES_COURS', 'auto')) === 'manuel' ? 'manuel' : 'auto';
    }

    /** Provenance du dernier taux lu : 'api', 'secours' ou 'manuel' ; null pour le XAF. */
    public static function origine(string $devise): ?string
    {
        $devise = strtoupper($devise);
        if ($devise === self::REFERENCE) {
            return null;
        }
        self::taux($devise);
        return self::$origines[$devise] ?? null;
    }

    /** Taux de secours (sans connexion) : DEVISE_<CODE>_SECOURS, 600 pour l'USD. */
    public static function secours(string $devise): ?float
    {
        $devise = strtoupper($devise);
        $valeur = (float) EnvLoader::get('DEVISE_' . $devise . '_SECOURS', $devise === 'USD' ? '600' : '0');
        return $valeur > 0 ? $valeur : null;
    }

    /**
     * Interroge les API gratuites, dans l'ordre. Aucune ecriture.
     *
     * @return array{taux:float, source:string}|null
     */
    public static function interrogerApi(string $devise): ?array
    {
        $devise = strtoupper($devise);
        if (self::$sourceForcee !== null) {
            return (self::$sourceForcee)($devise);
        }
        $taux = self::lireErApi((string) self::telecharger('https://open.er-api.com/v6/latest/' . rawurlencode($devise)));
        if ($taux !== null) {
            return ['taux' => $taux, 'source' => 'open.er-api.com'];
        }
        $taux = self::lireFrankfurter((string) self::telecharger('https://api.frankfurter.app/latest?from=' . rawurlencode($devise) . '&to=EUR'));
        if ($taux !== null) {
            return ['taux' => $taux, 'source' => 'frankfurter.app (BCE) x parite 655,957'];
        }
        return null;
    }

    /** Reponse de open.er-api.com -> XAF pour une unite, ou null. */
    public static function lireErApi(string $json): ?float
    {
        $r = json_decode($json, true);
        $taux = is_array($r) && ($r['result'] ?? '') === 'success' ? ($r['rates']['XAF'] ?? null) : null;
        return is_numeric($taux) && (float) $taux > 0 ? round((float) $taux, 4) : null;
    }

    /** Reponse de frankfurter.app (vers EUR) -> XAF pour une unite, ou null. */
    public static function lireFrankfurter(string $json): ?float
    {
        $r = json_decode($json, true);
        $eur = is_array($r) ? ($r['rates']['EUR'] ?? null) : null;
        return is_numeric($eur) && (float) $eur > 0 ? round((float) $eur * self::PARITE_EUR, 4) : null;
    }

    /**
     * Redemande le cours maintenant (tache planifiee, ou page dont le cours est
     * a rafraichir). Un cours plausible est memorise et inscrit a l'historique.
     *
     * @return array{taux:float, source:string}|null
     */
    public static function actualiser(string $devise): ?array
    {
        $devise = strtoupper($devise);
        $memo = self::lireMemo($devise);
        $obtenu = self::interrogerApi($devise);
        if ($obtenu !== null && !self::plausible($devise, (float) $obtenu['taux'])) {
            error_log(sprintf('[Tchadok][devises] cours %s aberrant ignore : %s (%s)', $devise, $obtenu['taux'], $obtenu['source']));
            $obtenu = null;
        }
        if ($obtenu === null) {
            self::ecrireMemo($devise, ['echec' => time()] + $memo);
            return null;
        }

        self::ecrireMemo($devise, ['taux' => (float) $obtenu['taux'], 'source' => $obtenu['source'], 'obtenu' => time()]);
        try {
            $db = TchadokDatabase::getInstance()->getConnection();
            $db?->prepare("INSERT INTO exchange_rates (currency, xaf_per_unit, source, active_from, reason) VALUES (?, ?, 'api', NOW(), ?)")
               ->execute([$devise, $obtenu['taux'], mb_substr('Cours automatique : ' . $obtenu['source'], 0, 300)]);
        } catch (Throwable $e) {
            // L'historique est une trace : son absence ne doit pas bloquer un paiement.
            error_log('[Tchadok][devises] cours non historise : ' . $e->getMessage());
        }
        unset(self::$cache[$devise]);
        return $obtenu;
    }

    /** Chemin du memo (dernier cours, dernier echec) d'une devise. */
    public static function fichierMemo(string $devise): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/devises-' . strtolower($devise) . '.json';
    }

    /**
     * Pour les tests : source simulee (null = vraies API) et delais en secondes.
     */
    public static function utiliserSource(?callable $source, ?int $rafraichirSecondes = null, ?int $validiteSecondes = null): void
    {
        self::$sourceForcee = $source;
        self::$rafraichirForce = $rafraichirSecondes;
        self::$validiteForcee = $validiteSecondes;
        self::oublier();
    }

    private static function tauxAutomatique(string $devise): ?float
    {
        $memo = self::lireMemo($devise);
        $age = isset($memo['obtenu']) ? time() - (int) $memo['obtenu'] : PHP_INT_MAX;
        $rafraichir = self::$rafraichirForce ?? 3600 * max(1, (int) EnvLoader::get('DEVISES_RAFRAICHIR_HEURES', '6'));
        $validite = self::$validiteForcee ?? 3600 * max(1, (int) EnvLoader::get('DEVISES_VALIDITE_HEURES', '24'));
        $enPause = isset($memo['echec']) && time() - (int) $memo['echec'] < self::PAUSE_APRES_ECHEC;

        if ($age >= $rafraichir && !$enPause) {
            $obtenu = self::actualiser($devise);
            if ($obtenu !== null) {
                self::$origines[$devise] = 'api';
                return (float) $obtenu['taux'];
            }
        }
        if (isset($memo['taux']) && $age < $validite) {
            self::$origines[$devise] = 'api';
            return (float) $memo['taux'];
        }
        self::$origines[$devise] = 'secours';
        return self::secours($devise);
    }

    /** Ecart de plus de 50 % avec le taux de secours : reponse aberrante. */
    private static function plausible(string $devise, float $taux): bool
    {
        $reference = self::secours($devise);
        return $taux > 0 && ($reference === null || ($taux >= $reference * 0.5 && $taux <= $reference * 1.5));
    }

    private static function telecharger(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 2, CURLOPT_USERAGENT => 'Tchadok/1.0 (cours du dollar)',
        ]);
        $corps = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        return is_string($corps) && $code === 200 ? $corps : null;
    }

    private static function lireMemo(string $devise): array
    {
        $memo = @json_decode((string) @file_get_contents(self::fichierMemo($devise)), true);
        return is_array($memo) ? $memo : [];
    }

    private static function ecrireMemo(string $devise, array $memo): void
    {
        $fichier = self::fichierMemo($devise);
        @mkdir(dirname($fichier), 0775, true);
        @file_put_contents($fichier, json_encode($memo), LOCK_EX);
    }
}
