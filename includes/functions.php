<?php
/**
 * Fonctions utilitaires pour Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/database.php';

/**
 * Démarre une session sécurisée
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Hache un mot de passe de manière sécurisée
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Vérifie un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Nettoie et sécurise les données d'entrée
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Valide une adresse email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone tchadien
 */
function validateTchadianPhone($phone) {
    // Format: +235 XX XX XX XX ou 235XXXXXXXX ou XXXXXXXX
    $pattern = '/^(\+235|235)?[0-9]{8}$/';
    $cleanPhone = preg_replace('/[\s\-\.]/', '', $phone);
    return preg_match($pattern, $cleanPhone);
}

/**
 * Génère un token sécurisé
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est un artiste
 */
function isArtist() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_ARTIST;
}

/**
 * Vérifie si l'utilisateur est un administrateur
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_ADMIN;
}

/**
 * Vérifie si l'utilisateur est un fan (utilisateur normal)
 */
function isFan() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_FAN;
}

/**
 * Redirige vers le dashboard approprié selon le type d'utilisateur
 */
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }

    if (isAdmin()) {
        header('Location: ' . SITE_URL . '/admin-dashboard.php');
        exit();
    } elseif (isArtist()) {
        header('Location: ' . SITE_URL . '/artist-dashboard.php');
        exit();
    } else {
        header('Location: ' . SITE_URL . '/user-dashboard.php');
        exit();
    }
}

/**
 * Obtient l'utilisateur actuel
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;

    try {
        $dbInstance = TchadokDatabase::getInstance();
        $db = $dbInstance->getConnection();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Redirige vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Affiche une erreur 404
 */
function show404() {
    http_response_code(404);
    include 'pages/404.php';
    exit();
}

/**
 * Formate une durée en secondes vers mm:ss
 */
function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $seconds);
}

/**
 * Formate un nombre avec des séparateurs
 */
function formatNumber($number) {
    // Gérer les valeurs null, vides ou non numériques
    if ($number === null || $number === '' || !is_numeric($number)) {
        return '0';
    }
    
    // Convertir en entier pour éviter les erreurs
    $number = (int) $number;
    
    return number_format($number, 0, ',', ' ');
}

/**
 * Formate un prix en FCFA
 */
function formatPrice($amount) {
    // Gérer les valeurs null, vides ou non numériques
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        return '0 FCFA';
    }
    
    // Convertir en entier pour éviter les erreurs
    $amount = (int) $amount;
    
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

/**
 * Génère un slug à partir d'un texte
 */
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Upload un fichier de manière sécurisée
 */
function uploadFile($file, $destination, $allowedTypes, $maxSize) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Aucun fichier sélectionné'];
    }
    
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileError = $file['error'];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload'];
    }
    
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    if ($fileSize > $maxSize) {
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    $newFileName = uniqid() . '.' . $fileExt;
    $uploadPath = $destination . $newFileName;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return ['success' => true, 'filename' => $newFileName, 'path' => $uploadPath];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de la sauvegarde'];
}

/**
 * Redimensionne une image
 */
function resizeImage($source, $destination, $maxWidth, $maxHeight) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $destination, 9);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($newImage, $destination, 85);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return true;
}

/**
 * Envoie un email
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $defaultHeaders = [
        'From' => SITE_EMAIL,
        'Reply-To' => SITE_EMAIL,
        'X-Mailer' => 'Tchadok Platform',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $headers = array_merge($defaultHeaders, $headers);
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $message, $headerString);
}

/**
 * Log une activité
 */
function logActivity($level, $message, $context = []) {
    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logEntry = "[$timestamp] [$level] $message $contextStr" . PHP_EOL;
    
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Obtient l'adresse IP du client
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Convertit les octets en format lisible
 */
function formatBytes($size, $precision = 2) {
    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Calcule le temps écoulé depuis une date
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'À l\'instant';
    if ($time < 3600) return floor($time/60) . 'min';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 2592000) return floor($time/86400) . 'j';
    if ($time < 31536000) return floor($time/2592000) . 'mois';
    
    return floor($time/31536000) . 'ans';
}

/**
 * Génère une pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';
    
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Précédent</a></li>';
    }
    
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Suivant</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Vérifie le token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Génère un token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Génère le HTML d'un champ CSRF caché
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Affiche un message flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Récupère et efface les messages flash
 */
function getFlashMessages() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Génère le HTML pour afficher les messages flash
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $html = '';
    
    foreach ($messages as $type => $message) {
        $alertClass = [
            FLASH_SUCCESS => 'alert-success',
            FLASH_ERROR => 'alert-danger',
            FLASH_INFO => 'alert-info',
            FLASH_WARNING => 'alert-warning'
        ][$type] ?? 'alert-info';
        
        $html .= "<div class=\"alert {$alertClass} alert-dismissible fade show\" role=\"alert\">";
        $html .= htmlspecialchars($message);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $html .= '</div>';
    }
    
    return $html;
}


// Initialisation de la session
startSecureSession();
?>