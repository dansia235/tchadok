<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

// Gestion des utilisateurs - API pour le dashboard
try {
    $tchadokDB = TchadokDatabase::getInstance();
    $pdo = $tchadokDB->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'list':
            // Recuperer la liste des utilisateurs
            $users = $pdo->query("
                SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.country, u.city,
                       u.is_active, u.email_verified, u.premium_status AS is_premium, u.created_at,
                       CASE
                           WHEN ad.user_id IS NOT NULL THEN 'admin'
                           WHEN ar.user_id IS NOT NULL THEN 'artist'
                           ELSE 'fan'
                       END AS user_type
                FROM users u
                LEFT JOIN admins ad ON ad.user_id = u.id
                LEFT JOIN artists ar ON ar.user_id = u.id
                ORDER BY u.created_at DESC
                LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'get':
            // Recuperer un utilisateur specifique
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'utilisateur requis');
            }

            $user = $pdo->prepare("
                SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.country, u.city,
                       u.is_active, u.email_verified, u.premium_status AS is_premium, u.created_at, u.last_login,
                       CASE
                           WHEN ad.user_id IS NOT NULL THEN 'admin'
                           WHEN ar.user_id IS NOT NULL THEN 'artist'
                           ELSE 'fan'
                       END AS user_type
                FROM users u
                LEFT JOIN admins ad ON ad.user_id = u.id
                LEFT JOIN artists ar ON ar.user_id = u.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $user->execute([$id]);
            $userData = $user->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                throw new Exception('Utilisateur non trouve');
            }

            echo json_encode(['success' => true, 'user' => $userData]);
            break;

        case 'create':
            // Creer un nouvel utilisateur
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $userType = $_POST['user_type'] ?? 'fan';
            $emailVerified = isset($_POST['email_verified']) ? 1 : 0;

            if ($firstName === '' || $lastName === '' || $username === '' || $email === '') {
                throw new Exception('Les champs prenom, nom, nom d\'utilisateur et email sont requis');
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                throw new Exception('Ce nom d\'utilisateur ou email existe deja');
            }

            $defaultPassword = password_hash('12345678', PASSWORD_DEFAULT);
            $normalizedType = in_array($userType, ['fan', 'artist', 'admin'], true) ? $userType : 'fan';

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, phone, country, city, password, is_active, email_verified, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
                ");

                $result = $stmt->execute([
                    $firstName,
                    $lastName,
                    $username,
                    $email,
                    $phone,
                    $country,
                    $city,
                    $defaultPassword,
                    $emailVerified
                ]);

                if (!$result) {
                    throw new Exception('Erreur lors de la creation de l\'utilisateur');
                }

                $userId = (int) $pdo->lastInsertId();

                if ($normalizedType === 'admin' && tableExists('admins')) {
                    $adminStmt = $pdo->prepare("
                        INSERT INTO admins (user_id, role, permissions, created_at)
                        VALUES (?, 'admin', ?, NOW())
                    ");
                    $adminStmt->execute([
                        $userId,
                        json_encode(['dashboard', 'users', 'content', 'finance'], JSON_UNESCAPED_UNICODE)
                    ]);
                } elseif ($normalizedType === 'artist' && tableExists('artists')) {
                    $artistStmt = $pdo->prepare("
                        INSERT INTO artists (user_id, stage_name, real_name, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, 1, NOW(), NOW())
                    ");

                    $stageName = trim($firstName . ' ' . $lastName);
                    if ($stageName === '') {
                        $stageName = $username;
                    }

                    $artistStmt->execute([$userId, $stageName, $stageName]);
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Utilisateur cree avec succes',
                    'user_id' => $userId
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            break;

        case 'update':
            // Mettre a jour un utilisateur
            $id = $_POST['user_id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'utilisateur requis');
            }

            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $isPremium = isset($_POST['is_premium']) ? 1 : 0;

            if ($firstName === '' || $lastName === '' || $username === '' || $email === '') {
                throw new Exception('Les champs prenom, nom, nom d\'utilisateur et email sont requis');
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$username, $email, $id]);
            if ($check->fetch()) {
                throw new Exception('Ce nom d\'utilisateur ou email existe deja');
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, username = ?, email = ?, phone = ?, country = ?, city = ?,
                    email_verified = ?, is_active = ?, premium_status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $firstName,
                $lastName,
                $username,
                $email,
                $phone,
                $country,
                $city,
                $emailVerified,
                $isActive,
                $isPremium,
                $id
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur mis a jour avec succes']);
            } else {
                throw new Exception('Erreur lors de la mise a jour de l\'utilisateur');
            }
            break;

        case 'delete':
            // Supprimer un utilisateur
            $id = $_GET['id'] ?? 0;
            if (!$id || $id == 1) {
                throw new Exception('Impossible de supprimer cet utilisateur');
            }

            $pdo->beginTransaction();

            try {
                $pdo->prepare("DELETE FROM playlist_tracks WHERE playlist_id IN (SELECT id FROM playlists WHERE user_id = ?)")->execute([$id]);
                $pdo->prepare("DELETE FROM playlists WHERE user_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE transactions SET user_id = NULL WHERE user_id = ?")->execute([$id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $result = $stmt->execute([$id]);

                if ($result) {
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Utilisateur supprime avec succes']);
                } else {
                    throw new Exception('Erreur lors de la suppression de l\'utilisateur');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            break;

        case 'bulk':
            // Actions groupees
            $operation = $_POST['operation'] ?? ($input['operation'] ?? '');
            $userIds = $_POST['users'] ?? ($input['users'] ?? []);

            if (empty($userIds) || !is_array($userIds)) {
                throw new Exception('Aucun utilisateur selectionne');
            }

            $userIds = array_values(array_filter($userIds, function ($id) {
                return (int) $id !== 1;
            }));

            if (empty($userIds)) {
                throw new Exception('Aucun utilisateur valide selectionne');
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
                    throw new Exception('Operation non reconnue');
            }

            $result = $stmt->execute($userIds);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Action groupee executee avec succes']);
            } else {
                throw new Exception('Erreur lors de l\'execution de l\'action groupee');
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
