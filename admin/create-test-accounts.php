<?php
/**
 * Creation de comptes de test (desactive)
 */

require_once '../includes/functions.php';

$pageTitle = 'Comptes de test';
$pageDescription = 'Module desactive';
$hideTopNav = true;
$hideFooter = true;

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg py-12">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-8 text-center shadow-elev-2">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-500/20 text-amber-200">
                <i class="fas fa-ban text-xl"></i>
            </div>
            <h1 class="mt-4 text-2xl font-display font-semibold text-text">Module desactive</h1>
            <p class="mt-3 text-sm text-muted">
                La generation de comptes de test est desactivee. Utilisez l'administration
                pour creer des comptes utilisateurs.
            </p>
            <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                <i class="fas fa-arrow-left"></i> Retour au dashboard
            </a>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
