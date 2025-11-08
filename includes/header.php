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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;900&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/main.css" rel="stylesheet">
    
    <!-- Progressive Web App -->
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <meta name="theme-color" content="#0066CC">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        /* Navbar styles from tchadok-homepage.html */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            color: #0066CC !important;
            display: flex;
            align-items: center;
        }
        
        .logo-svg {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        
        .navbar-nav .nav-link {
            color: #2C3E50 !important;
            font-weight: 600;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: #FFD700;
            transition: width 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover::after {
            width: 80%;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #0066CC, #0052a3);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: transparent;
            border: 2px solid #FFD700;
            color: #2C3E50;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background: #FFD700;
            color: #2C3E50;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Loading Spinner avec auto-masquage rapide -->
    <div id="pageLoader" class="position-fixed w-100 h-100 d-flex align-items-center justify-content-center" 
         style="background: rgba(255,255,255,0.9); z-index: 9999; animation: autoHide 1s forwards;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Chargement...</span>
        </div>
    </div>
    
    <style>
        @keyframes autoHide {
            0%, 50% { opacity: 1; }
            100% { opacity: 0; display: none !important; }
        }
        
        /* Force la disparition après 1 seconde */
        #pageLoader {
            animation: autoHide 1s forwards;
        }
    </style>

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
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/decouvrir.php">Découvrir</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/artists.php">Artistes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/radio-live.php">Radio Live</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/emissions.php">Émissions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/blog.php">Blog</a>
                    </li>
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
        // Cache le loader avec plusieurs méthodes pour s'assurer qu'il disparaît
        function hideLoader() {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.style.display = 'none';
            }
        }
        
        // Cache immédiatement après que le DOM soit prêt
        document.addEventListener('DOMContentLoaded', function() {
            // Cache le loader après 500ms pour éviter qu'il bloque les clics
            setTimeout(hideLoader, 500);
        });
        
        // Cache aussi quand tout est chargé
        window.addEventListener('load', hideLoader);
        
        // Cache aussi si il y a une erreur
        window.addEventListener('error', function() {
            setTimeout(hideLoader, 1000);
        });
        
        // Force le masquage après 1.5 secondes peu importe quoi
        setTimeout(() => {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.style.display = 'none';
                loader.style.visibility = 'hidden';
                loader.style.opacity = '0';
                console.log('🚫 Loader forcément masqué');
            }
        }, 1500);
        
        // Navbar background on scroll (from tchadok-homepage.html)
        $(document).ready(function() {
            $(window).scroll(function() {
                if ($(this).scrollTop() > 50) {
                    $('.navbar').css('background', 'rgba(255, 255, 255, 0.98)');
                } else {
                    $('.navbar').css('background', 'rgba(255, 255, 255, 0.95)');
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