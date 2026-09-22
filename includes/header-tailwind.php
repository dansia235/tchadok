<?php
/**
 * Header Tailwind - Version de migration progressive
 */

$metaTitle = $pageTitle ?? SITE_NAME;
$metaDescription = $pageDescription ?? SITE_TAGLINE;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$siteBasePath = parse_url(SITE_URL, PHP_URL_PATH) ?: '';
if ($siteBasePath !== '' && strpos($requestUri, $siteBasePath) === 0) {
    $requestUri = substr($requestUri, strlen($siteBasePath));
    if ($requestUri === '') {
        $requestUri = '/';
    }
}
$metaCanonical = $pageCanonical ?? (rtrim(SITE_URL, '/') . '/' . ltrim($requestUri, '/'));
$metaOgImage = $pageImage ?? (SITE_URL . '/assets/images/og-image.jpg');
$metaTwitterImage = $pageTwitterImage ?? (SITE_URL . '/assets/images/twitter-image.jpg');
?>
<!doctype html>
<html lang="fr" class="<?php echo isset($htmlClass) ? $htmlClass : 'theme-light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrfMeta(); ?>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="musique tchadienne, Tchad, artistes tchadiens, streaming, téléchargement, Tchadok">
    <meta name="author" content="Tchadok Team">
    <link rel="canonical" href="<?php echo htmlspecialchars($metaCanonical, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($metaCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($metaOgImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($metaCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($metaTwitterImage, ENT_QUOTES, 'UTF-8'); ?>">

    <title><?php echo htmlspecialchars(isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%230066CC'/%3E%3Cpath d='M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z' fill='%23FFD700'/%3E%3C/svg%3E">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">

    <script>
        (function() {
            try {
                var storedTheme = localStorage.getItem('tchadok-theme');
                var root = document.documentElement;
                root.classList.remove('theme-light', 'dark');
                root.classList.add(storedTheme === 'dark' ? 'dark' : 'theme-light');
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: '#0B0F17',
                        surface: '#141A26',
                        'surface-2': '#1B2433',
                        text: '#E6EAF2',
                        muted: '#A4AEC2',
                        accent: '#2F6DE0',
                        'accent-2': '#FFC107',
                        border: '#222B3B'
                    },
                    fontFamily: {
                        sans: ['Manrope', 'system-ui', 'sans-serif'],
                        display: ['Sora', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        'elev-1': '0 8px 24px rgba(0,0,0,0.35)',
                        'elev-2': '0 16px 40px rgba(0,0,0,0.45)',
                        'elev-3': '0 24px 60px rgba(0,0,0,0.55)'
                    }
                }
            }
        };
    </script>

    <link href="<?php echo SITE_URL; ?>/assets/css/tailwind-base.css" rel="stylesheet">

    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <meta name="theme-color" content="#0B0F17">
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : 'bg-bg text-text antialiased'; ?>">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $navItems = [
        ['url' => '/', 'file' => 'index.php', 'label' => 'Accueil'],
        ['url' => '/decouvrir.php', 'file' => 'decouvrir.php', 'label' => 'Découvrir'],
        ['url' => '/artists.php', 'file' => 'artists.php', 'label' => 'Artistes'],
        ['url' => '/radio-live.php', 'file' => 'radio-live.php', 'label' => 'Radio Live'],
        ['url' => '/emissions.php', 'file' => 'emissions.php', 'label' => 'Émissions'],
        ['url' => '/blog.php', 'file' => 'blog.php', 'label' => 'Blog'],
    ];
    ?>
    <?php if (empty($hideTopNav)): ?>
        <nav class="site-nav fixed top-0 z-40 w-full border-b border-white/10 bg-bg/80 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a class="site-nav-brand flex items-center gap-3 text-lg font-display font-semibold text-text" href="<?php echo SITE_URL; ?>">
                    <svg class="site-logo h-10 w-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                        <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                    </svg>
                    <span class="site-logo-text">Tchadok</span>
                </a>
                <div class="hidden items-center gap-6 md:flex">
                    <?php foreach ($navItems as $nav): ?>
                        <?php $isActive = ($currentPage === $nav['file']); ?>
                        <a href="<?php echo SITE_URL . $nav['url']; ?>"
                           class="site-nav-link border-b-2 <?php echo $isActive ? 'is-active border-accent text-text' : 'border-transparent text-muted hover:text-text'; ?> pb-1 text-sm font-semibold transition">
                            <?php echo $nav['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="hidden items-center gap-3 md:flex">
                    <?php if (!isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="site-nav-button rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-text hover:bg-white/5">Connexion</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="site-nav-cta rounded-full bg-accent px-4 py-2 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">S'inscrire</a>
                    <?php else: ?>
                        <details class="relative">
                            <summary class="site-nav-user flex cursor-pointer list-none items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-text">
                                <i class="fas fa-user-circle"></i>
                                <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Utilisateur'); ?>
                                <?php if (isset($_SESSION['premium_status']) && $_SESSION['premium_status']): ?>
                                    <i class="fas fa-crown text-yellow-400" title="Membre Premium"></i>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down text-xs text-muted"></i>
                            </summary>
                            <div class="absolute right-0 mt-2 w-48 rounded-2xl border border-white/10 bg-surface shadow-elev-2">
                                <a class="site-nav-link block px-4 py-2 text-sm text-muted hover:text-text" href="<?php echo SITE_URL; ?>/user-dashboard.php">Mon Profil</a>
                                <a class="site-nav-link block px-4 py-2 text-sm text-muted hover:text-text" href="<?php echo SITE_URL; ?>/premium.php">Premium</a>
                                <div class="my-1 h-px bg-white/10"></div>
                                <?php /* SEC-09 : deconnexion en POST avec jeton. Un simple lien GET
                                    permettait a n'importe quel site de deconnecter un utilisateur
                                    par une balise <img src=".../logout.php">. */ ?>
                                <form method="POST" action="<?php echo SITE_URL; ?>/logout.php" class="m-0">
                                    <?php echo csrfField(); ?>
                                    <button type="submit" class="site-nav-link block w-full px-4 py-2 text-left text-sm text-muted hover:text-text">Déconnexion</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Basculer le thème" aria-pressed="false">
                        <i class="fas fa-sun theme-toggle-icon--sun"></i>
                        <i class="fas fa-moon theme-toggle-icon--moon"></i>
                    </button>
                </div>
                <button class="md:hidden rounded-xl border border-white/10 bg-white/5 p-2 text-text" type="button" data-nav-toggle aria-expanded="false" aria-label="Ouvrir le menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="site-nav-mobile hidden border-t border-white/10 px-4 pb-4 md:hidden" data-nav-menu>
                <div class="flex flex-col gap-3 pt-4">
                    <?php foreach ($navItems as $nav): ?>
                        <?php $isActive = ($currentPage === $nav['file']); ?>
                        <a href="<?php echo SITE_URL . $nav['url']; ?>" class="site-nav-link <?php echo $isActive ? 'is-active text-text' : 'text-muted hover:text-text'; ?> text-sm font-semibold">
                            <?php echo $nav['label']; ?>
                        </a>
                    <?php endforeach; ?>
                    <div class="mt-4 flex flex-col gap-2">
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/login.php" class="site-nav-button rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-text">Connexion</a>
                            <a href="<?php echo SITE_URL; ?>/register.php" class="site-nav-cta rounded-full bg-accent px-4 py-2 text-sm font-semibold text-white">S'inscrire</a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="site-nav-button rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-text">Mon Profil</a>
                            <form method="POST" action="<?php echo SITE_URL; ?>/logout.php" class="m-0">
                                <?php echo csrfField(); ?>
                                <button type="submit" class="site-nav-button w-full rounded-full bg-white/5 px-4 py-2 text-left text-sm font-semibold text-text">Déconnexion</button>
                            </form>
                        <?php endif; ?>
                        <button class="theme-toggle theme-toggle--row flex items-center justify-between gap-2 rounded-full px-4 py-2 text-sm font-semibold" type="button" data-theme-toggle aria-label="Basculer le thème" aria-pressed="false">
                            <span class="theme-toggle-label">Thème</span>
                            <span class="flex items-center gap-2">
                                <i class="fas fa-sun theme-toggle-icon--sun"></i>
                                <i class="fas fa-moon theme-toggle-icon--moon"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <?php
    $flashHtml = displayFlashMessages();
    if (!empty($flashHtml)):
    ?>
        <div class="mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
            <?php echo $flashHtml; ?>
        </div>
    <?php endif; ?>

    <script>
        try {
            window.TCHADOK = {
                SITE_URL: '<?php echo SITE_URL; ?>',
                USER_ID: <?php echo isLoggedIn() ? ($_SESSION['user_id'] ?? null) : 'null'; ?>,
                IS_LOGGED_IN: <?php echo isLoggedIn() ? 'true' : 'false'; ?>,
                IS_PREMIUM: <?php echo (isLoggedIn() && isset($_SESSION['premium_status']) && $_SESSION['premium_status']) ? 'true' : 'false'; ?>,
                CSRF_TOKEN: '<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : 'none'; ?>'
            };
        } catch (error) {
            window.TCHADOK = {
                SITE_URL: '<?php echo SITE_URL; ?>',
                USER_ID: null,
                IS_LOGGED_IN: false,
                IS_PREMIUM: false,
                CSRF_TOKEN: 'none'
            };
        }

        /*
         * SEC-09 : jeton CSRF ajoute automatiquement a toute requete fetch()
         * modifiante (POST, PUT, PATCH, DELETE) vers le site lui-meme.
         *
         * Le serveur refuse desormais toute requete modifiante sans jeton
         * (includes/csrf-guard.php). Ce correctif rend la protection active
         * par defaut cote JavaScript aussi : un appel ecrit demain sans penser
         * au jeton fonctionnera, au lieu d'echouer en 419.
         *
         * N'ajoute jamais le jeton vers une autre origine : il y fuirait.
         * Respecte un en-tete X-CSRF-Token deja fourni par l'appelant.
         */
        (function () {
            if (!window.fetch) { return; }
            var meta = document.querySelector('meta[name="csrf-token"]');
            var jeton = meta ? meta.getAttribute('content') : '';
            if (!jeton) { return; }
            var fetchOriginal = window.fetch;
            var methodesModifiantes = ['POST', 'PUT', 'PATCH', 'DELETE'];

            window.fetch = function (ressource, options) {
                options = options || {};
                var methode = String(options.method || (ressource && ressource.method) || 'GET').toUpperCase();
                if (methodesModifiantes.indexOf(methode) === -1) {
                    return fetchOriginal.call(this, ressource, options);
                }
                var url = typeof ressource === 'string' ? ressource : (ressource && ressource.url) || '';
                var memeOrigine;
                try { memeOrigine = new URL(url, window.location.href).origin === window.location.origin; }
                catch (e) { memeOrigine = false; }
                if (!memeOrigine) {
                    return fetchOriginal.call(this, ressource, options);
                }
                var entetes = new Headers(options.headers || (ressource && ressource.headers) || {});
                if (!entetes.has('X-CSRF-Token')) {
                    entetes.set('X-CSRF-Token', jeton);
                }
                // Copie : ne pas modifier l'objet d'options de l'appelant.
                var optionsJeton = Object.assign({}, options, { headers: entetes });
                return fetchOriginal.call(this, ressource, optionsJeton);
            };
        })();
    </script>
