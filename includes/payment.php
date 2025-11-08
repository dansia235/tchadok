<?php
/**
 * Classe de gestion des paiements - Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/payment.php';
require_once dirname(__DIR__) . '/includes/functions.php';

class PaymentManager {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Initier un paiement
     */
    public function initiatePayment($paymentData) {
        try {
            // Validation des données
            $validation = $this->validatePaymentData($paymentData);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Calculer les frais et commissions
            $amount = $paymentData['amount'];
            $fees = calculateTransactionFees($amount, $paymentData['payment_method']);
            $commission = calculateCommission($amount);
            $totalAmount = $amount + $fees;
            
            // Vérifier le solde pour le portefeuille
            if ($paymentData['payment_method'] === 'wallet') {
                $user = $this->db->fetchOne("SELECT wallet_balance FROM users WHERE id = ?", [$paymentData['user_id']]);
                if ($user['wallet_balance'] < $totalAmount) {
                    return ['success' => false, 'error' => 'Solde insuffisant dans votre portefeuille'];
                }
            }
            
            // Générer une référence unique
            $reference = generateTransactionReference();
            
            // Enregistrer la transaction
            $transactionId = $this->db->insert('transactions', [
                'user_id' => $paymentData['user_id'],
                'artist_id' => $paymentData['artist_id'] ?? null,
                'type' => 'purchase',
                'amount' => $amount,
                'currency' => 'XAF',
                'description' => $paymentData['description'],
                'reference' => $reference,
                'gateway' => $paymentData['payment_method'],
                'status' => TRANSACTION_STATUS['PENDING']
            ]);
            
            // Enregistrer l'achat
            $purchaseId = $this->db->insert('purchases', [
                'user_id' => $paymentData['user_id'],
                'item_type' => $paymentData['item_type'],
                'item_id' => $paymentData['item_id'],
                'artist_id' => $paymentData['artist_id'],
                'amount' => $amount,
                'commission' => $commission,
                'payment_method' => $paymentData['payment_method'],
                'payment_reference' => $reference,
                'payment_status' => TRANSACTION_STATUS['PENDING'],
                'transaction_fee' => $fees,
                'currency' => 'XAF'
            ]);
            
            // Traiter selon la méthode de paiement
            switch ($paymentData['payment_method']) {
                case 'airtel_money':
                    return $this->processAirtelMoney($transactionId, $paymentData, $totalAmount);
                    
                case 'moov_money':
                    return $this->processMoovMoney($transactionId, $paymentData, $totalAmount);
                    
                case 'ecobank':
                    return $this->processEcobank($transactionId, $paymentData, $totalAmount);
                    
                case 'visa':
                    return $this->processVisa($transactionId, $paymentData, $totalAmount);
                    
                case 'gimac':
                    return $this->processGimac($transactionId, $paymentData, $totalAmount);
                    
                case 'wallet':
                    return $this->processWalletPayment($transactionId, $paymentData, $totalAmount);
                    
                default:
                    throw new Exception('Méthode de paiement non supportée');
            }
            
        } catch (Exception $e) {
            logActivity(LOG_LEVEL_ERROR, "Erreur paiement: " . $e->getMessage(), $paymentData);
            return ['success' => false, 'error' => 'Erreur lors du traitement du paiement'];
        }
    }
    
    /**
     * Traiter un paiement Airtel Money
     */
    private function processAirtelMoney($transactionId, $paymentData, $amount) {
        try {
            $config = AIRTEL_MONEY_CONFIG;
            
            // Formater le numéro de téléphone
            $phoneNumber = formatPhoneNumber($paymentData['phone_number']);
            
            // Obtenir le token d'accès
            $token = $this->getAirtelAccessToken();
            if (!$token) {
                throw new Exception('Impossible d\'obtenir le token Airtel');
            }
            
            // Préparer la requête
            $requestData = [
                'reference' => $paymentData['reference'],
                'subscriber' => [
                    'country' => $config['country'],
                    'currency' => $config['currency'],
                    'msisdn' => $phoneNumber
                ],
                'transaction' => [
                    'amount' => $amount,
                    'country' => $config['country'],
                    'currency' => $config['currency'],
                    'id' => $transactionId
                ]
            ];
            
            // Envoyer la requête de paiement
            $ch = curl_init($config['api_url'] . 'merchant/v1/payments/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Country: ' . $config['country'],
                'X-Currency: ' . $config['currency']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            
            if ($httpCode === 200 && isset($responseData['data']['transaction']['id'])) {
                // Mettre à jour le statut de la transaction
                $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['PROCESSING']);
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'external_id' => $responseData['data']['transaction']['id'],
                    'message' => 'Veuillez valider le paiement sur votre téléphone',
                    'redirect_url' => null
                ];
            } else {
                throw new Exception($responseData['message'] ?? 'Erreur Airtel Money');
            }
            
        } catch (Exception $e) {
            $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['FAILED']);
            logActivity(LOG_LEVEL_ERROR, "Erreur Airtel Money: " . $e->getMessage());
            return ['success' => false, 'error' => PAYMENT_MESSAGES['transaction_failed']];
        }
    }
    
    /**
     * Traiter un paiement Moov Money
     */
    private function processMoovMoney($transactionId, $paymentData, $amount) {
        try {
            $config = MOOV_MONEY_CONFIG;
            
            // Formater le numéro de téléphone
            $phoneNumber = formatPhoneNumber($paymentData['phone_number']);
            
            // Préparer la requête
            $requestData = [
                'merchant_id' => $config['merchant_id'],
                'transaction_id' => $transactionId,
                'msisdn' => $phoneNumber,
                'amount' => $amount,
                'currency' => $config['currency'],
                'description' => $paymentData['description'],
                'callback_url' => PAYMENT_CONFIG['webhook_url']
            ];
            
            // Signer la requête
            $signature = $this->generateMoovSignature($requestData);
            $requestData['signature'] = $signature;
            
            // Envoyer la requête
            $ch = curl_init($config['api_url'] . 'payment/request');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-KEY: ' . $config['api_key']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            
            if ($httpCode === 200 && $responseData['status'] === 'success') {
                $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['PROCESSING']);
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'external_id' => $responseData['payment_id'],
                    'message' => 'Veuillez valider le paiement sur votre téléphone',
                    'redirect_url' => null
                ];
            } else {
                throw new Exception($responseData['message'] ?? 'Erreur Moov Money');
            }
            
        } catch (Exception $e) {
            $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['FAILED']);
            logActivity(LOG_LEVEL_ERROR, "Erreur Moov Money: " . $e->getMessage());
            return ['success' => false, 'error' => PAYMENT_MESSAGES['transaction_failed']];
        }
    }
    
    /**
     * Traiter un paiement par portefeuille
     */
    private function processWalletPayment($transactionId, $paymentData, $amount) {
        try {
            $this->db->beginTransaction();
            
            // Débiter le portefeuille de l'utilisateur
            $result = $this->db->update('users', 
                ['wallet_balance' => new \PDO::PARAM_STR('wallet_balance - ' . $amount)],
                'id = ?',
                [$paymentData['user_id']]
            );
            
            if (!$result) {
                throw new Exception('Erreur lors du débit du portefeuille');
            }
            
            // Créditer l'artiste (après déduction de la commission)
            if (isset($paymentData['artist_id'])) {
                $commission = calculateCommission($paymentData['amount']);
                $artistAmount = $paymentData['amount'] - $commission;
                
                $this->db->update('artists',
                    ['total_earnings' => new \PDO::PARAM_STR('total_earnings + ' . $artistAmount)],
                    'id = ?',
                    [$paymentData['artist_id']]
                );
            }
            
            // Mettre à jour les statuts
            $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['COMPLETED']);
            $this->updatePurchaseStatus($paymentData['reference'], TRANSACTION_STATUS['COMPLETED']);
            
            // Donner accès au contenu acheté
            $this->grantAccess($paymentData);
            
            $this->db->commit();
            
            // Envoyer les notifications
            sendPaymentNotification($paymentData['user_id'], $transactionId, TRANSACTION_STATUS['COMPLETED']);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Paiement effectué avec succès',
                'redirect_url' => PAYMENT_CONFIG['return_url'] . '?transaction=' . $transactionId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['FAILED']);
            logActivity(LOG_LEVEL_ERROR, "Erreur paiement portefeuille: " . $e->getMessage());
            return ['success' => false, 'error' => PAYMENT_MESSAGES['transaction_failed']];
        }
    }
    
    /**
     * Valider les données de paiement
     */
    private function validatePaymentData($data) {
        $errors = [];
        
        // Champs obligatoires
        $required = ['user_id', 'amount', 'payment_method', 'item_type', 'item_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Le champ {$field} est obligatoire";
            }
        }
        
        // Validation du montant
        if (isset($data['amount'])) {
            if ($data['amount'] < PAYMENT_CONFIG['min_amount']) {
                $errors[] = "Le montant minimum est " . formatPrice(PAYMENT_CONFIG['min_amount']);
            }
            if ($data['amount'] > PAYMENT_CONFIG['max_amount']) {
                $errors[] = "Le montant maximum est " . formatPrice(PAYMENT_CONFIG['max_amount']);
            }
        }
        
        // Validation de la méthode de paiement
        if (isset($data['payment_method']) && !isPaymentMethodAvailable($data['payment_method'])) {
            $errors[] = "Méthode de paiement non disponible";
        }
        
        // Validation du numéro de téléphone pour mobile money
        if (in_array($data['payment_method'] ?? '', ['airtel_money', 'moov_money'])) {
            if (empty($data['phone_number'])) {
                $errors[] = "Le numéro de téléphone est obligatoire";
            } elseif (!validatePhoneNumber($data['phone_number'], $data['payment_method'])) {
                $errors[] = "Numéro de téléphone invalide pour " . PAYMENT_METHODS[$data['payment_method']]['name'];
            }
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return ['success' => true];
    }
    
    /**
     * Mettre à jour le statut d'une transaction
     */
    private function updateTransactionStatus($transactionId, $status) {
        return $this->db->update('transactions',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$transactionId]
        );
    }
    
    /**
     * Mettre à jour le statut d'un achat
     */
    private function updatePurchaseStatus($reference, $status) {
        return $this->db->update('purchases',
            ['payment_status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            'payment_reference = ?',
            [$reference]
        );
    }
    
    /**
     * Donner accès au contenu acheté
     */
    private function grantAccess($paymentData) {
        // Logique pour donner accès au contenu selon le type
        switch ($paymentData['item_type']) {
            case 'track':
                // Marquer le titre comme acheté pour l'utilisateur
                $this->db->query("UPDATE purchases SET expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE payment_reference = ?", 
                    [$paymentData['reference']]);
                break;
                
            case 'album':
                // Donner accès à tous les titres de l'album
                $tracks = $this->db->fetchAll("SELECT id FROM tracks WHERE album_id = ?", [$paymentData['item_id']]);
                foreach ($tracks as $track) {
                    // Logique pour donner accès à chaque titre
                }
                break;
                
            case 'premium':
                // Activer le statut premium
                $duration = $paymentData['duration'] ?? 30; // jours
                $this->db->update('users',
                    [
                        'premium_status' => 1,
                        'premium_expires_at' => date('Y-m-d H:i:s', strtotime("+{$duration} days"))
                    ],
                    'id = ?',
                    [$paymentData['user_id']]
                );
                break;
        }
    }
    
    /**
     * Obtenir le token d'accès Airtel
     */
    private function getAirtelAccessToken() {
        $config = AIRTEL_MONEY_CONFIG;
        
        $ch = curl_init($config['api_url'] . 'auth/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'client_credentials'
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    /**
     * Générer une signature pour Moov Money
     */
    private function generateMoovSignature($data) {
        $config = MOOV_MONEY_CONFIG;
        $dataString = implode('|', [
            $data['merchant_id'],
            $data['transaction_id'],
            $data['amount'],
            $data['currency']
        ]);
        
        return hash_hmac('sha256', $dataString, $config['secret_key']);
    }
    
    /**
     * Vérifier le statut d'une transaction
     */
    public function checkTransactionStatus($transactionId) {
        $transaction = $this->db->fetchOne("SELECT * FROM transactions WHERE id = ?", [$transactionId]);
        
        if (!$transaction) {
            return ['success' => false, 'error' => 'Transaction introuvable'];
        }
        
        // Vérifier selon la passerelle
        switch ($transaction['gateway']) {
            case 'airtel_money':
                return $this->checkAirtelStatus($transaction);
            case 'moov_money':
                return $this->checkMoovStatus($transaction);
            default:
                return [
                    'success' => true,
                    'status' => $transaction['status'],
                    'data' => $transaction
                ];
        }
    }
    
    /**
     * Traiter un webhook de paiement
     */
    public function handleWebhook($gateway, $data) {
        try {
            logPaymentTransaction(['webhook' => $gateway, 'data' => $data]);
            
            switch ($gateway) {
                case 'airtel':
                    return $this->handleAirtelWebhook($data);
                case 'moov':
                    return $this->handleMoovWebhook($data);
                case 'stripe':
                    return $this->handleStripeWebhook($data);
                default:
                    throw new Exception('Gateway non supporté');
            }
            
        } catch (Exception $e) {
            logActivity(LOG_LEVEL_ERROR, "Erreur webhook {$gateway}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Effectuer un remboursement
     */
    public function refundPayment($transactionId, $reason) {
        try {
            $transaction = $this->db->fetchOne("SELECT * FROM transactions WHERE id = ?", [$transactionId]);
            
            if (!$transaction) {
                return ['success' => false, 'error' => 'Transaction introuvable'];
            }
            
            if ($transaction['status'] !== TRANSACTION_STATUS['COMPLETED']) {
                return ['success' => false, 'error' => 'Cette transaction ne peut pas être remboursée'];
            }
            
            // Traiter le remboursement selon la méthode
            switch ($transaction['gateway']) {
                case 'wallet':
                    // Recréditer le portefeuille
                    $this->db->update('users',
                        ['wallet_balance' => new \PDO::PARAM_STR('wallet_balance + ' . $transaction['amount'])],
                        'id = ?',
                        [$transaction['user_id']]
                    );
                    break;
                    
                default:
                    // Implémenter les remboursements pour chaque gateway
                    break;
            }
            
            // Mettre à jour les statuts
            $this->updateTransactionStatus($transactionId, TRANSACTION_STATUS['REFUNDED']);
            
            // Enregistrer le remboursement
            $this->db->insert('transactions', [
                'user_id' => $transaction['user_id'],
                'type' => 'refund',
                'amount' => $transaction['amount'],
                'currency' => $transaction['currency'],
                'description' => 'Remboursement: ' . $reason,
                'reference' => 'REF-' . $transaction['reference'],
                'gateway' => $transaction['gateway'],
                'status' => TRANSACTION_STATUS['COMPLETED']
            ]);
            
            // Notification
            sendPaymentNotification($transaction['user_id'], $transactionId, TRANSACTION_STATUS['REFUNDED']);
            
            return ['success' => true, 'message' => 'Remboursement effectué avec succès'];
            
        } catch (Exception $e) {
            logActivity(LOG_LEVEL_ERROR, "Erreur remboursement: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors du remboursement'];
        }
    }
}

// Instance globale du gestionnaire de paiements
$paymentManager = new PaymentManager();
?>