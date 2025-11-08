<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

// Gestion des utilisateurs - API pour le dashboard
try {
    $tchadokDB = TchadokDatabase::getInstance();
    $pdo = $tchadokDB->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'list':
            // Récupérer la liste des utilisateurs
            $users = $pdo->query("
                SELECT id, username, first_name, last_name, email, country, city, is_active, email_verified, created_at
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        case 'get':
            // Récupérer un utilisateur spécifique
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'utilisateur requis');
            }
            
            $user = $pdo->prepare("
                SELECT id, username, first_name, last_name, email, phone, country, city, 
                       is_active, email_verified, is_premium, created_at, last_login
                FROM users 
                WHERE id = ?
            ");
            $user->execute([$id]);
            $userData = $user->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                throw new Exception('Utilisateur non trouvé');
            }
            
            echo json_encode(['success' => true, 'user' => $userData]);
            break;
            
        case 'create':
            // Créer un nouvel utilisateur
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $country = $_POST['country'] ?? '';
            $city = $_POST['city'] ?? '';
            $userType = $_POST['user_type'] ?? 'user';
            $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
            
            if (empty($firstName) || empty($lastName) || empty($username) || empty($email)) {
                throw new Exception('Les champs prénom, nom, nom d\'utilisateur et email sont requis');
            }
            
            // Vérifier si l'username ou email existe déjà
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                throw new Exception('Ce nom d\'utilisateur ou email existe déjà');
            }
            
            // Générer un mot de passe par défaut
            $defaultPassword = password_hash('12345678', PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, username, email, phone, country, city, password, is_active, email_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $firstName, $lastName, $username, $email, $phone, $country, $city, $defaultPassword, $emailVerified
            ]);
            
            if ($result) {
                $userId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Utilisateur créé avec succès', 'user_id' => $userId]);
            } else {
                throw new Exception('Erreur lors de la création de l\'utilisateur');
            }
            break;
            
        case 'update':
            // Mettre à jour un utilisateur
            $id = $_POST['user_id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'utilisateur requis');
            }
            
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $country = $_POST['country'] ?? '';
            $city = $_POST['city'] ?? '';
            $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $isPremium = isset($_POST['is_premium']) ? 1 : 0;
            
            if (empty($firstName) || empty($lastName) || empty($username) || empty($email)) {
                throw new Exception('Les champs prénom, nom, nom d\'utilisateur et email sont requis');
            }
            
            // Vérifier si l'username ou email existe déjà pour un autre utilisateur
            $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$username, $email, $id]);
            if ($check->fetch()) {
                throw new Exception('Ce nom d\'utilisateur ou email existe déjà');
            }
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, username = ?, email = ?, phone = ?, country = ?, city = ?, 
                    email_verified = ?, is_active = ?, is_premium = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $firstName, $lastName, $username, $email, $phone, $country, $city, 
                $emailVerified, $isActive, $isPremium, $id
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
            } else {
                throw new Exception('Erreur lors de la mise à jour de l\'utilisateur');
            }
            break;
            
        case 'delete':
            // Supprimer un utilisateur
            $id = $_GET['id'] ?? 0;
            if (!$id || $id == 1) { // Ne pas supprimer l'admin
                throw new Exception('Impossible de supprimer cet utilisateur');
            }
            
            // Supprimer en cascade (playlists, transactions, etc.)
            $pdo->beginTransaction();
            
            try {
                // Supprimer les playlists
                $pdo->prepare("DELETE FROM playlist_tracks WHERE playlist_id IN (SELECT id FROM playlists WHERE user_id = ?)")->execute([$id]);
                $pdo->prepare("DELETE FROM playlists WHERE user_id = ?")->execute([$id]);
                
                // Marquer les transactions comme orphelines plutôt que les supprimer
                $pdo->prepare("UPDATE transactions SET user_id = NULL WHERE user_id = ?")->execute([$id]);
                
                // Supprimer l'utilisateur
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $result = $stmt->execute([$id]);
                
                if ($result) {
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
                } else {
                    throw new Exception('Erreur lors de la suppression de l\'utilisateur');
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'bulk':
            // Actions groupées
            $operation = $_POST['operation'] ?? '';
            $userIds = $_POST['users'] ?? [];
            
            if (empty($userIds) || !is_array($userIds)) {
                throw new Exception('Aucun utilisateur sélectionné');
            }
            
            // Exclure l'admin des actions groupées
            $userIds = array_filter($userIds, function($id) { return $id != 1; });
            
            if (empty($userIds)) {
                throw new Exception('Aucun utilisateur valide sélectionné');
            }
            
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            
            switch ($operation) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
                    break;
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
                    break;
                case 'verify':
                    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id IN ($placeholders)");
                    break;
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                    break;
                default:
                    throw new Exception('Opération non reconnue');
            }
            
            $result = $stmt->execute($userIds);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Action groupée exécutée avec succès']);
            } else {
                throw new Exception('Erreur lors de l\'exécution de l\'action groupée');
            }
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>