<?php
/**
 * Interface d'execution SQL - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$message = '';
$error = '';
$results = [];
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_script'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sqlScript = $_POST['sql_script'];

        if ($sqlScript === 'check_structure') {
            $stmt = $pdo->query("DESCRIBE users");
            $results['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'tchadok'
                AND TABLE_NAME = 'users'
                AND COLUMN_NAME IN ('password', 'password_hash')
            ");
            $results['password_columns'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($sqlScript === 'fix_password') {
            $message = "La structure de la base de donnees utilise la colonne 'password', pas besoin de modification.";
        } elseif ($sqlScript === 'create_admin') {
            $passwordHash = password_hash('12345678', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active)
                VALUES ('admin_tchadok', 'admin@tchadok.td', ?, 'Admin', 'Tchadok', '62123456', 'Tchad', 'N''Djamena', 1, 1)
                ON DUPLICATE KEY UPDATE password = ?, email_verified = 1, is_active = 1
            ");
            $stmt->execute([$passwordHash, $passwordHash]);

            $userId = $pdo->lastInsertId();
            if (!$userId) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin_tchadok'");
                $stmt->execute();
                $userId = $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare("
                INSERT INTO admins (user_id, role, permissions)
                VALUES (?, 'super_admin', '[\"all\"]')
                ON DUPLICATE KEY UPDATE role = 'super_admin', permissions = '[\"all\"]'
            ");
            $stmt->execute([$userId]);

            $message = "Compte admin cree/mis a jour avec succes ! Utilisateur: admin_tchadok, Mot de passe: 12345678";
        } elseif ($sqlScript === 'update_all_passwords') {
            $passwordHash = password_hash('12345678', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?");
            $stmt->execute([$passwordHash]);
            $count = $stmt->rowCount();

            $message = "Mot de passe mis a jour pour $count utilisateur(s). Tous utilisent maintenant: 12345678";
        } elseif ($sqlScript === 'add_missing_columns') {
            $stmt = $pdo->query("SHOW COLUMNS FROM users");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $addedColumns = [];

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
                        // Ignorer si la colonne existe deja
                    }
                }
            }

            if (count($addedColumns) > 0) {
                $message = "Colonnes ajoutees : " . implode(', ', $addedColumns);
            } else {
                $message = "Toutes les colonnes necessaires existent deja !";
            }
        } elseif ($sqlScript === 'add_password_column') {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email");
                $message = "Colonne 'password' ajoutee avec succes !";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $message = "La colonne 'password' existe deja.";
                } else {
                    $error = "Erreur lors de l'ajout de la colonne password : " . $e->getMessage();
                }
            }
        }
    } catch (Exception $e) {
        $error = "Erreur SQL : " . $e->getMessage();
    }
}

$pageTitle = 'Execution SQL';
$pageDescription = 'Outils de maintenance SQL';
$hideTopNav = true;
$hideFooter = true;

$adminShellMetrics = [
    ['value' => !empty($results) ? (string) count($results) : '0', 'label' => 'resultats', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => $error !== '' ? 'alerte active' : 'maintenance', 'label' => '', 'tone' => $error !== '' ? 'border-rose-400/30 bg-rose-400/10 text-rose-200' : 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200'],
    ['value' => 'SQL', 'label' => 'console', 'tone' => 'border-white/10 bg-white/5 text-muted']
];
$dashboardSecondaryNavLabel = 'Maintenance';
$dashboardSecondaryNavItems = [
    ['label' => 'Apercu', 'target' => 'sql-overview', 'icon' => 'home'],
    ['label' => 'Actions', 'target' => 'sql-actions', 'icon' => 'bolt'],
    ['label' => 'Resultats', 'target' => 'sql-results', 'icon' => 'table']
];

$additionalJS = [
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(245,158,11,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include '../includes/admin-shell-header.php'; ?>

    <div id="sql-overview" class="mx-auto mt-6 max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-semibold text-text">Gestionnaire SQL</h1>
                <p class="text-sm text-muted">Executer des scripts de maintenance en toute securite.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                <i class="fas fa-arrow-left"></i> Retour dashboard
            </a>
        </div>

        <?php if ($message): ?>
            <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                <i class="fas fa-check-circle"></i>
                <span class="ml-2"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                <i class="fas fa-exclamation-triangle"></i>
                <span class="ml-2"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div id="sql-actions" class="mt-6 rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2">
            <h2 class="text-sm font-semibold text-text">Actions SQL rapides</h2>
            <form method="POST" class="mt-4 flex flex-wrap gap-2 text-xs">
                <button type="submit" name="sql_script" value="check_structure" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text">Verifier structure</button>
                <button type="submit" name="sql_script" value="fix_password" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text">Verifier colonne password</button>
                <button type="submit" name="sql_script" value="create_admin" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text">Creer admin</button>
                <button type="submit" name="sql_script" value="update_all_passwords" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text">Reset mots de passe</button>
                <button type="submit" name="sql_script" value="add_missing_columns" class="rounded-full border border-amber-400/40 bg-amber-400/10 px-4 py-2 text-amber-200">Ajouter colonnes</button>
                <button type="submit" name="sql_script" value="add_password_column" class="rounded-full border border-rose-400/40 bg-rose-400/10 px-4 py-2 text-rose-200">Ajouter password</button>
            </form>

            <div id="sql-results" class="mt-6 space-y-4 text-sm text-muted">
                <?php if (!empty($results)): ?>
                    <?php if (isset($results['structure'])): ?>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <h3 class="text-sm font-semibold text-text">Structure de la table users</h3>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-xs text-muted">
                                    <thead class="border-b border-white/10 uppercase">
                                        <tr>
                                            <th class="py-2">Field</th>
                                            <th class="py-2">Type</th>
                                            <th class="py-2">Null</th>
                                            <th class="py-2">Key</th>
                                            <th class="py-2">Default</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/10">
                                        <?php foreach ($results['structure'] as $col): ?>
                                            <tr>
                                                <td class="py-2"><?php echo htmlspecialchars($col['Field']); ?></td>
                                                <td class="py-2"><?php echo htmlspecialchars($col['Type']); ?></td>
                                                <td class="py-2"><?php echo htmlspecialchars($col['Null']); ?></td>
                                                <td class="py-2"><?php echo htmlspecialchars($col['Key']); ?></td>
                                                <td class="py-2"><?php echo htmlspecialchars($col['Default'] ?? 'NULL'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($results['password_columns'])): ?>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <h3 class="text-sm font-semibold text-text">Colonnes password detectees</h3>
                            <?php if (empty($results['password_columns'])): ?>
                                <p class="mt-2 text-rose-200">Aucune colonne password trouvee.</p>
                            <?php else: ?>
                                <ul class="mt-2 space-y-1 text-xs text-muted">
                                    <?php foreach ($results['password_columns'] as $col): ?>
                                        <li><?php echo htmlspecialchars($col); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-text">Aucun resultat affiche pour l'instant.</p>
                        <p class="mt-2 text-xs text-muted">Lancez une action SQL rapide pour afficher ici la structure, les controles ou les retours de maintenance.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-muted">
                <p class="font-semibold text-text">Scripts disponibles</p>
                <ul class="mt-2 space-y-1">
                    <li>/sql/check-table-structure.sql</li>
                    <li>/sql/fix-password-column.sql</li>
                    <li>/sql/update-password-structure.sql</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
