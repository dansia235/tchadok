<?php
/**
 * API Moov Money - Tchadok Platform
 * Simulation de l'API de paiement Moov Money
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Fonction de logging
function logPayment($action, $data) {
    $log = date('Y-m-d H:i:s') . " - Moov Money - $action: " . json_encode($data) . "\n";
    @file_put_contents('../../logs/payments_moov.log', $log, FILE_APPEND | LOCK_EX);
}

// Configuration API simulée
$config = [
    'api_key' => 'demo_moov_key_456',
    'merchant_id' => 'tchadok_moov_merchant',
    'secret' => 'demo_secret_moov_789',
    'environment' => 'sandbox',
    'currency' => 'XAF',
    'country' => 'TD'
];

// Obtient les données de la requête
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Fonction de validation du numéro Moov
function validateMoovNumber($phone) {
    // Format: +235 XX XX XX XX ou 235XXXXXXXX ou 0XXXXXXXX
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Préfixes Moov Tchad: 90, 91, 92, 93, 94, 95, 96, 97, 98, 99
    $moovPrefixes = ['90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];
    
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $prefix = substr($phone, 1, 2);
    } elseif (strlen($phone) === 11 && substr($phone, 0, 3) === '235') {
        $prefix = substr($phone, 3, 2);
    } elseif (strlen($phone) === 8) {
        $prefix = substr($phone, 0, 2);
    } else {
        return false;
    }
    
    return in_array($prefix, $moovPrefixes);
}

// Fonction de simulation du solde
function getAccountBalance($phone) {
    $lastDigits = (int)substr($phone, -2);
    
    // Simule différents soldes selon les derniers chiffres
    if ($lastDigits < 15) return 800;       // Solde insuffisant
    if ($lastDigits < 35) return 18000;     // Solde faible
    if ($lastDigits < 65) return 45000;     // Solde moyen
    if ($lastDigits < 85) return 95000;     // Bon solde
    return 200000;                          // Solde élevé
}

// Switch selon l'action
switch ($action) {
    case 'initiate':
        // Initiation d'un paiement
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Validation des données
        $required = ['phone', 'amount', 'reference', 'description'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Missing required field: $field",
                    'code' => 'MISSING_FIELD'
                ]);
                exit;
            }
        }
        
        $phone = $input['phone'];
        $amount = (float)$input['amount'];
        $reference = $input['reference'];
        $description = $input['description'];
        
        // Validation du numéro
        if (!validateMoovNumber($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid Moov Money number',
                'code' => 'INVALID_PHONE',
                'details' => 'This number is not a valid Moov Money number for Chad'
            ]);
            exit;
        }
        
        // Validation du montant
        if ($amount < 200) {
            echo json_encode([
                'success' => false,
                'error' => 'Minimum amount is 200 XAF',
                'code' => 'AMOUNT_TOO_LOW'
            ]);
            exit;
        }
        
        if ($amount > 750000) {
            echo json_encode([
                'success' => false,
                'error' => 'Maximum amount is 750,000 XAF',
                'code' => 'AMOUNT_TOO_HIGH'
            ]);
            exit;
        }
        
        // Vérifie le solde (simulation)
        $balance = getAccountBalance($phone);
        if ($balance < $amount) {
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient balance',
                'code' => 'INSUFFICIENT_BALANCE',
                'available_balance' => $balance
            ]);
            exit;
        }
        
        // Génère un ID de transaction
        $transactionId = 'MOOV_' . date('YmdHis') . '_' . rand(10000, 99999);
        
        // Simule des cas d'erreur aléatoires (7% de chance)
        if (rand(1, 100) <= 7) {
            $errors = [
                ['code' => 'NETWORK_TIMEOUT', 'message' => 'Network timeout, please try again'],
                ['code' => 'PIN_INCORRECT', 'message' => 'Incorrect PIN entered by customer'],
                ['code' => 'ACCOUNT_SUSPENDED', 'message' => 'Customer account is suspended'],
                ['code' => 'DAILY_LIMIT_EXCEEDED', 'message' => 'Daily transaction limit exceeded']
            ];
            $error = $errors[array_rand($errors)];
            
            echo json_encode([
                'success' => false,
                'error' => $error['message'],
                'code' => $error['code'],
                'transaction_id' => $transactionId
            ]);
            exit;
        }
        
        logPayment('INITIATE', [
            'transaction_id' => $transactionId,
            'phone' => $phone,
            'amount' => $amount,
            'reference' => $reference
        ]);
        
        echo json_encode([
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'PENDING',
            'message' => 'Payment request sent to customer',
            'details' => [
                'phone' => $phone,
                'amount' => $amount,
                'currency' => 'XAF',
                'reference' => $reference,
                'description' => $description,
                'fee' => round($amount * 0.015, 0), // 1.5% de frais
                'total_amount' => $amount + round($amount * 0.015, 0)
            ],
            'next_step' => 'Customer will receive USSD push to confirm payment',
            'expires_in' => 300 // 5 minutes
        ]);
        break;
        
    case 'status':
        // Vérification du statut d'un paiement
        $transactionId = $_GET['transaction_id'] ?? '';
        
        if (empty($transactionId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Transaction ID is required',
                'code' => 'MISSING_TRANSACTION_ID'
            ]);
            exit;
        }
        
        // Simule le statut selon l'âge de la transaction
        $timestamp = substr($transactionId, 5, 14); // YYYYMMDDHHMMSS
        $transactionTime = DateTime::createFromFormat('YmdHis', $timestamp);
        $now = new DateTime();
        $ageMinutes = $now->diff($transactionTime)->i;
        
        // Détermine le statut selon l'âge
        if ($ageMinutes < 1) {
            $status = 'PENDING';
            $message = 'Waiting for customer confirmation';
        } elseif ($ageMinutes < 3) {
            // 85% de succès après 1-3 minutes
            $status = (rand(1, 100) <= 85) ? 'COMPLETED' : 'PENDING';
            $message = ($status === 'COMPLETED') ? 'Payment completed successfully' : 'Still waiting for customer';
        } elseif ($ageMinutes < 5) {
            // 95% de succès après 3-5 minutes ou échec
            $status = (rand(1, 100) <= 95) ? 'COMPLETED' : 'FAILED';
            $message = ($status === 'COMPLETED') ? 'Payment completed successfully' : 'Customer declined payment';
        } else {
            // Après 5 minutes, considéré comme expiré
            $status = 'EXPIRED';
            $message = 'Payment request expired';
        }
        
        logPayment('STATUS_CHECK', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'age_minutes' => $ageMinutes
        ]);
        
        $response = [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => $status,
            'message' => $message,
            'timestamp' => $now->format('Y-m-d H:i:s')
        ];
        
        if ($status === 'COMPLETED') {
            $response['payment_details'] = [
                'moov_reference' => 'MM' . rand(1000000, 9999999),
                'fee_charged' => 300,
                'completion_time' => $now->format('Y-m-d H:i:s'),
                'customer_name' => 'XXXX XXXX' // Nom masqué
            ];
        }
        
        echo json_encode($response);
        break;
        
    case 'balance':
        // Vérification du solde (pour tests)
        $phone = $_GET['phone'] ?? '';
        
        if (!validateMoovNumber($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid Moov Money number',
                'code' => 'INVALID_PHONE'
            ]);
            exit;
        }
        
        $balance = getAccountBalance($phone);
        
        echo json_encode([
            'success' => true,
            'phone' => $phone,
            'balance' => $balance,
            'currency' => 'XAF',
            'account_status' => 'ACTIVE',
            'account_type' => 'PREPAID'
        ]);
        break;
        
    case 'refund':
        // Remboursement (simulation)
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $originalTransactionId = $input['original_transaction_id'] ?? '';
        $amount = (float)($input['amount'] ?? 0);
        $reason = $input['reason'] ?? 'Customer request';
        
        if (empty($originalTransactionId)) {
            echo json_encode([
                'success' => false,
                'error' => 'Original transaction ID is required',
                'code' => 'MISSING_TRANSACTION_ID'
            ]);
            exit;
        }
        
        $refundId = 'MOOV_REFUND_' . date('YmdHis') . '_' . rand(1000, 9999);
        
        logPayment('REFUND', [
            'refund_id' => $refundId,
            'original_transaction_id' => $originalTransactionId,
            'amount' => $amount,
            'reason' => $reason
        ]);
        
        echo json_encode([
            'success' => true,
            'refund_id' => $refundId,
            'original_transaction_id' => $originalTransactionId,
            'status' => 'PROCESSING',
            'amount' => $amount,
            'currency' => 'XAF',
            'reason' => $reason,
            'estimated_completion' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'message' => 'Refund is being processed'
        ]);
        break;
        
    case 'webhook':
        // Webhook pour les notifications (simulation)
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        logPayment('WEBHOOK', $input);
        
        echo json_encode([
            'success' => true,
            'message' => 'Webhook received and processed'
        ]);
        break;
        
    default:
        // Documentation de l'API
        echo json_encode([
            'name' => 'Moov Money API Simulation',
            'version' => '2.0',
            'environment' => 'sandbox',
            'provider' => 'Moov Africa Chad',
            'endpoints' => [
                'POST /moov-money.php?action=initiate' => 'Initiate payment',
                'GET /moov-money.php?action=status&transaction_id=XXX' => 'Check payment status',
                'GET /moov-money.php?action=balance&phone=XXX' => 'Check account balance',
                'POST /moov-money.php?action=refund' => 'Process refund',
                'POST /moov-money.php?action=webhook' => 'Receive webhooks'
            ],
            'supported_prefixes' => ['90', '91', '92', '93', '94', '95', '96', '97', '98', '99'],
            'currency' => 'XAF',
            'limits' => [
                'min_amount' => 200,
                'max_amount' => 750000,
                'daily_limit' => 3000000
            ],
            'fees' => [
                'transaction_fee' => '1.5%',
                'minimum_fee' => 100,
                'maximum_fee' => 5000
            ]
        ], JSON_PRETTY_PRINT);
        break;
}
?>