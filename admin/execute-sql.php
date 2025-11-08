<?php
/**
 * Interface d'exécution SQL - Tchadok Platform
 * Permet d'exécuter des scripts SQL directement
 */

session_start();
require_once '../includes/database.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    // Permettre l'accès sans connexion pour la configuration initiale
    // À sécuriser en production !
}

$message = '';
$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_script'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sqlScript = $_POST['sql_script'];
        
        // Exécution du script SQL
        if ($sqlScript === 'check_structure') {
            // Vérifier la structure de la table
            $stmt = $pdo->query("DESCRIBE users");
            $results['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Vérifier les colonnes password
            $stmt = $pdo->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'tchadok' 
                AND TABLE_NAME = 'users'
                AND COLUMN_NAME IN ('password', 'password_hash')
            ");
            $results['password_columns'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } elseif ($sqlScript === 'fix_password') {
            // La table utilise 'password' et non 'password_hash'
            $message = "La structure de la base de données utilise la colonne 'password', pas besoin de modification.";
            
        } elseif ($sqlScript === 'create_admin') {
            // 1. Créer l'utilisateur dans la table users
            $passwordHash = password_hash('12345678', PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) 
                VALUES ('admin_tchadok', 'admin@tchadok.td', ?, 'Admin', 'Tchadok', '62123456', 'Tchad', 'N\'Djamena', 1, 1)
                ON DUPLICATE KEY UPDATE password = ?, email_verified = 1, is_active = 1
            ");
            $stmt->execute([$passwordHash, $passwordHash]);
            
            // 2. Récupérer l'ID de l'utilisateur
            $userId = $pdo->lastInsertId();
            if (!$userId) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin_tchadok'");
                $stmt->execute();
                $userId = $stmt->fetchColumn();
            }
            
            // 3. Créer l'entrée dans la table admins
            $stmt = $pdo->prepare("
                INSERT INTO admins (user_id, role, permissions) 
                VALUES (?, 'super_admin', '[\"all\"]')
                ON DUPLICATE KEY UPDATE role = 'super_admin', permissions = '[\"all\"]'
            ");
            $stmt->execute([$userId]);
            
            $message = "Compte admin créé/mis à jour avec succès ! Utilisateur: admin_tchadok, Mot de passe: 12345678";
            
        } elseif ($sqlScript === 'update_all_passwords') {
            // Mettre à jour tous les mots de passe
            $passwordHash = password_hash('12345678', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?");
            $stmt->execute([$passwordHash]);
            $count = $stmt->rowCount();
            
            $message = "Mot de passe mis à jour pour $count utilisateur(s). Tous utilisent maintenant: 12345678";
            
        } elseif ($sqlScript === 'add_missing_columns') {
            // Ajouter les colonnes manquantes
            $stmt = $pdo->query("SHOW COLUMNS FROM users");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $addedColumns = [];
            
            // Liste des colonnes à vérifier et ajouter
            $columnsToAdd = [
                'user_type' => "ALTER TABLE users ADD COLUMN user_type ENUM('fan', 'artist', 'admin') DEFAULT 'fan' AFTER last_name",
                'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER last_name",
                'profile_image' => "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) AFTER user_type",
                'bio' => "ALTER TABLE users ADD COLUMN bio TEXT AFTER profile_image",
                'date_of_birth' => "ALTER TABLE users ADD COLUMN date_of_birth DATE AFTER bio",
                'gender' => "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other') AFTER date_of_birth",
                'location' => "ALTER TABLE users ADD COLUMN location VARCHAR(100) AFTER gender",
                'is_verified' => "ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE AFTER location"
            ];
            
            foreach ($columnsToAdd as $column => $sql) {
                if (!in_array($column, $existingColumns)) {
                    try {
                        $pdo->exec($sql);
                        $addedColumns[] = $column;
                    } catch (Exception $e) {
                        // Ignorer si la colonne existe déjà
                    }
                }
            }
            
            if (count($addedColumns) > 0) {
                $message = "Colonnes ajoutées : " . implode(', ', $addedColumns);
            } else {
                $message = "Toutes les colonnes nécessaires existent déjà !";
            }
            
        } elseif ($sqlScript === 'add_password_column') {
            // Ajouter spécifiquement la colonne password
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email");
                $message = "Colonne 'password' ajoutée avec succès !";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $message = "La colonne 'password' existe déjà.";
                } else {
                    $error = "Erreur lors de l'ajout de la colonne password : " . $e->getMessage();
                }
            }
        }
        
    } catch (Exception $e) {
        $error = "Erreur SQL : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exécution SQL - Tchadok Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-admin {
            background: linear-gradient(135deg, #2C3E50, #0066CC);
        }
        .sql-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
        }
        .btn-sql {
            background: linear-gradient(135deg, #0066CC, #0052a3);
            border: none;
            color: white;
            margin: 0.25rem;
        }
        .btn-sql:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <svg width="30" height="30" class="me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                    <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                </svg>
                Gestionnaire SQL
            </a>
        </div>
    </nav>

    <div class="container py-4">
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

        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-database me-2"></i>Actions SQL Rapides</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <h5>Choisir une action :</h5>
                        <button type="submit" name="sql_script" value="check_structure" class="btn btn-sql">
                            <i class="fas fa-search me-1"></i>
                            Vérifier la structure de la table users
                        </button>
                        <button type="submit" name="sql_script" value="fix_password" class="btn btn-sql">
                            <i class="fas fa-wrench me-1"></i>
                            Corriger la colonne password
                        </button>
                        <button type="submit" name="sql_script" value="create_admin" class="btn btn-sql">
                            <i class="fas fa-user-plus me-1"></i>
                            Créer/Mettre à jour le compte admin
                        </button>
                        <button type="submit" name="sql_script" value="update_all_passwords" class="btn btn-sql">
                            <i class="fas fa-key me-1"></i>
                            Mettre tous les mots de passe à 12345678
                        </button>
                        <button type="submit" name="sql_script" value="add_missing_columns" class="btn btn-sql btn-warning text-dark">
                            <i class="fas fa-plus-circle me-1"></i>
                            Ajouter les colonnes manquantes
                        </button>
                        <button type="submit" name="sql_script" value="add_password_column" class="btn btn-sql btn-danger text-white">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Ajouter la colonne password
                        </button>
                    </div>
                </form>

                <?php if (!empty($results)): ?>
                <div class="mt-4">
                    <h5>Résultats :</h5>
                    <div class="sql-output">
                        <?php
                        if (isset($results['structure'])) {
                            echo "<h6>Structure de la table users :</h6>";
                            echo "<table class='table table-sm'>";
                            echo "<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($results['structure'] as $col) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
                                echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
                                echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
                                echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
                                echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                        }
                        
                        if (isset($results['password_columns'])) {
                            echo "<h6>Colonnes password trouvées :</h6>";
                            if (empty($results['password_columns'])) {
                                echo "<p class='text-danger'>Aucune colonne 'password' ou 'password_hash' trouvée !</p>";
                                echo "<p>Vous devez cliquer sur 'Corriger la colonne password' pour ajouter la colonne.</p>";
                            } else {
                                echo "<ul>";
                                foreach ($results['password_columns'] as $col) {
                                    echo "<li>" . htmlspecialchars($col) . "</li>";
                                }
                                echo "</ul>";
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-4 alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Scripts SQL disponibles :</h6>
                    <ul class="mb-0">
                        <li><strong>/sql/check-table-structure.sql</strong> - Vérifier la structure actuelle</li>
                        <li><strong>/sql/fix-password-column.sql</strong> - Corriger la colonne password</li>
                        <li><strong>/sql/update-password-structure.sql</strong> - Script complet de mise à jour</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>