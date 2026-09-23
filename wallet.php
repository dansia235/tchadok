<?php
/**
 * Portefeuille - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=wallet');
    exit();
}

$pageTitle = 'Portefeuille';
$pageDescription = 'Pilotez votre solde, vos mouvements et vos points de fidelite';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=wallet');
    exit();
}

$dashboardUrl = SITE_URL . '/user-dashboard.php';
$workspaceLabel = 'Espace fan';
$roleLabel = 'Fan';

if (isAdmin()) {
    $dashboardUrl = SITE_URL . '/admin-dashboard.php';
    $workspaceLabel = 'Console admin';
    $roleLabel = 'Admin';
} elseif (isArtist()) {
    $dashboardUrl = SITE_URL . '/artist-dashboard.php';
    $workspaceLabel = 'Studio artiste';
    $roleLabel = 'Artiste';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$walletBalance = (float) ($user['wallet_balance'] ?? 0);
$loyaltyPoints = (int) ($user['loyalty_points'] ?? 0);
$profileName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($profileName === '') {
    $profileName = $user['username'] ?? 'Utilisateur';
}

$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');
$walletStats = [
    'pending_transactions' => 0,
    'completed_transactions' => 0,
    'total_spent' => 0.0,
    'recent_activity' => 0
];
$walletMovements = [];

try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    if ($db && tableExists('payment_transactions')) {
        $stmt = $db->prepare("
            SELECT transaction_id, amount, payment_method, status, created_at
            FROM payment_transactions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 6
        ");
        $stmt->execute([$userId]);
        $walletMovements = $stmt->fetchAll();
        $walletStats['recent_activity'] = count($walletMovements);

        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status IN ('completed', 'success') THEN 1 ELSE 0 END) AS completed_count
            FROM payment_transactions
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $summaryRow = $stmt->fetch();
        $walletStats['pending_transactions'] = (int) ($summaryRow['pending_count'] ?? 0);
        $walletStats['completed_transactions'] = (int) ($summaryRow['completed_count'] ?? 0);
    }

    // DATA-05 : la depense totale vient des commandes payees.
    if ($db && tableExists('orders')) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total), 0)
            FROM orders
            WHERE user_id = ? AND status = 'paid'
        ");
        $stmt->execute([$userId]);
        $walletStats['total_spent'] = (float) $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $walletMovements = [];
}

if (empty($walletMovements)) {
    $walletMovements = [
        [
            'transaction_id' => 'WALLET-OVERVIEW',
            'amount' => $walletBalance,
            'payment_method' => 'wallet',
            'status' => 'available',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'transaction_id' => 'PREMIUM-READY',
            'amount' => 1500,
            'payment_method' => 'premium',
            'status' => !empty($user['premium_status']) ? 'completed' : 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]
    ];
    $walletStats['recent_activity'] = count($walletMovements);
}

$fundingChannels = [
    [
        'title' => 'Recharge assistee',
        'text' => 'Demandez une recharge ou une verification de solde via le support.',
        'href' => SITE_URL . '/contact.php',
        'icon' => 'headset',
        'tone' => 'bg-accent/20 text-accent',
        'cta' => 'Contacter le support'
    ],
    [
        'title' => 'Abonnement Premium',
        'text' => 'Utilisez votre budget pour activer ou prolonger votre experience Premium.',
        'href' => SITE_URL . '/premium.php',
        'icon' => 'crown',
        'tone' => 'bg-amber-400/20 text-amber-700 dark:text-amber-200',
        'cta' => 'Voir les offres'
    ],
    [
        'title' => 'Preferences de compte',
        'text' => 'Mettez a jour vos coordonnees pour fluidifier les paiements et confirmations.',
        'href' => SITE_URL . '/settings.php',
        'icon' => 'sliders-h',
        'tone' => 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300',
        'cta' => 'Ouvrir les parametres'
    ]
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.10),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.10),transparent_24%),#f5f7fb] pb-16 pt-8 dark:bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17]">
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_340px]">
            <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-elev-2 dark:border-white/10 dark:bg-surface/75 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <a href="<?php echo $dashboardUrl; ?>" class="grid h-12 w-12 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-900 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-text dark:hover:bg-white/10">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <p class="text-xs uppercase tracking-[0.28em] text-muted"><?php echo htmlspecialchars($workspaceLabel); ?></p>
                            <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Portefeuille</h1>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                Centralisez votre solde, vos flux recents, vos points de fidelite et les parcours utiles
                                pour payer, recharger ou demander de l'assistance.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-muted"><?php echo htmlspecialchars($roleLabel); ?></span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-muted"><?php echo htmlspecialchars($profileName); ?></span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-muted">Membre depuis <?php echo htmlspecialchars($memberSince); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:min-w-[240px]">
                        <a href="<?php echo SITE_URL; ?>/premium.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                            <i class="fas fa-crown"></i>
                            Utiliser pour Premium
                        </a>
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-text dark:hover:bg-white/10">
                            <i class="fas fa-headset"></i>
                            Aide et recharge
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-elev-2 dark:border-white/10 dark:bg-surface/75">
                <p class="text-xs uppercase tracking-[0.24em] text-muted">Synthese</p>
                <div class="mt-4 space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Disponible</p>
                        <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatPrice($walletBalance); ?></p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Points</p>
                            <p class="mt-2 text-sm font-semibold text-text"><?php echo formatNumber($loyaltyPoints); ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">En attente</p>
                            <p class="mt-2 text-sm font-semibold text-text"><?php echo formatNumber($walletStats['pending_transactions']); ?> transaction(s)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-elev-1 dark:border-white/10 dark:bg-surface/75">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Solde</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($walletBalance, 0, ',', ' '); ?></p>
                <p class="mt-2 text-xs text-muted">FCFA disponibles</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-elev-1 dark:border-white/10 dark:bg-surface/75">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Points</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($loyaltyPoints); ?></p>
                <p class="mt-2 text-xs text-muted">Fidelite cumulee</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-elev-1 dark:border-white/10 dark:bg-surface/75">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Mouvements</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($walletStats['recent_activity']); ?></p>
                <p class="mt-2 text-xs text-muted">Flux recents visibles</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-elev-1 dark:border-white/10 dark:bg-surface/75">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Depenses</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($walletStats['total_spent'], 0, ',', ' '); ?></p>
                <p class="mt-2 text-xs text-muted">FCFA confirmes</p>
            </div>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_360px]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-elev-2 dark:border-white/10 dark:bg-surface/75">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Flux recents</h2>
                            <p class="mt-2 text-sm text-muted">Une vue consolidee de vos derniers mouvements disponibles.</p>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-muted">
                            <?php echo formatNumber(count($walletMovements)); ?> entree(s)
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        <?php foreach ($walletMovements as $movement): ?>
                            <?php
                                $status = strtolower((string) ($movement['status'] ?? 'pending'));
                                $isPositive = in_array($status, ['available', 'completed', 'success'], true);
                                $statusLabel = $status === 'available' ? 'Disponible' : ucfirst($status);
                                $methodLabel = str_replace('_', ' ', (string) ($movement['payment_method'] ?? 'wallet'));
                            ?>
                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-11 w-11 place-items-center rounded-xl <?php echo $isPositive ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-400/20 text-amber-700 dark:text-amber-200'; ?>">
                                        <i class="fas <?php echo $isPositive ? 'fa-arrow-down' : 'fa-clock'; ?>"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars(strtoupper((string) ($movement['transaction_id'] ?? 'MOUVEMENT'))); ?></p>
                                        <p class="text-xs text-muted">
                                            <?php echo htmlspecialchars(ucwords($methodLabel)); ?>
                                            <?php if (!empty($movement['created_at'])): ?>
                                                - <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $movement['created_at']))); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold <?php echo $isPositive ? 'text-emerald-700 dark:text-emerald-200' : 'text-amber-700 dark:text-amber-200'; ?>">
                                        <?php echo formatPrice((float) ($movement['amount'] ?? 0)); ?>
                                    </p>
                                    <p class="text-xs text-muted"><?php echo htmlspecialchars($statusLabel); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-elev-2 dark:border-white/10 dark:bg-surface/75">
                    <h2 class="text-lg font-semibold text-text">Parcours utiles</h2>
                    <p class="mt-2 text-sm text-muted">Les chemins les plus utiles autour du paiement, du support et de la gestion du compte.</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <?php foreach ($fundingChannels as $channel): ?>
                            <a href="<?php echo $channel['href']; ?>" class="rounded-3xl border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10">
                                <span class="grid h-12 w-12 place-items-center rounded-2xl <?php echo $channel['tone']; ?>">
                                    <i class="fas fa-<?php echo $channel['icon']; ?>"></i>
                                </span>
                                <h3 class="mt-4 text-base font-semibold text-text"><?php echo htmlspecialchars($channel['title']); ?></h3>
                                <p class="mt-2 text-sm leading-6 text-muted"><?php echo htmlspecialchars($channel['text']); ?></p>
                                <span class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-text">
                                    <?php echo htmlspecialchars($channel['cta']); ?>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-elev-2 dark:border-white/10 dark:bg-surface/75">
                    <h3 class="text-base font-semibold text-text">Points de vigilance</h3>
                    <div class="mt-4 space-y-3 text-sm text-muted">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            Les recharges et ajustements manuels passent encore par le support ou les modules de paiement dedies.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            Mettez a jour vos coordonnees avant toute demande de verification ou de remboursement.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            Utilisez le centre de securite pour proteger l'acces a votre budget et vos avantages.
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white to-accent/10 p-6 shadow-elev-1 dark:border-white/10 dark:from-white/5 dark:to-accent/10">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Prochaine action</p>
                    <p class="mt-3 text-sm font-semibold text-text">
                        <?php if ($walletBalance >= 1500): ?>
                            Votre solde couvre deja au moins un plan Premium mensuel. Vous pouvez passer a l'etape suivante.
                        <?php else: ?>
                            Votre solde est limite. Une recharge ou un plan adapte vous aidera a garder un parcours de paiement fluide.
                        <?php endif; ?>
                    </p>
                    <div class="mt-5 grid gap-3">
                        <a href="<?php echo SITE_URL; ?>/premium.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold text-bg">
                            <i class="fas fa-crown"></i>
                            Voir les offres Premium
                        </a>
                        <a href="<?php echo SITE_URL; ?>/security-settings.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-text dark:hover:bg-white/10">
                            <i class="fas fa-shield-alt"></i>
                            Proteger mes paiements
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
