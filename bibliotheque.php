<?php
/**
 * Ma bibliotheque (SHOP-04) : ce que le membre possede, et ses achats.
 *
 * Tout se lit dans `entitlements` : un droit actif (non revoque, non expire)
 * par titre -- l'achat d'une sortie en ouvre un par titre. La lecture passe
 * par le lecteur du site, qui obtient une URL signee de media.php (SEC-06) :
 * aucun lien direct vers un fichier. Le telechargement hors connexion, lisible
 * seulement dans l'application, relevera de SHOP-08.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/bibliotheque.php'));
}
$userId = (int) $_SESSION['user_id'];
$db = TchadokDatabase::getInstance()->getConnection();

$titres = $sorties = $achats = [];
if ($db) {
    $stmt = $db->prepare(
        "SELECT t.id, t.title, t.duration, ar.stage_name, r.title AS sortie, MIN(e.granted_at) AS depuis
           FROM entitlements e
           JOIN tracks t ON t.id = e.item_id AND t.deleted_at IS NULL
           JOIN artists ar ON ar.id = t.artist_id
           LEFT JOIN releases r ON r.id = t.release_id
          WHERE e.user_id = ? AND e.item_type = 'track' AND e.revoked_at IS NULL
            AND (e.expires_at IS NULL OR e.expires_at > NOW())
          GROUP BY t.id
          ORDER BY depuis DESC, t.title"
    );
    $stmt->execute([$userId]);
    $titres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        "SELECT r.id, r.title, r.format, r.total_tracks, ar.stage_name, e.granted_at
           FROM entitlements e
           JOIN releases r ON r.id = e.item_id AND r.deleted_at IS NULL
           JOIN artists ar ON ar.id = r.artist_id
          WHERE e.user_id = ? AND e.item_type = 'release' AND e.revoked_at IS NULL
            AND (e.expires_at IS NULL OR e.expires_at > NOW())
          ORDER BY e.granted_at DESC"
    );
    $stmt->execute([$userId]);
    $sorties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        "SELECT reference, status, total, paid_at, invoice_number, payment_method
           FROM orders
          WHERE user_id = ? AND status IN ('paid', 'review', 'refunded', 'disputed')
          ORDER BY COALESCE(paid_at, created_at) DESC LIMIT 50"
    );
    $stmt->execute([$userId]);
    $achats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$aRegler = Panier::commandesARegler($userId);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$etatAchat = static fn (string $s): string => [
    'paid' => 'Paye', 'review' => 'En verification', 'refunded' => 'Rembourse', 'disputed' => 'Conteste',
][$s] ?? $s;

$pageTitle = 'Ma bibliotheque';
$pageDescription = 'Vos titres et vos achats';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-display font-bold text-text">Ma bibliotheque</h1>
                <p class="mt-1 text-sm text-muted"><?php echo count($titres); ?> titre(s) · <?php echo count($sorties); ?> sortie(s)</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/panier.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                <i class="fas fa-basket-shopping mr-2"></i>Mon panier
            </a>
        </div>

        <?php if ($aRegler !== []): ?>
            <div class="alert alert-warning" role="status">
                <i class="fas fa-circle-info mt-0.5"></i>
                <span><?php echo count($aRegler); ?> commande(s) en attente de paiement.
                    <a class="underline" href="<?php echo SITE_URL; ?>/paiement.php?commande=<?php echo e(rawurlencode((string) $aRegler[0]['reference'])); ?>">Regler la plus recente</a></span>
            </div>
        <?php endif; ?>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-titres">
            <h2 id="titre-titres" class="text-lg font-semibold text-text">Mes titres</h2>
            <?php if ($titres === []): ?>
                <p class="mt-4 text-sm text-muted">Aucun titre pour le moment. Les titres que vous achetez apparaissent ici, pour toujours.</p>
            <?php else: ?>
                <ul class="mt-4 divide-y divide-white/5">
                    <?php foreach ($titres as $t): ?>
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <button type="button" data-lire="<?php echo (int) $t['id']; ?>" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent text-white" aria-label="Ecouter <?php echo e($t['title']); ?>">
                                    <i class="fas fa-play"></i>
                                </button>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-text"><?php echo e($t['title']); ?></p>
                                    <p class="truncate text-xs text-muted"><?php echo e($t['stage_name']); ?><?php echo $t['sortie'] ? ' · ' . e($t['sortie']) : ''; ?></p>
                                </div>
                            </div>
                            <span class="text-xs text-muted"><?php echo e(formatDuration((int) $t['duration'])); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($sorties !== []): ?>
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-sorties">
                <h2 id="titre-sorties" class="text-lg font-semibold text-text">Mes sorties</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($sorties as $s): ?>
                        <li class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text"><?php echo e($s['title']); ?></p>
                            <p class="text-xs text-muted"><?php echo e($s['stage_name']); ?> · <?php echo e(ucfirst(str_replace('_', ' ', (string) $s['format']))); ?> · <?php echo (int) $s['total_tracks']; ?> titre(s)</p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-achats">
            <h2 id="titre-achats" class="px-6 pt-6 text-lg font-semibold text-text">Mes achats</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Commande</th><th class="px-6 py-3">Moyen</th><th class="px-6 py-3">Montant</th><th class="px-6 py-3">Etat</th><th class="px-6 py-3">Facture</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($achats === []): ?>
                            <tr><td colspan="6" class="px-6 py-6 text-center text-muted">Aucun achat.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($achats as $a): ?>
                            <tr class="border-b border-white/5">
                                <td class="whitespace-nowrap px-6 py-3 text-xs text-muted"><?php echo $a['paid_at'] ? e(date('d/m/Y', strtotime((string) $a['paid_at']))) : '—'; ?></td>
                                <td class="px-6 py-3 font-mono text-xs text-text"><?php echo e($a['reference']); ?></td>
                                <td class="px-6 py-3 text-xs text-muted"><?php echo e(Factures::libelleMoyen($a['payment_method'])); ?></td>
                                <td class="whitespace-nowrap px-6 py-3 text-text"><?php echo e($fcfa($a['total'])); ?></td>
                                <td class="px-6 py-3 text-xs text-muted"><?php echo e($etatAchat((string) $a['status'])); ?></td>
                                <td class="px-6 py-3 text-xs">
                                    <?php if ($a['invoice_number']): ?>
                                        <a class="text-text underline" href="<?php echo SITE_URL; ?>/facture.php?commande=<?php echo e(rawurlencode((string) $a['reference'])); ?>"><?php echo e($a['invoice_number']); ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                    <?php if (in_array($a['status'], ['paid', 'review'], true)): ?>
                                        <a class="mt-1 block text-muted underline hover:text-text" href="<?php echo SITE_URL; ?>/reclamation.php?commande=<?php echo e(rawurlencode((string) $a['reference'])); ?>">Signaler un probleme</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('click', function (evenement) {
    const bouton = evenement.target.closest('[data-lire]');
    if (bouton && typeof window.playTrack === 'function') {
        window.playTrack(bouton.dataset.lire);
    }
});
</script>

<?php include 'includes/footer-tailwind.php'; ?>
