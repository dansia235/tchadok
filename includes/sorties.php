<?php
/**
 * Sorties et formats de vente (DATA-03).
 *
 * Une sortie est ce que l'artiste met en vente : un single, un maxi single, un
 * EP, un album, une compilation. Le modele precedent ne connaissait que des
 * « albums » et des titres isoles, ce qui rendait impossible la vente d'un
 * single comme produit.
 *
 * LES REGLES DE FORMAT SONT APPLIQUEES ICI, PAS DANS LE FORMULAIRE
 *   Un controle qui ne vit que dans l'interface se contourne en envoyant la
 *   requete directement. Les regles ci-dessous sont donc verifiees a
 *   l'enregistrement et a la publication, quel que soit le point d'entree --
 *   formulaire artiste, console, ou future API de l'application Android.
 *
 * QUAND CHAQUE REGLE S'APPLIQUE
 *   A la creation, une sortie n'a aucun titre : exiger huit titres pour un
 *   album empecherait de le creer. Le nombre de titres est donc verifie au
 *   moment de la PUBLICATION (passage a « en attente » ou « approuve »), et le
 *   prix a l'enregistrement, ou il est deja connu.
 */

declare(strict_types=1);

final class Sorties
{
    /**
     * Regles par format.
     *
     * titres_min / titres_max : composition attendue
     * bundle_obligatoire      : un prix de sortie complete est-il exige ?
     * achat_titre             : 'oui' | 'configurable'
     * remise_min              : remise minimale du bundle par rapport a la
     *                           somme des titres, en pourcentage
     */
    public const FORMATS = [
        'single' => [
            'libelle' => 'Single', 'titres_min' => 1, 'titres_max' => 2,
            'bundle_obligatoire' => false, 'achat_titre' => 'oui', 'remise_min' => 0,
            'multi_artistes' => false,
        ],
        'maxi_single' => [
            'libelle' => 'Maxi single', 'titres_min' => 3, 'titres_max' => 5,
            'bundle_obligatoire' => true, 'achat_titre' => 'oui', 'remise_min' => 10,
            'multi_artistes' => false,
        ],
        'ep' => [
            'libelle' => 'EP', 'titres_min' => 4, 'titres_max' => 7,
            'bundle_obligatoire' => true, 'achat_titre' => 'oui', 'remise_min' => 10,
            'multi_artistes' => false,
        ],
        'album' => [
            'libelle' => 'Album', 'titres_min' => 8, 'titres_max' => 0,
            'bundle_obligatoire' => true, 'achat_titre' => 'configurable', 'remise_min' => 15,
            'multi_artistes' => false,
        ],
        'compilation' => [
            'libelle' => 'Compilation', 'titres_min' => 8, 'titres_max' => 0,
            'bundle_obligatoire' => true, 'achat_titre' => 'oui', 'remise_min' => 15,
            'multi_artistes' => true,
        ],
    ];

    public const STATUTS = ['draft', 'pending', 'approved', 'rejected'];

    public static function formatConnu(string $format): bool
    {
        return isset(self::FORMATS[$format]);
    }

    public static function libelle(string $format): string
    {
        return self::FORMATS[$format]['libelle'] ?? $format;
    }

    /**
     * Rappel de composition, a afficher dans les formulaires.
     */
    public static function attendu(string $format): string
    {
        if (!self::formatConnu($format)) {
            return '';
        }

        $regle = self::FORMATS[$format];
        $composition = $regle['titres_max'] > 0
            ? "{$regle['titres_min']} a {$regle['titres_max']} titres"
            : "{$regle['titres_min']} titres ou plus";

        return $composition . ($regle['bundle_obligatoire'] ? ', prix de la sortie obligatoire' : '');
    }

    /**
     * Controles applicables a l'enregistrement, avant tout titre.
     *
     * @return string[] messages d'erreur, vide si tout va bien
     */
    public static function validerEnregistrement(string $format, ?float $prixBundle, bool $gratuit): array
    {
        if (!self::formatConnu($format)) {
            return ['Format de sortie inconnu.'];
        }

        $regle = self::FORMATS[$format];
        $erreurs = [];

        if ($gratuit) {
            return $erreurs; // une sortie gratuite n'a pas de prix a verifier
        }

        if ($regle['bundle_obligatoire'] && ($prixBundle === null || $prixBundle <= 0)) {
            $erreurs[] = sprintf(
                'Un %s doit avoir un prix pour la sortie complete.',
                strtolower($regle['libelle'])
            );
        }

        return $erreurs;
    }

    /**
     * Controles applicables a la publication : la composition est alors connue.
     *
     * @return string[] messages d'erreur, vide si la sortie peut etre publiee
     */
    public static function validerPublication(int $releaseId): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return ['Base de donnees indisponible.'];
        }

        try {
            $stmt = $db->prepare('SELECT format, price_bundle, is_free FROM releases WHERE id = ? LIMIT 1');
            $stmt->execute([$releaseId]);
            $sortie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sortie) {
                return ['Sortie introuvable.'];
            }

            $stmt = $db->prepare(
                'SELECT COUNT(*) AS titres, COALESCE(SUM(price), 0) AS somme
                 FROM tracks WHERE release_id = ?'
            );
            $stmt->execute([$releaseId]);
            $composition = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['titres' => 0, 'somme' => 0];

            $artistes = 0;
            if (!empty(self::FORMATS[$sortie['format']]['multi_artistes'])) {
                $stmt = $db->prepare('SELECT COUNT(DISTINCT artist_id) FROM tracks WHERE release_id = ?');
                $stmt->execute([$releaseId]);
                $artistes = (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][sorties] validation impossible : ' . $e->getMessage());
            return ['Verification impossible pour le moment.'];
        }

        return self::verifierComposition(
            (string) $sortie['format'],
            (int) $composition['titres'],
            $sortie['price_bundle'] === null ? null : (float) $sortie['price_bundle'],
            (float) $composition['somme'],
            (bool) $sortie['is_free'],
            $artistes
        );
    }

    /**
     * Le coeur des regles, sans acces base : testable, et reutilisable par une
     * future API.
     *
     * @return string[]
     */
    public static function verifierComposition(
        string $format,
        int $nombreTitres,
        ?float $prixBundle,
        float $sommeDesTitres,
        bool $gratuit = false,
        int $nombreArtistes = 0
    ): array {
        if (!self::formatConnu($format)) {
            return ['Format de sortie inconnu.'];
        }

        $regle = self::FORMATS[$format];
        $erreurs = [];

        if ($nombreTitres < $regle['titres_min']) {
            $erreurs[] = sprintf(
                'Un %s compte au moins %d titre%s ; celui-ci en a %d. Ajoutez des titres, ou changez de format.',
                strtolower($regle['libelle']),
                $regle['titres_min'],
                $regle['titres_min'] > 1 ? 's' : '',
                $nombreTitres
            );
        }

        if ($regle['titres_max'] > 0 && $nombreTitres > $regle['titres_max']) {
            $erreurs[] = sprintf(
                'Un %s compte au plus %d titres ; celui-ci en a %d.',
                strtolower($regle['libelle']),
                $regle['titres_max'],
                $nombreTitres
            );
        }

        if ($regle['multi_artistes'] && $nombreArtistes > 0 && $nombreArtistes < 2) {
            $erreurs[] = 'Une compilation réunit plusieurs artistes.';
        }

        if ($gratuit) {
            return $erreurs;
        }

        if ($regle['bundle_obligatoire'] && ($prixBundle === null || $prixBundle <= 0)) {
            $erreurs[] = sprintf('Un %s doit avoir un prix pour la sortie complete.', strtolower($regle['libelle']));

            return $erreurs;
        }

        // La vente groupee doit valoir quelque chose : sans remise, personne
        // n'a de raison d'acheter la sortie plutot que les titres un par un.
        if ($prixBundle !== null && $regle['remise_min'] > 0 && $sommeDesTitres > 0) {
            $plafond = $sommeDesTitres * (1 - $regle['remise_min'] / 100);
            if ($prixBundle > $plafond) {
                $erreurs[] = sprintf(
                    'Le prix de la sortie (%s FCFA) doit etre inferieur d\'au moins %d %% a la somme des titres (%s FCFA), soit %s FCFA au plus.',
                    number_format($prixBundle, 0, ',', ' '),
                    $regle['remise_min'],
                    number_format($sommeDesTitres, 0, ',', ' '),
                    number_format($plafond, 0, ',', ' ')
                );
            }
        }

        return $erreurs;
    }

    /**
     * Change le statut d'une sortie, en verifiant sa composition des qu'elle
     * quitte le brouillon.
     *
     * @return array{succes:bool, erreurs:string[]}
     */
    public static function changerStatut(int $releaseId, string $statut, ?int $parQui = null, string $motif = ''): array
    {
        if (!in_array($statut, self::STATUTS, true)) {
            return ['succes' => false, 'erreurs' => ['Statut inconnu.']];
        }

        if (in_array($statut, ['pending', 'approved'], true)) {
            $erreurs = self::validerPublication($releaseId);
            if ($erreurs !== []) {
                return ['succes' => false, 'erreurs' => $erreurs];
            }
        }

        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.']];
        }

        try {
            $db->prepare(
                'UPDATE releases
                 SET status = ?, rejected_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?'
            )->execute([$statut, $statut === 'rejected' ? ($motif ?: null) : null, $parQui, $releaseId]);
        } catch (Throwable $e) {
            error_log('[Tchadok][sorties] changement de statut impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Enregistrement impossible pour le moment.']];
        }

        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer(
                match ($statut) {
                    'approved' => 'contenu.approuve',
                    'rejected' => 'contenu.rejete',
                    default    => 'contenu.cree',
                },
                [
                    'cible_type' => 'sortie',
                    'cible_id'   => $releaseId,
                    'apres'      => ['statut' => $statut],
                    'raison'     => $motif !== '' ? $motif : null,
                    'acteur'     => $parQui,
                ]
            );
        }

        return ['succes' => true, 'erreurs' => []];
    }

    /**
     * Identifiant lisible, unique dans la table indiquee.
     */
    public static function slug(string $texte, string $table, ?int $exclure = null): string
    {
        $base = self::normaliser($texte);
        if ($base === '') {
            $base = 'sortie';
        }

        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db || !in_array($table, ['releases', 'tracks', 'artists'], true)) {
            return $base;
        }

        $slug = $base;
        $suffixe = 2;

        while (true) {
            $sql = "SELECT id FROM `{$table}` WHERE slug = ?" . ($exclure !== null ? ' AND id <> ?' : '') . ' LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute($exclure !== null ? [$slug, $exclure] : [$slug]);

            if ($stmt->fetchColumn() === false) {
                return $slug;
            }

            $slug = $base . '-' . $suffixe;
            $suffixe++;
        }
    }

    /**
     * Texte ramene a des minuscules, des chiffres et des traits d'union.
     * Les accents sont translitteres : « Kélou » donne « kelou », pas « klou ».
     */
    public static function normaliser(string $texte): string
    {
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
        ];

        $texte = mb_strtolower(trim($texte), 'UTF-8');
        $texte = strtr($texte, $accents);
        $texte = preg_replace('/[^a-z0-9]+/', '-', $texte) ?? '';

        return trim(substr($texte, 0, 200), '-');
    }
}
