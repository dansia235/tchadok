<?php
/**
 * Configuration des systèmes de paiement - Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

// Configuration Airtel Money
define('AIRTEL_MONEY_CONFIG', [
    'api_url' => 'https://openapiuat.airtel.africa/',
    'client_id' => 'YOUR_AIRTEL_CLIENT_ID',
    'client_secret' => 'YOUR_AIRTEL_CLIENT_SECRET',
    'merchant_id' => 'YOUR_MERCHANT_ID',
    'pin' => 'YOUR_PIN',
    'public_key' => 'YOUR_PUBLIC_KEY',
    'currency' => 'XAF',
    'country' => 'TD',
    'timeout' => 30,
    'environment' => 'sandbox' // production ou sandbox
]);

// Configuration Moov Money
define('MOOV_MONEY_CONFIG', [
    'api_url' => 'https://api.moov-africa.td/',
    'merchant_id' => 'YOUR_MOOV_MERCHANT_ID',
    'api_key' => 'YOUR_MOOV_API_KEY',
    'secret_key' => 'YOUR_MOOV_SECRET_KEY',
    'currency' => 'XAF',
    'timeout' => 30,
    'environment' => 'sandbox'
]);

// Configuration Ecobank
define('ECOBANK_CONFIG', [
    'api_url' => 'https://developer.ecobank.com/corporateapi/',
    'client_id' => 'YOUR_ECOBANK_CLIENT_ID',
    'client_secret' => 'YOUR_ECOBANK_CLIENT_SECRET',
    'merchant_id' => 'YOUR_ECOBANK_MERCHANT_ID',
    'terminal_id' => 'YOUR_TERMINAL_ID',
    'currency' => 'XAF',
    'country_code' => 'TD',
    'timeout' => 30,
    'environment' => 'sandbox'
]);

// Configuration VISA/Mastercard (via passerelle)
define('CARD_PAYMENT_CONFIG', [
    'gateway' => 'stripe', // ou autre passerelle disponible en Afrique
    'public_key' => 'YOUR_STRIPE_PUBLIC_KEY',
    'secret_key' => 'YOUR_STRIPE_SECRET_KEY',
    'currency' => 'XAF',
    'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
    'timeout' => 30,
    'environment' => 'sandbox'
]);

// Configuration GIMAC (CEMAC)
define('GIMAC_CONFIG', [
    'api_url' => 'https://api.gimac-cemac.org/',
    'merchant_id' => 'YOUR_GIMAC_MERCHANT_ID',
    'api_key' => 'YOUR_GIMAC_API_KEY',
    'secret_key' => 'YOUR_GIMAC_SECRET_KEY',
    'bank_code' => 'YOUR_BANK_CODE',
    'currency' => 'XAF',
    'timeout' => 30,
    'environment' => 'sandbox'
]);

// Configuration générale des paiements
define('PAYMENT_CONFIG', [
    'min_amount' => 100, // Montant minimum en FCFA
    'max_amount' => 10000000, // Montant maximum en FCFA
    'transaction_fee_percentage' => 2.5, // Frais de transaction en pourcentage
    'transaction_fee_fixed' => 50, // Frais fixes en FCFA
    'commission_rate' => 15, // Commission Tchadok en pourcentage
    'vat_rate' => 18, // TVA en pourcentage
    'webhook_url' => SITE_URL . '/webhooks/payment.php',
    'return_url' => SITE_URL . '/payment/success.php',
    'cancel_url' => SITE_URL . '/payment/cancel.php',
    'notification_url' => SITE_URL . '/payment/notify.php'
]);

// Messages d'erreur personnalisés
define('PAYMENT_MESSAGES', [
    'insufficient_funds' => 'Solde insuffisant pour effectuer cette transaction',
    'invalid_number' => 'Numéro de téléphone invalide',
    'transaction_failed' => 'La transaction a échoué. Veuillez réessayer',
    'network_error' => 'Erreur de connexion au service de paiement',
    'invalid_amount' => 'Montant invalide',
    'service_unavailable' => 'Service temporairement indisponible',
    'invalid_pin' => 'Code PIN incorrect',
    'expired_session' => 'Session expirée. Veuillez vous reconnecter',
    'duplicate_transaction' => 'Transaction déjà effectuée',
    'limit_exceeded' => 'Limite de transaction dépassée'
]);

// Codes de statut de transaction
define('TRANSACTION_STATUS', [
    'PENDING' => 'pending',
    'PROCESSING' => 'processing',
    'COMPLETED' => 'completed',
    'FAILED' => 'failed',
    'CANCELLED' => 'cancelled',
    'REFUNDED' => 'refunded',
    'EXPIRED' => 'expired'
]);

// Méthodes de paiement disponibles
define('PAYMENT_METHODS', [
    'airtel_money' => [
        'name' => 'Airtel Money',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#ED1C24',
        'enabled' => true,
        'min_amount' => 100,
        'max_amount' => 5000000,
        'countries' => ['TD'],
        'phone_regex' => '/^(\+235)?(6[0-9]{7})$/'
    ],
    'moov_money' => [
        'name' => 'Moov Money',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#FFD800',
        'enabled' => true,
        'min_amount' => 100,
        'max_amount' => 5000000,
        'countries' => ['TD'],
        'phone_regex' => '/^(\+235)?(9[0-9]{7})$/'
    ],
    'ecobank' => [
        'name' => 'Ecobank',
        'icon' => 'fas fa-university',
        'color' => '#005DAA',
        'enabled' => true,
        'min_amount' => 1000,
        'max_amount' => 10000000,
        'countries' => ['TD'],
        'requires_account' => true
    ],
    'visa' => [
        'name' => 'Carte VISA',
        'icon' => 'fab fa-cc-visa',
        'color' => '#1A1F71',
        'enabled' => true,
        'min_amount' => 1000,
        'max_amount' => 10000000,
        'countries' => ['ALL']
    ],
    'gimac' => [
        'name' => 'Carte GIMAC',
        'icon' => 'fas fa-credit-card',
        'color' => '#008C45',
        'enabled' => true,
        'min_amount' => 1000,
        'max_amount' => 10000000,
        'countries' => ['TD', 'CM', 'CF', 'CG', 'GA', 'GQ']
    ],
    'wallet' => [
        'name' => 'Portefeuille Tchadok',
        'icon' => 'fas fa-wallet',
        'color' => '#0066CC',
        'enabled' => true,
        'min_amount' => 0,
        'max_amount' => 10000000,
        'instant' => true
    ]
]);

/**
 * Calculer les frais de transaction
 */
function calculateTransactionFees($amount, $method) {
    $fees = 0;
    
    // Frais fixes
    $fees += PAYMENT_CONFIG['transaction_fee_fixed'];
    
    // Frais en pourcentage
    $fees += ($amount * PAYMENT_CONFIG['transaction_fee_percentage'] / 100);
    
    // Frais spécifiques par méthode
    switch ($method) {
        case 'airtel_money':
        case 'moov_money':
            // Frais supplémentaires pour mobile money
            $fees += ($amount * 1.5 / 100);
            break;
        case 'visa':
        case 'gimac':
            // Frais bancaires
            $fees += ($amount * 2.0 / 100);
            break;
        case 'wallet':
            // Pas de frais supplémentaires pour le portefeuille
            $fees = 0;
            break;
    }
    
    return round($fees, 2);
}

/**
 * Calculer la commission Tchadok
 */
function calculateCommission($amount) {
    return round($amount * PAYMENT_CONFIG['commission_rate'] / 100, 2);
}

/**
 * Valider un numéro de téléphone pour Mobile Money
 */
function validatePhoneNumber($phone, $provider) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    if (!isset(PAYMENT_METHODS[$provider]['phone_regex'])) {
        return false;
    }
    
    return preg_match(PAYMENT_METHODS[$provider]['phone_regex'], $cleanPhone);
}

/**
 * Formater un numéro de téléphone pour l'API
 */
function formatPhoneNumber($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ajouter le code pays si absent
    if (strpos($cleanPhone, '235') !== 0) {
        $cleanPhone = '235' . $cleanPhone;
    }
    
    return $cleanPhone;
}

/**
 * Générer une référence de transaction unique
 */
function generateTransactionReference($prefix = 'TCH') {
    return $prefix . date('Ymd') . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Obtenir le taux de change (si nécessaire)
 */
function getExchangeRate($fromCurrency, $toCurrency) {
    // Pour l'instant, on utilise uniquement XAF
    if ($fromCurrency === $toCurrency) {
        return 1;
    }
    
    // Implémenter la logique de taux de change si nécessaire
    return 1;
}

/**
 * Vérifier si une méthode de paiement est disponible
 */
function isPaymentMethodAvailable($method, $country = 'TD') {
    if (!isset(PAYMENT_METHODS[$method])) {
        return false;
    }
    
    $methodConfig = PAYMENT_METHODS[$method];
    
    if (!$methodConfig['enabled']) {
        return false;
    }
    
    if (isset($methodConfig['countries'])) {
        return in_array($country, $methodConfig['countries']) || in_array('ALL', $methodConfig['countries']);
    }
    
    return true;
}

/**
 * Obtenir les méthodes de paiement disponibles pour un pays
 */
function getAvailablePaymentMethods($country = 'TD') {
    $available = [];
    
    foreach (PAYMENT_METHODS as $key => $method) {
        if (isPaymentMethodAvailable($key, $country)) {
            $available[$key] = $method;
        }
    }
    
    return $available;
}

/**
 * Chiffrer les données sensibles
 */
function encryptPaymentData($data) {
    $key = hash('sha256', 'TCHADOK_PAYMENT_SECRET_KEY_2024', true);
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt(
        json_encode($data),
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    return base64_encode($iv . $encrypted);
}

/**
 * Déchiffrer les données sensibles
 */
function decryptPaymentData($encryptedData) {
    $key = hash('sha256', 'TCHADOK_PAYMENT_SECRET_KEY_2024', true);
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    return json_decode($decrypted, true);
}

/**
 * Logger les transactions pour audit
 */
function logPaymentTransaction($transactionData) {
    $logFile = LOG_PATH . 'payments_' . date('Y-m-d') . '.log';
    $logEntry = date('Y-m-d H:i:s') . ' - ' . json_encode($transactionData) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Envoyer une notification de paiement
 */
function sendPaymentNotification($userId, $transactionId, $status) {
    global $db;
    
    $message = '';
    $type = 'payment';
    
    switch ($status) {
        case TRANSACTION_STATUS['COMPLETED']:
            $message = 'Votre paiement a été effectué avec succès.';
            break;
        case TRANSACTION_STATUS['FAILED']:
            $message = 'Votre paiement a échoué. Veuillez réessayer.';
            break;
        case TRANSACTION_STATUS['REFUNDED']:
            $message = 'Votre paiement a été remboursé.';
            break;
    }
    
    if ($message) {
        $db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => 'Paiement',
            'message' => $message,
            'data' => json_encode(['transaction_id' => $transactionId]),
            'action_url' => SITE_URL . '/pages/user/purchases.php#transaction-' . $transactionId
        ]);
    }
}
?>