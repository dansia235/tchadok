<?php
/**
 * Signaler un contenu (MOD-03) : titre, sortie ou profil d'artiste.
 *
 *   signaler.php?type=track&id=12
 *
 * Une revendication de droits d'auteur retire le contenu provisoirement,
 * immediatement, pendant l'examen.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/moderation.php';
require_once 'includes/signalements.php';

$type = in_array($_GET['type'] ?? $_POST['type'] ?? '', ['track', 'release', 'artist'], true) ? (string) ($_GET['type'] ?? $_POST['type']) : 'track';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/signaler.php?type=' . $type . '&id=' . $id));
}
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Signalements::signaler((int) $_SESSION['user_id'], $type, $id, (string) ($_POST['categorie'] ?? ''), (string) ($_POST['description'] ?? ''),
        (string) ($_POST['titulaire'] ?? ''), (string) ($_POST['contact'] ?? ''));
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/signaler.php?type=' . $type . '&id=' . $id . '&envoye=1');
    }
    $erreur = $r['message'];
}
$champ = 'mt-1 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text';
$pageTitle = 'Signaler un contenu';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <section class="mx-auto max-w-2xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <h1 class="text-2xl font-display font-semibold text-text">Signaler un contenu</h1>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <?php if (!isset($_GET['envoye'])): ?>
                <form method="POST" class="mt-6 space-y-4">
                    <?php echo csrfField(); ?><input type="hidden" name="type" value="<?php echo e($type); ?>"><input type="hidden" name="id" value="<?php echo $id; ?>">
                    <label class="block text-sm text-muted">Motif<select name="categorie" class="<?php echo $champ; ?>"><?php foreach (Signalements::CATEGORIES as $c => $d): ?><option value="<?php echo e($c); ?>"><?php echo e($d['libelle']); ?></option><?php endforeach; ?></select></label>
                    <label class="block text-sm text-muted">Description (20 caracteres au moins)<textarea name="description" rows="4" required minlength="20" class="<?php echo $champ; ?>"></textarea></label>
                    <fieldset class="rounded-2xl border border-white/10 p-4">
                        <legend class="px-2 text-xs text-muted">Revendication de droits d'auteur uniquement</legend>
                        <label class="block text-sm text-muted">Nom du titulaire des droits<input name="titulaire" class="<?php echo $champ; ?>"></label>
                        <label class="mt-3 block text-sm text-muted">E-mail de contact<input name="contact" type="email" class="<?php echo $champ; ?>"></label>
                        <p class="mt-2 text-xs text-muted">Le contenu est retire provisoirement des l'envoi ; l'artiste peut repondre sous <?php echo Signalements::DELAI_REPONSE_JOURS; ?> jours. Une revendication abusive engage son auteur.</p>
                    </fieldset>
                    <button class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Envoyer le signalement</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
