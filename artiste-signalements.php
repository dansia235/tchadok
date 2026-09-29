<?php
/**
 * Signalements visant mes contenus, et contre-notification (MOD-03).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/moderation.php';
require_once 'includes/signalements.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/artiste-signalements.php'));
}
$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT id FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([(int) $_SESSION['user_id']]);
$artistId = (int) $stmt->fetchColumn();
if ($artistId === 0) {
    show404();
}
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Signalements::repondre((int) ($_POST['signalement'] ?? 0), (int) $_SESSION['user_id'], (string) ($_POST['reponse'] ?? ''));
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/artiste-signalements.php');
    }
    $erreur = $r['message'];
}
$signalements = Signalements::duArtiste($artistId);
$pageTitle = 'Signalements';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
        <header class="rounded-3xl border border-white/10 bg-surface/75 p-6">
            <h1 class="text-2xl font-display font-semibold text-text">Signalements sur mes contenus</h1>
            <p class="mt-2 text-sm text-muted">Une revendication de droits retire votre contenu pendant l'examen. Repondez dans le delai indique avec vos justificatifs (contrat, date de creation, depot a un organisme) : aucune decision n'est prise sans examen de votre reponse. <a class="underline" href="<?php echo SITE_URL; ?>/aide.php">Procedure</a></p>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-4" role="alert"><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </header>
        <?php if ($signalements === []): ?><p class="text-sm text-muted">Aucun signalement.</p><?php endif; ?>
        <?php foreach ($signalements as $s): ?>
            <article class="rounded-3xl border border-white/10 bg-surface/75 p-5">
                <p class="text-xs text-muted"><?php echo e($s['reason']); ?> · <?php echo e(date('d/m/Y', strtotime((string) $s['created_at']))); ?> · <?php echo e($s['decision'] ? 'tranche : ' . $s['decision'] : ($s['takedown_at'] ? 'contenu retire provisoirement' : 'en examen')); ?></p>
                <h2 class="mt-1 font-semibold text-text"><?php echo e($s['titre']); ?></h2>
                <p class="mt-2 text-sm text-muted"><?php echo e($s['description']); ?></p>
                <?php if ($s['decision_reason']): ?><p class="mt-2 text-sm text-text">Decision : <?php echo e($s['decision_reason']); ?></p><?php endif; ?>
                <?php if (in_array($s['status'], ['pending', 'reviewing'], true)): ?>
                    <?php if ($s['response_deadline']): ?><p class="mt-2 text-xs text-amber-200">Reponse attendue avant le <?php echo e(date('d/m/Y', strtotime((string) $s['response_deadline']))); ?>.</p><?php endif; ?>
                    <form method="POST" class="mt-3 space-y-2"><?php echo csrfField(); ?><input type="hidden" name="signalement" value="<?php echo (int) $s['id']; ?>">
                        <textarea name="reponse" rows="4" minlength="30" required class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" placeholder="Votre reponse et vos justificatifs"><?php echo e($s['counter_notice'] ?? ''); ?></textarea>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Envoyer ma reponse</button></form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
