<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tchadok - La plateforme musicale de référence du Tchad. Découvrez, écoutez et achetez la meilleure musique tchadienne.">
    <meta name="keywords" content="musique tchadienne, Tchad, artistes tchadiens, streaming, téléchargement, Tchadok">
    <meta name="author" content="Tchadok Team">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:title" content="<?php echo $pageTitle ?? SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo $pageDescription ?? SITE_TAGLINE; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo SITE_URL; ?>">
    <meta property="twitter:title" content="<?php echo $pageTitle ?? SITE_NAME; ?>">
    <meta property="twitter:description" content="<?php echo $pageDescription ?? SITE_TAGLINE; ?>">
    <meta property="twitter:image" content="<?php echo SITE_URL; ?>/assets/images/twitter-image.jpg">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%230066CC'/%3E%3Cpath d='M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z' fill='%23FFD700'/%3E%3C/svg%3E">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;900&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/main.css" rel="stylesheet">
    
    <!-- Progressive Web App -->
    
    <!-- jQuery - Chargé avant les scripts qui l'utilisent -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <meta name="theme-color" content="#0066CC">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
<link href="<?php echo SITE_URL; ?>/assets/css/player.css" rel="stylesheet">
</head>
<body>
    <!-- Loading Spinner -->
    <div id="pageLoader" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.95);z-index:9999;display:flex;align-items:center;justify-content:center;transition:opacity 0.3s ease;">
        <div style="text-align:center;">
            <svg width="50" height="50" viewBox="0 0 100 100" style="animation:spin 1s linear infinite;">
                <circle cx="50" cy="50" r="40" fill="none" stroke="#0066CC" stroke-width="6" stroke-dasharray="200" stroke-dashoffset="60" stroke-linecap="round"/>
            </svg>
        </div>
    </div>
<!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <svg class="logo-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                    <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                </svg>
                Tchadok
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
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
                <ul class="navbar-nav mx-auto">
                    <?php foreach ($navItems as $nav): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo ($currentPage === $nav['file']) ? ' active' : ''; ?>" href="<?php echo SITE_URL . $nav['url']; ?>"><?php echo $nav['label']; ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex gap-2">
                    <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-secondary-custom">Connexion</a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary-custom">S'inscrire</a>
                    <?php else: ?>
                    <div class="dropdown">
                        <button class="btn btn-primary-custom dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Utilisateur'); ?>
                            <?php if (isset($_SESSION['premium_status']) && $_SESSION['premium_status']): ?>
                                <i class="fas fa-crown text-warning ms-1" title="Membre Premium"></i>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user-dashboard.php">
                                <i class="fas fa-user me-2"></i> Mon Profil
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/premium.php">
                                <i class="fas fa-crown me-2"></i> Premium
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                            </a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Flash Messages -->
    <?php 
    $flashMessages = getFlashMessages();
    if (!empty($flashMessages)): 
    ?>
    <div class="container mt-3" style="padding-top: 80px;">
        <?php echo displayFlashMessages(); ?>
    </div>
    <?php endif; ?>

    <script>
        // Transition fluide au chargement
        function hideLoader() {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.style.opacity = '0';
                document.body.style.opacity = '1';
                setTimeout(() => { 
                    loader.style.display = 'none';
                }, 400);
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.4s ease-in-out';
            setTimeout(hideLoader, 100);
        });

        window.addEventListener('load', hideLoader);
        setTimeout(hideLoader, 1500);

        // Navbar scroll effect modernisé
        $(document).ready(function() {
            $(window).scroll(function() {
                if ($(this).scrollTop() > 30) {
                    $('.navbar').addClass('shadow-sm').css('padding', '0.6rem 0');
                } else {
                    $('.navbar').removeClass('shadow-sm').css('padding', '1rem 0');
                }
            });
        });
        
        // Configuration globale avec gestion d'erreurs
        try {
            window.TCHADOK = {
                SITE_URL: '<?php echo SITE_URL; ?>',
                USER_ID: <?php echo isLoggedIn() ? ($_SESSION['user_id'] ?? null) : 'null'; ?>,
                IS_LOGGED_IN: <?php echo isLoggedIn() ? 'true' : 'false'; ?>,
                IS_PREMIUM: <?php echo (isLoggedIn() && isset($_SESSION['premium_status']) && $_SESSION['premium_status']) ? 'true' : 'false'; ?>,
                CSRF_TOKEN: '<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : 'none'; ?>'
            };
        } catch (error) {
            console.warn('Erreur lors de la configuration TCHADOK:', error);
            window.TCHADOK = {
                SITE_URL: '<?php echo SITE_URL; ?>',
                USER_ID: null,
                IS_LOGGED_IN: false,
                IS_PREMIUM: false,
                CSRF_TOKEN: 'none'
            };
            hideLoader();
        }
    </script>

