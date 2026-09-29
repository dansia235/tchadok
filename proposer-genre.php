<?php
/**
 * Proposition d'un genre par un artiste (TAXO-03).
 *
 * L'artiste ne cree plus de genre : il en propose un, qui n'est utilisable
 * qu'apres decision motivee de l'equipe editoriale (admin/taxonomie.php).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/taxonomie.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/proposer-genre.php'));
}
$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([(int) $_SESSION['user_id']]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$artiste) {
    show404();
}
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Taxonomie::proposer((int) $artiste['id'], (string) ($_POST['nom'] ?? ''),
        ctype_digit((string) ($_POST['categorie'] ?? '')) ? (int) $_POST['categorie'] : null, (string) ($_POST['note'] ?? ''));
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/proposer-genre.php');
    }
    $erreur = $r['message'];
}
$categories = $db->query("SELECT id, name FROM genres WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$stmt = $db->prepare('SELECT * FROM genre_proposals WHERE artist_id = ? ORDER BY created_at DESC LIMIT 20');
$stmt->execute([(int) $artiste['id']]);
$mesPropositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$etats = ['pending' => 'en attente', 'accepted' => 'acceptee', 'rejected' => 'refusee'];

$pageTitle = 'Proposer un genre';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <section class="mx-auto max-w-2xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <h1 class="text-2xl font-display font-semibold text-text">Proposer un genre</h1>
            <p class="mt-2 text-sm text-muted">Votre musique ne trouve pas sa place dans la liste ? Proposez un genre : l'equipe editoriale l'examine et vous repond par e-mail. En attendant, choisissez le genre le plus proche.</p>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <form method="POST" class="mt-6 space-y-4">
                <?php echo csrfField(); ?>
                <label class="block text-sm text-muted">Nom du genre<input name="nom" required minlength="2" maxlength="50" class="mt-1 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"></label>
                <label class="block text-sm text-muted">Categorie<select name="categorie" class="mt-1 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    <?php foreach ($categories as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo e($c['name']); ?></option><?php endforeach; ?></select></label>
                <label class="block text-sm text-muted">Pourquoi ce genre ? (facultatif)<textarea name="note" rows="3" maxlength="500" class="mt-1 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"></textarea></label>
                <button class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Envoyer la proposition</button>
            </form>
            <?php if ($mesPropositions !== []): ?>
                <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-muted">Mes propositions</h2>
                <ul class="mt-2 divide-y divide-white/5 text-sm">
                    <?php foreach ($mesPropositions as $p): ?>
                        <li class="py-2 text-text"><?php echo e($p['name']); ?> — <span class="text-muted"><?php echo e($etats[$p['status']]); ?><?php echo $p['decision_reason'] ? ' : ' . e($p['decision_reason']) : ''; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
