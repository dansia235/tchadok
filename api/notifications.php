<?php
/**
 * API de gestion des notifications - Tchadok Platform
 * Support: notifications push, email, SMS, in-app
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
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
 * Gestion des requêtes GET
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'list';
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
    
    switch ($action) {
        case 'list':
            getUserNotifications($userId);
            break;
        case 'unread':
            getUnreadNotifications($userId);
            break;
        case 'count':
            getNotificationCount($userId);
            break;
        case 'settings':
            getNotificationSettings($userId);
            break;
        case 'types':
            getNotificationTypes();
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes POST
 */
function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? 'send';
    
    switch ($action) {
        case 'send':
            sendNotification($input ?: $_POST);
            break;
        case 'broadcast':
            broadcastNotification($input ?: $_POST);
            break;
        case 'schedule':
            scheduleNotification($input ?: $_POST);
            break;
        case 'subscribe':
            subscribeToNotifications($input ?: $_POST);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes PUT
 */
function handlePutRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'update';
    
    switch ($action) {
        case 'mark_read':
            markNotificationAsRead($input);
            break;
        case 'mark_all_read':
            markAllNotificationsAsRead($input);
            break;
        case 'update_settings':
            updateNotificationSettings($input);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Gestion des requêtes DELETE
 */
function handleDeleteRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? 'delete';
    
    switch ($action) {
        case 'delete':
            deleteNotification($input['notification_id'] ?? $_GET['id']);
            break;
        case 'clear_all':
            clearAllNotifications($input['user_id'] ?? $_GET['user_id']);
            break;
        case 'unsubscribe':
            unsubscribeFromNotifications($input);
            break;
        default:
            throw new Exception('Action non reconnue', 400);
    }
}

/**
 * Récupérer les notifications d'un utilisateur
 */
function getUserNotifications($userId) {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $type = $_GET['type'] ?? null;
    $status = $_GET['status'] ?? null; // read, unread, all
    
    // Simulation de notifications
    $notifications = [];
    $notificationTypes = [
        'new_track' => [
            'icon' => 'fas fa-music',
            'color' => '#28a745',
            'title' => 'Nouveau titre disponible',
            'category' => 'Musique'
        ],
        'new_follower' => [
            'icon' => 'fas fa-user-plus',
            'color' => '#007bff',
            'title' => 'Nouveau abonné',
            'category' => 'Social'
        ],
        'payment_success' => [
            'icon' => 'fas fa-check-circle',
            'color' => '#28a745',
            'title' => 'Paiement réussi',
            'category' => 'Paiement'
        ],
        'playlist_shared' => [
            'icon' => 'fas fa-share',
            'color' => '#17a2b8',
            'title' => 'Playlist partagée',
            'category' => 'Playlist'
        ],
        'premium_expires' => [
            'icon' => 'fas fa-crown',
            'color' => '#ffc107',
            'title' => 'Premium expire bientôt',
            'category' => 'Abonnement'
        ]
    ];
    
    for ($i = 0; $i < $limit; $i++) {
        $typeKey = array_rand($notificationTypes);
        $typeInfo = $notificationTypes[$typeKey];
        $isRead = rand(0, 1) === 1;
        
        if ($status && (($status === 'read' && !$isRead) || ($status === 'unread' && $isRead))) {
            continue;
        }
        
        $notifications[] = [
            'id' => rand(1000, 9999),
            'user_id' => $userId,
            'type' => $typeKey,
            'title' => $typeInfo['title'],
            'message' => generateNotificationMessage($typeKey),
            'icon' => $typeInfo['icon'],
            'color' => $typeInfo['color'],
            'category' => $typeInfo['category'],
            'data' => generateNotificationData($typeKey),
            'is_read' => $isRead,
            'is_important' => rand(0, 3) === 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 720) . ' hours')),
            'read_at' => $isRead ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 360) . ' hours')) : null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . rand(1, 30) . ' days')),
            'action_url' => generateActionUrl($typeKey),
            'sender' => generateSender($typeKey)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'total_count' => count($notifications),
            'unread_count' => count(array_filter($notifications, fn($n) => !$n['is_read'])),
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupérer les notifications non lues
 */
function getUnreadNotifications($userId) {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    // Simulation de notifications non lues
    $unreadNotifications = [
        [
            'id' => rand(1000, 9999),
            'type' => 'new_track',
            'title' => 'Nouveau titre de Khalil MC',
            'message' => 'Découvrez "Sahara Beat", le nouveau hit afrobeat',
            'icon' => 'fas fa-music',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'priority' => 'high'
        ],
        [
            'id' => rand(1000, 9999),
            'type' => 'payment_success',
            'title' => 'Paiement confirmé',
            'message' => 'Votre abonnement Premium a été renouvelé avec succès',
            'icon' => 'fas fa-check-circle',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
            'priority' => 'medium'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $unreadNotifications,
            'count' => count($unreadNotifications),
            'user_id' => $userId
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Compter les notifications
 */
function getNotificationCount($userId) {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_count' => rand(50, 200),
            'unread_count' => rand(2, 15),
            'important_count' => rand(0, 3),
            'user_id' => $userId
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Récupérer les paramètres de notifications
 */
function getNotificationSettings($userId) {
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    $settings = [
        'push_notifications' => true,
        'email_notifications' => true,
        'sms_notifications' => false,
        'categories' => [
            'new_tracks' => true,
            'social' => true,
            'payments' => true,
            'playlists' => false,
            'promotions' => false,
            'system' => true
        ],
        'frequency' => 'instant', // instant, daily, weekly
        'quiet_hours' => [
            'enabled' => true,
            'start' => '22:00',
            'end' => '08:00'
        ],
        'languages' => ['fr', 'ar'],
        'channels' => [
            'web' => true,
            'mobile' => true,
            'email' => true,
            'sms' => false
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'settings' => $settings,
            'last_updated' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Types de notifications disponibles
 */
function getNotificationTypes() {
    $types = [
        [
            'id' => 'new_track',
            'name' => 'Nouveaux titres',
            'description' => 'Notifications pour les nouveaux titres d\'artistes suivis',
            'icon' => 'fas fa-music',
            'color' => '#28a745',
            'category' => 'Musique',
            'default_enabled' => true
        ],
        [
            'id' => 'new_follower',
            'name' => 'Nouveaux abonnés',
            'description' => 'Quelqu\'un vous suit sur Tchadok',
            'icon' => 'fas fa-user-plus',
            'color' => '#007bff',
            'category' => 'Social',
            'default_enabled' => true
        ],
        [
            'id' => 'payment_success',
            'name' => 'Paiements réussis',
            'description' => 'Confirmations de paiements et achats',
            'icon' => 'fas fa-check-circle',
            'color' => '#28a745',
            'category' => 'Paiement',
            'default_enabled' => true
        ],
        [
            'id' => 'playlist_shared',
            'name' => 'Playlists partagées',
            'description' => 'Quand quelqu\'un partage une playlist avec vous',
            'icon' => 'fas fa-share',
            'color' => '#17a2b8',
            'category' => 'Playlist',
            'default_enabled' => false
        ],
        [
            'id' => 'premium_expires',
            'name' => 'Expiration Premium',
            'description' => 'Rappels d\'expiration d\'abonnement',
            'icon' => 'fas fa-crown',
            'color' => '#ffc107',
            'category' => 'Abonnement',
            'default_enabled' => true
        ],
        [
            'id' => 'system_maintenance',
            'name' => 'Maintenance système',
            'description' => 'Informations sur les maintenances et mises à jour',
            'icon' => 'fas fa-tools',
            'color' => '#6c757d',
            'category' => 'Système',
            'default_enabled' => true
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'types' => $types,
            'total_count' => count($types)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Envoyer une notification
 */
function sendNotification($data) {
    $requiredFields = ['user_id', 'type', 'title', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Le champ '$field' est requis", 400);
        }
    }
    
    $notification = [
        'id' => rand(10000, 99999),
        'user_id' => (int)$data['user_id'],
        'type' => $data['type'],
        'title' => $data['title'],
        'message' => $data['message'],
        'icon' => $data['icon'] ?? 'fas fa-bell',
        'color' => $data['color'] ?? '#007bff',
        'data' => $data['data'] ?? null,
        'channels' => $data['channels'] ?? ['web', 'push'],
        'priority' => $data['priority'] ?? 'normal',
        'expires_at' => $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days')),
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'sent'
    ];
    
    // Simulation d'envoi
    $sendResult = processNotificationSending($notification);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'notification' => $notification,
            'delivery_status' => $sendResult,
            'message' => 'Notification envoyée avec succès'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Diffusion de notification à plusieurs utilisateurs
 */
function broadcastNotification($data) {
    $requiredFields = ['target_users', 'type', 'title', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Le champ '$field' est requis", 400);
        }
    }
    
    $targetUsers = is_array($data['target_users']) ? $data['target_users'] : explode(',', $data['target_users']);
    $successCount = 0;
    $failedCount = 0;
    $results = [];
    
    foreach ($targetUsers as $userId) {
        try {
            $notification = [
                'id' => rand(10000, 99999),
                'user_id' => (int)$userId,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Simulation d'envoi
            $sent = rand(0, 9) < 8; // 80% de succès
            if ($sent) {
                $successCount++;
                $results[] = ['user_id' => $userId, 'status' => 'sent'];
            } else {
                $failedCount++;
                $results[] = ['user_id' => $userId, 'status' => 'failed', 'error' => 'Utilisateur non joignable'];
            }
        } catch (Exception $e) {
            $failedCount++;
            $results[] = ['user_id' => $userId, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'broadcast_id' => 'BC_' . time(),
            'total_users' => count($targetUsers),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'message' => "Diffusion terminée: $successCount envoyées, $failedCount échouées"
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($data) {
    $notificationId = $data['notification_id'] ?? null;
    if (!$notificationId) {
        throw new Exception('ID de notification requis', 400);
    }
    
    // Simulation de mise à jour
    $updated = true;
    
    echo json_encode([
        'success' => $updated,
        'data' => [
            'notification_id' => $notificationId,
            'read_at' => date('Y-m-d H:i:s'),
            'message' => 'Notification marquée comme lue'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($data) {
    $userId = $data['user_id'] ?? ($_SESSION['user_id'] ?? null);
    if (!$userId) {
        throw new Exception('ID utilisateur requis', 400);
    }
    
    // Simulation
    $updatedCount = rand(5, 25);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'updated_count' => $updatedCount,
            'read_at' => date('Y-m-d H:i:s'),
            'message' => "$updatedCount notifications marquées comme lues"
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Fonctions helper
 */
function generateNotificationMessage($type) {
    $messages = [
        'new_track' => [
            'Khalil MC vient de sortir "Sahara Beat" - Écoutez maintenant !',
            'Nouveau titre disponible de votre artiste préféré',
            'Découvrez le dernier hit afrobeat du moment'
        ],
        'new_follower' => [
            'Vous avez un nouvel abonné ! Merci de partager votre passion musicale',
            'Votre communauté grandit sur Tchadok',
            'Quelqu\'un d\'autre apprécie votre goût musical'
        ],
        'payment_success' => [
            'Votre paiement de 5000 FCFA a été traité avec succès',
            'Abonnement Premium activé - Profitez de tous les avantages',
            'Votre achat a été confirmé'
        ],
        'playlist_shared' => [
            'Sarah a partagé sa playlist "Chill Tchadien" avec vous',
            'Nouvelle playlist collaborative disponible',
            'Découvrez une sélection musicale personnalisée'
        ],
        'premium_expires' => [
            'Votre abonnement Premium expire dans 3 jours',
            'Renouvelez votre Premium pour continuer à profiter',
            'N\'oubliez pas de renouveler votre abonnement'
        ]
    ];
    
    return $messages[$type][array_rand($messages[$type])];
}

function generateNotificationData($type) {
    switch ($type) {
        case 'new_track':
            return [
                'track_id' => rand(1, 100),
                'artist_id' => rand(1, 20),
                'genre' => 'Afrobeat'
            ];
        case 'new_follower':
            return [
                'follower_id' => rand(1, 1000),
                'follower_name' => 'Utilisateur ' . rand(1, 100)
            ];
        case 'payment_success':
            return [
                'transaction_id' => 'TXN_' . rand(100000, 999999),
                'amount' => rand(1000, 10000),
                'method' => 'Airtel Money'
            ];
        default:
            return null;
    }
}

function generateActionUrl($type) {
    $baseUrl = SITE_URL ?? 'https://tchadok.com';
    switch ($type) {
        case 'new_track':
            return $baseUrl . '/track/' . rand(1, 100);
        case 'payment_success':
            return $baseUrl . '/account/payments';
        case 'playlist_shared':
            return $baseUrl . '/playlist/' . rand(1, 50);
        default:
            return $baseUrl . '/notifications';
    }
}

function generateSender($type) {
    switch ($type) {
        case 'new_track':
            return [
                'type' => 'artist',
                'id' => rand(1, 20),
                'name' => 'Khalil MC',
                'avatar' => 'assets/images/default-avatar.png'
            ];
        case 'payment_success':
            return [
                'type' => 'system',
                'name' => 'Tchadok Payments',
                'avatar' => 'assets/images/logo.svg'
            ];
        default:
            return [
                'type' => 'system',
                'name' => 'Tchadok',
                'avatar' => 'assets/images/logo.svg'
            ];
    }
}

function processNotificationSending($notification) {
    // Simulation d'envoi multi-canal
    $channels = $notification['channels'];
    $results = [];
    
    foreach ($channels as $channel) {
        $success = rand(0, 9) < 8; // 80% de succès
        $results[$channel] = [
            'status' => $success ? 'sent' : 'failed',
            'timestamp' => date('c'),
            'error' => $success ? null : 'Canal temporairement indisponible'
        ];
    }
    
    return $results;
}

// Fonctions simplifiées pour les autres actions
function scheduleNotification($data) {
    echo json_encode(['success' => true, 'message' => 'Notification programmée']);
}

function subscribeToNotifications($data) {
    echo json_encode(['success' => true, 'message' => 'Abonnement aux notifications activé']);
}

function updateNotificationSettings($data) {
    echo json_encode(['success' => true, 'message' => 'Paramètres mis à jour']);
}

function deleteNotification($notificationId) {
    echo json_encode(['success' => true, 'message' => 'Notification supprimée']);
}

function clearAllNotifications($userId) {
    echo json_encode(['success' => true, 'message' => 'Toutes les notifications supprimées']);
}

function unsubscribeFromNotifications($data) {
    echo json_encode(['success' => true, 'message' => 'Désabonnement effectué']);
}
?>