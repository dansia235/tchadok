<?php
/**
 * API Airtel Money - Tchadok Platform
 * Simulation de l'API de paiement Airtel Money
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
    $log = date('Y-m-d H:i:s') . " - Airtel Money - $action: " . json_encode($data) . "\n";
    @file_put_contents('../../logs/payments_airtel.log', $log, FILE_APPEND | LOCK_EX);
}

// Configuration API simulée
$config = [
    'api_key' => 'demo_airtel_key_123',
    'client_id' => 'tchadok_airtel_client',
    'secret' => 'demo_secret_airtel_456',
    'environment' => 'sandbox',
    'currency' => 'XAF',
    'country' => 'TD'
];

// Obtient les données de la requête
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Fonction de validation du numéro Airtel
function validateAirtelNumber($phone) {
    // Format: +235 XX XX XX XX ou 235XXXXXXXX ou 0XXXXXXXX
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Préfixes Airtel Tchad: 62, 63, 64, 65, 66, 68, 69
    $airtelPrefixes = ['62', '63', '64', '65', '66', '68', '69'];
    
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $prefix = substr($phone, 1, 2);
    } elseif (strlen($phone) === 11 && substr($phone, 0, 3) === '235') {
        $prefix = substr($phone, 3, 2);
    } elseif (strlen($phone) === 8) {
        $prefix = substr($phone, 0, 2);
    } else {
        return false;
    }
    
    return in_array($prefix, $airtelPrefixes);
}

// Fonction de simulation du solde
function getAccountBalance($phone) {
    $lastDigits = (int)substr($phone, -2);
    
    // Simule différents soldes selon les derniers chiffres
    if ($lastDigits < 20) return 1500;      // Solde insuffisant
    if ($lastDigits < 40) return 25000;     // Solde moyen
    if ($lastDigits < 70) return 75000;     // Bon solde
    return 150000;                          // Solde élevé
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
        if (!validateAirtelNumber($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid Airtel Money number',
                'code' => 'INVALID_PHONE',
                'details' => 'This number is not a valid Airtel Money number for Chad'
            ]);
            exit;
        }
        
        // Validation du montant
        if ($amount < 100) {
            echo json_encode([
                'success' => false,
                'error' => 'Minimum amount is 100 XAF',
                'code' => 'AMOUNT_TOO_LOW'
            ]);
            exit;
        }
        
        if ($amount > 500000) {
            echo json_encode([
                'success' => false,
                'error' => 'Maximum amount is 500,000 XAF',
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
        $transactionId = 'AIRTEL_' . date('YmdHis') . '_' . rand(1000, 9999);
        
        // Simule des cas d'erreur aléatoires (5% de chance)
        if (rand(1, 100) <= 5) {
            $errors = [
                ['code' => 'NETWORK_ERROR', 'message' => 'Network timeout, please retry'],
                ['code' => 'PIN_REQUIRED', 'message' => 'Customer needs to enter PIN'],
                ['code' => 'ACCOUNT_BLOCKED', 'message' => 'Customer account is temporarily blocked']
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
            'message' => 'Payment initiated successfully',
            'details' => [
                'phone' => $phone,
                'amount' => $amount,
                'currency' => 'XAF',
                'reference' => $reference,
                'description' => $description,
                'fee' => round($amount * 0.02, 0), // 2% de frais
                'total_amount' => $amount + round($amount * 0.02, 0)
            ],
            'next_step' => 'Customer will receive SMS to confirm payment'
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
        $timestamp = substr($transactionId, 7, 14); // YYYYMMDDHHMMSS
        $transactionTime = DateTime::createFromFormat('YmdHis', $timestamp);
        $now = new DateTime();
        $ageMinutes = $now->diff($transactionTime)->i;
        
        // Détermine le statut selon l'âge
        if ($ageMinutes < 2) {
            $status = 'PENDING';
            $message = 'Payment is being processed';
        } elseif ($ageMinutes < 5) {
            // 90% de succès après 2-5 minutes
            $status = (rand(1, 100) <= 90) ? 'COMPLETED' : 'FAILED';
            $message = ($status === 'COMPLETED') ? 'Payment completed successfully' : 'Payment failed - customer cancelled';
        } else {
            // Après 5 minutes, considéré comme expiré si pas encore traité
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
                'airtel_reference' => 'AMT' . rand(100000, 999999),
                'fee_charged' => 500,
                'completion_time' => $now->format('Y-m-d H:i:s')
            ];
        }
        
        echo json_encode($response);
        break;
        
    case 'balance':
        // Vérification du solde (pour tests)
        $phone = $_GET['phone'] ?? '';
        
        if (!validateAirtelNumber($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid Airtel Money number',
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
            'account_status' => 'ACTIVE'
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
            'message' => 'Webhook received successfully'
        ]);
        break;
        
    default:
        // Documentation de l'API
        echo json_encode([
            'name' => 'Airtel Money API Simulation',
            'version' => '1.0',
            'environment' => 'sandbox',
            'endpoints' => [
                'POST /airtel-money.php?action=initiate' => 'Initiate payment',
                'GET /airtel-money.php?action=status&transaction_id=XXX' => 'Check payment status',
                'GET /airtel-money.php?action=balance&phone=XXX' => 'Check account balance',
                'POST /airtel-money.php?action=webhook' => 'Receive webhooks'
            ],
            'supported_prefixes' => ['62', '63', '64', '65', '66', '68', '69'],
            'currency' => 'XAF',
            'limits' => [
                'min_amount' => 100,
                'max_amount' => 500000,
                'daily_limit' => 2000000
            ]
        ], JSON_PRETTY_PRINT);
        break;
}
?>