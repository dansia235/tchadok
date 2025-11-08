<?php
/**
 * API de paiement Mobile Money - Tchadok Platform
 * Support: Airtel Money, Moov Money, Ecobank, Visa
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            $action = $_POST['action'] ?? 'initiate';
            switch ($action) {
                case 'initiate':
                    initiatePayment();
                    break;
                case 'verify':
                    verifyPayment();
                    break;
                case 'refund':
                    processRefund();
                    break;
                default:
                    throw new Exception('Action non reconnue', 400);
            }
            break;
            
        case 'GET':
            $action = $_GET['action'] ?? 'status';
            switch ($action) {
                case 'status':
                    getPaymentStatus();
                    break;
                case 'methods':
                    getPaymentMethods();
                    break;
                case 'history':
                    getPaymentHistory();
                    break;
                case 'balance':
                    getWalletBalance();
                    break;
                default:
                    throw new Exception('Action non reconnue', 400);
            }
            break;
            
        default:
            throw new Exception('Méthode non autorisée', 405);
    }
    
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'code' => $statusCode,
            'timestamp' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Initiation d'un paiement
 */
function initiatePayment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; // Fallback pour form-data
    }
    
    // Validation des données requises
    $required = ['amount', 'phone', 'provider', 'type', 'user_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Le champ '$field' est requis", 400);
        }
    }
    
    $amount = (float)$input['amount'];
    $phone = sanitizePhone($input['phone']);
    $provider = strtolower($input['provider']); // airtel, moov, ecobank, visa
    $type = $input['type']; // premium, track, album, wallet
    $userId = (int)$input['user_id'];
    $itemId = $input['item_id'] ?? null;
    $description = $input['description'] ?? 'Paiement Tchadok';
    
    // Validation du montant
    if ($amount < 100) {
        throw new Exception('Le montant minimum est de 100 FCFA', 400);
    }
    
    if ($amount > 500000) {
        throw new Exception('Le montant maximum est de 500,000 FCFA', 400);
    }
    
    // Validation du téléphone
    if (!isValidPhone($phone, $provider)) {
        throw new Exception('Numéro de téléphone invalide pour ce fournisseur', 400);
    }
    
    // Génération de l'ID de transaction
    $transactionId = generateTransactionId();
    
    // Données de la transaction
    $transaction = [
        'id' => $transactionId,
        'user_id' => $userId,
        'amount' => $amount,
        'currency' => 'XAF', // Franc CFA
        'phone' => $phone,
        'provider' => $provider,
        'type' => $type,
        'item_id' => $itemId,
        'description' => $description,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
        'attempts' => 0,
        'external_ref' => null,
        'fees' => calculateFees($amount, $provider),
        'net_amount' => $amount - calculateFees($amount, $provider)
    ];
    
    // Enregistrement en base (simulation)
    saveTransaction($transaction);
    
    // Initiation du paiement selon le fournisseur
    $paymentResult = null;
    switch ($provider) {
        case 'airtel':
            $paymentResult = initiateAirtelPayment($transaction);
            break;
        case 'moov':
            $paymentResult = initiateMoovPayment($transaction);
            break;
        case 'ecobank':
            $paymentResult = initiateEcobankPayment($transaction);
            break;
        case 'visa':
            $paymentResult = initiateVisaPayment($transaction);
            break;
        default:
            throw new Exception('Fournisseur de paiement non supporté', 400);
    }
    
    // Mise à jour avec la référence externe
    if ($paymentResult && isset($paymentResult['external_ref'])) {
        updateTransaction($transactionId, [
            'external_ref' => $paymentResult['external_ref'],
            'status' => 'initiated'
        ]);
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transactionId,
            'status' => 'initiated',
            'amount' => $amount,
            'fees' => $transaction['fees'],
            'net_amount' => $transaction['net_amount'],
            'provider' => $provider,
            'expires_at' => $transaction['expires_at'],
            'instructions' => getPaymentInstructions($provider, $phone, $amount),
            'verification_url' => SITE_URL . '/api/payment.php?action=status&id=' . $transactionId
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Vérification du statut d'un paiement
 */
function verifyPayment() {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $transactionId = $input['transaction_id'] ?? null;
    if (!$transactionId) {
        throw new Exception('ID de transaction requis', 400);
    }
    
    $transaction = getTransaction($transactionId);
    if (!$transaction) {
        throw new Exception('Transaction non trouvée', 404);
    }
    
    // Vérification auprès du fournisseur
    $status = null;
    switch ($transaction['provider']) {
        case 'airtel':
            $status = verifyAirtelPayment($transaction);
            break;
        case 'moov':
            $status = verifyMoovPayment($transaction);
            break;
        case 'ecobank':
            $status = verifyEcobankPayment($transaction);
            break;
        case 'visa':
            $status = verifyVisaPayment($transaction);
            break;
    }
    
    // Mise à jour du statut
    if ($status && $status['status'] !== $transaction['status']) {
        updateTransaction($transactionId, [
            'status' => $status['status'],
            'verified_at' => date('Y-m-d H:i:s'),
            'external_data' => json_encode($status)
        ]);
        
        // Traitement post-paiement si succès
        if ($status['status'] === 'completed') {
            processSuccessfulPayment($transaction);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transactionId,
            'status' => $status['status'] ?? $transaction['status'],
            'amount' => $transaction['amount'],
            'verified_at' => date('c'),
            'message' => getStatusMessage($status['status'] ?? $transaction['status'])
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Statut d'un paiement
 */
function getPaymentStatus() {
    $transactionId = $_GET['id'] ?? null;
    if (!$transactionId) {
        throw new Exception('ID de transaction requis', 400);
    }
    
    $transaction = getTransaction($transactionId);
    if (!$transaction) {
        throw new Exception('Transaction non trouvée', 404);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transactionId,
            'status' => $transaction['status'],
            'amount' => $transaction['amount'],
            'provider' => $transaction['provider'],
            'created_at' => $transaction['created_at'],
            'expires_at' => $transaction['expires_at'],
            'is_expired' => strtotime($transaction['expires_at']) < time()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Méthodes de paiement disponibles
 */
function getPaymentMethods() {
    $methods = [
        [
            'id' => 'airtel',
            'name' => 'Airtel Money',
            'icon' => 'fas fa-mobile-alt',
            'color' => '#E31E24',
            'fees' => '2%',
            'min_amount' => 100,
            'max_amount' => 500000,
            'processing_time' => 'Instantané',
            'available' => true,
            'countries' => ['TD'],
            'phone_pattern' => '^(\+235)?[679][0-9]{7}$'
        ],
        [
            'id' => 'moov',
            'name' => 'Moov Money',
            'icon' => 'fas fa-money-bill-wave',
            'color' => '#00B5E2',
            'fees' => '1.5%',
            'min_amount' => 100,
            'max_amount' => 300000,
            'processing_time' => 'Instantané',
            'available' => true,
            'countries' => ['TD'],
            'phone_pattern' => '^(\+235)?[679][0-9]{7}$'
        ],
        [
            'id' => 'ecobank',
            'name' => 'Ecobank Mobile',
            'icon' => 'fas fa-university',
            'color' => '#1F4E79',
            'fees' => '2.5%',
            'min_amount' => 500,
            'max_amount' => 1000000,
            'processing_time' => '1-3 minutes',
            'available' => true,
            'countries' => ['TD', 'CM', 'GA', 'GQ'],
            'phone_pattern' => '^(\+235)?[679][0-9]{7}$'
        ],
        [
            'id' => 'visa',
            'name' => 'Visa/Mastercard',
            'icon' => 'fab fa-cc-visa',
            'color' => '#1A1F71',
            'fees' => '3%',
            'min_amount' => 1000,
            'max_amount' => 2000000,
            'processing_time' => '2-5 minutes',
            'available' => true,
            'countries' => ['TD', 'International'],
            'card_types' => ['visa', 'mastercard']
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'methods' => $methods,
            'default_currency' => 'XAF',
            'country' => 'TD'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Historique des paiements
 */
function getPaymentHistory() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $status = $_GET['status'] ?? null;
    
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    // Simulation de données d'historique
    $payments = generatePaymentHistory($userId, $limit, $offset, $status);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'payments' => $payments,
            'total_count' => rand(50, 200),
            'limit' => $limit,
            'offset' => $offset,
            'user_id' => $userId
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Solde du portefeuille
 */
function getWalletBalance() {
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'balance' => rand(5000, 50000), // FCFA
            'currency' => 'XAF',
            'last_updated' => date('c'),
            'pending_transactions' => rand(0, 3),
            'total_spent' => rand(50000, 500000),
            'total_earned' => rand(10000, 100000) // Pour les artistes
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * FONCTIONS HELPER
 */

function sanitizePhone($phone) {
    // Nettoyer et formater le numéro
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Ajouter le code pays si manquant
    if (strpos($phone, '+235') !== 0 && strpos($phone, '235') !== 0) {
        if (strlen($phone) === 8) {
            $phone = '+235' . $phone;
        }
    }
    
    return $phone;
}

function isValidPhone($phone, $provider) {
    // Validation basique du numéro tchadien
    $pattern = '/^(\+235)?[679][0-9]{7}$/';
    return preg_match($pattern, $phone);
}

function generateTransactionId() {
    return 'TXN_' . date('Ymd') . '_' . strtoupper(uniqid());
}

function calculateFees($amount, $provider) {
    $rates = [
        'airtel' => 0.02,   // 2%
        'moov' => 0.015,    // 1.5%
        'ecobank' => 0.025, // 2.5%
        'visa' => 0.03      // 3%
    ];
    
    $rate = $rates[$provider] ?? 0.02;
    return round($amount * $rate);
}

function getPaymentInstructions($provider, $phone, $amount) {
    $instructions = [
        'airtel' => [
            'title' => 'Paiement Airtel Money',
            'steps' => [
                'Composez *444# sur votre téléphone',
                'Sélectionnez "Payer marchant"',
                'Entrez le code marchand: TCHADOK',
                'Confirmez le montant: ' . number_format($amount) . ' FCFA',
                'Entrez votre code PIN Airtel Money'
            ],
            'timeout' => '10 minutes'
        ],
        'moov' => [
            'title' => 'Paiement Moov Money',
            'steps' => [
                'Composez *555# sur votre téléphone',
                'Sélectionnez "Paiement marchand"',
                'Entrez le code: TCHADOK2024',
                'Confirmez: ' . number_format($amount) . ' FCFA',
                'Validez avec votre code secret'
            ],
            'timeout' => '10 minutes'
        ],
        'ecobank' => [
            'title' => 'Paiement Ecobank Mobile',
            'steps' => [
                'Ouvrez l\'app Ecobank Mobile',
                'Sélectionnez "Paiement marchand"',
                'Scannez le QR code ou entrez: TCHADOK',
                'Confirmez le montant et validez'
            ],
            'timeout' => '15 minutes'
        ],
        'visa' => [
            'title' => 'Paiement par Carte',
            'steps' => [
                'Vous serez redirigé vers la page sécurisée',
                'Entrez vos informations de carte',
                'Confirmez le paiement avec votre banque'
            ],
            'timeout' => '20 minutes'
        ]
    ];
    
    return $instructions[$provider] ?? null;
}

function getStatusMessage($status) {
    $messages = [
        'pending' => 'Paiement en attente',
        'initiated' => 'Paiement initié, en attente de confirmation',
        'processing' => 'Paiement en cours de traitement',
        'completed' => 'Paiement réussi',
        'failed' => 'Paiement échoué',
        'expired' => 'Paiement expiré',
        'cancelled' => 'Paiement annulé',
        'refunded' => 'Paiement remboursé'
    ];
    
    return $messages[$status] ?? 'Statut inconnu';
}

// Fonctions de simulation des APIs externes
function initiateAirtelPayment($transaction) {
    // Simulation API Airtel
    return [
        'success' => true,
        'external_ref' => 'AIR_' . rand(100000, 999999),
        'status' => 'initiated',
        'message' => 'Demande de paiement envoyée'
    ];
}

function initiateMoovPayment($transaction) {
    return [
        'success' => true,
        'external_ref' => 'MOOV_' . rand(100000, 999999),
        'status' => 'initiated'
    ];
}

function initiateEcobankPayment($transaction) {
    return [
        'success' => true,
        'external_ref' => 'ECO_' . rand(100000, 999999),
        'status' => 'initiated'
    ];
}

function initiateVisaPayment($transaction) {
    return [
        'success' => true,
        'external_ref' => 'VISA_' . rand(100000, 999999),
        'status' => 'initiated',
        'redirect_url' => 'https://secure.visa.com/payment/...'
    ];
}

function verifyAirtelPayment($transaction) {
    // Simulation de vérification
    $statuses = ['completed', 'processing', 'failed'];
    return ['status' => $statuses[array_rand($statuses)]];
}

function verifyMoovPayment($transaction) {
    $statuses = ['completed', 'processing', 'failed'];
    return ['status' => $statuses[array_rand($statuses)]];
}

function verifyEcobankPayment($transaction) {
    $statuses = ['completed', 'processing', 'failed'];
    return ['status' => $statuses[array_rand($statuses)]];
}

function verifyVisaPayment($transaction) {
    $statuses = ['completed', 'processing', 'failed'];
    return ['status' => $statuses[array_rand($statuses)]];
}

// Fonctions de base de données (simulation)
function saveTransaction($transaction) {
    // Simulation de sauvegarde
    return true;
}

function getTransaction($id) {
    // Simulation de récupération
    return [
        'id' => $id,
        'status' => 'pending',
        'amount' => 5000,
        'provider' => 'airtel',
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
    ];
}

function updateTransaction($id, $data) {
    // Simulation de mise à jour
    return true;
}

function processSuccessfulPayment($transaction) {
    // Traitement post-paiement (déblocage contenu, ajout crédit, etc.)
    return true;
}

function generatePaymentHistory($userId, $limit, $offset, $status) {
    $payments = [];
    for ($i = 1; $i <= $limit; $i++) {
        $payments[] = [
            'id' => 'TXN_' . ($i + $offset),
            'amount' => rand(1000, 50000),
            'type' => ['premium', 'track', 'album', 'wallet'][rand(0, 3)],
            'provider' => ['airtel', 'moov', 'ecobank', 'visa'][rand(0, 3)],
            'status' => $status ?: ['completed', 'failed', 'pending'][rand(0, 2)],
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'description' => 'Paiement Tchadok'
        ];
    }
    return $payments;
}

function processRefund() {
    // Implémentation du remboursement
    throw new Exception('Fonctionnalité de remboursement en cours de développement', 501);
}
?>