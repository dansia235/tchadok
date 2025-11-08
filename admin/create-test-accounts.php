<?php
/**
 * Script d'exécution pour créer les comptes de test
 * Tchadok Platform
 *
 * ATTENTION : Ce script ne doit être utilisé qu'en développement !
 * Il supprime et recrée tous les comptes de test.
 */

// Charger la configuration de l'environnement
require_once __DIR__ . '/../config/env.php';

// Sécurité : Ne fonctionne qu'en mode développement
if (!EnvLoader::isDevelopment() && env('ENABLE_TEST_ACCOUNTS', 'false') !== 'true') {
    die('❌ ERREUR : Ce script ne peut être exécuté qu\'en mode développement !<br><br>
         Pour activer ce script, assurez-vous que dans votre fichier .env :<br>
         - APP_ENV=development<br>
         - ENABLE_TEST_ACCOUNTS=true');
}

require_once __DIR__ . '/../includes/database.php';

// Définir le titre de la page
$pageTitle = 'Création des Comptes de Test';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Tchadok Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .container {
            max-width: 900px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #0066CC, #004999);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
        }
        .badge-admin { background: linear-gradient(135deg, #667eea, #764ba2); }
        .badge-fan { background: linear-gradient(135deg, #0066CC, #004999); }
        .badge-artist { background: linear-gradient(135deg, #FFD700, #FFA500); color: #1a1a1a; }
        .account-item {
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #0066CC;
        }
        .account-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
            transition: all 0.3s ease;
        }
        .btn-execute {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #1a1a1a;
            font-weight: 700;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
        }
        .btn-execute:hover {
            background: linear-gradient(135deg, #FFA500, #FFD700);
            color: #1a1a1a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
        }
        .alert {
            border-radius: 10px;
        }
        .result-table {
            font-size: 0.9rem;
        }
        .warning-box {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 152, 0, 0.1));
            border-left: 4px solid #FFA500;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>
                    Création des Comptes de Test
                </h1>
                <p class="mb-0 mt-2 opacity-75">
                    <i class="fas fa-flask me-1"></i>
                    Mode Développement - Gestion des comptes de test
                </p>
            </div>
            <div class="card-body p-4">

                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
                    // Exécuter le script SQL
                    echo '<div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Exécution du script en cours...
                    </div>';

                    try {
                        // Obtenir la connexion à la base de données
                        $dbInstance = TchadokDatabase::getInstance();
                        $db = $dbInstance->getConnection();

                        if (!$db) {
                            throw new Exception("Impossible de se connecter à la base de données. Vérifiez votre configuration .env");
                        }

                        // Lire le fichier SQL
                        $sqlFile = __DIR__ . '/../sql/create-test-accounts.sql';

                        if (!file_exists($sqlFile)) {
                            throw new Exception("Le fichier SQL n'existe pas : $sqlFile");
                        }

                        $sql = file_get_contents($sqlFile);

                        // Diviser en requêtes individuelles (simplification)
                        // Note: Pour une meilleure gestion, utilisez mysqli::multi_query
                        $queries = array_filter(
                            array_map('trim', explode(';', $sql)),
                            function($query) {
                                return !empty($query) &&
                                       !preg_match('/^--/', $query) &&
                                       !preg_match('/^\/\*/', $query);
                            }
                        );

                        $success = 0;
                        $errors = [];

                        foreach ($queries as $query) {
                            if (empty(trim($query))) continue;

                            try {
                                $db->query($query);
                                $success++;
                            } catch (Exception $e) {
                                // Ignorer certaines erreurs non critiques
                                if (strpos($e->getMessage(), 'UNION') === false &&
                                    strpos($e->getMessage(), 'SELECT') === false) {
                                    $errors[] = $e->getMessage();
                                }
                            }
                        }

                        if (empty($errors)) {
                            echo '<div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Succès !</strong> Les comptes de test ont été créés avec succès.
                                <br><small>Requêtes exécutées : ' . $success . '</small>
                            </div>';

                            // Afficher les comptes créés
                            $result = $db->query("
                                SELECT
                                    u.id,
                                    u.username,
                                    u.email,
                                    u.first_name,
                                    u.last_name,
                                    u.premium_status,
                                    u.wallet_balance,
                                    CASE
                                        WHEN a.id IS NOT NULL THEN 'Artiste'
                                        WHEN adm.id IS NOT NULL THEN 'Admin'
                                        ELSE 'Fan'
                                    END as type_profil,
                                    CASE
                                        WHEN a.id IS NOT NULL THEN ar.stage_name
                                        ELSE NULL
                                    END as nom_artiste
                                FROM users u
                                LEFT JOIN artists a ON u.id = a.user_id
                                LEFT JOIN artists ar ON a.id = ar.id
                                LEFT JOIN admins adm ON u.id = adm.user_id
                                WHERE u.email LIKE '%@test.tchadok.td'
                                ORDER BY
                                    CASE
                                        WHEN adm.id IS NOT NULL THEN 1
                                        WHEN a.id IS NOT NULL THEN 2
                                        ELSE 3
                                    END,
                                    u.id
                            ");

                            if ($result && $result->num_rows > 0) {
                                echo '<div class="mt-4">
                                    <h5><i class="fas fa-list-check me-2"></i>Comptes créés :</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover result-table mt-3">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Nom</th>
                                                    <th>Premium</th>
                                                    <th>Solde</th>
                                                </tr>
                                            </thead>
                                            <tbody>';

                                while ($row = $result->fetch_assoc()) {
                                    $badgeClass = 'badge-fan';
                                    if ($row['type_profil'] === 'Admin') $badgeClass = 'badge-admin';
                                    if ($row['type_profil'] === 'Artiste') $badgeClass = 'badge-artist';

                                    $displayName = $row['first_name'] . ' ' . $row['last_name'];
                                    if ($row['nom_artiste']) {
                                        $displayName .= ' (' . $row['nom_artiste'] . ')';
                                    }

                                    echo '<tr>
                                        <td><span class="badge ' . $badgeClass . '">' . $row['type_profil'] . '</span></td>
                                        <td><code>' . htmlspecialchars($row['username']) . '</code></td>
                                        <td><small>' . htmlspecialchars($row['email']) . '</small></td>
                                        <td>' . htmlspecialchars($displayName) . '</td>
                                        <td>' . ($row['premium_status'] ? '<i class="fas fa-crown text-warning"></i>' : '-') . '</td>
                                        <td>' . number_format($row['wallet_balance'], 0, ',', ' ') . ' FCFA</td>
                                    </tr>';
                                }

                                echo '</tbody></table></div></div>';
                            }

                        } else {
                            echo '<div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Attention !</strong> Le script a été exécuté mais avec quelques erreurs :
                                <ul class="mb-0 mt-2">';
                            foreach ($errors as $error) {
                                echo '<li><small>' . htmlspecialchars($error) . '</small></li>';
                            }
                            echo '</ul></div>';
                        }

                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>Erreur !</strong> ' . htmlspecialchars($e->getMessage()) . '
                        </div>';
                    }

                } else {
                    // Afficher le formulaire
                ?>

                <div class="warning-box">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Avertissement</h5>
                    <p class="mb-2">
                        Ce script va <strong>supprimer tous les comptes de test existants</strong>
                        et en créer de nouveaux. Les comptes concernés sont ceux avec :
                    </p>
                    <ul class="mb-0">
                        <li>Email se terminant par <code>@test.tchadok.td</code></li>
                        <li>Username contenant <code>_test</code></li>
                    </ul>
                </div>

                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Comptes qui seront créés</h5>

                <div class="row">
                    <div class="col-md-12">
                        <div class="account-item">
                            <span class="badge badge-admin mb-2">ADMIN</span>
                            <h6 class="mb-1">Administrateur Tchadok</h6>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i> admin_test |
                                <i class="fas fa-envelope me-1"></i> admin@test.tchadok.td
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-fan mb-2">FAN</span>
                            <h6 class="mb-1">Amina Hassan</h6>
                            <small class="text-muted">
                                Premium <i class="fas fa-crown text-warning"></i><br>
                                5 000 FCFA
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-fan mb-2">FAN</span>
                            <h6 class="mb-1">Mahamat Idriss</h6>
                            <small class="text-muted">
                                Standard<br>
                                2 500 FCFA
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-fan mb-2">FAN</span>
                            <h6 class="mb-1">Fatima Oumar</h6>
                            <small class="text-muted">
                                Étudiant <i class="fas fa-graduation-cap"></i><br>
                                1 200 FCFA
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-artist mb-2">ARTISTE</span>
                            <h6 class="mb-1">Ngar Star</h6>
                            <small class="text-muted">
                                Vérifié <i class="fas fa-check-circle text-primary"></i><br>
                                150K streams
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-artist mb-2">ARTISTE</span>
                            <h6 class="mb-1">Sasa Voice</h6>
                            <small class="text-muted">
                                Émergente<br>
                                32K streams
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-item">
                            <span class="badge badge-artist mb-2">ARTISTE</span>
                            <h6 class="mb-1">Ibro Beats</h6>
                            <small class="text-muted">
                                Débutant<br>
                                5.4K streams
                            </small>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <i class="fas fa-key me-2"></i>
                    <strong>Mot de passe pour tous les comptes :</strong>
                    <code class="ms-2">tchadok2024</code>
                </div>

                <form method="POST" class="text-center mt-4">
                    <button type="submit" name="execute" class="btn btn-execute btn-lg">
                        <i class="fas fa-play me-2"></i>
                        Exécuter le Script
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour au tableau de bord
                    </a>
                </div>

                <?php } ?>

            </div>
        </div>

        <div class="text-center mt-4 text-white">
            <small>
                <i class="fas fa-shield-alt me-1"></i>
                Script de développement - Ne pas utiliser en production
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
