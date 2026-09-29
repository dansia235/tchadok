<?php
/**
 * Plans d'abonnement (SUB-01).
 *
 * Libelle et activation d'un plan ; le PRIX se regle dans la grille tarifaire
 * (admin/tarifs.php, DATA-04), seule source des prix, deja journalisee. Un
 * changement n'affecte pas les abonnements en cours : leur prix est fige.
 *
 * Consultation et modification : permission tarif.modifier.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';

Autorisations::exiger('tarif.modifier');
$db = TchadokDatabase::getInstance()->getConnection();
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['plan'] ?? 0);
    $libelle = trim((string) ($_POST['label'] ?? ''));
    $actif = isset($_POST['is_active']) ? 1 : 0;
    $stmt = $db->prepare('SELECT * FROM subscription_plans WHERE id = ?');
    $stmt->execute([$id]);
    $avant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$avant || mb_strlen($libelle) < 3 || mb_strlen($libelle) > 80) {
        $erreur = 'Plan inconnu ou libelle invalide (3 a 80 caracteres).';
    } else {
        $db->prepare('UPDATE subscription_plans SET label = ?, is_active = ?, updated_by = ? WHERE id = ?')
           ->execute([$libelle, $actif, (int) $_SESSION['user_id'], $id]);
        JournalAudit::enregistrer('tarif.modifie', [
            'cible_type' => 'plan_abonnement', 'cible_id' => $avant['code'],
            'avant' => ['libelle' => $avant['label'], 'actif' => (int) $avant['is_active']],
            'apres' => ['libelle' => $libelle, 'actif' => $actif],
        ]);
        setFlashMessage(FLASH_SUCCESS, 'Plan mis a jour.');
        redirect(SITE_URL . '/admin/abonnements.php');
    }
}

$plans = Abonnements::plans(false);
$stats = $db->query(
    "SELECT
        (SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status = 'active' AND start_date <= NOW() AND end_date > NOW()) AS actifs,
        (SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status = 'active' AND end_date > NOW() AND cancelled_at IS NOT NULL) AS resilies,
        (SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND end_date > NOW() AND end_date <= NOW() + INTERVAL 7 DAY) AS echeance_7j,
        (SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE order_id IS NOT NULL AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS encaisse_mois"
)->fetch(PDO::FETCH_ASSOC);
$abonnes = $db->query(
    "SELECT p.code, COUNT(DISTINCT s.user_id) AS n FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
      WHERE s.status = 'active' AND s.start_date <= NOW() AND s.end_date > NOW() GROUP BY p.code"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

$pageTitle = 'Abonnements';
$pageDescription = 'Plans Premium';
$hideTopNav = true;
$hideFooter = true;

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Abonnements Premium</h1>
                    <p class="mt-3 max-w-2xl text-sm text-muted">Les prix se reglent dans <a class="underline" href="<?php echo SITE_URL; ?>/admin/tarifs.php">la grille tarifaire</a>. Un changement de prix n'affecte pas les abonnements en cours.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <dl class="mt-6 grid gap-3 sm:grid-cols-4">
                <?php foreach (['actifs' => 'Abonnes actifs', 'resilies' => 'Resilies, encore actifs', 'echeance_7j' => 'Echeance sous 7 jours', 'encaisse_mois' => 'Souscrit ce mois'] as $cle => $libelle): ?>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <dt class="text-xs text-muted"><?php echo e($libelle); ?></dt>
                        <dd class="mt-1 text-xl font-semibold text-text"><?php echo $cle === 'encaisse_mois' ? e($fcfa($stats[$cle])) : (int) $stats[$cle]; ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-plans">
            <h2 id="titre-plans" class="text-lg font-semibold text-text">Plans</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($plans as $p): ?>
                    <form method="POST" class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="plan" value="<?php echo (int) $p['id']; ?>">
                        <div class="min-w-[220px] flex-1">
                            <label class="text-xs text-muted" for="label-<?php echo (int) $p['id']; ?>">Libelle (<?php echo e($p['code']); ?>, <?php echo (int) $p['duree']; ?> mois)</label>
                            <input id="label-<?php echo (int) $p['id']; ?>" name="label" value="<?php echo e($p['label']); ?>" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                        </div>
                        <div class="text-sm text-text"><?php echo e($fcfa($p['prix'])); ?><span class="block text-xs text-muted"><?php echo (int) ($abonnes[$p['code']] ?? 0); ?> abonne(s)</span></div>
                        <label class="flex items-center gap-2 text-sm text-text"><input type="checkbox" name="is_active" <?php echo $p['actif'] ? 'checked' : ''; ?>> Propose</label>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Enregistrer</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
