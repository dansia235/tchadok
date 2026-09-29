<?php
/**
 * Traitement des signalements (MOD-03). Permission : signalement.traiter.
 *
 * File priorisee (droits d'auteur en tete), contre-notification de
 * l'artiste, decision motivee, journal des decisions consultable.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/moderation.php';
require_once __DIR__ . '/../includes/signalements.php';

Autorisations::exiger('signalement.traiter');
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Signalements::decider((int) ($_POST['signalement'] ?? 0), (string) ($_POST['decision'] ?? ''), (string) ($_POST['motif'] ?? ''), (int) $_SESSION['user_id']);
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/admin/signalements.php');
    }
    $erreur = $r['message'];
}
$file = Signalements::file();
$journal = Signalements::journal();
$types = ['track' => 'Titre', 'release' => 'Sortie', 'artist' => 'Artiste'];

$pageTitle = 'Signalements';
$hideTopNav = true;
$hideFooter = true;
include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Catalogue</p><h1 class="mt-2 text-3xl font-display font-bold text-text">Signalements</h1>
                    <p class="mt-2 text-sm text-muted">Les revendications de droits passent en tete ; le contenu est deja retire provisoirement. Toute decision est motivee et notifiee.</p></div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </section>

        <section class="space-y-3" aria-label="File de traitement">
            <?php if ($file === []): ?><p class="text-sm text-muted">Aucun signalement en attente.</p><?php endif; ?>
            <?php foreach ($file as $s): ?>
                <article class="rounded-3xl border <?php echo (int) $s['priority'] === 1 ? 'border-rose-400/40 bg-rose-400/5' : 'border-white/10 bg-surface/75'; ?> p-5" data-signalement="<?php echo (int) $s['id']; ?>">
                    <p class="text-xs uppercase tracking-wide text-muted">Priorite <?php echo (int) $s['priority']; ?> · <?php echo e($s['reason']); ?> · <?php echo e($types[$s['reported_type']] ?? $s['reported_type']); ?> · le <?php echo e(date('d/m/Y H:i', strtotime((string) $s['created_at']))); ?></p>
                    <h2 class="mt-1 text-base font-semibold text-text"><?php echo e($s['titre'] ?? '—'); ?></h2>
                    <p class="mt-2 text-sm text-muted"><?php echo e($s['description']); ?></p>
                    <?php if ($s['claimant_name']): ?><p class="mt-1 text-xs text-muted">Titulaire declare : <?php echo e($s['claimant_name']); ?> (<?php echo e($s['claimant_contact']); ?>)</p><?php endif; ?>
                    <?php if ($s['takedown_at']): ?><p class="mt-1 text-xs text-amber-200">Retire provisoirement le <?php echo e(date('d/m/Y H:i', strtotime((string) $s['takedown_at']))); ?> · reponse attendue avant le <?php echo e(date('d/m/Y', strtotime((string) $s['response_deadline']))); ?></p><?php endif; ?>
                    <p class="mt-2 text-sm <?php echo $s['counter_notice'] ? 'text-text' : 'text-muted'; ?>">Reponse de l'artiste : <?php echo $s['counter_notice'] ? e($s['counter_notice']) : 'aucune pour l\'instant'; ?></p>
                    <form method="POST" class="mt-3 grid gap-2 sm:grid-cols-[160px_1fr_auto] sm:items-end">
                        <?php echo csrfField(); ?><input type="hidden" name="signalement" value="<?php echo (int) $s['id']; ?>">
                        <label class="text-xs text-muted">Decision<select name="decision" class="w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"><option value="retire">Retirer le contenu</option><option value="maintenu">Maintenir en ligne</option><option value="rejete">Rejeter le signalement</option></select></label>
                        <label class="text-xs text-muted">Motif (notifie)<input name="motif" required minlength="5" class="w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Decider</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="titre-journal">
            <h2 id="titre-journal" class="text-lg font-semibold text-text">Journal des decisions</h2>
            <ul class="mt-3 divide-y divide-white/5 text-xs text-muted">
                <?php if ($journal === []): ?><li class="py-2">Aucune.</li><?php endif; ?>
                <?php foreach ($journal as $j): ?><li class="py-2"><span class="text-text"><?php echo e($j['titre'] ?? '—'); ?></span> — <?php echo e($j['decision']); ?> le <?php echo e(date('d/m/Y', strtotime((string) $j['decided_at']))); ?> par <?php echo e($j['decideur'] ?? '—'); ?> : <?php echo e($j['decision_reason']); ?></li><?php endforeach; ?>
            </ul>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
