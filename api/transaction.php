<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

// Gestion des transactions - API pour le dashboard
try {
    $tchadokDB = TchadokDatabase::getInstance();
    $pdo = $tchadokDB->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get':
            // Récupérer une transaction spécifique
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID de transaction requis');
            }
            
            $transaction = $pdo->prepare("
                SELECT t.*, u.username, u.first_name, u.last_name, u.email
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.id = ?
            ");
            $transaction->execute([$id]);
            $transactionData = $transaction->fetch(PDO::FETCH_ASSOC);
            
            if (!$transactionData) {
                throw new Exception('Transaction non trouvée');
            }
            
            echo json_encode(['success' => true, 'transaction' => $transactionData]);
            break;
            
        case 'create':
            // Créer une nouvelle transaction
            $userId = $_POST['user_id'] ?? 0;
            $type = $_POST['type'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $currency = $_POST['currency'] ?? 'XAF';
            $status = $_POST['status'] ?? 'pending';
            $paymentMethod = $_POST['payment_method'] ?? '';
            $reference = $_POST['reference'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (!$userId || !$type || $amount <= 0) {
                throw new Exception('Utilisateur, type et montant sont requis');
            }
            
            // Générer une référence si elle n'est pas fournie
            if (empty($reference)) {
                $reference = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount, currency, status, payment_method, reference, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $userId, $type, $amount, $currency, $status, $paymentMethod, $reference, $description
            ]);
            
            if ($result) {
                $transactionId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Transaction créée avec succès', 'transaction_id' => $transactionId]);
            } else {
                throw new Exception('Erreur lors de la création de la transaction');
            }
            break;
            
        case 'approve':
            // Approuver une transaction
            $id = $_GET['id'] ?? $_POST['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID de transaction requis');
            }
            
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Transaction approuvée avec succès']);
            } else {
                throw new Exception('Transaction non trouvée ou déjà traitée');
            }
            break;
            
        case 'reject':
            // Rejeter une transaction
            $id = $_GET['id'] ?? $_POST['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID de transaction requis');
            }
            
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Transaction rejetée avec succès']);
            } else {
                throw new Exception('Transaction non trouvée ou déjà traitée');
            }
            break;
            
        case 'delete':
            // Supprimer une transaction (admin seulement)
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID de transaction requis');
            }
            
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Transaction supprimée avec succès']);
            } else {
                throw new Exception('Erreur lors de la suppression de la transaction');
            }
            break;
            
        case 'stats':
            // Statistiques des transactions
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Début du mois
            $dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Aujourd'hui
            
            $stats = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_amount
                FROM transactions 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stats->execute([$dateFrom, $dateTo]);
            $statsData = $stats->fetch(PDO::FETCH_ASSOC);
            
            // Statistiques par type
            $typeStats = $pdo->prepare("
                SELECT type, COUNT(*) as count, SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount
                FROM transactions 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY type
                ORDER BY total_amount DESC
            ");
            $typeStats->execute([$dateFrom, $dateTo]);
            $typeStatsData = $typeStats->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'stats' => $statsData,
                'type_stats' => $typeStatsData,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>