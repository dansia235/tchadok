<?php
/**
 * Paiement Premium - Tchadok Platform
 * Page de souscription et paiement pour l'abonnement Premium
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=premium-payment');
    exit();
}

$pageTitle = 'Paiement Premium';
$pageDescription = 'Souscrivez à l\'abonnement Premium';

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
        'savings' => 5000 // Économie par rapport au mensuel
    ]
];

// Récupérer le plan sélectionné
$selectedPlan = isset($_GET['plan']) && isset($plans[$_GET['plan']]) ? $_GET['plan'] : 'monthly';

// Traiter le paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planType = sanitizeInput($_POST['plan_type'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');

    // Validation
    if (!isset($plans[$planType])) {
        $error = 'Plan d\'abonnement invalide.';
    } elseif (empty($paymentMethod)) {
        $error = 'Veuillez sélectionner une méthode de paiement.';
    } elseif (empty($phoneNumber)) {
        $error = 'Veuillez entrer votre numéro de téléphone.';
    } elseif (!preg_match('/^[0-9]{8,10}$/', str_replace(' ', '', $phoneNumber))) {
        $error = 'Numéro de téléphone invalide.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];
            $amount = $plans[$planType]['price'];
            $transactionId = 'TCHAD' . time() . rand(1000, 9999);

            $db->beginTransaction();

            // Créer la transaction de paiement
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

            // Calculer les dates d'abonnement
            $startDate = date('Y-m-d H:i:s');
            $endDate = $planType === 'monthly'
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : date('Y-m-d H:i:s', strtotime('+1 year'));

            // Créer l'abonnement
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

            // Mettre à jour la transaction avec l'ID de l'abonnement
            $stmt = $db->prepare("UPDATE payment_transactions SET subscription_id = ? WHERE id = ?");
            $stmt->execute([$subscriptionId, $transactionDbId]);

            // SIMULATION: Activer immédiatement l'abonnement (en production, attendre la confirmation du paiement)
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?");
            $stmt->execute([$subscriptionId]);

            $stmt = $db->prepare("UPDATE payment_transactions SET status = 'success' WHERE id = ?");
            $stmt->execute([$transactionDbId]);

            // Mettre à jour le statut premium de l'utilisateur
            $stmt = $db->prepare("UPDATE users SET premium_status = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            $db->commit();

            $_SESSION['user_data']['premium_status'] = 1;

            $success = '✅ Paiement effectué avec succès ! Votre abonnement Premium est maintenant actif.';

            // Redirection après 3 secondes
            header('refresh:3;url=' . SITE_URL . '/user-dashboard.php');

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Une erreur est survenue lors du traitement du paiement: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="payment-container">
    <!-- Header -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="d-flex align-items-center">
                        <a href="<?php echo SITE_URL; ?>/premium.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div class="header-icon-lg">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div>
                            <h1 class="mb-2">Paiement Premium</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenu -->
    <section class="payment-content py-5">
        <div class="container">
            <div class="row">
                <!-- Formulaire de Paiement -->
                <div class="col-lg-8">
                    <div class="payment-card">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-modern mb-4">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <div class="spinner-border spinner-border-sm me-2" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Redirection vers votre dashboard...
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-modern mb-4">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$success): ?>
                            <form method="POST" action="" id="paymentForm">
                                <input type="hidden" name="plan_type" value="<?php echo htmlspecialchars($selectedPlan); ?>">

                                <!-- Sélection du Plan -->
                                <div class="section-title">
                                    <h4><i class="fas fa-star me-2"></i>Choisissez Votre Plan</h4>
                                </div>

                                <div class="plans-selection">
                                    <?php foreach ($plans as $key => $plan): ?>
                                        <div class="plan-option <?php echo $key === $selectedPlan ? 'selected' : ''; ?>">
                                            <input type="radio"
                                                   id="plan_<?php echo $key; ?>"
                                                   name="plan_type"
                                                   value="<?php echo $key; ?>"
                                                   <?php echo $key === $selectedPlan ? 'checked' : ''; ?>
                                                   onchange="updatePlanSelection('<?php echo $key; ?>')">
                                            <label for="plan_<?php echo $key; ?>">
                                                <div class="plan-header">
                                                    <h5><?php echo $plan['name']; ?></h5>
                                                    <?php if ($plan['savings']): ?>
                                                        <span class="badge bg-success">
                                                            Économisez <?php echo number_format($plan['savings'], 0, ',', ' '); ?> XAF
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="plan-price">
                                                    <span class="amount"><?php echo number_format($plan['price'], 0, ',', ' '); ?></span>
                                                    <span class="currency">XAF</span>
                                                    <span class="period">/ <?php echo $plan['duration']; ?></span>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Méthode de Paiement -->
                                <div class="section-title mt-5">
                                    <h4><i class="fas fa-credit-card me-2"></i>Méthode de Paiement</h4>
                                </div>

                                <div class="payment-methods">
                                    <div class="payment-method-option">
                                        <input type="radio" id="airtel" name="payment_method" value="airtel_money" required>
                                        <label for="airtel">
                                            <div class="method-logo airtel">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <div class="method-info">
                                                <strong>Airtel Money</strong>
                                                <small>Paiement mobile sécurisé</small>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="payment-method-option">
                                        <input type="radio" id="moov" name="payment_method" value="moov_money" required>
                                        <label for="moov">
                                            <div class="method-logo moov">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <div class="method-info">
                                                <strong>Moov Money</strong>
                                                <small>Paiement mobile sécurisé</small>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="payment-method-option">
                                        <input type="radio" id="salam" name="payment_method" value="salam_pay" required>
                                        <label for="salam">
                                            <div class="method-logo salam">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <div class="method-info">
                                                <strong>Salam Pay</strong>
                                                <small>Paiement mobile sécurisé</small>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="payment-method-option">
                                        <input type="radio" id="card" name="payment_method" value="credit_card" required>
                                        <label for="card">
                                            <div class="method-logo card">
                                                <i class="fas fa-credit-card"></i>
                                            </div>
                                            <div class="method-info">
                                                <strong>Carte Bancaire</strong>
                                                <small>Visa, Mastercard</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Numéro de Téléphone -->
                                <div class="section-title mt-5">
                                    <h4><i class="fas fa-phone me-2"></i>Informations de Contact</h4>
                                </div>

                                <div class="form-group mb-4">
                                    <label for="phone_number" class="form-label">
                                        Numéro de Téléphone *
                                    </label>
                                    <div class="phone-input-wrapper">
                                        <span class="phone-prefix">+235</span>
                                        <input type="tel"
                                               class="form-control form-control-modern phone-input"
                                               id="phone_number"
                                               name="phone_number"
                                               placeholder="XX XX XX XX"
                                               required>
                                    </div>
                                    <small class="text-muted">
                                        Vous recevrez une notification de paiement sur ce numéro
                                    </small>
                                </div>

                                <!-- Conditions -->
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        J'accepte les <a href="#" class="text-primary">conditions d'utilisation</a>
                                        et la <a href="#" class="text-primary">politique de confidentialité</a>
                                    </label>
                                </div>

                                <!-- Bouton de Paiement -->
                                <button type="submit" class="btn btn-premium btn-lg w-100">
                                    <i class="fas fa-lock me-2"></i>
                                    Payer <?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF
                                </button>

                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Paiement sécurisé et crypté
                                    </small>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Récapitulatif -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt me-2"></i>
                            Récapitulatif
                        </h5>

                        <div class="summary-item">
                            <span>Plan sélectionné</span>
                            <strong><?php echo $plans[$selectedPlan]['name']; ?></strong>
                        </div>

                        <div class="summary-item">
                            <span>Prix</span>
                            <strong><?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF</strong>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-item total">
                            <span>Total à payer</span>
                            <strong class="text-primary">
                                <?php echo number_format($plans[$selectedPlan]['price'], 0, ',', ' '); ?> XAF
                            </strong>
                        </div>

                        <div class="premium-features mt-4">
                            <h6 class="mb-3">Avantages Premium</h6>
                            <ul class="features-list">
                                <li><i class="fas fa-check-circle text-success me-2"></i>Écoute illimitée</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Sans publicité</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Qualité audio HD</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Téléchargement offline</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Accès anticipé</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Support prioritaire</li>
                            </ul>
                        </div>
                    </div>

                    <div class="security-info mt-4">
                        <div class="info-item">
                            <i class="fas fa-lock text-success"></i>
                            <small>Paiement 100% sécurisé</small>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-redo text-primary"></i>
                            <small>Renouvellement automatique</small>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-times-circle text-danger"></i>
                            <small>Annulation à tout moment</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --gris-harmattan: #2C3E50;
}

.payment-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding-bottom: 3rem;
}

/* Page Header */
.page-header {
    background: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-top: 80px;
}

.header-icon-lg {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--jaune-solaire), #FFC700);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bleu-tchadien);
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
}

.page-header h1 {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 2rem;
}

/* Payment Card */
.payment-card {
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.section-title {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.section-title h4 {
    color: var(--gris-harmattan);
    font-weight: 600;
    margin: 0;
}

/* Plans Selection */
.plans-selection {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.plan-option {
    position: relative;
}

.plan-option input[type="radio"] {
    display: none;
}

.plan-option label {
    display: block;
    padding: 2rem 1.5rem;
    border: 3px solid #e9ecef;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0;
    height: 100%;
}

.plan-option input[type="radio"]:checked + label,
.plan-option.selected label {
    border-color: var(--jaune-solaire);
    background: rgba(255, 215, 0, 0.05);
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
}

.plan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.plan-header h5 {
    margin: 0;
    color: var(--gris-harmattan);
    font-weight: 600;
}

.plan-price {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.plan-price .amount {
    font-size: 2rem;
    font-weight: 700;
    color: var(--bleu-tchadien);
}

.plan-price .currency {
    font-size: 1.2rem;
    font-weight: 600;
    color: #6c757d;
}

.plan-price .period {
    font-size: 1rem;
    color: #6c757d;
}

/* Payment Methods */
.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.payment-method-option {
    position: relative;
}

.payment-method-option input[type="radio"] {
    display: none;
}

.payment-method-option label {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0;
}

.payment-method-option input[type="radio"]:checked + label {
    border-color: var(--bleu-tchadien);
    background: rgba(0, 102, 204, 0.05);
}

.method-logo {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 1rem;
    color: white;
}

.method-logo.airtel {
    background: linear-gradient(135deg, #ED1C24, #C1121F);
}

.method-logo.moov {
    background: linear-gradient(135deg, #009FE3, #0077B6);
}

.method-logo.salam {
    background: linear-gradient(135deg, #00A651, #008741);
}

.method-logo.card {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a252f);
}

.method-info strong {
    display: block;
    color: var(--gris-harmattan);
    font-size: 1rem;
}

.method-info small {
    display: block;
    color: #6c757d;
    font-size: 0.85rem;
}

/* Phone Input */
.phone-input-wrapper {
    display: flex;
    align-items: center;
}

.phone-prefix {
    padding: 0.875rem 1rem;
    background: #e9ecef;
    border: 2px solid #e9ecef;
    border-right: none;
    border-radius: 12px 0 0 12px;
    font-weight: 600;
    color: var(--gris-harmattan);
}

.phone-input {
    border-radius: 0 12px 12px 0 !important;
    border-left: none !important;
}

/* Form Controls */
.form-control-modern {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.1);
}

/* Premium Button */
.btn-premium {
    background: linear-gradient(135deg, var(--jaune-solaire), #FFC700);
    color: var(--bleu-tchadien);
    border: none;
    font-weight: 700;
    padding: 1.25rem 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
    transition: all 0.3s ease;
}

.btn-premium:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
    color: var(--bleu-tchadien);
}

/* Summary Card */
.summary-card {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: sticky;
    top: 100px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
}

.summary-item span {
    color: #6c757d;
}

.summary-item strong {
    color: var(--gris-harmattan);
}

.summary-item.total {
    font-size: 1.2rem;
}

.summary-divider {
    height: 2px;
    background: #f0f0f0;
    margin: 1rem 0;
}

/* Features List */
.features-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    padding: 0.5rem 0;
    color: var(--gris-harmattan);
}

/* Security Info */
.security-info {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0;
}

.info-item i {
    font-size: 1.2rem;
}

/* Alerts */
.alert-modern {
    border-radius: 12px;
    border: none;
    padding: 1.25rem;
}

/* Responsive */
@media (max-width: 991px) {
    .page-header {
        margin-top: 70px;
    }

    .payment-card {
        padding: 2rem;
    }

    .plans-selection,
    .payment-methods {
        grid-template-columns: 1fr;
    }

    .summary-card {
        position: relative;
        top: 0;
        margin-top: 2rem;
    }
}
</style>

<script>
function updatePlanSelection(planType) {
    document.querySelector('input[name="plan_type"]').value = planType;

    // Mettre à jour l'affichage
    document.querySelectorAll('.plan-option').forEach(option => {
        option.classList.remove('selected');
    });
    document.querySelector('#plan_' + planType).closest('.plan-option').classList.add('selected');

    // Rediriger pour mettre à jour le récapitulatif
    window.location.href = '<?php echo SITE_URL; ?>/premium-payment.php?plan=' + planType;
}

// Formater le numéro de téléphone
document.getElementById('phone_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,2}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});
</script>

<?php include 'includes/footer.php'; ?>
