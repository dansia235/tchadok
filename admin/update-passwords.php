<?php
/**
 * Mise a jour des mots de passe - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/database.php';

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

        $newPasswordHash = password_hash('12345678', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE 1");
        $stmt->execute([$newPasswordHash]);
        $updatedCount = $stmt->rowCount();

        $message = "Mot de passe mis a jour pour $updatedCount utilisateur(s). Tous utilisent maintenant : 12345678";
    } catch (Exception $e) {
        $error = "Erreur lors de la mise a jour : " . $e->getMessage();
    }
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = 'N/A';
}

$pageTitle = 'Mise a jour mots de passe';
$pageDescription = 'Mise a jour globale des mots de passe';
$hideTopNav = true;
$hideFooter = true;

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pt-10 pb-16">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-semibold text-text">Mise a jour des mots de passe</h1>
                <p class="text-sm text-muted">Operation globale pour les comptes de test.</p>
            </div>
            <a href="dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
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

        <div class="mt-6 rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2">
            <div class="rounded-2xl border border-sky-400/30 bg-sky-400/10 p-4 text-sm text-sky-200">
                <p class="font-semibold text-text">Information</p>
                <p class="mt-1 text-xs text-sky-100/80">Cette operation met tous les mots de passe a <strong>12345678</strong>.</p>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                    <p class="text-2xl font-semibold text-text"><?php echo $totalUsers; ?></p>
                    <p class="text-xs text-muted">Utilisateurs dans la base</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                    <p class="text-2xl font-semibold text-text"><?php echo $updatedCount; ?></p>
                    <p class="text-xs text-muted">Mots de passe mis a jour</p>
                </div>
            </div>

            <div class="mt-6 text-xs text-muted">
                <p class="font-semibold text-text">Cette mise a jour concerne :</p>
                <ul class="mt-2 space-y-1">
                    <li>Compte administrateur</li>
                    <li>Comptes artistes</li>
                    <li>Comptes fans</li>
                </ul>
            </div>

            <form method="POST" class="mt-6" onsubmit="return confirm('Confirmer la mise a jour de tous les mots de passe vers 12345678 ?')">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="update_passwords" value="1" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white">
                        Mettre a jour
                    </button>
                    <a href="dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        Annuler
                    </a>
                </div>
            </form>

            <div class="mt-6 rounded-2xl border border-amber-400/40 bg-amber-400/10 p-4 text-xs text-amber-200">
                <p class="font-semibold text-text">Note de securite</p>
                <p class="mt-1 text-amber-100/80">Mot de passe simple a utiliser uniquement en developpement.</p>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
