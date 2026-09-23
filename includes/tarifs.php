<?php
/**
 * Grille tarifaire administree (DATA-04).
 *
 * Les prix vivaient dans le code, a plusieurs endroits, avec des valeurs
 * contradictoires : deux tarifs Premium differents selon le fichier lu, une
 * commission declaree trois fois et jamais appliquee. Un prix recopie finit
 * toujours par diverger, et il ne se change pas sans deploiement.
 *
 * LA GRILLE FAIT FOI, PAS LE FORMULAIRE
 *   Le plancher et le plafond sont verifies ici, cote serveur, a chaque
 *   enregistrement. Un champ `min` dans le HTML ne protege rien : la requete se
 *   forge. C'est la meme regle que pour les formats de sortie (DATA-03).
 *
 * POURQUOI UN PLANCHER
 *   Il evite la guerre des prix qui detruirait la valeur percue du catalogue
 *   tchadien, et il garantit que la commission couvre les frais mobile money.
 *
 * MISE EN CACHE
 *   La grille est lue une fois par requete. Elle change quelques fois par an ;
 *   la relire a chaque appel couterait une requete SQL par titre affiche.
 */

declare(strict_types=1);

final class Tarifs
{
    public const PORTEES = ['track', 'release', 'subscription'];

    /** Grille chargee, ou null tant qu'elle ne l'est pas. */
    private static ?array $grille = null;

    /**
     * Valeurs de secours, utilisees uniquement si la base est injoignable ou la
     * table absente. Elles ne sont PAS une seconde source de verite : elles
     * evitent qu'une panne de base transforme un prix en zero.
     */
    private const SECOURS = [
        'track:'                          => ['min' => 100.0, 'max' => 1000.0, 'suggere' => 300.0, 'commission' => 15.0],
        'release:single'                  => ['min' => 150.0, 'max' => 1500.0, 'suggere' => 500.0, 'commission' => 15.0],
        'release:maxi_single'             => ['min' => 400.0, 'max' => 2500.0, 'suggere' => 1000.0, 'commission' => 15.0],
        'release:ep'                      => ['min' => 500.0, 'max' => 3500.0, 'suggere' => 1500.0, 'commission' => 15.0],
        'release:album'                   => ['min' => 750.0, 'max' => 6000.0, 'suggere' => 2500.0, 'commission' => 15.0],
        'release:compilation'             => ['min' => 750.0, 'max' => 8000.0, 'suggere' => 3000.0, 'commission' => 20.0],
        'subscription:premium_monthly'    => ['min' => 2000.0, 'max' => 2000.0, 'suggere' => 2000.0, 'commission' => 0.0],
        'subscription:premium_annual'     => ['min' => 20000.0, 'max' => 20000.0, 'suggere' => 20000.0, 'commission' => 0.0],
    ];

    /**
     * Toute la grille en vigueur aujourd'hui, indexee « portee:format ».
     */
    public static function grille(): array
    {
        if (self::$grille !== null) {
            return self::$grille;
        }

        self::$grille = self::SECOURS;

        try {
            $db = TchadokDatabase::getInstance()->getConnection();
            if (!$db) {
                return self::$grille;
            }

            // Une regle peut etre datee : on ne retient que celle en vigueur, et
            // la plus recente si plusieurs se chevauchent.
            $lignes = $db->query(
                "SELECT scope, format, currency, min_price, max_price, suggested, commission_rate
                 FROM pricing_rules
                 WHERE active_from <= CURDATE() AND (active_to IS NULL OR active_to >= CURDATE())
                 ORDER BY active_from ASC, id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lignes as $ligne) {
                self::$grille[$ligne['scope'] . ':' . (string) $ligne['format']] = [
                    'min'        => (float) $ligne['min_price'],
                    'max'        => (float) $ligne['max_price'],
                    'suggere'    => (float) $ligne['suggested'],
                    'commission' => (float) $ligne['commission_rate'],
                    'devise'     => (string) $ligne['currency'],
                ];
            }
        } catch (Throwable $e) {
            // Table absente (migration non appliquee) ou base indisponible : on
            // garde les valeurs de secours plutot que de bloquer le site.
            error_log('[Tchadok][tarifs] grille illisible : ' . $e->getMessage());
        }

        return self::$grille;
    }

    /**
     * La regle applicable, ou null si la portee est inconnue.
     *
     * @return array{min:float,max:float,suggere:float,commission:float}|null
     */
    public static function regle(string $portee, ?string $format = null): ?array
    {
        $grille = self::grille();
        $cle = $portee . ':' . ($format ?? '');

        if (isset($grille[$cle])) {
            return $grille[$cle];
        }

        if (isset($grille[$portee . ':'])) {
            return $grille[$portee . ':'];
        }

        // Un format sans regle propre -- un format ajoute plus tard, ou une
        // valeur inattendue dans la requete -- est borne par l'ENVELOPPE de sa
        // portee : le plus bas plancher et le plus haut plafond qu'elle
        // connaisse. Sans ce repli, un format inconnu echapperait a toute borne,
        // ce qui est exactement ce qu'un envoi forge cherche.
        $enveloppe = null;
        foreach ($grille as $connue => $regle) {
            if (!str_starts_with($connue, $portee . ':')) {
                continue;
            }

            $enveloppe = $enveloppe === null ? $regle : [
                'min'        => min($enveloppe['min'], $regle['min']),
                'max'        => max($enveloppe['max'], $regle['max']),
                'suggere'    => $enveloppe['suggere'],
                'commission' => max($enveloppe['commission'], $regle['commission']),
            ];
        }

        return $enveloppe;
    }

    /**
     * Verifie un prix contre la grille.
     *
     * Gratuit (0) reste permis : c'est un choix de diffusion, pas un prix.
     *
     * @return array{valide:bool, valeur:float, message:string}
     */
    public static function valider($brut, string $portee = 'track', ?string $format = null): array
    {
        // Les controles de forme (nombre, negatif, pas de 50) restent ceux de
        // SEC-17 : inutile de les ecrire deux fois.
        $forme = validerPrix($brut);
        if (!$forme['valide']) {
            return $forme;
        }

        $valeur = $forme['valeur'];
        if ($valeur === 0.0) {
            return ['valide' => true, 'valeur' => 0.0, 'message' => ''];
        }

        $regle = self::regle($portee, $format);
        if ($regle === null) {
            return ['valide' => true, 'valeur' => $valeur, 'message' => ''];
        }

        if ($valeur < $regle['min']) {
            return [
                'valide'  => false,
                'valeur'  => 0.0,
                'message' => sprintf(
                    'Le prix minimum est de %s FCFA. Un plancher commun protege la valeur du catalogue et couvre les frais de paiement mobile.',
                    self::montant($regle['min'])
                ),
            ];
        }

        if ($valeur > $regle['max']) {
            return [
                'valide'  => false,
                'valeur'  => 0.0,
                'message' => sprintf('Le prix maximum est de %s FCFA.', self::montant($regle['max'])),
            ];
        }

        return ['valide' => true, 'valeur' => $valeur, 'message' => ''];
    }

    /**
     * Prix d'un abonnement : 'monthly' ou 'yearly'.
     */
    public static function abonnement(string $periode): float
    {
        $format = $periode === 'yearly' || $periode === 'annual' ? 'premium_annual' : 'premium_monthly';
        $regle = self::regle('subscription', $format);

        return $regle !== null ? $regle['suggere'] : 0.0;
    }

    /**
     * Economie realisee sur l'annuel par rapport a douze mensualites.
     */
    public static function economieAnnuelle(): float
    {
        return max(0.0, self::abonnement('monthly') * 12 - self::abonnement('yearly'));
    }

    /**
     * Part de la plateforme, en pourcentage.
     */
    public static function tauxCommission(string $portee = 'track', ?string $format = null): float
    {
        $regle = self::regle($portee, $format);

        return $regle !== null ? $regle['commission'] : 15.0;
    }

    /**
     * Commission sur un montant, arrondie au franc : le FCFA n'a pas de
     * subdivision en circulation.
     */
    public static function commission(float $montant, string $portee = 'track', ?string $format = null): float
    {
        return round($montant * self::tauxCommission($portee, $format) / 100);
    }

    /**
     * Ce que touche l'artiste.
     */
    public static function partArtiste(float $montant, string $portee = 'track', ?string $format = null): float
    {
        return round($montant - self::commission($montant, $portee, $format));
    }

    /**
     * Rappel affichable sous un champ de prix.
     */
    public static function indication(string $portee = 'track', ?string $format = null): string
    {
        $regle = self::regle($portee, $format);
        if ($regle === null) {
            return '';
        }

        return sprintf(
            'Entre %s et %s FCFA -- suggere : %s FCFA. Commission plateforme : %s %%.',
            self::montant($regle['min']),
            self::montant($regle['max']),
            self::montant($regle['suggere']),
            rtrim(rtrim(number_format($regle['commission'], 2, ',', ' '), '0'), ',')
        );
    }

    /**
     * Toutes les regles telles qu'enregistrees, pour l'ecran d'administration.
     */
    public static function lignes(): array
    {
        try {
            $db = TchadokDatabase::getInstance()->getConnection();
            if (!$db) {
                return [];
            }

            return $db->query(
                'SELECT * FROM pricing_rules ORDER BY FIELD(scope, \'track\', \'release\', \'subscription\'), id'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[Tchadok][tarifs] lecture impossible : ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistre une regle modifiee depuis l'administration.
     *
     * @return array{succes:bool, erreurs:string[]}
     */
    public static function enregistrer(int $id, array $valeurs, ?int $parQui = null): array
    {
        $min     = (float) ($valeurs['min_price'] ?? 0);
        $max     = (float) ($valeurs['max_price'] ?? 0);
        $suggere = (float) ($valeurs['suggested'] ?? 0);
        $taux    = (float) ($valeurs['commission_rate'] ?? 0);
        $erreurs = [];

        if ($min < 0 || $max < 0 || $suggere < 0) {
            $erreurs[] = 'Les montants ne peuvent pas etre negatifs.';
        }
        if ($max < $min) {
            $erreurs[] = 'Le plafond ne peut pas etre inferieur au plancher.';
        }
        if ($suggere < $min || $suggere > $max) {
            $erreurs[] = 'Le prix suggere doit se situer entre le plancher et le plafond.';
        }
        if ($taux < 0 || $taux > 50) {
            $erreurs[] = 'La commission doit se situer entre 0 et 50 %.';
        }

        if ($erreurs !== []) {
            return ['succes' => false, 'erreurs' => $erreurs];
        }

        try {
            $db = TchadokDatabase::getInstance()->getConnection();
            if (!$db) {
                return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.']];
            }

            $stmt = $db->prepare('SELECT * FROM pricing_rules WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $avant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$avant) {
                return ['succes' => false, 'erreurs' => ['Regle introuvable.']];
            }

            $db->prepare(
                'UPDATE pricing_rules
                 SET min_price = ?, max_price = ?, suggested = ?, commission_rate = ?, updated_by = ?
                 WHERE id = ?'
            )->execute([$min, $max, $suggere, $taux, $parQui, $id]);
        } catch (Throwable $e) {
            error_log('[Tchadok][tarifs] enregistrement impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Enregistrement impossible pour le moment.']];
        }

        self::$grille = null; // la grille en cache est perimee

        // Un changement de tarif engage la plateforme vis-a-vis des artistes :
        // il se retrouve, date et attribue (SEC-19).
        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('tarif.modifie', [
                'cible_type' => 'tarif',
                'cible_id'   => $id,
                'avant'      => [
                    'min' => (float) $avant['min_price'], 'max' => (float) $avant['max_price'],
                    'suggere' => (float) $avant['suggested'], 'commission' => (float) $avant['commission_rate'],
                ],
                'apres'      => ['min' => $min, 'max' => $max, 'suggere' => $suggere, 'commission' => $taux],
                'acteur'     => $parQui,
            ]);
        }

        return ['succes' => true, 'erreurs' => []];
    }

    /** Vide le cache -- utile aux tests et apres une modification. */
    public static function oublier(): void
    {
        self::$grille = null;
    }

    private static function montant(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }
}
