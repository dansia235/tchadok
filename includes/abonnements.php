<?php
/**
 * Abonnements Premium (LOT 7).
 *
 * SOURCE DE VERITE : la table `subscriptions`. `users.premium_status` et
 * `users.premium_expires_at` n'en sont que le cache, tenu a jour par
 * synchroniser() -- et lu par media-access.php avec controle de la date, de
 * sorte qu'un abonnement echu perd ses avantages meme si la tache de nuit n'a
 * pas encore tourne.
 *
 * Le statut n'est plus pris en session a la connexion (SUB-03) : estPremium()
 * le lit en base, une fois par requete.
 */

declare(strict_types=1);

final class Abonnements
{
    /**
     * Ce que Premium apporte REELLEMENT (SUB-04). Chaque avantage annonce sur
     * premium.php vient de cette liste, et chacun est verifie par le code :
     * n'ajouter ici qu'un avantage implemente.
     *
     *   ecoute_integrale  media-access.php : titres payants en entier, et
     *                     non en extrait de 30 s
     *   playlists         Abonnements::peutCreerPlaylist() : illimitees, contre
     *                     FREE_PLAYLIST_LIMIT pour un compte gratuit
     *   priorite_support  admin/remboursements.php : reclamations des abonnes
     *                     traitees en premier
     */
    public const AVANTAGES = [
        'ecoute_integrale' => ['icone' => 'fa-infinity',     'titre' => 'Tout le catalogue en entier',
                               'texte' => 'Ecoutez chaque titre dans son integralite, y compris les titres payants, au lieu d\'un extrait de 30 secondes.'],
        'playlists'        => ['icone' => 'fa-layer-group',  'titre' => 'Playlists illimitees',
                               'texte' => 'Creez autant de playlists que vous voulez (10 avec un compte gratuit).'],
        'priorite_support' => ['icone' => 'fa-headset',      'titre' => 'Reclamations prioritaires',
                               'texte' => 'Vos reclamations passent en tete de la file de notre equipe.'],
    ];

    /** Delais des rappels avant echeance, en jours. */
    public const RAPPELS = [7, 1];

    /** @var array<int,bool> */
    private static array $cache = [];

    // -----------------------------------------------------------------
    // Plans (SUB-01)
    // -----------------------------------------------------------------

    /**
     * Plans actifs, prix lu dans la grille administree.
     *
     * @return array<string,array{id:int, code:string, label:string, duree:int, prix:float}>
     */
    public static function plans(bool $actifsSeulement = true): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }
        $plans = [];
        foreach ($db->query('SELECT * FROM subscription_plans' . ($actifsSeulement ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $regle = Tarifs::regle('subscription', (string) $p['code']);
            $plans[(string) $p['code']] = [
                'id'     => (int) $p['id'],
                'code'   => (string) $p['code'],
                'label'  => (string) $p['label'],
                'duree'  => (int) $p['duration_months'],
                'prix'   => $regle !== null ? (float) $regle['suggere'] : 0.0,
                'actif'  => (int) $p['is_active'] === 1,
            ];
        }
        return $plans;
    }

    // -----------------------------------------------------------------
    // Statut (SUB-03)
    // -----------------------------------------------------------------

    /** Abonnement actif maintenant ? Lu en base, une fois par requete. */
    public static function estPremium(?int $userId): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }
        if (!array_key_exists($userId, self::$cache)) {
            $db = self::base();
            $actif = false;
            if ($db) {
                $stmt = $db->prepare("SELECT 1 FROM subscriptions WHERE user_id = ? AND status = 'active' AND start_date <= NOW() AND end_date > NOW() LIMIT 1");
                $stmt->execute([$userId]);
                $actif = (bool) $stmt->fetchColumn();
            }
            self::$cache[$userId] = $actif;
        }
        return self::$cache[$userId];
    }

    /**
     * Situation d'un membre : periode en cours, periode suivante deja payee,
     * historique.
     *
     * @return array{courant:?array, suivant:?array, fin:?string, historique:array}
     */
    public static function situation(int $userId): array
    {
        $db = self::base();
        $vide = ['courant' => null, 'suivant' => null, 'fin' => null, 'historique' => []];
        if (!$db) {
            return $vide;
        }
        $stmt = $db->prepare(
            'SELECT s.*, p.label, o.reference AS order_reference FROM subscriptions s
               LEFT JOIN subscription_plans p ON p.id = s.plan_id
               LEFT JOIN orders o ON o.id = s.order_id
              WHERE s.user_id = ? AND s.order_id IS NOT NULL
              ORDER BY s.start_date DESC'
        );
        $stmt->execute([$userId]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $maintenant = time();
        $courant = $suivant = null;
        $fin = null;
        foreach ($lignes as $l) {
            if ($l['status'] !== 'active') {
                continue;
            }
            $debut = strtotime((string) $l['start_date']);
            $echeance = strtotime((string) $l['end_date']);
            if ($debut <= $maintenant && $echeance > $maintenant) {
                $courant = $l;
            } elseif ($debut > $maintenant) {
                $suivant = $l;
            }
            if ($echeance > $maintenant && ($fin === null || $echeance > strtotime($fin))) {
                $fin = (string) $l['end_date'];
            }
        }

        return ['courant' => $courant, 'suivant' => $suivant, 'fin' => $fin, 'historique' => $lignes];
    }

    // -----------------------------------------------------------------
    // Souscription (SUB-02)
    // -----------------------------------------------------------------

    /**
     * Cree la commande d'abonnement, a regler par le tunnel commun.
     *
     * Un renouvellement n'est ouvert qu'a moins de 7 jours de l'echeance :
     * au-dela, le membre paierait une periode lointaine sans le vouloir.
     *
     * @return array{succes:bool, reference:?string, erreur:?string}
     */
    public static function souscrire(int $userId, string $code): array
    {
        $plans = self::plans();
        if (!isset($plans[$code]) || $plans[$code]['prix'] <= 0) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Formule indisponible.'];
        }
        $situation = self::situation($userId);
        if ($situation['suivant'] !== null) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Votre prochaine periode est deja payee.'];
        }
        if ($situation['fin'] !== null && strtotime($situation['fin']) - time() > 7 * 86400) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Votre abonnement est actif jusqu\'au ' . date('d/m/Y', strtotime($situation['fin'])) . '. Le renouvellement ouvre 7 jours avant l\'echeance.'];
        }

        $db = self::base();
        // Une commande d'abonnement deja passee et non reglee : on la reprend.
        $stmt = $db->prepare(
            "SELECT o.reference FROM orders o JOIN order_items oi ON oi.order_id = o.id
              WHERE o.user_id = ? AND o.status IN ('awaiting_payment', 'failed', 'expired', 'cancelled')
                AND oi.item_type = 'subscription' AND oi.item_id = ? AND o.created_at > NOW() - INTERVAL 1 DAY
              ORDER BY o.id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $plans[$code]['id']]);
        $existante = $stmt->fetchColumn();
        if ($existante !== false) {
            return ['succes' => true, 'reference' => (string) $existante, 'erreur' => null];
        }

        $reference = Commandes::reference();
        $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'awaiting_payment', 'XAF')")->execute([$reference, $userId]);
        $orderId = (int) $db->lastInsertId();
        $ajout = Commandes::ajouterArticle($orderId, 'subscription', $plans[$code]['id'], $plans[$code]['prix'], null, $plans[$code]['label'], $code);
        if (!$ajout['succes']) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Souscription impossible pour le moment.'];
        }
        return ['succes' => true, 'reference' => $reference, 'erreur' => null];
    }

    /**
     * Active l'abonnement d'une commande ENCAISSEE. Appele par la machine a
     * etats dans sa transaction. Idempotent (une ligne par commande).
     *
     * La periode commence maintenant, ou a la fin de la periode deja payee :
     * jamais de chevauchement.
     */
    public static function activer(PDO $db, int $orderId): bool
    {
        $stmt = $db->prepare(
            "SELECT o.user_id, o.payment_method, o.gateway_ref, oi.item_id AS plan_id, oi.unit_price, p.duration_months
               FROM orders o
               JOIN order_items oi ON oi.order_id = o.id AND oi.item_type = 'subscription'
               JOIN subscription_plans p ON p.id = oi.item_id
              WHERE o.id = ? AND o.status = 'paid' LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM subscriptions WHERE order_id = ?');
        $stmt->execute([$orderId]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        // Verrou du membre : deux activations simultanees ne calculent pas la
        // meme date de depart.
        $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$c['user_id']]);
        $stmt = $db->prepare("SELECT MAX(end_date) FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW()");
        $stmt->execute([$c['user_id']]);
        $depart = (string) ($stmt->fetchColumn() ?: date('Y-m-d H:i:s'));

        $db->prepare(
            "INSERT INTO subscriptions (user_id, plan_id, order_id, plan_type, amount, currency, payment_method, transaction_id, status, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, 'XAF', ?, ?, 'active', ?, DATE_ADD(?, INTERVAL ? MONTH))"
        )->execute([
            $c['user_id'], $c['plan_id'], $orderId, (int) $c['duration_months'] >= 12 ? 'yearly' : 'monthly', $c['unit_price'],
            $c['payment_method'], $c['gateway_ref'], $depart, $depart, (int) $c['duration_months'],
        ]);
        self::synchroniser($db, (int) $c['user_id']);
        return true;
    }

    /**
     * Resiliation en un clic : plus de rappel ni de renouvellement, l'acces
     * reste jusqu'a la fin de la periode PAYEE (SUB-02).
     */
    public static function resilier(int $userId): bool
    {
        $db = self::base();
        $stmt = $db->prepare("UPDATE subscriptions SET cancelled_at = NOW() WHERE user_id = ? AND status = 'active' AND end_date > NOW() AND cancelled_at IS NULL");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Commande remboursee ou contestee : la periode correspondante s'arrete.
     */
    public static function revoquerPourCommande(PDO $db, int $orderId): void
    {
        $stmt = $db->prepare('SELECT user_id FROM subscriptions WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $userId = $stmt->fetchColumn();
        if ($userId === false) {
            return;
        }
        $db->prepare(
            "UPDATE subscriptions SET status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW()),
                    end_date = LEAST(end_date, GREATEST(start_date, NOW()))
              WHERE order_id = ? AND status = 'active'"
        )->execute([$orderId]);
        self::synchroniser($db, (int) $userId);
    }

    // -----------------------------------------------------------------
    // Echeances (SUB-03)
    // -----------------------------------------------------------------

    /**
     * Tache quotidienne : clot les periodes echues, envoie les rappels.
     *
     * @return array{expirees:int, rappels:int}
     */
    public static function traiterEcheances(): array
    {
        $db = self::base();
        if (!$db) {
            return ['expirees' => 0, 'rappels' => 0];
        }

        $echus = $db->query("SELECT id, user_id, cancelled_at FROM subscriptions WHERE status = 'active' AND end_date <= NOW()")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($echus as $s) {
            $db->prepare('UPDATE subscriptions SET status = ? WHERE id = ?')->execute([$s['cancelled_at'] !== null ? 'cancelled' : 'expired', $s['id']]);
            // Droits d'acces issus de l'abonnement ; ceux d'un ACHAT ne sont pas touches.
            $db->prepare("UPDATE entitlements SET revoked_at = NOW() WHERE user_id = ? AND source = 'subscription' AND revoked_at IS NULL")->execute([$s['user_id']]);
            self::synchroniser($db, (int) $s['user_id']);
        }

        $rappels = 0;
        foreach (self::RAPPELS as $jours) {
            $colonne = 'reminder_' . $jours . '_sent_at';
            // Seule la DERNIERE periode payee declenche un rappel, et pas apres une resiliation.
            $stmt = $db->prepare(
                "SELECT s.id, s.end_date, u.email, u.first_name FROM subscriptions s JOIN users u ON u.id = s.user_id
                  WHERE s.status = 'active' AND s.cancelled_at IS NULL AND s.{$colonne} IS NULL
                    AND s.end_date > NOW() AND s.end_date <= NOW() + INTERVAL ? DAY
                    AND NOT EXISTS (SELECT 1 FROM subscriptions s2 WHERE s2.user_id = s.user_id AND s2.status = 'active' AND s2.start_date >= s.end_date)"
            );
            $stmt->execute([$jours]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $db->prepare("UPDATE subscriptions SET {$colonne} = NOW() WHERE id = ?")->execute([$s['id']]);
                sendEmail((string) $s['email'], 'Tchadok - votre Premium se termine le ' . date('d/m/Y', strtotime((string) $s['end_date'])),
                    '<p>Bonjour ' . htmlspecialchars((string) $s['first_name'], ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>Votre abonnement Premium prend fin le <strong>' . date('d/m/Y', strtotime((string) $s['end_date'])) . '</strong>. '
                    . 'Il n\'est pas renouvele automatiquement : pour continuer, renouvelez-le depuis '
                    . '<a href="' . rtrim((string) EnvLoader::get('SITE_URL', ''), '/') . '/abonnement.php">votre espace abonnement</a>.</p>');
                $rappels++;
            }
        }

        return ['expirees' => count($echus), 'rappels' => $rappels];
    }

    /**
     * Recalcule le cache users.premium_status / premium_expires_at.
     */
    public static function synchroniser(PDO $db, int $userId): void
    {
        $stmt = $db->prepare("SELECT MAX(end_date) FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW()");
        $stmt->execute([$userId]);
        $fin = $stmt->fetchColumn() ?: null;
        $db->prepare('UPDATE users SET premium_status = ?, premium_expires_at = ? WHERE id = ?')->execute([$fin !== null ? 1 : 0, $fin, $userId]);
        unset(self::$cache[$userId]);
    }

    // -----------------------------------------------------------------
    // Avantages (SUB-04)
    // -----------------------------------------------------------------

    public static function limitePlaylists(): int
    {
        return defined('FREE_PLAYLIST_LIMIT') ? (int) FREE_PLAYLIST_LIMIT : 10;
    }

    /**
     * @return array{permis:bool, message:?string}
     */
    public static function peutCreerPlaylist(int $userId): array
    {
        if (self::estPremium($userId)) {
            return ['permis' => true, 'message' => null];
        }
        $db = self::base();
        $stmt = $db->prepare('SELECT COUNT(*) FROM playlists WHERE user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() >= self::limitePlaylists()) {
            return ['permis' => false, 'message' => sprintf('Un compte gratuit peut creer %d playlists. Passez Premium pour en creer sans limite.', self::limitePlaylists())];
        }
        return ['permis' => true, 'message' => null];
    }

    public static function oublier(): void
    {
        self::$cache = [];
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}

/** Raccourci pour les vues : le membre connecte est-il Premium, maintenant ? */
function estPremium(): bool
{
    return isLoggedIn() && Abonnements::estPremium((int) $_SESSION['user_id']);
}
