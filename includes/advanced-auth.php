<?php
/**
 * Système d'authentification avancé avec 2FA - Tchadok Platform
 * Support: Email, SMS, Google Authenticator, Backup codes
 */

/**
 * Génère un code de vérification à 6 chiffres
 */
function generateVerificationCode() {
    return sprintf('%06d', rand(0, 999999));
}

/**
 * Génère un token sécurisé
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Envoie un code de vérification par email
 */
function sendEmailVerificationCode($email, $code, $purpose = 'login') {
    $subject = '';
    $message = '';
    
    switch ($purpose) {
        case 'login':
            $subject = 'Code de connexion Tchadok';
            $message = "
                <h2>Code de connexion</h2>
                <p>Votre code de vérification est :</p>
                <h1 style='color: #0066CC; font-size: 3em; letter-spacing: 5px;'>$code</h1>
                <p>Ce code expire dans 10 minutes.</p>
                <p>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
            ";
            break;
            
        case 'registration':
            $subject = 'Vérification de votre compte Tchadok';
            $message = "
                <h2>Bienvenue sur Tchadok !</h2>
                <p>Pour finaliser votre inscription, utilisez ce code :</p>
                <h1 style='color: #0066CC; font-size: 3em; letter-spacing: 5px;'>$code</h1>
                <p>Ce code expire dans 24 heures.</p>
            ";
            break;
            
        case 'password_reset':
            $subject = 'Réinitialisation de mot de passe Tchadok';
            $message = "
                <h2>Réinitialisation de mot de passe</h2>
                <p>Utilisez ce code pour réinitialiser votre mot de passe :</p>
                <h1 style='color: #0066CC; font-size: 3em; letter-spacing: 5px;'>$code</h1>
                <p>Ce code expire dans 30 minutes.</p>
            ";
            break;
    }
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Tchadok <noreply@tchadok.com>',
        'Reply-To: support@tchadok.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Simulation d'envoi d'email (remplacer par vraie fonction mail)
    error_log("Email envoyé à $email: Code $code pour $purpose");
    
    return true;
}

/**
 * Envoie un code de vérification par SMS
 */
function sendSMSVerificationCode($phone, $code, $purpose = 'login') {
    $message = '';
    
    switch ($purpose) {
        case 'login':
            $message = "Tchadok: Votre code de connexion est $code. Expire dans 10 min.";
            break;
        case 'registration':
            $message = "Tchadok: Code de vérification $code pour finaliser votre inscription.";
            break;
        case 'password_reset':
            $message = "Tchadok: Code de réinitialisation $code. Expire dans 30 min.";
            break;
    }
    
    // Simulation d'envoi SMS (intégrer API SMS Tchad)
    error_log("SMS envoyé à $phone: $message");
    
    return true;
}

/**
 * Valide un numéro de téléphone tchadien
 */
function validateTchadianPhone($phone) {
    // Nettoyer le numéro
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Format tchadien: +235 XX XX XX XX ou 235 XX XX XX XX ou XX XX XX XX
    $patterns = [
        '/^\+235[679][0-9]{7}$/',  // +235 avec indicatif opérateur
        '/^235[679][0-9]{7}$/',    // 235 avec indicatif opérateur
        '/^[679][0-9]{7}$/'        // Sans indicatif pays
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Normalise un numéro de téléphone tchadien
 */
function normalizeTchadianPhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Ajouter l'indicatif pays si manquant
    if (preg_match('/^[679][0-9]{7}$/', $phone)) {
        return '+235' . $phone;
    }
    
    if (preg_match('/^235[679][0-9]{7}$/', $phone)) {
        return '+' . $phone;
    }
    
    return $phone;
}

/**
 * Génère un secret pour Google Authenticator
 */
function generateTOTPSecret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $secret;
}

/**
 * Génère l'URL QR Code pour Google Authenticator
 */
function generateTOTPQRCodeURL($secret, $issuer = 'Tchadok', $accountName = '') {
    $url = 'otpauth://totp/' . urlencode($issuer . ':' . $accountName) . 
           '?secret=' . $secret . 
           '&issuer=' . urlencode($issuer);
    return $url;
}

/**
 * Vérifie un code TOTP (Google Authenticator)
 */
function verifyTOTPCode($secret, $code, $timeWindow = 1) {
    $timeStep = 30; // 30 secondes par step
    $currentTime = time();
    
    for ($i = -$timeWindow; $i <= $timeWindow; $i++) {
        $time = $currentTime + ($i * $timeStep);
        $expectedCode = generateTOTPCode($secret, floor($time / $timeStep));
        
        if (hash_equals($expectedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Génère un code TOTP basé sur un secret et un timestamp
 */
function generateTOTPCode($secret, $timeStep) {
    $secret = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeStep);
    $hash = hash_hmac('sha1', $time, $secret, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset+0]) & 0x7f) << 24) |
        ((ord($hash[$offset+1]) & 0xff) << 16) |
        ((ord($hash[$offset+2]) & 0xff) << 8) |
        (ord($hash[$offset+3]) & 0xff)
    ) % 1000000;
    
    return sprintf('%06d', $code);
}

/**
 * Décode une chaîne base32
 */
function base32_decode($secret) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $paddingCharCount = substr_count($secret, '=');
    $allowedValues = array(6, 4, 3, 1, 0);
    
    if (!in_array($paddingCharCount, $allowedValues)) return false;
    
    for ($i = 0; $i < 4; $i++){
        if ($paddingCharCount == $allowedValues[$i] &&
            substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) return false;
    }
    
    $secret = str_replace('=', '', $secret);
    $binaryString = '';
    
    for ($i = 0; $i < strlen($secret); $i = $i + 8) {
        $x = '';
        if (!in_array($secret[$i], str_split($base32chars))) return false;
        for ($j = 0; $j < 8; $j++) {
            $x .= str_pad(base_convert(strpos($base32chars, $secret[$i + $j]), 10, 2), 5, '0', STR_PAD_LEFT);
        }
        $eightBits = str_split($x, 8);
        for ($z = 0; $z < count($eightBits); $z++) {
            $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
        }
    }
    
    return $binaryString;
}

/**
 * Génère des codes de récupération
 */
function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 caractères hex
    }
    return $codes;
}

/**
 * Hash un code de récupération
 */
function hashBackupCode($code) {
    return password_hash(strtoupper($code), PASSWORD_DEFAULT);
}

/**
 * Vérifie un code de récupération
 */
function verifyBackupCode($code, $hashedCode) {
    return password_verify(strtoupper($code), $hashedCode);
}

/**
 * Enregistre un token de connexion persistent
 */
function createRememberToken($userId) {
    $token = generateSecureToken();
    $selector = generateSecureToken(12);
    $hashedToken = password_hash($token, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Stocker en base de données (simulation)
    storeRememberToken($userId, $selector, $hashedToken, $expires);
    
    // Retourner le cookie value (selector:token)
    return $selector . ':' . $token;
}

/**
 * Vérifie un token de connexion persistent
 */
function verifyRememberToken($cookieValue) {
    if (!$cookieValue || !str_contains($cookieValue, ':')) {
        return false;
    }
    
    list($selector, $token) = explode(':', $cookieValue, 2);
    
    // Récupérer le token de la base (simulation)
    $storedToken = getRememberToken($selector);
    
    if (!$storedToken || strtotime($storedToken['expires']) < time()) {
        return false;
    }
    
    if (password_verify($token, $storedToken['hashed_token'])) {
        return $storedToken['user_id'];
    }
    
    return false;
}

/**
 * Supprime les tokens de connexion d'un utilisateur
 */
function deleteRememberTokens($userId) {
    // Simulation - supprimer de la base de données
    error_log("Suppression des remember tokens pour l'utilisateur $userId");
    return true;
}

/**
 * Enregistre une tentative de connexion
 */
function logLoginAttempt($identifier, $success, $ip = null, $userAgent = null) {
    $ip = $ip ?: $_SERVER['REMOTE_ADDR'];
    $userAgent = $userAgent ?: $_SERVER['HTTP_USER_AGENT'];
    
    $attempt = [
        'identifier' => $identifier,
        'success' => $success,
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'timestamp' => date('Y-m-d H:i:s'),
        'location' => getLocationFromIP($ip)
    ];
    
    // Stocker en base (simulation)
    storeLoginAttempt($attempt);
    
    return true;
}

/**
 * Vérifie si un compte est bloqué (trop de tentatives échouées)
 */
function isAccountLocked($identifier) {
    $attempts = getRecentFailedAttempts($identifier, 15); // 15 dernières minutes
    
    if (count($attempts) >= 5) {
        return [
            'locked' => true,
            'until' => date('H:i', strtotime('+15 minutes')),
            'attempts' => count($attempts)
        ];
    }
    
    return ['locked' => false];
}

/**
 * Détecte une connexion suspecte
 */
function detectSuspiciousLogin($userId, $ip, $userAgent) {
    $recentLogins = getRecentSuccessfulLogins($userId, 30); // 30 derniers jours
    
    $suspicious = false;
    $reasons = [];
    
    // Nouvelle adresse IP
    $knownIPs = array_column($recentLogins, 'ip_address');
    if (!in_array($ip, $knownIPs)) {
        $suspicious = true;
        $reasons[] = 'Nouvelle adresse IP';
    }
    
    // Nouveau user agent (navigateur/appareil)
    $knownUserAgents = array_column($recentLogins, 'user_agent');
    $deviceFingerprint = generateDeviceFingerprint($userAgent);
    $knownFingerprints = array_map('generateDeviceFingerprint', $knownUserAgents);
    
    if (!in_array($deviceFingerprint, $knownFingerprints)) {
        $suspicious = true;
        $reasons[] = 'Nouvel appareil/navigateur';
    }
    
    // Géolocalisation inhabituelle
    $currentLocation = getLocationFromIP($ip);
    $knownLocations = array_unique(array_column($recentLogins, 'location'));
    
    if (!in_array($currentLocation, $knownLocations)) {
        $suspicious = true;
        $reasons[] = 'Nouvelle localisation';
    }
    
    return [
        'suspicious' => $suspicious,
        'reasons' => $reasons,
        'confidence' => $suspicious ? (count($reasons) / 3) * 100 : 0
    ];
}

/**
 * Génère une empreinte d'appareil simplifiée
 */
function generateDeviceFingerprint($userAgent) {
    // Extraire le navigateur et l'OS
    $browser = 'unknown';
    $os = 'unknown';
    
    if (preg_match('/Chrome\/([0-9]+)/', $userAgent)) $browser = 'chrome';
    elseif (preg_match('/Firefox\/([0-9]+)/', $userAgent)) $browser = 'firefox';
    elseif (preg_match('/Safari\/([0-9]+)/', $userAgent)) $browser = 'safari';
    elseif (preg_match('/Edge\/([0-9]+)/', $userAgent)) $browser = 'edge';
    
    if (preg_match('/Windows/', $userAgent)) $os = 'windows';
    elseif (preg_match('/Mac OS/', $userAgent)) $os = 'macos';
    elseif (preg_match('/Linux/', $userAgent)) $os = 'linux';
    elseif (preg_match('/Android/', $userAgent)) $os = 'android';
    elseif (preg_match('/iPhone|iPad/', $userAgent)) $os = 'ios';
    
    return $browser . '_' . $os;
}

/**
 * Obtient la localisation approximative depuis une IP
 */
function getLocationFromIP($ip) {
    // Simulation - dans la réalité, utiliser un service de géolocalisation IP
    $locations = ['N\'Djamena', 'Abéché', 'Moundou', 'Sarh', 'Am Timan'];
    return $locations[array_rand($locations)];
}

/**
 * Envoie une notification de connexion suspecte
 */
function sendSuspiciousLoginNotification($userId, $details) {
    $user = getUserById($userId);
    if (!$user) return false;
    
    $email = $user['email'];
    $name = $user['first_name'];
    $ip = $details['ip'];
    $location = $details['location'];
    $device = $details['device'];
    $time = date('d/m/Y à H:i');
    
    $subject = 'Connexion détectée sur votre compte Tchadok';
    $message = "
        <h2>Connexion détectée</h2>
        <p>Bonjour $name,</p>
        <p>Une connexion a été détectée sur votre compte Tchadok :</p>
        <ul>
            <li><strong>Date :</strong> $time</li>
            <li><strong>Adresse IP :</strong> $ip</li>
            <li><strong>Localisation :</strong> $location</li>
            <li><strong>Appareil :</strong> $device</li>
        </ul>
        <p>Si c'était vous, vous pouvez ignorer cet email.</p>
        <p>Sinon, <a href='" . SITE_URL . "/security/change-password'>changez immédiatement votre mot de passe</a>.</p>
    ";
    
    return sendEmailVerificationCode($email, '', 'security_alert');
}

/**
 * Fonctions de simulation de base de données
 */
function storeRememberToken($userId, $selector, $hashedToken, $expires) {
    // Simulation - stocker en base de données
    return true;
}

function getRememberToken($selector) {
    // Simulation - récupérer de la base de données
    return [
        'user_id' => 1,
        'hashed_token' => password_hash('dummy_token', PASSWORD_DEFAULT),
        'expires' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ];
}

function storeLoginAttempt($attempt) {
    // Simulation - stocker en base de données
    error_log("Tentative de connexion: " . json_encode($attempt));
    return true;
}

function getRecentFailedAttempts($identifier, $minutes) {
    // Simulation - récupérer les tentatives échouées récentes
    return []; // Retourner tableau vide pour simulation
}

function getRecentSuccessfulLogins($userId, $days) {
    // Simulation - récupérer les connexions réussies récentes
    return [
        [
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'location' => 'N\'Djamena',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]
    ];
}

function getUserById($userId) {
    // Simulation - récupérer utilisateur de la base
    return [
        'id' => $userId,
        'email' => 'user@example.com',
        'first_name' => 'John'
    ];
}

/**
 * Vérifie la force d'un mot de passe
 */
function checkPasswordStrength($password) {
    $score = 0;
    $feedback = [];
    
    // Longueur
    if (strlen($password) >= 8) $score += 25;
    else $feedback[] = 'Au moins 8 caractères requis';
    
    if (strlen($password) >= 12) $score += 10;
    
    // Complexité
    if (preg_match('/[a-z]/', $password)) $score += 15;
    else $feedback[] = 'Ajouter des lettres minuscules';
    
    if (preg_match('/[A-Z]/', $password)) $score += 15;
    else $feedback[] = 'Ajouter des lettres majuscules';
    
    if (preg_match('/[0-9]/', $password)) $score += 15;
    else $feedback[] = 'Ajouter des chiffres';
    
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 20;
    else $feedback[] = 'Ajouter des caractères spéciaux';
    
    // Patterns communs (malus)
    if (preg_match('/^(password|123456|qwerty)/i', $password)) {
        $score -= 50;
        $feedback[] = 'Éviter les mots de passe courants';
    }
    
    $score = max(0, min(100, $score));
    
    if ($score < 50) $strength = 'Faible';
    elseif ($score < 75) $strength = 'Moyen';
    else $strength = 'Fort';
    
    return [
        'score' => $score,
        'strength' => $strength,
        'feedback' => $feedback
    ];
}

/**
 * Active la 2FA pour un utilisateur
 */
function enable2FA($userId, $method, $contact = null) {
    $secret = '';
    
    switch ($method) {
        case 'totp':
            $secret = generateTOTPSecret();
            break;
        case 'sms':
            if (!$contact || !validateTchadianPhone($contact)) {
                return ['success' => false, 'error' => 'Numéro de téléphone invalide'];
            }
            $contact = normalizeTchadianPhone($contact);
            break;
        case 'email':
            if (!$contact || !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Email invalide'];
            }
            break;
    }
    
    // Sauvegarder en base (simulation)
    save2FASettings($userId, $method, $secret, $contact);
    
    $result = [
        'success' => true,
        'method' => $method,
        'backup_codes' => generateBackupCodes()
    ];
    
    if ($method === 'totp') {
        $user = getUserById($userId);
        $accountName = $user['email'];
        $result['qr_url'] = generateTOTPQRCodeURL($secret, 'Tchadok', $accountName);
        $result['secret'] = $secret;
    }
    
    return $result;
}

/**
 * Sauvegarde les paramètres 2FA
 */
function save2FASettings($userId, $method, $secret, $contact) {
    // Simulation - sauvegarder en base
    error_log("2FA activée pour utilisateur $userId: $method");
    return true;
}

/**
 * Récupère les paramètres 2FA d'un utilisateur
 */
function get2FASettings($userId) {
    // Simulation - récupérer de la base
    return [
        'enabled' => false,
        'method' => null,
        'secret' => null,
        'contact' => null,
        'backup_codes' => []
    ];
}

/**
 * Authentification en deux étapes complète
 */
function authenticate2FA($userId, $code, $method = null) {
    $settings = get2FASettings($userId);
    
    if (!$settings['enabled']) {
        return ['success' => false, 'error' => '2FA non activée'];
    }
    
    $method = $method ?: $settings['method'];
    
    switch ($method) {
        case 'totp':
            if (verifyTOTPCode($settings['secret'], $code)) {
                return ['success' => true, 'method' => 'totp'];
            }
            break;
            
        case 'sms':
        case 'email':
            // Vérifier le code stocké temporairement
            if (verifyStoredCode($userId, $code, $method)) {
                return ['success' => true, 'method' => $method];
            }
            break;
            
        case 'backup':
            // Vérifier les codes de récupération
            if (verifyAndConsumeBackupCode($userId, $code)) {
                return ['success' => true, 'method' => 'backup'];
            }
            break;
    }
    
    return ['success' => false, 'error' => 'Code invalide'];
}

/**
 * Vérifie un code stocké temporairement
 */
function verifyStoredCode($userId, $code, $method) {
    // Simulation - vérifier le code en base
    return $code === '123456'; // Code de test
}

/**
 * Vérifie et consomme un code de récupération
 */
function verifyAndConsumeBackupCode($userId, $code) {
    // Simulation - vérifier et marquer comme utilisé
    return strlen($code) === 8; // Codes de 8 caractères
}
?>