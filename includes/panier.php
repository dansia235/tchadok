<?php
/**
 * Panier (SHOP-01) et passage en commande (SHOP-02).
 *
 * Le panier d'un membre est une commande au statut `cart` (DATA-05) ; celui
 * d'un visiteur vit en session et rejoint la base a la connexion.
 *
 * REGLES
 *   1. Le prix vient TOUJOURS de la base, jamais du client. Il est relu a
 *      chaque affichage et au passage en commande ; s'il a change entre-temps,
 *      le client en est averti AVANT de payer, jamais debite en silence.
 *   2. Ne se vend que ce qui est publie, payant, d'un artiste actif -- et que
 *      le membre ne possede pas deja (achat direct, ou via la sortie).
 *   3. Pas de double achat dans un meme panier : un titre deja couvert par
 *      une sortie presente est refuse ; ajouter la sortie retire ses titres.
 *   4. Passer commande fige le panier (`awaiting_payment`) : il ne bouge plus,
 *      et un nouveau panier s'ouvre pour les achats suivants.
 */

declare(strict_types=1);

final class Panier
{
    public const TYPES = ['track', 'release'];
    public const MAX_ARTICLES = 50;

    // -----------------------------------------------------------------
    // Consultation
    // -----------------------------------------------------------------

    /**
     * Contenu du panier, prix relus en base. Les articles devenus invendables
     * sont retires, les prix modifies mis a jour -- et chaque changement est
     * annonce dans `avertissements`.
     *
     * @return array{order_id:?int, reference:?string, lignes:array<int,array<string,mixed>>, sous_total:float, frais:float, total:float, avertissements:string[]}
     */
    public static function contenu(?int $userId): array
    {
        $db = self::base();
        $vide = ['order_id' => null, 'reference' => null, 'lignes' => [], 'sous_total' => 0.0, 'frais' => 0.0, 'total' => 0.0, 'avertissements' => []];
        if (!$db) {
            return $vide;
        }

        if ($userId === null) {
            return self::contenuInvite($db) + $vide;
        }

        $avertissements = self::fusionner($userId);
        $orderId = self::panierExistant($db, $userId);
        if ($orderId === null) {
            return ['avertissements' => $avertissements] + $vide;
        }

        $avertissements = array_merge($avertissements, self::rafraichir($db, $userId, $orderId));

        $stmt = $db->prepare('SELECT item_type, item_id, label, unit_price, quantity, artist_id FROM order_items WHERE order_id = ? ORDER BY id');
        $stmt->execute([$orderId]);
        $lignes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $article = self::article($db, (string) $l['item_type'], (int) $l['item_id']);
            $lignes[] = [
                'type'    => (string) $l['item_type'],
                'id'      => (int) $l['item_id'],
                'libelle' => (string) $l['label'],
                'artiste' => $article['artiste'] ?? '',
                'format'  => $article['format'] ?? null,
                'prix'    => (float) $l['unit_price'],
            ];
        }

        $stmt = $db->prepare('SELECT reference, subtotal, gateway_fee, total FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'order_id'       => $orderId,
            'reference'      => (string) $o['reference'],
            'lignes'         => $lignes,
            'sous_total'     => (float) $o['subtotal'],
            'frais'          => (float) $o['gateway_fee'],
            'total'          => (float) $o['total'],
            'avertissements' => $avertissements,
        ];
    }

    /** Nombre d'articles, pour le badge de l'en-tete. Une seule requete. */
    public static function compter(?int $userId): int
    {
        if ($userId === null) {
            return count(self::sessionInvite());
        }
        $db = self::base();
        if (!$db) {
            return 0;
        }
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.user_id = ? AND o.status = 'cart'"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() + count(self::sessionInvite());
    }

    // -----------------------------------------------------------------
    // Modification
    // -----------------------------------------------------------------

    /**
     * @return array{succes:bool, message:string}
     */
    public static function ajouter(?int $userId, string $type, int $id): array
    {
        $db = self::base();
        if (!$db) {
            return self::echec('Service momentanement indisponible.');
        }
        if (!in_array($type, self::TYPES, true) || $id <= 0) {
            return self::echec('Article inconnu.');
        }

        $article = self::article($db, $type, $id);
        if ($article === null) {
            return self::echec('Article introuvable.');
        }
        if ($article['motif'] !== null) {
            return self::echec($article['motif']);
        }
        if ($userId !== null && self::possede($db, $userId, $type, $id, $article['release_id'])) {
            return self::echec($type === 'track' ? 'Vous possedez deja ce titre.' : 'Vous possedez deja cette sortie.');
        }

        // Contenu actuel, pour les controles de double achat.
        $actuels = $userId === null ? self::sessionInvite() : self::lignesEnBase($db, $userId);
        $cle = $type . ':' . $id;
        if (isset($actuels[$cle])) {
            return ['succes' => true, 'message' => 'Deja dans votre panier.'];
        }
        if ($type === 'track' && $article['release_id'] && isset($actuels['release:' . $article['release_id']])) {
            return self::echec('Ce titre est deja inclus dans une sortie de votre panier.');
        }
        if (count($actuels) >= self::MAX_ARTICLES) {
            return self::echec('Votre panier est plein (' . self::MAX_ARTICLES . ' articles).');
        }

        // Ajouter une sortie retire ses titres achetes a l'unite : ils sont inclus.
        $retires = 0;
        if ($type === 'release') {
            foreach (self::titresDeSortie($db, $id) as $trackId) {
                if (isset($actuels['track:' . $trackId])) {
                    self::retirer($userId, 'track', $trackId);
                    $retires++;
                }
            }
        }

        if ($userId === null) {
            $_SESSION['panier_invite'][$cle] = ['type' => $type, 'id' => $id];
        } else {
            $orderId = Commandes::panier($userId);
            if ($orderId === null) {
                return self::echec('Panier indisponible pour le moment.');
            }
            $resultat = Commandes::ajouterArticle($orderId, $type, $id, $article['prix'], $article['artist_id'], $article['libelle'], $article['format']);
            if (!$resultat['succes']) {
                return self::echec($resultat['erreurs'][0] ?? 'Ajout impossible.');
            }
        }

        return ['succes' => true, 'message' => 'Ajoute au panier.' . ($retires > 0 ? " {$retires} titre(s) de cette sortie retire(s) : ils y sont inclus." : '')];
    }

    public static function retirer(?int $userId, string $type, int $id): bool
    {
        if ($userId === null) {
            unset($_SESSION['panier_invite'][$type . ':' . $id]);
            return true;
        }

        $db = self::base();
        $orderId = $db ? self::panierExistant($db, $userId) : null;
        if ($orderId === null) {
            return false;
        }
        $db->prepare('DELETE FROM order_items WHERE order_id = ? AND item_type = ? AND item_id = ?')->execute([$orderId, $type, $id]);
        Commandes::recalculer($orderId);
        return true;
    }

    /**
     * Passe le panier en commande : prix relus une derniere fois, puis figes.
     *
     * @return array{succes:bool, erreur:?string, reference:?string, avertissements:string[]}
     */
    public static function commander(int $userId): array
    {
        $contenu = self::contenu($userId);
        if ($contenu['avertissements'] !== []) {
            // Quelque chose a change depuis le dernier affichage : le client
            // doit le voir avant de payer.
            return ['succes' => false, 'erreur' => 'Votre panier a change. Verifiez-le avant de payer.', 'reference' => null, 'avertissements' => $contenu['avertissements']];
        }
        if ($contenu['order_id'] === null || $contenu['lignes'] === []) {
            return ['succes' => false, 'erreur' => 'Votre panier est vide.', 'reference' => null, 'avertissements' => []];
        }

        $db = self::base();
        $stmt = $db->prepare("UPDATE orders SET status = 'awaiting_payment' WHERE id = ? AND user_id = ? AND status = 'cart'");
        $stmt->execute([$contenu['order_id'], $userId]);
        if ($stmt->rowCount() !== 1) {
            return ['succes' => false, 'erreur' => 'Commande impossible pour le moment.', 'reference' => null, 'avertissements' => []];
        }

        return ['succes' => true, 'erreur' => null, 'reference' => $contenu['reference'], 'avertissements' => []];
    }

    /**
     * Commandes passees et non payees, que le membre peut regler ou reprendre.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function commandesARegler(int $userId): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }
        $stmt = $db->prepare(
            "SELECT reference, status, total, created_at FROM orders
              WHERE user_id = ? AND status IN ('awaiting_payment', 'failed', 'expired', 'cancelled')
                AND total > 0
              ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bouton « Ajouter au panier » (assets/js/panier.js). Rien pour un article
     * gratuit : il s'ecoute sans achat. Le prix affiche n'est qu'une
     * indication -- celui qui sera facture est relu en base.
     */
    public static function bouton(string $type, int $id, float $prix, bool $gratuit, string $classes = ''): string
    {
        if ($gratuit || $prix <= 0 || !in_array($type, self::TYPES, true)) {
            return '';
        }
        $libelle = number_format($prix, 0, ',', ' ') . ' FCFA';
        return '<button type="button" data-panier-ajouter data-type="' . $type . '" data-id="' . $id . '"'
            . ' class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-text hover:bg-white/10 ' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="Ajouter au panier, ' . $libelle . '"><i class="fas fa-basket-shopping"></i> ' . $libelle . '</button>';
    }

    // -----------------------------------------------------------------
    // Articles
    // -----------------------------------------------------------------

    /**
     * Article vendable ? Prix lu en base. `motif` non nul = invendable, avec
     * la raison a afficher.
     *
     * @return array{type:string, id:int, libelle:string, artiste:string, artist_id:int, prix:float, format:?string, release_id:?int, motif:?string}|null
     */
    public static function article(PDO $db, string $type, int $id): ?array
    {
        if ($type === 'track') {
            $stmt = $db->prepare(
                'SELECT t.id, t.title, t.price, t.is_free, t.status, t.deleted_at, t.release_id, t.artist_id,
                        ar.stage_name, ar.is_active AS artiste_actif, ar.deleted_at AS artiste_retire,
                        r.allow_track_buy
                   FROM tracks t
                   JOIN artists ar ON ar.id = t.artist_id
                   LEFT JOIN releases r ON r.id = t.release_id
                  WHERE t.id = ?'
            );
        } elseif ($type === 'release') {
            $stmt = $db->prepare(
                'SELECT r.id, r.title, r.price_bundle AS price, r.is_free, r.status, r.deleted_at, NULL AS release_id, r.artist_id,
                        r.format, ar.stage_name, ar.is_active AS artiste_actif, ar.deleted_at AS artiste_retire
                   FROM releases r
                   JOIN artists ar ON ar.id = r.artist_id
                  WHERE r.id = ?'
            );
        } else {
            return null;
        }
        $stmt->execute([$id]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) {
            return null;
        }

        $motif = null;
        if ($a['status'] !== 'approved' || $a['deleted_at'] !== null) {
            $motif = 'Cet article n\'est pas disponible a la vente.';
        } elseif ((int) $a['artiste_actif'] !== 1 || $a['artiste_retire'] !== null) {
            $motif = 'Cet artiste n\'est plus disponible.';
        } elseif ((int) $a['is_free'] === 1 || (float) $a['price'] <= 0) {
            $motif = 'Cet article est gratuit : il s\'ecoute sans achat.';
        } elseif ($type === 'track' && $a['release_id'] !== null && isset($a['allow_track_buy']) && (int) $a['allow_track_buy'] === 0) {
            $motif = 'Ce titre ne se vend qu\'avec la sortie complete.';
        }

        return [
            'type'       => $type,
            'id'         => (int) $a['id'],
            'libelle'    => (string) $a['title'],
            'artiste'    => (string) $a['stage_name'],
            'artist_id'  => (int) $a['artist_id'],
            'prix'       => (float) $a['price'],
            'format'     => $type === 'release' ? (string) $a['format'] : null,
            'release_id' => $a['release_id'] !== null ? (int) $a['release_id'] : null,
            'motif'      => $motif,
        ];
    }

    /**
     * Le membre possede-t-il deja l'article (droit actif, direct ou via la
     * sortie qui contient le titre) ?
     */
    public static function possede(PDO $db, int $userId, string $type, int $id, ?int $releaseId = null): bool
    {
        $stmt = $db->prepare(
            "SELECT 1 FROM entitlements
              WHERE user_id = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
                AND source IN ('purchase', 'gift', 'promo')
                AND ((item_type = ? AND item_id = ?) OR (item_type = 'release' AND item_id = ? AND ? > 0))
              LIMIT 1"
        );
        $stmt->execute([$userId, $type, $id, (int) $releaseId, (int) $releaseId]);
        return (bool) $stmt->fetchColumn();
    }

    // -----------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------

    /**
     * Relit chaque ligne : retire l'invendable et le deja possede, met a jour
     * les prix modifies (prix ET commission, en recreant la ligne).
     *
     * @return string[] avertissements
     */
    private static function rafraichir(PDO $db, int $userId, int $orderId): array
    {
        $avertissements = [];
        $stmt = $db->prepare('SELECT item_type, item_id, label, unit_price FROM order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $type = (string) $l['item_type'];
            $id = (int) $l['item_id'];
            if (!in_array($type, self::TYPES, true)) {
                continue;
            }
            $article = self::article($db, $type, $id);

            if ($article === null || $article['motif'] !== null) {
                self::retirer($userId, $type, $id);
                $avertissements[] = sprintf('« %s » a ete retire : %s', $l['label'], $article['motif'] ?? 'article introuvable.');
            } elseif (self::possede($db, $userId, $type, $id, $article['release_id'])) {
                self::retirer($userId, $type, $id);
                $avertissements[] = sprintf('« %s » a ete retire : vous le possedez deja.', $l['label']);
            } elseif (abs($article['prix'] - (float) $l['unit_price']) > 0.001) {
                self::retirer($userId, $type, $id);
                Commandes::ajouterArticle($orderId, $type, $id, $article['prix'], $article['artist_id'], $article['libelle'], $article['format']);
                $avertissements[] = sprintf('Le prix de « %s » est passe de %s a %s.', $l['label'],
                    number_format((float) $l['unit_price'], 0, ',', ' '), number_format($article['prix'], 0, ',', ' ') . ' FCFA');
            }
        }

        return $avertissements;
    }

    /**
     * Verse le panier du visiteur dans le panier du membre qui vient de se
     * connecter.
     *
     * @return string[]
     */
    private static function fusionner(int $userId): array
    {
        $invite = self::sessionInvite();
        if ($invite === []) {
            return [];
        }
        unset($_SESSION['panier_invite']);

        $avertissements = [];
        foreach ($invite as $a) {
            $r = self::ajouter($userId, $a['type'], $a['id']);
            if (!$r['succes']) {
                $avertissements[] = $r['message'];
            }
        }
        return $avertissements;
    }

    /** @return array<string,array{type:string, id:int}> */
    private static function sessionInvite(): array
    {
        $brut = $_SESSION['panier_invite'] ?? [];
        $propre = [];
        foreach (is_array($brut) ? $brut : [] as $a) {
            if (is_array($a) && in_array($a['type'] ?? '', self::TYPES, true) && (int) ($a['id'] ?? 0) > 0) {
                $propre[$a['type'] . ':' . (int) $a['id']] = ['type' => (string) $a['type'], 'id' => (int) $a['id']];
            }
        }
        return $propre;
    }

    private static function contenuInvite(PDO $db): array
    {
        $lignes = [];
        $total = 0.0;
        $avertissements = [];
        foreach (self::sessionInvite() as $cle => $a) {
            $article = self::article($db, $a['type'], $a['id']);
            if ($article === null || $article['motif'] !== null) {
                unset($_SESSION['panier_invite'][$cle]);
                $avertissements[] = 'Un article indisponible a ete retire de votre panier.';
                continue;
            }
            $lignes[] = ['type' => $a['type'], 'id' => $a['id'], 'libelle' => $article['libelle'], 'artiste' => $article['artiste'],
                         'format' => $article['format'], 'prix' => $article['prix']];
            $total += $article['prix'];
        }
        return ['lignes' => $lignes, 'sous_total' => $total, 'frais' => 0.0, 'total' => $total, 'avertissements' => $avertissements];
    }

    /** @return array<string,true> cles « type:id » */
    private static function lignesEnBase(PDO $db, int $userId): array
    {
        $orderId = self::panierExistant($db, $userId);
        if ($orderId === null) {
            return [];
        }
        $stmt = $db->prepare('SELECT item_type, item_id FROM order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $cles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $cles[$l['item_type'] . ':' . $l['item_id']] = true;
        }
        return $cles;
    }

    /** @return int[] */
    private static function titresDeSortie(PDO $db, int $releaseId): array
    {
        $stmt = $db->prepare('SELECT id FROM tracks WHERE release_id = ? AND deleted_at IS NULL');
        $stmt->execute([$releaseId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function panierExistant(PDO $db, int $userId): ?int
    {
        $stmt = $db->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'cart' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array{succes:false, message:string} */
    private static function echec(string $message): array
    {
        return ['succes' => false, 'message' => $message];
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
