<?php
/**
 * Constantes de l'application Tchadok
 * @author Tchadok Team
 * @version 1.0
 */

// Configuration générale du site
define('SITE_NAME', 'Tchadok');
define('SITE_TAGLINE', 'La musique tchadienne à portée de clic');
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/tchadok');
}
define('SITE_EMAIL', 'info@tchadok.td');
define('SITE_PHONE', '+235 XX XX XX XX');

// Chemins des dossiers
define('UPLOADS_PATH', 'uploads/');
define('AUDIO_PATH', UPLOADS_PATH . 'audio/');
define('IMAGES_PATH', UPLOADS_PATH . 'images/');
define('DOCUMENTS_PATH', UPLOADS_PATH . 'documents/');

// Limites de fichiers
define('MAX_AUDIO_SIZE', 50 * 1024 * 1024); // 50MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_AUDIO_TYPES', ['mp3', 'wav', 'flac', 'm4a']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

// Configuration de pagination
define('TRACKS_PER_PAGE', 20);
define('ALBUMS_PER_PAGE', 12);
define('ARTISTS_PER_PAGE', 15);
define('BLOG_POSTS_PER_PAGE', 10);

// Configuration des utilisateurs
define('MIN_PASSWORD_LENGTH', 8);
define('DEFAULT_AVATAR', 'assets/images/default-avatar.png');
define('DEFAULT_COVER', 'assets/images/default-cover.jpg');

// Types d'utilisateurs
define('USER_TYPE_FAN', 'fan');
define('USER_TYPE_ARTIST', 'artist');
define('USER_TYPE_ADMIN', 'admin');

// Statuts des contenus
define('STATUS_DRAFT', 'draft');
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// Types de paiement
define('PAYMENT_AIRTEL', 'airtel_money');
define('PAYMENT_MOOV', 'moov_money');
define('PAYMENT_ECOBANK', 'ecobank');
define('PAYMENT_VISA', 'visa');
define('PAYMENT_GIMAC', 'gimac');
define('PAYMENT_WALLET', 'wallet');

// Commission par défaut
define('DEFAULT_COMMISSION_RATE', 15.0); // 15%

// Durée de session (en secondes)
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 7 jours
}

// Configuration email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@tchadok.td');
define('SMTP_PASSWORD', 'your_email_password');

// Réseaux sociaux officiels
define('FACEBOOK_URL', 'https://facebook.com/TchadokOfficial');
define('INSTAGRAM_URL', 'https://instagram.com/tchadok_music');
define('TWITTER_URL', 'https://twitter.com/TchadokMusic');
define('YOUTUBE_URL', 'https://youtube.com/c/TchadokOfficial');

// Configuration mobile money
define('AIRTEL_API_URL', 'https://openapiuat.airtel.africa/');
define('MOOV_API_URL', 'https://api.moov-africa.td/');
define('ECOBANK_API_URL', 'https://developer.ecobank.com/');

// Devises
define('DEFAULT_CURRENCY', 'XAF');
define('CURRENCY_SYMBOL', 'FCFA');

// Langues supportées
define('SUPPORTED_LANGUAGES', [
    'fr' => 'Français',
    'ar' => 'العربية',
    'en' => 'English'
]);

// Configuration sécurité
define('BCRYPT_COST', 12);
define('JWT_SECRET', 'tchadok_jwt_secret_key_2024');
define('JWT_EXPIRE_TIME', 3600); // 1 heure

// Messages flash
define('FLASH_SUCCESS', 'success');
define('FLASH_ERROR', 'error');
define('FLASH_INFO', 'info');
define('FLASH_WARNING', 'warning');

// Types de notifications
define('NOTIFICATION_NEW_FOLLOWER', 'new_follower');
define('NOTIFICATION_NEW_TRACK', 'new_track');
define('NOTIFICATION_PURCHASE', 'purchase');
define('NOTIFICATION_COMMENT', 'comment');
define('NOTIFICATION_LIKE', 'like');

// Statuts premium
define('PREMIUM_MONTHLY', 2000); // 2000 FCFA/mois
define('PREMIUM_ANNUAL', 20000); // 20000 FCFA/an (2 mois gratuits)

// Limites pour les utilisateurs gratuits
define('FREE_DOWNLOADS_PER_MONTH', 5);
define('FREE_PLAYLIST_LIMIT', 10);

// Configuration des charts
define('CHART_DAILY', 'daily');
define('CHART_WEEKLY', 'weekly');
define('CHART_MONTHLY', 'monthly');
define('CHART_YEARLY', 'yearly');

// Types de rapports
define('REPORT_INAPPROPRIATE', 'inappropriate');
define('REPORT_COPYRIGHT', 'copyright');
define('REPORT_SPAM', 'spam');
define('REPORT_FAKE', 'fake');

// Couleurs du thème tchadien
define('THEME_COLORS', [
    'primary' => '#0066CC',    // Bleu Tchadien
    'secondary' => '#FFD700',  // Jaune Solaire
    'danger' => '#CC3333',     // Rouge Terre
    'success' => '#228B22',    // Vert Savane
    'dark' => '#2C3E50',       // Gris Harmattan
    'light' => '#FFFFFF'       // Blanc Coton
]);

// Configuration des logs
define('LOG_PATH', 'logs/');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_DEBUG', 'DEBUG');

// Version de l'application
define('APP_VERSION', '1.0.0');
define('APP_BUILD', '2024.001');

// Configuration de cache
define('CACHE_ENABLED', true);
if (!defined('CACHE_LIFETIME')) {
    define('CACHE_LIFETIME', 3600); // 1 heure
}

// Fuseau horaire
date_default_timezone_set('Africa/Ndjamena');

// Configuration de développement/production
define('ENVIRONMENT', 'development'); // development, staging, production
define('DEBUG_MODE', ENVIRONMENT === 'development');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>