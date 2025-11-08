<?php
/**
 * Dashboard Admin Innovant - Tchadok Platform
 * Interface moderne avec fonctionnalités avancées
 */

session_start();
require_once '../includes/database.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Traitement des actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate_data') {
        try {
            $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../api/data-generator.php?action=all';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception("Erreur cURL: " . $curlError);
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP Error $httpCode");
            }
            
            $result = json_decode($response, true);
            
            if ($result && isset($result['success']) && $result['success']) {
                $success_message = "Données générées avec succès: " . ($result['message'] ?? 'Génération terminée');
                if (isset($result['data'])) {
                    $details = [];
                    foreach ($result['data'] as $key => $value) {
                        $details[] = "$key: $value";
                    }
                    $success_message .= " (" . implode(', ', $details) . ")";
                }
            } else {
                $error_message = "Erreur lors de la génération: " . ($result['error'] ?? 'Erreur inconnue');
            }
        } catch (Exception $e) {
            $error_message = "Erreur lors de la génération: " . $e->getMessage();
        }
        
    } elseif ($action === 'clear_data') {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->beginTransaction();
            
            $pdo->exec("DELETE FROM playlist_tracks");
            $pdo->exec("DELETE FROM playlists WHERE user_id > 1");
            $pdo->exec("DELETE FROM transactions WHERE user_id > 1");
            $pdo->exec("DELETE FROM tracks");
            $pdo->exec("DELETE FROM albums");
            $pdo->exec("DELETE FROM artists WHERE user_id > 1");
            $pdo->exec("DELETE FROM users WHERE id > 1");
            
            $pdo->exec("ALTER TABLE playlist_tracks AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE playlists AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE transactions AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE tracks AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE albums AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE artists AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 2");
            
            $pdo->commit();
            $success_message = "Toutes les données ont été supprimées avec succès (compte admin préservé)";
            
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollback();
            $error_message = "Erreur lors de la suppression: " . $e->getMessage();
        }
    }
    
    header('Location: dashboard-new.php' . (isset($success_message) ? '?success=' . urlencode($success_message) : (isset($error_message) ? '?error=' . urlencode($error_message) : '')));
    exit;
}

// Récupération des statistiques
$db = TchadokDatabase::getInstance();
$stats = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch (Exception $e) {
    $pdo = null;
    $dbConnected = false;
}

if ($dbConnected) {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['artists'] = $pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn();
    $stats['albums'] = $pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
    $stats['tracks'] = $pdo->query("SELECT COUNT(*) FROM tracks")->fetchColumn();
    $stats['playlists'] = $pdo->query("SELECT COUNT(*) FROM playlists")->fetchColumn();
    $stats['transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetchColumn();
    $stats['revenue'] = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'completed'")->fetchColumn() ?: 0;
    
    // Données pour les graphiques
    $recentUsers = $pdo->query("SELECT username, first_name, last_name, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recentTransactions = $pdo->query("SELECT u.username, t.amount, t.description, t.created_at FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'completed' ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
    $topTracks = $pdo->query("SELECT t.title, ar.stage_name, t.total_streams FROM tracks t JOIN artists ar ON t.artist_id = ar.id ORDER BY t.total_streams DESC LIMIT 5")->fetchAll();
    $topArtists = $pdo->query("SELECT stage_name, total_streams, verified FROM artists ORDER BY total_streams DESC LIMIT 10")->fetchAll();
    
    // Données pour graphiques
    $monthlyStats = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            'users' as type
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll();
}

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
$currentTab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Tchadok Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFD700;
            --secondary-color: #2C3E50;
            --accent-color: #0066CC;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }
        
        .navbar-admin {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            min-height: calc(100vh - 76px);
            border-radius: 0 20px 20px 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
            padding: 12px 20px;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #ffed4e);
            color: var(--secondary-color);
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            min-height: calc(100vh - 100px);
            margin: 12px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .chart-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 100%;
            border: none;
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, var(--accent-color), #0052a3);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }
        
        .btn-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 102, 204, 0.4);
            color: white;
        }
        
        .btn-success-admin {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .btn-danger-admin {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .btn-warning-admin {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            border: none;
            color: var(--dark-color);
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-warning-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
            color: var(--dark-color);
        }
        
        .activity-item {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-color);
        }
        
        .activity-item:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(5px);
        }
        
        .quick-action-card {
            background: linear-gradient(135deg, var(--primary-color), #ffed4e);
            border-radius: 15px;
            padding: 1.5rem;
            color: var(--secondary-color);
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
            cursor: pointer;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px) rotate(1deg);
            box-shadow: 0 15px 35px rgba(255, 215, 0, 0.4);
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }
        
        .table-custom {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .table-custom thead {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
        }
        
        .table-custom tbody tr:hover {
            background: rgba(0, 102, 204, 0.1);
            transform: scale(1.01);
        }
        
        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .nav-tabs .nav-link {
            border-radius: 12px 12px 0 0;
            border: none;
            color: var(--secondary-color);
            font-weight: 600;
            margin-right: 8px;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #ffed4e);
            color: var(--secondary-color);
        }
        
        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
        
        /* Fix pour les dropdowns */
        .navbar {
            z-index: 1050;
            position: relative;
        }
        
        .navbar-dark {
            background: linear-gradient(135deg, #2C3E50, #0066CC) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }
        
        .dropdown-menu {
            z-index: 1060 !important;
        }
        
        .navbar .dropdown-menu {
            z-index: 1070 !important;
        }
        
        /* Sidebar z-index fix */
        .sidebar {
            z-index: 1040;
        }
        
        /* Main content z-index */
        .main-content {
            z-index: 1030;
            position: relative;
        }
        
        /* Modal z-index fix */
        .modal {
            z-index: 1080 !important;
        }
        
        .modal-backdrop {
            z-index: 1075 !important;
        }
        
        /* Notification alerts z-index */
        .alert {
            z-index: 1055;
            position: relative;
        }
        
        /* Ensure dropdowns in cards work properly */
        .chart-card {
            position: relative;
            z-index: 1;
        }
        
        .chart-card .dropdown-menu {
            z-index: 1065 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <svg width="40" height="40" class="me-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                    <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                </svg>
                <div>
                    <h4 class="mb-0">Tchadok Admin</h4>
                    <small class="text-white-50">Dashboard de Gestion</small>
                </div>
            </a>
            
            <div class="navbar-nav ms-auto d-flex flex-row">
                <div class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus me-2"></i>Nouvel utilisateur</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-music me-2"></i>Nouveau track</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-dollar-sign me-2"></i>Nouveau paiement</a></li>
                    </ul>
                </div>
                
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23FFD700'/%3E%3Ctext x='50' y='60' text-anchor='middle' font-size='40' fill='%232C3E50'%3EA%3C/text%3E%3C/svg%3E" 
                             width="35" height="35" class="rounded-circle me-2" alt="Admin">
                        <span>Admin</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <div class="sidebar p-3">
                    <div class="nav flex-column nav-pills">
                        <a class="nav-link <?php echo $currentTab === 'overview' ? 'active' : ''; ?>" href="?tab=overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Vue d'ensemble
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'users' ? 'active' : ''; ?>" href="?tab=users">
                            <i class="fas fa-users me-2"></i>Utilisateurs
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'artists' ? 'active' : ''; ?>" href="?tab=artists">
                            <i class="fas fa-microphone me-2"></i>Artistes
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'music' ? 'active' : ''; ?>" href="?tab=music">
                            <i class="fas fa-music me-2"></i>Musique
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'analytics' ? 'active' : ''; ?>" href="?tab=analytics">
                            <i class="fas fa-chart-line me-2"></i>Analyses
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'payments' ? 'active' : ''; ?>" href="?tab=payments">
                            <i class="fas fa-credit-card me-2"></i>Paiements
                        </a>
                        <a class="nav-link <?php echo $currentTab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                            <i class="fas fa-cogs me-2"></i>Paramètres
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content p-4">
                    <!-- Messages d'alerte -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Contenu selon l'onglet actif
                    switch ($currentTab) {
                        case 'overview':
                            include 'dashboard-tabs/overview.php';
                            break;
                        case 'users':
                            include 'dashboard-tabs/users.php';
                            break;
                        case 'artists':
                            include 'dashboard-tabs/artists.php';
                            break;
                        case 'music':
                            include 'dashboard-tabs/music.php';
                            break;
                        case 'analytics':
                            include 'dashboard-tabs/analytics.php';
                            break;
                        case 'payments':
                            include 'dashboard-tabs/payments.php';
                            break;
                        case 'settings':
                            include 'dashboard-tabs/settings.php';
                            break;
                        default:
                            include 'dashboard-tabs/overview.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <div class="btn-group dropup">
            <button type="button" class="btn btn-admin rounded-circle" data-bs-toggle="dropdown" style="width: 60px; height: 60px;">
                <i class="fas fa-plus fa-lg"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-2"></i>Ajouter Utilisateur
                </a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addTrackModal">
                    <i class="fas fa-music me-2"></i>Ajouter Musique
                </a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addArtistModal">
                    <i class="fas fa-microphone me-2"></i>Ajouter Artiste
                </a></li>
            </ul>
        </div>
    </div>

    <!-- Modals -->
    <?php include 'dashboard-modals/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Fix pour les dropdowns Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            // Forcer le bon positionnement des dropdowns
            const dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(function(dropdown) {
                dropdown.addEventListener('shown.bs.dropdown', function() {
                    const menu = this.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        menu.style.zIndex = '1070';
                    }
                });
            });
        });
    </script>
    <script>
        // Nettoyer l'URL après affichage des messages
        if (window.location.search.includes('success=') || window.location.search.includes('error=')) {
            setTimeout(function() {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('error');
                history.replaceState({}, document.title, url.toString());
            }, 5000);
        }

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .chart-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('animate-fade-in');
                }, index * 100);
            });
        });
    </script>
</body>
</html>