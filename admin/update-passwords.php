<?php
/**
 * Script de mise à jour des mots de passe - Tchadok Platform
 * Change tous les mots de passe existants vers 12345678
 */

session_start();
require_once '../includes/database.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = getUserById($_SESSION['admin_id']);
if (!$admin || $admin['user_type'] !== 'admin') {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$updatedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_passwords'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Nouveau mot de passe haché
        $newPasswordHash = password_hash('12345678', PASSWORD_DEFAULT);
        
        // Mise à jour de tous les mots de passe
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE 1");
        $stmt->execute([$newPasswordHash]);
        $updatedCount = $stmt->rowCount();
        
        $message = "Mot de passe mis à jour pour $updatedCount utilisateur(s). Tous les comptes utilisent maintenant le mot de passe : 12345678";
        
    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération du nombre d'utilisateurs
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = 'N/A';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à Jour Mots de Passe - Tchadok Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFD700;
            --secondary-color: #2C3E50;
            --accent-color: #0066CC;
        }
        
        .navbar-admin {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
        }
        
        .card-update {
            border: 2px solid var(--accent-color);
            background: #f0f8ff;
        }
        
        .btn-update {
            background: linear-gradient(135deg, var(--accent-color), #0052a3);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
            color: white;
        }
        
        .password-display {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            background: #2C3E50;
            color: #FFD700;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            letter-spacing: 2px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <svg width="30" height="30" class="me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                    <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                </svg>
                Mise à Jour Mots de Passe
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour au dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card card-update">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-key me-2"></i>
                            Mise à Jour des Mots de Passe
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Information</h5>
                            <p>Cette opération va changer le mot de passe de <strong>TOUS</strong> les utilisateurs existants vers :</p>
                            <div class="password-display">12345678</div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $totalUsers; ?></h3>
                                        <p class="mb-0">Utilisateurs dans la base</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $updatedCount; ?></h3>
                                        <p class="mb-0">Mots de passe mis à jour</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6>Cette mise à jour concerne :</h6>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item"><i class="fas fa-user-shield text-danger me-2"></i>Compte administrateur (admin_tchadok)</li>
                            <li class="list-group-item"><i class="fas fa-microphone text-warning me-2"></i>Tous les comptes artistes</li>
                            <li class="list-group-item"><i class="fas fa-users text-info me-2"></i>Tous les comptes fans/utilisateurs</li>
                        </ul>
                        
                        <form method="POST" onsubmit="return confirm('Confirmer la mise à jour de tous les mots de passe vers 12345678 ?')">
                            <button type="submit" name="update_passwords" value="1" class="btn btn-update">
                                <i class="fas fa-sync-alt me-2"></i>
                                Mettre à jour tous les mots de passe
                            </button>
                            
                            <a href="dashboard.php" class="btn btn-secondary ms-3">
                                <i class="fas fa-times me-2"></i>
                                Annuler
                            </a>
                        </form>
                        
                        <div class="alert alert-warning mt-4">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Note de sécurité</h6>
                            <p class="mb-0">Ce mot de passe simple est uniquement pour les tests en développement. En production, utilisez des mots de passe sécurisés !</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Action réservée aux administrateurs - <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>