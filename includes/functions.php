<?php
/**
 * Fonctions utilitaires pour Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';

// CFG-05 : controle de coherence de l'environnement.
// Place AVANT la couche base de donnees : si la configuration n'est pas
// celle attendue, mieux vaut s'arreter que se connecter a la mauvaise base.
require_once __DIR__ . '/environment-guard.php';

require_once __DIR__ . '/database.php';

/**
 * Nom du cookie de session (SEC-10).
 * "PHPSESSID", nom par defaut, annonce la technologie du serveur.
 */
const TCHADOK_SESSION_COOKIE = 'TCHADOKSESSID';

/** Durees d'inactivite, en secondes (SEC-10). */
function dureeSessionOrdinaire(): int
{
    return max(300, class_exists('EnvLoader') ? EnvLoader::int('SESSION_LIFETIME', 1800) : 1800);
}

function dureeSessionAdministration(): int
{
    return max(60, class_exists('EnvLoader') ? EnvLoader::int('ADMIN_SESSION_LIFETIME', 900) : 900);
}

/**
 * Démarre une session sécurisée
 *
 * SEC-10.
 *
 *  - cookie_secure suit SESSION_SECURE (avance en SEC-06) : force a 1, il
 *    rendait la connexion impossible en HTTP avec Firefox, Safari ou curl.
 *    Sans valeur, il reste a true : en cas d'oubli, on echoue du cote sur.
 *  - use_strict_mode : un identifiant de session qui n'a pas ete emis par
 *    le serveur est refuse, et remplace par un nouveau. Sans cela, un
 *    attaquant pouvait imposer a sa victime un identifiant de son choix,
 *    puis s'en servir une fois la victime connectee (fixation de session).
 *  - cookie renomme, sans duree (supprime a la fermeture du navigateur).
 *  - gc_maxlifetime aligne sur la plus longue duree d'inactivite : sinon le
 *    ramasse-miettes de PHP pouvait supprimer une session encore valide.
 *
 * La regeneration de l'identifiant a la connexion est faite dans
 * Auth::startUserSession() ; la validation de chaque requete dans
 * validerSessionCourante().
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = class_exists('EnvLoader') ? EnvLoader::bool('SESSION_SECURE', true) : true;
        $samesite = class_exists('EnvLoader') ? (string) EnvLoader::get('SESSION_SAMESITE', 'Lax') : 'Lax';
        if (!in_array($samesite, ['Lax', 'Strict', 'None'], true)) {
            $samesite = 'Lax';
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) max(dureeSessionOrdinaire(), dureeSessionAdministration()));

        session_name(TCHADOK_SESSION_COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $samesite,
        ]);
        session_start();
    }
}

/**
 * Valide la session d'un utilisateur connecte, a chaque requete (SEC-10).
 *
 * 1. Inactivite : au-dela de ADMIN_SESSION_LIFETIME pour un administrateur,
 *    de SESSION_LIFETIME pour les autres, la session est fermee.
 * 2. Registre : la table user_sessions fait foi. Une session qui n'y figure
 *    plus -- mot de passe change depuis un autre appareil, revocation par
 *    l'utilisateur ou un administrateur -- est fermee.
 *
 * Choix de conception : le plan prevoyait une colonne password_changed_at.
 * Le registre user_sessions existant rend le meme service sans modification
 * de schema, et permet en plus la revocation appareil par appareil (SEC-11).
 */
function validerSessionCourante(): void
{
    if (PHP_SAPI === 'cli' || !isLoggedIn()) {
        return;
    }

    $maintenant = time();
    $estAdmin = ($_SESSION['user_type'] ?? '') === USER_TYPE_ADMIN;
    $duree = $estAdmin ? dureeSessionAdministration() : dureeSessionOrdinaire();
    $derniere = (int) ($_SESSION['derniere_activite'] ?? $maintenant);

    if ($maintenant - $derniere > $duree) {
        terminerSessionCourante('inactivite');
        return;
    }

    // Registre : uniquement si l'inscription a reussi a la connexion. Sinon
    // (table absente, erreur ponctuelle), la session fonctionne sans cette
    // capacite de revocation plutot que de rendre la connexion impossible.
    if (!empty($_SESSION['session_enregistree'])) {
        $db = TchadokDatabase::getInstance()->getConnection();
        if ($db) {
            try {
                $stmt = $db->prepare('SELECT user_id FROM user_sessions WHERE id = ? LIMIT 1');
                $stmt->execute([session_id()]);
                $proprietaire = $stmt->fetchColumn();

                if ($proprietaire === false || (int) $proprietaire !== (int) $_SESSION['user_id']) {
                    terminerSessionCourante('revoquee');
                    return;
                }

                // Mise a jour de l'activite en base au plus une fois par minute :
                // une ecriture a chaque requete serait inutilement couteuse.
                if ($maintenant - (int) ($_SESSION['registre_maj'] ?? 0) >= 60) {
                    $db->prepare('UPDATE user_sessions SET last_activity = NOW() WHERE id = ?')
                       ->execute([session_id()]);
                    $_SESSION['registre_maj'] = $maintenant;
                }
            } catch (Throwable $e) {
                // Base momentanement indisponible : on ne deconnecte pas tout le
                // monde pour autant. Le reste du site echouera de toute facon.
                error_log('[Tchadok][session] verification du registre impossible : ' . $e->getMessage());
            }
        }
    }

    $_SESSION['derniere_activite'] = $maintenant;
}

/**
 * Ferme la session courante sans detruire le mecanisme de session : les
 * donnees sont effacees, un nouvel identifiant est emis, et le motif est
 * conserve pour que la page de connexion l'explique a l'utilisateur.
 */
function terminerSessionCourante(string $motif): void
{
    $ancienId = session_id();

    $db = TchadokDatabase::getInstance()->getConnection();
    if ($db && $ancienId !== '') {
        try {
            $db->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([$ancienId]);
        } catch (Throwable $e) {
            error_log('[Tchadok][session] ' . $e->getMessage());
        }
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['fin_session'] = $motif;
    error_log(sprintf('[Tchadok][session] session fermee (%s)', $motif));
}

/**
 * Ferme les sessions d'un utilisateur ailleurs que sur l'appareil courant,
 * et invalide sa connexion automatique. A appeler apres tout changement de
 * mot de passe (SEC-10).
 *
 * @param bool $garderCourante false quand l'appelant n'est pas l'utilisateur
 *                             lui-meme (reinitialisation par lien, admin)
 */
function revoquerSessionsUtilisateur(int $userId, bool $garderCourante = true): int
{
    $db = TchadokDatabase::getInstance()->getConnection();
    if (!$db) {
        return 0;
    }

    try {
        if ($garderCourante && session_id() !== '') {
            $stmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = ? AND id <> ?');
            $stmt->execute([$userId, session_id()]);
        } else {
            $stmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = ?');
            $stmt->execute([$userId]);
        }
        $nombre = $stmt->rowCount();

        // Un cookie "se souvenir de moi" vole ne doit pas survivre au
        // changement de mot de passe. Tous les appareils sont concernes, y
        // compris celui qui change le mot de passe : sa session reste ouverte,
        // seule la reconnexion sans mot de passe est retiree (SEC-11).
        $jetons = RememberMe::revoquerTout($userId);

        error_log(sprintf(
            '[Tchadok][session] %d session(s) et %d jeton(s) de connexion automatique revoque(s) pour l\'utilisateur %d',
            $nombre,
            $jetons,
            $userId
        ));
        return $nombre;
    } catch (Throwable $e) {
        error_log('[Tchadok][session] revocation impossible : ' . $e->getMessage());
        return 0;
    }
}

/**
 * Sessions ouvertes d'un utilisateur, la plus recemment active en tete
 * (ecran « Appareils connectes », SEC-11).
 *
 * L'identifiant de session n'est jamais renvoye : c'est un secret, et il
 * n'aurait rien a faire dans une page. Chaque ligne porte a la place une
 * empreinte, suffisante pour designer la session a revoquer.
 */
function sessionsUtilisateur(int $userId): array
{
    $db = TchadokDatabase::getInstance()->getConnection();
    if (!$db) {
        return [];
    }

    $limite = max(dureeSessionOrdinaire(), dureeSessionAdministration());

    try {
        $stmt = $db->prepare(
            'SELECT SHA2(id, 256) AS empreinte, id = ? AS courante, ip_address, user_agent,
                    created_at, last_activity
             FROM user_sessions
             WHERE user_id = ? AND last_activity > (NOW() - INTERVAL ? SECOND)
             ORDER BY last_activity DESC'
        );
        $stmt->execute([session_id(), $userId, $limite]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[Tchadok][session] lecture du registre impossible : ' . $e->getMessage());
        return [];
    }

    foreach ($lignes as &$ligne) {
        $ligne['courante'] = (bool) $ligne['courante'];
        $ligne['appareil'] = etiquetteAppareil((string) ($ligne['user_agent'] ?? ''));
    }

    return $lignes;
}

/**
 * Ferme une session designee par l'empreinte de son identifiant, a condition
 * qu'elle appartienne bien a l'utilisateur indique (SEC-11).
 */
function revoquerSessionParEmpreinte(string $empreinte, int $userId): bool
{
    $db = TchadokDatabase::getInstance()->getConnection();
    if (!$db || !ctype_xdigit($empreinte) || strlen($empreinte) !== 64) {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = ? AND SHA2(id, 256) = ?');
        $stmt->execute([$userId, $empreinte]);
        $ferme = $stmt->rowCount() > 0;

        // Fermer la session ne suffit pas : sans cela, l'appareil ecarte se
        // reconnaissait tout seul a la requete suivante grace a son cookie.
        RememberMe::revoquerParSession($empreinte, $userId);

        return $ferme;
    } catch (Throwable $e) {
        error_log('[Tchadok][session] revocation impossible : ' . $e->getMessage());
        return false;
    }
}

/**
 * Nom lisible d'un appareil a partir de sa signature de navigateur, pour
 * l'ecran « Appareils connectes ». Approximatif par nature : la signature est
 * declarative, elle sert a se reconnaitre, pas a authentifier.
 */
function etiquetteAppareil(string $userAgent): string
{
    if (trim($userAgent) === '') {
        return 'Appareil inconnu';
    }

    $navigateur = 'Navigateur inconnu';
    foreach ([
        'Edg'     => 'Edge',
        'OPR'     => 'Opera',
        'Chrome'  => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari'  => 'Safari',
        'curl'    => 'curl',
    ] as $motif => $nom) {
        if (stripos($userAgent, $motif) !== false) {
            $navigateur = $nom;
            break;
        }
    }

    $systeme = '';
    foreach ([
        'Android'    => 'Android',
        'iPhone'     => 'iPhone',
        'iPad'       => 'iPad',
        'Windows'    => 'Windows',
        'Macintosh'  => 'macOS',
        'Linux'      => 'Linux',
    ] as $motif => $nom) {
        if (stripos($userAgent, $motif) !== false) {
            $systeme = $nom;
            break;
        }
    }

    return $systeme !== '' ? "$navigateur sur $systeme" : $navigateur;
}

/**
 * Emet un nouvel identifiant pour la session courante en conservant ses
 * donnees, et reporte le changement dans le registre (SEC-10).
 *
 * A utiliser apres un changement de mot de passe ou de privilege : si
 * l'ancien identifiant avait fuite, il ne sert plus a rien. Sans la mise a
 * jour du registre, validerSessionCourante() fermerait la session a la
 * requete suivante, l'identifiant n'y figurant plus.
 */
function renouvelerIdentifiantSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $ancienId = session_id();
    session_regenerate_id(true);
    $nouvelId = session_id();

    if (!empty($_SESSION['session_enregistree'])) {
        $db = TchadokDatabase::getInstance()->getConnection();
        if ($db) {
            try {
                $db->prepare('UPDATE user_sessions SET id = ?, last_activity = NOW() WHERE id = ?')
                   ->execute([$nouvelId, $ancienId]);
                $_SESSION['session_id'] = $nouvelId;

                // SEC-11 : le jeton de connexion automatique de cet appareil
                // suit le nouvel identifiant, pour rester revocable par
                // session depuis l'ecran « Appareils connectes ».
                $db->prepare('UPDATE remember_tokens SET session_id = ? WHERE session_id = ?')
                   ->execute([$nouvelId, $ancienId]);
            } catch (Throwable $e) {
                error_log('[Tchadok][session] ' . $e->getMessage());
            }
        }
    }
}

/**
 * Message a afficher sur la page de connexion apres une fermeture de
 * session, puis efface (lecture unique). Null si rien a signaler.
 */
function messageFinSession(): ?string
{
    $motif = $_SESSION['fin_session'] ?? null;
    unset($_SESSION['fin_session']);

    return match ($motif) {
        'inactivite' => 'Votre session a ete fermee apres une periode d\'inactivite. Reconnectez-vous pour continuer.',
        'revoquee'   => 'Votre session a ete fermee : le mot de passe du compte a ete modifie, ou la session a ete revoquee.',
        default      => null,
    };
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
    
    // SEC-06 : uniqid() derive de l'horloge (microsecondes) : les noms
    // etaient previsibles et enumerables. 128 bits aleatoires a la place.
    // La validation du contenu reel du fichier est traitee en SEC-17.
    $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExt;
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
 * Adresse du client (SEC-13).
 *
 * Deux implementations coexistaient, toutes deux fondees sur des en-tetes que
 * le client ecrit lui-meme (`Client-IP`, `X-Forwarded-For`). Il suffisait donc
 * d'ajouter un en-tete pour choisir l'adresse inscrite dans le registre des
 * sessions, dans les ecoutes -- donc dans la geographie du barometre -- et
 * pour echapper a toute limitation par adresse.
 *
 * Par defaut, seule REMOTE_ADDR fait foi : c'est la seule valeur etablie par
 * la connexion elle-meme. `X-Forwarded-For` n'est lu que si la requete arrive
 * d'un proxy declare dans TRUSTED_PROXIES ; la chaine est alors parcourue de
 * droite a gauche, en sautant les proxys connus, jusqu'a la premiere adresse
 * qui ne l'est pas : celle du client. Tout ce qui se trouve a gauche a pu
 * etre ecrit par le client et n'est pas exploitable.
 *
 * `HTTP_CLIENT_IP` n'est plus lu du tout : cet en-tete n'a aucun emetteur
 * legitime dans une chaine de proxys, il ne sert qu'a la falsification.
 *
 * @param array|null $proxysDeConfiance liste explicite (adresses ou CIDR) ;
 *                                      par defaut celle du fichier d'environnement
 */
function clientIp(?array $proxysDeConfiance = null): string
{
    $distante = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($distante === '' || filter_var($distante, FILTER_VALIDATE_IP) === false) {
        return '0.0.0.0';
    }

    $proxys = $proxysDeConfiance ?? proxysDeConfiance();
    if (!$proxys || !adresseCorrespond($distante, $proxys)) {
        return $distante;
    }

    $chaine = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($chaine === '') {
        return $distante;
    }

    foreach (array_reverse(array_map('trim', explode(',', $chaine))) as $entree) {
        if ($entree === '' || filter_var($entree, FILTER_VALIDATE_IP) === false) {
            // Entree illisible : la chaine n'est plus interpretable, on s'en
            // tient a l'adresse etablie par la connexion.
            return $distante;
        }
        if (adresseCorrespond($entree, $proxys)) {
            continue;
        }

        return $entree;
    }

    // Toute la chaine est faite de proxys declares : aucune adresse cliente.
    return $distante;
}

/**
 * Proxys declares dans TRUSTED_PROXIES : adresses ou plages CIDR, separees
 * par des virgules. Vide par defaut -- le cas d'un serveur expose directement.
 */
function proxysDeConfiance(): array
{
    if (!class_exists('EnvLoader')) {
        return [];
    }

    $brut = trim((string) EnvLoader::get('TRUSTED_PROXIES', ''));
    if ($brut === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $brut)), static fn ($v) => $v !== ''));
}

/**
 * L'adresse correspond-elle a l'une des regles (adresse exacte ou CIDR) ?
 */
function adresseCorrespond(string $ip, array $regles): bool
{
    foreach ($regles as $regle) {
        if (adresseDansPlage($ip, (string) $regle)) {
            return true;
        }
    }

    return false;
}

/**
 * Comparaison binaire, valable en IPv4 comme en IPv6 : inet_pton ramene les
 * deux familles a une suite d'octets, et le prefixe se compare bit a bit.
 */
function adresseDansPlage(string $ip, string $regle): bool
{
    $binIp = @inet_pton($ip);
    if ($binIp === false) {
        return false;
    }

    if (!str_contains($regle, '/')) {
        $binRegle = @inet_pton($regle);

        return $binRegle !== false && $binIp === $binRegle;
    }

    [$reseau, $prefixe] = explode('/', $regle, 2);
    $binReseau = @inet_pton(trim($reseau));
    if ($binReseau === false || strlen($binIp) !== strlen($binReseau) || !ctype_digit(trim($prefixe))) {
        return false;
    }

    $bits = (int) trim($prefixe);
    $maximum = strlen($binIp) * 8;
    if ($bits < 0 || $bits > $maximum) {
        return false;
    }

    $octetsPleins = intdiv($bits, 8);
    if ($octetsPleins > 0 && substr($binIp, 0, $octetsPleins) !== substr($binReseau, 0, $octetsPleins)) {
        return false;
    }

    $bitsRestants = $bits % 8;
    if ($bitsRestants === 0) {
        return true;
    }

    $masque = ~((1 << (8 - $bitsRestants)) - 1) & 0xFF;

    return (ord($binIp[$octetsPleins]) & $masque) === (ord($binReseau[$octetsPleins]) & $masque);
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
 *
 * SEC-09 : la verification est desormais faite pour toute requete modifiante
 * par includes/csrf-guard.php, avant l'execution du point d'entree. Cette
 * fonction reste disponible pour les pages qui la verifiaient deja.
 * Elle acceptait un jeton null, que hash_equals() refuse en PHP 8 par une
 * TypeError : l'absence de jeton produisait une erreur fatale au lieu d'un
 * refus propre.
 */
function verifyCSRFToken($token) {
    $attendu = $_SESSION['csrf_token'] ?? '';
    return is_string($attendu) && $attendu !== ''
        && is_string($token) && $token !== ''
        && hash_equals($attendu, $token);
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
 * Balise meta exposant le jeton aux appels JavaScript (SEC-09).
 * Lue par le correctif de fetch() de includes/header-tailwind.php, qui
 * ajoute l'en-tete X-CSRF-Token a toute requete modifiante vers le site.
 */
function csrfMeta() {
    return '<meta name="csrf-token" content="' . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
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


// SEC-11 : connexion automatique (cookie selecteur + verificateur). Chargee
// avant le demarrage de la session : revoquerSessionsUtilisateur() s'appuie
// dessus, et includes/auth.php l'utilise des son chargement.
require_once __DIR__ . '/remember-me.php';

// SEC-12 : limitation de debit et verrouillage des tentatives de connexion.
require_once __DIR__ . '/rate-limit.php';

// Initialisation de la session
startSecureSession();

// SEC-10 : inactivite et registre des sessions. AVANT la garde CSRF : une
// session fermee pour inactivite perd son jeton, et une requete envoyee
// depuis un formulaire reste ouvert trop longtemps est alors refusee avec le
// message "session expiree", qui est le bon.
validerSessionCourante();

// SEC-09 : verification CSRF de toute requete modifiante, AVANT l'execution
// du point d'entree. Placee apres le demarrage de la session, qui porte le
// jeton attendu. Voir includes/csrf-guard.php pour le mecanisme d'exemption.
require_once __DIR__ . '/csrf-guard.php';
CsrfGuard::verifier();
?>