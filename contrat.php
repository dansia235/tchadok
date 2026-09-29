<?php
/**
 * Contrat de distribution, cote artiste (PAYOUT-05).
 *
 * Affiche la version en vigueur et enregistre l'acceptation (horodatee,
 * empreinte du texte, adresse, navigateur), exigee avant toute publication et
 * toute demande de versement.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/contrat.php'));
}
$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([(int) $_SESSION['user_id']]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$artiste) {
    show404();
}

$retour = destinationInterne($_GET['retour'] ?? $_POST['retour'] ?? null);
$contrat = Contrats::courant();
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contrat !== null) {
    if (empty($_POST['lu'])) {
        $erreur = 'Cochez la case pour confirmer avoir lu le contrat.';
    } elseif (Contrats::accepter((int) $artiste['id'], (int) $contrat['id'], (int) $_SESSION['user_id'])) {
        JournalAudit::enregistrer('contrat.accepte', ['cible_type' => 'artiste', 'cible_id' => $artiste['id'], 'apres' => ['version' => $contrat['version']]]);
        setFlashMessage(FLASH_SUCCESS, 'Contrat accepte (version ' . $contrat['version'] . ').');
        redirect(SITE_URL . ($retour ?? '/artiste-revenus.php'));
    }
}

$accepte = $contrat !== null && Contrats::aAccepte((int) $artiste['id'], (int) $contrat['id']);
$acceptations = Contrats::acceptations((int) $artiste['id']);

$pageTitle = 'Contrat de distribution';
$pageDescription = 'Contrat de distribution Tchadok';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <section class="mx-auto max-w-3xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace artiste</p>
            <h1 class="mt-2 text-2xl font-display font-semibold text-text">Contrat de distribution</h1>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>

            <?php if ($contrat === null): ?>
                <p class="mt-6 text-sm text-muted">Aucune version du contrat n'est encore publiee.</p>
            <?php else: ?>
                <p class="mt-2 text-sm text-muted"><?php echo e($contrat['title']); ?> — version <?php echo e($contrat['version']); ?>, en vigueur depuis le <?php echo e(date('d/m/Y', strtotime((string) $contrat['published_at']))); ?>.</p>
                <?php if ($accepte): ?>
                    <div class="alert alert-success mt-6" role="status"><i class="fas fa-check-circle mt-0.5"></i><span>Vous avez accepte cette version.</span></div>
                <?php elseif ($retour !== null): ?>
                    <div class="alert alert-warning mt-6" role="status"><i class="fas fa-circle-info mt-0.5"></i><span>Pour publier ou demander un versement, acceptez d'abord la version en vigueur du contrat.</span></div>
                <?php endif; ?>
                <article class="mt-6 max-h-[28rem] overflow-y-auto whitespace-pre-line rounded-2xl border border-white/10 bg-bg/60 p-5 text-sm leading-7 text-text" tabindex="0" aria-label="Texte du contrat"><?php echo e($contrat['body']); ?></article>
                <?php if (!$accepte): ?>
                    <form method="POST" class="mt-6 space-y-4">
                        <?php echo csrfField(); ?>
                        <?php if ($retour !== null): ?><input type="hidden" name="retour" value="<?php echo e($retour); ?>"><?php endif; ?>
                        <label class="flex items-start gap-3 text-sm text-text"><input type="checkbox" name="lu" value="1" class="mt-1" required>
                            J'ai lu le contrat de distribution et je l'accepte au nom de <?php echo e($artiste['stage_name']); ?>.</label>
                        <button type="submit" class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Accepter la version <?php echo e($contrat['version']); ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($acceptations !== []): ?>
                <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-muted">Versions acceptees</h2>
                <ul class="mt-2 text-sm text-text">
                    <?php foreach ($acceptations as $a): ?>
                        <li><?php echo e($a['version']); ?> — <?php echo e($a['title']); ?>, le <?php echo e(date('d/m/Y a H:i', strtotime((string) $a['accepted_at']))); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
