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

                <!-- Test de connexion -->
                <div class="mb-4">
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="testConnection()">
                        <i class="fas fa-plug me-2"></i>
                        Tester la connexion à la base de données
                    </button>
                    <div id="connection-result" class="mt-3" style="display: none;"></div>
                </div>

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

                        // Utiliser le fichier SQL simplifié s'il existe, sinon l'original
                        $sqlFile = __DIR__ . '/../sql/create-test-accounts-simple.sql';
                        if (!file_exists($sqlFile)) {
                            $sqlFile = __DIR__ . '/../sql/create-test-accounts.sql';
                        }

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
                        $executed = 0;
                        $debug_mode = true; // Activer le mode debug
                        $debug_info = [];

                        // Démarrer une transaction PDO
                        $db->beginTransaction();

                        foreach ($queries as $query) {
                            $query = trim($query);
                            if (empty($query)) continue;

                            // Ignorer les commandes SET et TRANSACTION au début
                            if (preg_match('/^(SET|START TRANSACTION|COMMIT)/i', $query)) {
                                continue;
                            }

                            $executed++;
                            $query_preview = substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');

                            try {
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                $success++;

                                if ($debug_mode) {
                                    $debug_info[] = [
                                        'num' => $executed,
                                        'status' => 'success',
                                        'query' => $query_preview,
                                        'affected_rows' => $stmt->rowCount()
                                    ];
                                }
                            } catch (PDOException $e) {
                                $errorMsg = $e->getMessage();

                                // Enregistrer toutes les erreurs en mode debug
                                if ($debug_mode) {
                                    $debug_info[] = [
                                        'num' => $executed,
                                        'status' => 'error',
                                        'query' => $query_preview,
                                        'error' => $errorMsg
                                    ];
                                }

                                // Ignorer certaines erreurs non critiques (comme les SELECT pour affichage)
                                if (strpos($errorMsg, 'UNION') === false &&
                                    strpos($errorMsg, 'SELECT') === false &&
                                    strpos($errorMsg, 'RÉSUMÉ') === false &&
                                    strpos($errorMsg, 'INFORMATIONS') === false) {
                                    $errors[] = "Requête #$executed : " . $errorMsg . "<br><small>Requête: " . htmlspecialchars($query_preview) . "</small>";
                                }
                            }
                        }

                        // Valider la transaction si pas d'erreurs critiques
                        if (count($errors) === 0) {
                            $db->commit();
                            $debug_info[] = [
                                'num' => ++$executed,
                                'status' => 'success',
                                'query' => 'COMMIT TRANSACTION',
                                'affected_rows' => 0
                            ];
                        } else {
                            $db->rollBack();
                            $debug_info[] = [
                                'num' => ++$executed,
                                'status' => 'error',
                                'query' => 'ROLLBACK TRANSACTION',
                                'error' => 'Transaction annulée à cause d\'erreurs'
                            ];
                        }

                        // Afficher le mode debug
                        if ($debug_mode && !empty($debug_info)) {
                            echo '<div class="alert alert-secondary">
                                <h6><i class="fas fa-bug me-2"></i>Mode Debug</h6>
                                <details>
                                    <summary style="cursor: pointer;">Voir les détails d\'exécution (' . count($debug_info) . ' requêtes)</summary>
                                    <table class="table table-sm mt-2">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Statut</th>
                                                <th>Requête</th>
                                                <th>Détails</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            foreach ($debug_info as $info) {
                                $statusBadge = $info['status'] === 'success'
                                    ? '<span class="badge bg-success">OK</span>'
                                    : '<span class="badge bg-danger">ERREUR</span>';
                                $details = $info['status'] === 'success'
                                    ? $info['affected_rows'] . ' lignes affectées'
                                    : '<small>' . htmlspecialchars($info['error']) . '</small>';

                                echo '<tr>
                                    <td>' . $info['num'] . '</td>
                                    <td>' . $statusBadge . '</td>
                                    <td><small>' . htmlspecialchars($info['query']) . '</small></td>
                                    <td>' . $details . '</td>
                                </tr>';
                            }

                            echo '</tbody></table>
                                </details>
                            </div>';
                        }

                        if (empty($errors)) {
                            echo '<div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Succès !</strong> Les comptes de test ont été créés avec succès.
                                <br><small>Requêtes exécutées : ' . $success . ' / ' . $executed . '</small>
                            </div>';

                            // Afficher les comptes créés
                            $stmt = $db->prepare("
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
                                    a.stage_name as nom_artiste
                                FROM users u
                                LEFT JOIN artists a ON u.id = a.user_id
                                LEFT JOIN admins adm ON u.id = adm.user_id
                                WHERE u.email LIKE :email
                                ORDER BY
                                    CASE
                                        WHEN adm.id IS NOT NULL THEN 1
                                        WHEN a.id IS NOT NULL THEN 2
                                        ELSE 3
                                    END,
                                    u.id
                            ");

                            $stmt->execute(['email' => '%@test.tchadok.td']);
                            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if ($results && count($results) > 0) {
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

                                foreach ($results as $row) {
                                    $badgeClass = 'badge-fan';
                                    if ($row['type_profil'] === 'Admin') $badgeClass = 'badge-admin';
                                    if ($row['type_profil'] === 'Artiste') $badgeClass = 'badge-artist';

                                    $displayName = $row['first_name'] . ' ' . $row['last_name'];
                                    if ($row['nom_artiste']) {
                                        $displayName .= ' (' . $row['nom_artiste'] . ')';
                                    }

                                    echo '<tr>
                                        <td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($row['type_profil']) . '</span></td>
                                        <td><code>' . htmlspecialchars($row['username']) . '</code></td>
                                        <td><small>' . htmlspecialchars($row['email']) . '</small></td>
                                        <td>' . htmlspecialchars($displayName) . '</td>
                                        <td>' . ($row['premium_status'] ? '<i class="fas fa-crown text-warning"></i>' : '-') . '</td>
                                        <td>' . number_format($row['wallet_balance'], 0, ',', ' ') . ' FCFA</td>
                                    </tr>';
                                }

                                echo '</tbody></table></div></div>';
                            } else {
                                echo '<div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Aucun compte de test trouvé. Ils ont peut-être été supprimés.
                                </div>';
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
                        // Annuler la transaction en cas d'erreur
                        if ($db && $db->inTransaction()) {
                            $db->rollBack();
                        }

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
    <script>
    function testConnection() {
        const resultDiv = document.getElementById('connection-result');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-spinner fa-spin me-2"></i>
                Test de connexion en cours...
            </div>
        `;

        fetch('test-db-connection.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = `
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Connexion réussie !</h5>
                            <p class="mb-0">${data.message}</p>
                        </div>
                        <div class="card mt-2">
                            <div class="card-header bg-light">
                                <strong>Détails de la connexion</strong>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Base de données</th>
                                        <td>${data.details.env.DB_DATABASE} @ ${data.details.env.DB_HOST}</td>
                                    </tr>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <td>${data.details.env.DB_USERNAME}</td>
                                    </tr>
                                    <tr>
                                        <th>Connexion PDO</th>
                                        <td><span class="badge bg-success">${data.details.connection}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Test requête</th>
                                        <td><span class="badge bg-success">${data.details.query_test}</span></td>
                                    </tr>
                                </table>
                                <h6 class="mt-3">Tables</h6>
                                <ul class="list-unstyled">
                    `;
                    for (const [table, status] of Object.entries(data.details.tables)) {
                        const badgeClass = status === 'EXISTS' ? 'bg-success' : 'bg-danger';
                        html += `<li><span class="badge ${badgeClass}">${status}</span> ${table}</li>`;
                    }
                    html += `
                                </ul>
                                <h6 class="mt-3">Statistiques</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Total utilisateurs:</strong> ${data.details.users_count}</li>
                                    <li><strong>Comptes de test:</strong> ${data.details.test_accounts_count}</li>
                                </ul>
                                <details class="mt-3">
                                    <summary class="text-muted" style="cursor: pointer;">Colonnes de la table users</summary>
                                    <pre class="mt-2">${data.details.users_columns.join('\n')}</pre>
                                </details>
                            </div>
                        </div>
                    `;
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-times-circle me-2"></i>Erreur de connexion</h5>
                            <p class="mb-2"><strong>Message:</strong> ${data.message}</p>
                            <details>
                                <summary class="text-muted" style="cursor: pointer;">Voir les détails</summary>
                                <pre class="mt-2">${JSON.stringify(data.details, null, 2)}</pre>
                            </details>
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-times-circle me-2"></i>Erreur</h5>
                        <p class="mb-0">${error.message}</p>
                    </div>
                `;
            });
    }
    </script>
</body>
</html>
