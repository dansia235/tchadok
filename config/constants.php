<?php
/**
 * Constantes de l'application Tchadok
 * @author Tchadok Team
 * @version 1.0
 */

// Configuration générale du site
define('SITE_NAME', 'Tchadok');
define('SITE_TAGLINE', 'La musique tchadienne à portée de clic');

// SITE_URL : Utiliser .env si disponible, sinon valeur par défaut
if (!defined('SITE_URL')) {
    if (function_exists('env')) {
        define('SITE_URL', env('SITE_URL', env('APP_URL', 'http://localhost/tchadok')));
    } else {
        define('SITE_URL', 'http://localhost/tchadok');
    }
}

define('SITE_EMAIL', 'info@tchadok.td');
define('SITE_PHONE', '+235 XX XX XX XX');

// Chemins des dossiers
//
// SEC-06 : l'audio et les documents ne sont plus deposes sous uploads/,
// servi directement par Apache, mais sous storage/uploads/, que les
// .htaccess bloquent. L'audio n'est accessible que par media.php, apres
// verification de la signature de l'URL et du droit d'acces.
//
// Les images (pochettes, avatars) restent publiques sous uploads/images :
// les servir via PHP a chaque affichage serait couteux, pour un contenu
// qui n'a pas a etre protege.
//
// Les titres deposes avant ce changement restent dans uploads/audio,
// desormais interdit d'acces direct (uploads/audio/.htaccess) et toujours
// servis par media.php.
define('UPLOADS_PATH', 'uploads/');
define('PRIVATE_UPLOADS_PATH', 'storage/uploads/');
define('AUDIO_PATH', PRIVATE_UPLOADS_PATH . 'audio/');
define('IMAGES_PATH', UPLOADS_PATH . 'images/');
define('DOCUMENTS_PATH', PRIVATE_UPLOADS_PATH . 'documents/');

// Episodes de podcast : contenu gratuit et public par nature, sans rapport
// avec le catalogue payant. Ils restent donc servis directement, depuis un
// emplacement dedie ou l'execution de scripts est coupee (uploads/.htaccess).
// Sans cette constante, ils auraient herite par defaut de AUDIO_PATH et du
// controle d'acces des titres payants.
define('PODCAST_AUDIO_PATH', UPLOADS_PATH . 'podcasts/');

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

// DATA-04 : la commission ne s'ecrit plus ici. Elle varie selon le produit et
// se change sans deploiement -- voir la table `pricing_rules` et
// `Tarifs::tauxCommission()`. Une valeur recopiee dans le code finit par
// contredire celle qui est appliquee.

// SEC-17 : bornes de forme, pas de prix. Elles interdisent l'absurde -- un
// montant negatif, une saisie a six chiffres, un prix hors grille de 50 FCFA --
// quel que soit le produit. Le plancher et le plafond commerciaux, eux,
// viennent de `pricing_rules` (DATA-04).
define('PRIX_MINIMUM', 0);
define('PRIX_MAXIMUM', 500000);  // FCFA : au-dela, c'est une erreur de saisie
define('PRIX_PAS', 50);          // grille tarifaire, alignee sur les formulaires

// Durée de session (en secondes)
if (!defined('SESSION_LIFETIME')) {
    if (function_exists('env')) {
        define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 7 * 24 * 60 * 60));
    } else {
        define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 7 jours
    }
}

// Configuration email
// SEC-03 : les identifiants SMTP ne sont plus ecrits en dur ici.
// Ils sont lus depuis le fichier d'environnement (MAIL_HOST, MAIL_PORT,
// MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION) par la couche d'envoi.
// Ces constantes n'etaient utilisees par aucun fichier du projet :
// sendEmail() s'appuie sur mail(), ce que QA-02 corrigera.

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
// SEC-03 : JWT_SECRET etait ecrit en dur ('tchadok_jwt_secret_key_2024')
// et versionne dans Git, donc public. La constante n'etait utilisee par
// aucun fichier du projet : elle est supprimee plutot que deplacee.
// Si une authentification par jeton est introduite plus tard, la cle
// devra etre lue par env('JWT_SECRET') sans valeur de repli.

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

// DATA-04 : les prix Premium ne sont plus ecrits ici. Ces deux constantes
// disaient 2 000 / 20 000 FCFA quand les pages publiques affichaient
// 2 500 / 25 000 -- et personne ne les lisait. Le tarif vit dans
// `pricing_rules` ; il se lit par `Tarifs::abonnement('monthly'|'yearly')`.

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
    if (function_exists('env')) {
        define('CACHE_LIFETIME', (int)env('CACHE_LIFETIME', 3600));
    } else {
        define('CACHE_LIFETIME', 3600); // 1 heure
    }
}

// Fuseau horaire : gere par config/env.php a partir de APP_TIMEZONE.
// L'appel en dur qui figurait ici ecrasait le reglage de l'environnement.

// ----------------------------------------------------------------------
// Environnement (CFG-01)
//
// Ces deux constantes etaient ECRITES EN DUR :
//     define('ENVIRONMENT', 'development');
//     define('DEBUG_MODE', ENVIRONMENT === 'development');
//
// Quelle que soit la valeur de APP_ENV dans le fichier d'environnement,
// DEBUG_MODE valait donc true et display_errors restait actif -- y compris
// en production. Elles derivent desormais du fichier reellement charge.
//
// error_reporting et display_errors sont regles par config/env.php :
// ne pas les repositionner ici, sous peine de reintroduire le probleme.
// ----------------------------------------------------------------------
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', class_exists('EnvLoader') ? EnvLoader::environment() : 'production');
}

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', defined('APP_DEBUG') ? (bool) APP_DEBUG : false);
}
?>