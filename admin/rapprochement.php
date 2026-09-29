<?php
/**
 * Rapprochement avec les releves des operateurs (PAY-10).
 *
 * Consultation : permission finance.rapport.lire.
 * Cloture d'un ecart : finance.remboursement.executer, motif obligatoire,
 * trace au journal d'audit. Un ecart ne se supprime jamais.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';

Autorisations::exiger('finance.rapport.lire');
$peutClore = Autorisations::peut('finance.remboursement.executer');

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'clore') {
        Autorisations::exiger('finance.remboursement.executer');
        $motif = trim((string) ($_POST['motif'] ?? ''));
        if (mb_strlen($motif) < 5) {
            $erreur = 'Motif obligatoire (5 caracteres au moins) : il figure au journal d\'audit.';
        } elseif (Rapprochement::cloturer((int) ($_POST['ecart'] ?? 0), $motif, (int) $_SESSION['user_id'])) {
            setFlashMessage(FLASH_SUCCESS, 'Ecart clos.');
            redirect(SITE_URL . '/admin/rapprochement.php');
        } else {
            $erreur = 'Ecart introuvable ou deja clos.';
        }
    } elseif (($_POST['action'] ?? '') === 'executer') {
        $date = (string) ($_POST['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= date('Y-m-d')) {
            $bilans = Rapprochement::executerTout($date);
            setFlashMessage(FLASH_SUCCESS, sprintf('Rapprochement du %s : %d ecart(s) releve(s).', $date, array_sum(array_column($bilans, 'ecarts'))));
            redirect(SITE_URL . '/admin/rapprochement.php');
        }
        $erreur = 'Date invalide.';
    }
}

$db = TchadokDatabase::getInstance()->getConnection();
$ouverts = Rapprochement::ouverts();
$executions = $db ? $db->query('SELECT * FROM reconciliation_runs ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC) : [];
$clos = $db ? $db->query(
    'SELECT d.*, u.username FROM reconciliation_discrepancies d LEFT JOIN users u ON u.id = d.resolved_by
      WHERE d.resolved_at IS NOT NULL ORDER BY d.resolved_at DESC LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC) : [];

$montant = static fn (?int $v, ?string $devise): string => $v === null ? '—'
    : Devises::formater(($devise === 'USD' ? $v / 100 : (float) $v), (string) ($devise ?: 'XAF'));
$libellesPasserelles = array_map(fn($p) => $p->libelle(), FabriquePasserelles::disponibles());

$pageTitle = 'Rapprochement';
$pageDescription = 'Ecarts entre les releves des operateurs et les paiements enregistres';
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
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Rapprochement</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                        Chaque jour, le releve de chaque operateur est compare aux paiements enregistres par Tchadok.
                        Un ecart est un litige a venir : il reste ouvert jusqu'a sa cloture, motivee et tracee.
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

            <form method="POST" class="mt-6 flex flex-wrap items-end gap-3">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="executer">
                <div>
                    <label for="date" class="text-xs font-semibold text-muted">Rapprocher la journee du</label>
                    <input id="date" type="date" name="date" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"
                           class="mt-2 rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                </div>
                <button type="submit" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">
                    <i class="fas fa-scale-balanced mr-2"></i>Lancer
                </button>
                <span class="text-xs text-muted">Execution automatique quotidienne : <code>php scripts/rapprochement.php</code></span>
            </form>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-ouverts">
            <h2 id="titre-ouverts" class="px-6 pt-6 text-lg font-semibold text-text">Ecarts ouverts <span class="text-muted">(<?php echo count($ouverts); ?>)</span></h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Jour</th><th class="px-4 py-3">Passerelle</th><th class="px-4 py-3">Ecart</th>
                            <th class="px-4 py-3">Reference</th><th class="px-4 py-3">Operateur</th><th class="px-4 py-3">Tchadok</th>
                            <th class="px-4 py-3">Detail</th><?php if ($peutClore): ?><th class="px-4 py-3">Cloture</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ouverts): ?>
                            <tr><td colspan="8" class="px-4 py-6 text-center text-muted">Aucun ecart ouvert.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ouverts as $o): ?>
                            <tr class="border-b border-white/5 align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted"><?php echo e(date('d/m/Y', strtotime((string) $o['statement_date']))); ?></td>
                                <td class="px-4 py-3 text-text"><?php echo e($libellesPasserelles[$o['gateway']] ?? $o['gateway']); ?></td>
                                <td class="px-4 py-3 text-text"><?php echo e(Rapprochement::TYPES[$o['type']] ?? $o['type']); ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-muted">
                                    <?php echo e($o['gateway_ref'] ?? '—'); ?>
                                    <?php if ($o['order_reference']): ?><span class="block text-text"><?php echo e($o['order_reference']); ?></span><?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs"><?php echo e($montant($o['operator_amount'] !== null ? (int) $o['operator_amount'] : null, $o['currency'])); ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs"><?php echo e($montant($o['platform_amount'] !== null ? (int) $o['platform_amount'] : null, $o['currency'])); ?></td>
                                <td class="px-4 py-3 text-xs text-muted"><?php echo e($o['detail']); ?></td>
                                <?php if ($peutClore): ?>
                                    <td class="px-4 py-3">
                                        <form method="POST" class="flex min-w-[240px] gap-2">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="clore">
                                            <input type="hidden" name="ecart" value="<?php echo (int) $o['id']; ?>">
                                            <label class="sr-only" for="motif-<?php echo (int) $o['id']; ?>">Motif de cloture</label>
                                            <input id="motif-<?php echo (int) $o['id']; ?>" name="motif" required minlength="5" placeholder="Motif (rembourse, corrige...)"
                                                   class="w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text">
                                            <button type="submit" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text hover:bg-white/10">Clore</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-executions">
                <h2 id="titre-executions" class="text-lg font-semibold text-text">Dernieres executions</h2>
                <ul class="mt-4 divide-y divide-white/5 text-sm">
                    <?php if (!$executions): ?><li class="py-3 text-muted">Aucune execution.</li><?php endif; ?>
                    <?php foreach ($executions as $x): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span class="text-text"><?php echo e(date('d/m/Y', strtotime((string) $x['statement_date']))); ?> · <?php echo e($libellesPasserelles[$x['gateway']] ?? $x['gateway']); ?></span>
                            <span class="text-xs <?php echo $x['status'] === 'ok' ? 'text-emerald-300' : ($x['status'] === 'ecarts' ? 'text-amber-300' : 'text-rose-300'); ?>">
                                <?php echo e($x['status'] === 'erreur' ? 'erreur : ' . ($x['error_message'] ?? '?')
                                    : sprintf('%s — releve %d, Tchadok %d, %d ecart(s)', $x['status'], $x['operator_lines'], $x['platform_lines'], $x['discrepancies'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-clos">
                <h2 id="titre-clos" class="text-lg font-semibold text-text">Derniers ecarts clos</h2>
                <ul class="mt-4 divide-y divide-white/5 text-sm">
                    <?php if (!$clos): ?><li class="py-3 text-muted">Aucun.</li><?php endif; ?>
                    <?php foreach ($clos as $c): ?>
                        <li class="py-3">
                            <span class="text-text"><?php echo e(Rapprochement::TYPES[$c['type']] ?? $c['type']); ?></span>
                            <span class="block text-xs text-muted">
                                <?php echo e(sprintf('%s par %s : %s', date('d/m/Y H:i', strtotime((string) $c['resolved_at'])), $c['username'] ?? 'ligne de commande', $c['resolution'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
