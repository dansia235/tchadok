<?php
/**
 * Paiement Premium - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verifier si l'utilisateur est connecte
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=premium-payment');
    exit();
}

$pageTitle = 'Paiement Premium';
$pageDescription = 'Souscrivez a l\'abonnement Premium';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
$success = '';
$error = '';

// Plans d'abonnement
$plans = [
    'monthly' => [
        'name' => 'Mensuel',
        'price' => 2500,
        'duration' => 'mois',
        'savings' => null
    ],
    'yearly' => [
        'name' => 'Annuel',
        'price' => 25000,
        'duration' => 'an',
        'savings' => 5000
    ]
];

// Recuperer le plan selectionne
$selectedPlan = isset($_GET['plan']) && isset($plans[$_GET['plan']]) ? $_GET['plan'] : 'monthly';

// Traiter le paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planType = sanitizeInput($_POST['plan_type'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');

    if (!isset($plans[$planType])) {
        $error = 'Plan d\'abonnement invalide.';
    } elseif (empty($paymentMethod)) {
        $error = 'Veuillez selectionner une methode de paiement.';
    } elseif (empty($phoneNumber)) {
        $error = 'Veuillez entrer votre numero de telephone.';
    } elseif (!preg_match('/^[0-9]{8,10}$/', str_replace(' ', '', $phoneNumber))) {
        $error = 'Numero de telephone invalide.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];
            $amount = $plans[$planType]['price'];
            $transactionId = 'TCHAD' . time() . rand(1000, 9999);

            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO payment_transactions (user_id, amount, currency, payment_method, transaction_id, phone_number, status, created_at)
                VALUES (?, ?, 'XAF', ?, ?, ?, 'pending', NOW())
            ");

            $stmt->execute([
                $userId,
                $amount,
                $paymentMethod,
                $transactionId,
                $phoneNumber
            ]);

            $transactionDbId = $db->lastInsertId();

            $startDate = date('Y-m-d H:i:s');
            $endDate = $planType === 'monthly'
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : date('Y-m-d H:i:s', strtotime('+1 year'));

            $stmt = $db->prepare("
                INSERT INTO subscriptions (user_id, plan_type, amount, currency, payment_method, transaction_id, status, start_date, end_date, created_at)
                VALUES (?, ?, ?, 'XAF', ?, ?, 'pending', ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $planType,
                $amount,
                $paymentMethod,
                $transactionId,
                $startDate,
                $endDate
            ]);

            $subscriptionId = $db->lastInsertId();

            $stmt = $db->prepare("UPDATE payment_transactions SET subscription_id = ? WHERE id = ?");
            $stmt->execute([$subscriptionId, $transactionDbId]);

            $db->commit();

            $success = 'Votre demande de paiement a ete enregistree. Vous recevrez une confirmation des que la transaction sera validee.';

            header('refresh:3;url=' . SITE_URL . '/user-dashboard.php');
        } catch (Exception $e) {
            if (isset($db) && $db && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = GestionErreurs::messagePublic($e, 'paiement premium');
        }
    }
}

$additionalJS = [
    SITE_URL . '/assets/js/premium-payment.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17] pb-16 pt-8">
    <section>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-center gap-4">
                    <a href="<?php echo SITE_URL; ?>/premium.php" class="grid h-11 w-11 place-items-center rounded-2xl border border-white/10 bg-white/5 text-text hover:bg-white/10">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-amber-400/20 text-amber-300">
                            <i class="fas fa-crown text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Premium</p>
                            <h1 class="mt-1 text-3xl font-display font-bold text-text">Paiement Premium</h1>
                            <p class="mt-2 text-sm text-muted">
                                <i class="fas fa-user mr-2"></i>
                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle mt-0.5"></i>
                                <div>
                                    <p><?php echo $success; ?></p>
                                    <div class="mt-3 flex items-center gap-2 text-xs text-emerald-100/80">
                                        <span class="inline-flex h-4 w-4 items-center justify-center">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                            </svg>
                                        </span>
                                        Redirection vers votre dashboard...
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle mt-0.5"></i>
                                <span><?php echo $error; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!$success): ?>
                            <form method="POST" action="" id="paymentForm" class="space-y-8">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="plan_type" value="<?php echo htmlspecialchars($selectedPlan); ?>" data-plan-input>

                                <div>
                                    <h2 class="text-lg font-semibold text-text">Choisissez votre plan</h2>
                                    <p class="mt-2 text-sm text-muted">Selectionnez le rythme qui vous convient.</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <?php foreach ($plans as $key => $plan): ?>
                                        <label class="block">
                                            <input type="radio"
                                                   id="plan_<?php echo $key; ?>"
                                                   name="plan_type"
                                                   value="<?php echo $key; ?>"
                                                   class="peer sr-only"
                                                   data-plan-radio
                                                   <?php echo $key === $selectedPlan ? 'checked' : ''; ?>>
                                            <div class="flex h-full flex-col justify-between rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 peer-checked:border-amber-400/60 peer-checked:bg-amber-400/10">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-sm font-semibold text-text"><?php echo $plan['name']; ?></h3>
                                                    <?php if ($plan['savings']): ?>
                                                        <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200">
                                                            Economisez <?php echo number_format($plan['savings'], 0, ',', ' '); ?> XAF
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-4 flex items-baseline gap-2">
                                                    <span class="text-2xl font-semibold text-text"><?php echo number_format($plan['price'], 0, ',', ' '); ?></span>
                                                    <span class="text-xs text-muted">XAF / <?php echo $plan['duration']; ?></span>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div>
                                    <h2 class="text-lg font-semibold text-text">Methode de paiement</h2>
                                    <p class="mt-2 text-sm text-muted">Choisissez votre canal prefere.</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <?php
                                        $methods = [
                                            ['value' => 'airtel_money', 'label' => 'Airtel Money', 'note' => 'Paiement mobile securise', 'color' => 'bg-rose-500/20 text-rose-200', 'icon' => 'fa-mobile-alt'],
                                            ['value' => 'moov_money', 'label' => 'Moov Money', 'note' => 'Paiement mobile securise', 'color' => 'bg-sky-500/20 text-sky-200', 'icon' => 'fa-mobile-alt'],
                                            ['value' => 'salam_pay', 'label' => 'Salam Pay', 'note' => 'Paiement mobile securise', 'color' => 'bg-emerald-500/20 text-emerald-200', 'icon' => 'fa-mobile-alt'],
                                            ['value' => 'credit_card', 'label' => 'Carte bancaire', 'note' => 'Visa, Mastercard', 'color' => 'bg-white/10 text-text', 'icon' => 'fa-credit-card']
                                        ];
                                    ?>
                                    <?php foreach ($methods as $method): ?>
                                        <label class="block">
                                            <input type="radio" name="payment_method" value="<?php echo $method['value']; ?>" class="peer sr-only" required>
                                            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 peer-checked:border-accent peer-checked:bg-accent/10">
                                                <span class="grid h-11 w-11 place-items-center rounded-xl <?php echo $method['color']; ?>">
                                                    <i class="fas <?php echo $method['icon']; ?>"></i>
                                                </span>
                                                <div>
                                                    <p class="text-sm font-semibold text-text"><?php echo $method['label']; ?></p>
                                                    <p class="text-xs text-muted"><?php echo $method['note']; ?></p>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div>
                                    <h2 class="text-lg font-semibold text-text">Informations de contact</h2>
                                    <p class="mt-2 text-sm text-muted">Le numero utilise pour confirmer le paiement.</p>
                                </div>

                                <div>
                                    <label for="phone_number" class="text-sm font-semibold text-text">Numero de telephone *</label>
                                    <div class="mt-2 flex">
                                        <span class="inline-flex items-center rounded-l-2xl border border-white/10 bg-white/5 px-4 text-sm font-semibold text-text">+235</span>
                                        <input type="tel"
                                               class="w-full rounded-r-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                               id="phone_number"
                                               name="phone_number"
                                               placeholder="XX XX XX XX"
                                               data-phone-input
                                               required>
                                    </div>
                                    <p class="mt-2 text-xs text-muted">Vous recevrez une notification de paiement sur ce numero.</p>
                                </div>

                                <label class="flex items-start gap-3 text-xs text-muted">
                                    <input class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" type="checkbox" id="terms" required>
                                    <span>J'accepte les conditions d'utilisation et la politique de confidentialite.</span>
                                </label>

                                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-bg shadow-elev-1 hover:shadow-elev-2">
                                    <i class="fas fa-lock"></i>
                                    Payer <?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF
                                </button>

                                <p class="text-center text-xs text-muted">
                                    <i class="fas fa-shield-alt mr-1"></i>
                                    Paiement securise et chiffre
                                </p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 lg:sticky lg:top-8">
                        <h2 class="text-base font-semibold text-text">Recapitulatif</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted">Plan selectionne</span>
                                <span class="font-semibold text-text"><?php echo $plans[$selectedPlan]['name']; ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted">Prix</span>
                                <span class="font-semibold text-text"><?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF</span>
                            </div>
                            <div class="h-px bg-white/10"></div>
                            <div class="flex items-center justify-between text-base">
                                <span class="text-muted">Total a payer</span>
                                <span class="font-semibold text-accent"><?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF</span>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <h3 class="text-sm font-semibold text-text">Avantages Premium</h3>
                            <ul class="mt-3 space-y-2 text-xs text-muted">
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Ecoute illimitee</li>
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Sans publicite</li>
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Qualite audio HD</li>
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Telechargement offline</li>
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Acces anticipe</li>
                                <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Support prioritaire</li>
                            </ul>
                        </div>

                        <a href="<?php echo SITE_URL; ?>/wallet.php" class="mt-4 flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text hover:bg-white/10">
                            <span>Verifier mon portefeuille</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                        </a>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 text-xs text-muted shadow-elev-1">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-lock text-emerald-300"></i>
                            Paiement 100% securise
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <i class="fas fa-redo text-accent"></i>
                            Renouvellement automatique
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <i class="fas fa-times-circle text-rose-300"></i>
                            Annulation a tout moment
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
