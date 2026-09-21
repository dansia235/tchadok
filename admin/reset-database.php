<?php
/**
 * Reinitialisation base de donnees (desactive)
 */

require_once '../includes/functions.php';

$pageTitle = 'Reinitialisation';
$pageDescription = 'Module desactive';
$hideTopNav = true;
$hideFooter = true;

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg py-12">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-8 text-center shadow-elev-2">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-500/20 text-rose-200">
                <i class="fas fa-lock text-xl"></i>
            </div>
            <h1 class="mt-4 text-2xl font-display font-semibold text-text">Reinitialisation desactivee</h1>
            <p class="mt-3 text-sm text-muted">
                La reinitialisation automatique est desactivee pour proteger les donnees.
            </p>
            <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                <i class="fas fa-arrow-left"></i> Retour au dashboard
            </a>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
