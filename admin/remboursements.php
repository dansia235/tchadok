<?php
/**
 * Remboursements et reclamations (SHOP-07).
 *
 * Consultation : finance.transaction.lire.
 * Decision (rembourser, refuser, cloturer un remboursement manuel) :
 * finance.remboursement.executer -- role responsable_finance. Motif
 * obligatoire, journal d'audit.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';

Autorisations::exiger('finance.transaction.lire');
$peutDecider = Autorisations::peut('finance.remboursement.executer');
$auteur = (int) $_SESSION['user_id'];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Autorisations::exiger('finance.remboursement.executer');
    $action = (string) ($_POST['action'] ?? '');
    $motif = (string) ($_POST['motif'] ?? '');

    $resultat = match ($action) {
        'rembourser' => Remboursements::rembourser((int) ($_POST['commande'] ?? 0), $motif, $auteur),
        'refuser'    => Remboursements::refuser((int) ($_POST['dossier'] ?? 0), $motif, $auteur),
        'cloturer'   => Remboursements::cloturerManuel((int) ($_POST['dossier'] ?? 0), $motif, $auteur)
            ? ['succes' => true, 'message' => 'Remboursement cloture.']
            : ['succes' => false, 'message' => 'Cloture impossible : motif de 5 caracteres au moins, et dossier ouvert.'],
        default      => ['succes' => false, 'message' => 'Action inconnue.'],
    };

    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/admin/remboursements.php' . (isset($_POST['reference']) ? '?commande=' . urlencode((string) $_POST['reference']) : ''));
    }
    $erreur = $resultat['message'];
}

$db = TchadokDatabase::getInstance()->getConnection();
$dossiers = Remboursements::aTraiter();

// Recherche d'une commande
$recherche = trim((string) ($_GET['commande'] ?? $_POST['reference'] ?? ''));
$commande = null;
$lignes = [];
$historique = [];
if ($recherche !== '' && $db) {
    $stmt = $db->prepare('SELECT o.*, u.email, u.first_name, u.last_name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.reference = ?');
    $stmt->execute([$recherche]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($commande) {
        $stmt = $db->prepare('SELECT oi.*, a.stage_name FROM order_items oi LEFT JOIN artists a ON a.id = oi.artist_id WHERE oi.order_id = ?');
        $stmt->execute([$commande['id']]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $db->prepare('SELECT * FROM refunds WHERE order_id = ? ORDER BY id DESC');
        $stmt->execute([$commande['id']]);
        $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$champMotif = static fn (string $id, string $indication): string =>
    '<label class="sr-only" for="' . $id . '">Motif</label><input id="' . $id . '" name="motif" required minlength="5" placeholder="' . e($indication) . '"'
    . ' class="w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text">';

$pageTitle = 'Remboursements';
$pageDescription = 'Reclamations et remboursements';
$hideTopNav = true;
$hideFooter = true;

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Finance</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Remboursements</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                        Un remboursement retire l'acces aux contenus, reprend la part de l'artiste et passe par l'operateur
                        quand il le permet. La commande d'origine n'est jamais modifiee : une ecriture d'annulation s'y ajoute.
                    </p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                    <i class="fas fa-arrow-left mr-2"></i>Console
                </a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?>
                <div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div>
            <?php endif; ?>

            <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
                <div>
                    <label for="commande" class="text-xs font-semibold text-muted">Reference de commande</label>
                    <input id="commande" name="commande" value="<?php echo e($recherche); ?>" placeholder="TCHK-2026-..." class="mt-2 rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                </div>
                <button class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white"><i class="fas fa-search mr-2"></i>Rechercher</button>
            </form>
        </section>

        <?php if ($recherche !== ''): ?>
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-commande">
                <?php if (!$commande): ?>
                    <p class="text-sm text-muted">Aucune commande « <?php echo e($recherche); ?> ».</p>
                <?php else: ?>
                    <h2 id="titre-commande" class="text-lg font-semibold text-text"><?php echo e($commande['reference']); ?>
                        <span class="ml-2 text-sm text-muted"><?php echo e($commande['status']); ?> · <?php echo e($fcfa($commande['total'])); ?> · <?php echo e(Factures::libelleMoyen($commande['payment_method'])); ?></span></h2>
                    <p class="mt-1 text-xs text-muted"><?php echo e(trim($commande['first_name'] . ' ' . $commande['last_name'])); ?> · <?php echo e($commande['email']); ?> · facture <?php echo e($commande['invoice_number'] ?? '—'); ?></p>
                    <ul class="mt-4 text-sm">
                        <?php foreach ($lignes as $l): ?>
                            <li class="flex justify-between border-b border-white/5 py-2"><span class="text-text"><?php echo e($l['label']); ?> <span class="text-xs text-muted"><?php echo e($l['stage_name'] ?? ''); ?></span></span>
                                <span class="text-xs text-muted"><?php echo e($fcfa($l['unit_price'])); ?> · part artiste <?php echo e($fcfa($l['artist_net'])); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php foreach ($historique as $h): ?>
                        <p class="mt-3 text-xs text-muted">Dossier #<?php echo (int) $h['id']; ?> : <?php echo e(Remboursements::LIBELLES[$h['status']] ?? $h['status']); ?>
                            <?php echo $h['decision_reason'] ? ' — ' . e($h['decision_reason']) : ''; ?></p>
                    <?php endforeach; ?>
                    <?php if ($peutDecider && in_array($commande['status'], ['paid', 'review'], true)): ?>
                        <form method="POST" class="mt-4 flex max-w-xl gap-2" onsubmit="return confirm('Rembourser integralement cette commande ? L\'acces aux contenus sera retire.')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="rembourser">
                            <input type="hidden" name="commande" value="<?php echo (int) $commande['id']; ?>">
                            <input type="hidden" name="reference" value="<?php echo e($commande['reference']); ?>">
                            <?php echo $champMotif('motif-commande', 'Motif du remboursement'); ?>
                            <button class="whitespace-nowrap rounded-full bg-rose-600 px-4 py-1.5 text-xs font-semibold text-white">Rembourser</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-dossiers">
            <h2 id="titre-dossiers" class="px-6 pt-6 text-lg font-semibold text-text">Dossiers a traiter <span class="text-muted">(<?php echo count($dossiers); ?>)</span></h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-3">Depose</th><th class="px-4 py-3">Commande</th><th class="px-4 py-3">Client</th><th class="px-4 py-3">Etat</th><th class="px-4 py-3">Detail</th><?php if ($peutDecider): ?><th class="px-4 py-3">Decision</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php if (!$dossiers): ?><tr><td colspan="6" class="px-4 py-6 text-center text-muted">Aucun dossier ouvert.</td></tr><?php endif; ?>
                        <?php foreach ($dossiers as $d): ?>
                            <tr class="border-b border-white/5 align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted"><?php echo e(date('d/m/Y H:i', strtotime((string) $d['created_at']))); ?></td>
                                <td class="px-4 py-3"><a class="font-mono text-xs text-text underline" href="?commande=<?php echo e(rawurlencode((string) $d['reference'])); ?>"><?php echo e($d['reference']); ?></a>
                                    <span class="block text-xs text-muted"><?php echo e($fcfa($d['total'])); ?></span></td>
                                <td class="px-4 py-3 text-xs text-muted"><?php echo e(trim($d['first_name'] . ' ' . $d['last_name'])); ?>
                                    <?php if (!empty($d['prioritaire'])): ?><span class="mt-1 block w-fit rounded-full bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-300">Premium · prioritaire</span><?php endif; ?></td>
                                <td class="px-4 py-3 text-xs text-text"><?php echo e(Remboursements::LIBELLES[$d['status']] ?? $d['status']); ?></td>
                                <td class="px-4 py-3 text-xs text-muted">
                                    <?php if ($d['customer_reason']): ?><p><?php echo e(Remboursements::MOTIFS_CLIENT[$d['customer_reason']] ?? $d['customer_reason']); ?></p><?php endif; ?>
                                    <?php if ($d['customer_message']): ?><p class="mt-1 italic">« <?php echo e($d['customer_message']); ?> »</p><?php endif; ?>
                                    <?php if ($d['failure_message']): ?><p class="mt-1 text-amber-300"><?php echo e($d['failure_message']); ?></p><?php endif; ?>
                                </td>
                                <?php if ($peutDecider): ?>
                                    <td class="px-4 py-3">
                                        <?php if ($d['status'] === 'demande'): ?>
                                            <form method="POST" class="flex min-w-[260px] gap-2">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="commande" value="<?php echo (int) $d['order_id']; ?>">
                                                <input type="hidden" name="dossier" value="<?php echo (int) $d['id']; ?>">
                                                <?php echo $champMotif('motif-' . (int) $d['id'], 'Motif (communique au client si refus)'); ?>
                                                <button name="action" value="rembourser" class="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">Rembourser</button>
                                                <button name="action" value="refuser" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Refuser</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="flex min-w-[260px] gap-2">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="cloturer">
                                                <input type="hidden" name="dossier" value="<?php echo (int) $d['id']; ?>">
                                                <?php echo $champMotif('cloture-' . (int) $d['id'], 'Comment le remboursement a ete fait'); ?>
                                                <button class="whitespace-nowrap rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Marquer rembourse</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
