<?php
/**
 * Releve d'un versement (PAYOUT-03) : le document que l'artiste montre a son
 * producteur. Ventes de la periode ligne a ligne, commission appliquee,
 * ajustements, regularisations, net verse -- les composantes s'additionnent
 * exactement au net.
 *
 * Accessible a l'artiste concerne et a la finance (finance.rapport.lire).
 * Page imprimable : « Imprimer -> Enregistrer en PDF ».
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

$id = (int) ($_GET['id'] ?? 0);
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/releve-versement.php?id=' . $id));
}
$r = Versements::releve($id);
$db = TchadokDatabase::getInstance()->getConnection();
$proprietaire = false;
if ($r !== null) {
    $stmt = $db->prepare('SELECT 1 FROM artists WHERE id = ? AND user_id = ?');
    $stmt->execute([$r['versement']['artist_id'], (int) $_SESSION['user_id']]);
    $proprietaire = (bool) $stmt->fetchColumn();
}
if ($r === null || (!$proprietaire && !Autorisations::peut('finance.rapport.lire'))) {
    show404();
}
$p = $r['versement'];
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
header('Cache-Control: private, no-store');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Releve de versement n° <?php echo (int) $p['id']; ?> — Tchadok</title>
    <style>
        body { margin: 0; background: #eef1f6; color: #1a1f36; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .page { max-width: 860px; margin: 24px auto; background: #fff; padding: 36px; border-radius: 12px; box-shadow: 0 4px 24px #0001; }
        .barre { max-width: 860px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 8px; padding: 0 16px; }
        .barre a, .barre button { font: inherit; padding: 8px 14px; border-radius: 999px; border: 1px solid #dde2ea; background: #fff; color: #1a1f36; text-decoration: none; cursor: pointer; }
        .barre button { background: #2f6de0; border-color: #2f6de0; color: #fff; }
        h1 { font-size: 22px; margin: 0 0 4px; } .doux { color: #5b6478; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #dde2ea; text-align: left; vertical-align: top; font-size: 13px; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #5b6478; }
        .num { text-align: right; white-space: nowrap; } .total td { font-weight: 700; border-bottom: 2px solid #1a1f36; }
        h2 { font-size: 15px; margin: 28px 0 0; }
        @media (max-width: 600px) { .page { padding: 18px; margin: 12px; } }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; max-width: none; } .barre { display: none; } }
    </style>
</head>
<body>
    <div class="barre"><a href="<?php echo SITE_URL; ?>/artiste-revenus.php">Mes revenus</a><button type="button" onclick="window.print()">Imprimer / PDF</button></div>
    <main class="page">
        <h1>Releve de versement n° <?php echo (int) $p['id']; ?></h1>
        <p class="doux"><?php echo e($p['stage_name']); ?> · ventes du <?php echo e(date('d/m/Y', strtotime((string) $p['period_start']) ?: time())); ?> au <?php echo e(date('d/m/Y', strtotime((string) $p['period_end']))); ?>
            · <?php echo e(Versements::LIBELLES[$p['status']] ?? $p['status']); ?><?php echo $p['paid_at'] ? ' le ' . e(date('d/m/Y', strtotime((string) $p['paid_at']))) : ''; ?>
            · <?php echo e(Factures::libelleMoyen($p['method'])); ?> <?php echo e($p['destination']); ?></p>

        <h2>Ventes de la periode</h2>
        <table>
            <thead><tr><th>Date</th><th>Commande</th><th>Article</th><th class="num">Prix</th><th class="num">Commission</th><th class="num">Votre part</th></tr></thead>
            <tbody>
                <?php if ($r['ventes'] === []): ?><tr><td colspan="6" class="doux">Aucune vente sur la periode.</td></tr><?php endif; ?>
                <?php foreach ($r['ventes'] as $v): ?>
                    <tr><td><?php echo e(date('d/m/Y', strtotime((string) $v['paid_at']))); ?></td><td><?php echo e($v['reference']); ?></td><td><?php echo e($v['label']); ?></td>
                        <td class="num"><?php echo e($fcfa((float) $v['unit_price'] * (int) $v['quantity'])); ?></td>
                        <td class="num"><?php echo e($fcfa($v['commission'])); ?> (<?php echo e(rtrim(rtrim((string) $v['commission_rate'], '0'), '.')); ?> %)</td>
                        <td class="num"><?php echo e($fcfa($v['artist_net'])); ?></td></tr>
                <?php endforeach; ?>
                <tr class="total"><td colspan="3">Total des ventes</td><td class="num"><?php echo e($fcfa($r['total_brut'])); ?></td><td class="num"><?php echo e($fcfa($r['total_commission'])); ?></td><td class="num"><?php echo e($fcfa($r['total_ventes'])); ?></td></tr>
            </tbody>
        </table>

        <h2>Ajustements</h2>
        <table>
            <tbody>
                <?php if ($r['ajustements'] === []): ?><tr><td class="doux">Aucun.</td></tr><?php endif; ?>
                <?php foreach ($r['ajustements'] as $a): ?>
                    <tr><td><?php echo e(date('d/m/Y', strtotime((string) $a['created_at']))); ?></td><td><?php echo e($a['reason']); ?></td><td class="num"><?php echo e($fcfa($a['amount'])); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Recapitulatif</h2>
        <table>
            <tbody>
                <tr><td>Votre part sur les ventes de la periode</td><td class="num"><?php echo e($fcfa($r['total_ventes'])); ?></td></tr>
                <tr><td>Ajustements</td><td class="num"><?php echo e($fcfa($r['total_ajustements'])); ?></td></tr>
                <tr><td>Regularisations (ventes anterieures encore dues, ou reprises de ventes deja versees puis remboursees ou contestees)</td><td class="num"><?php echo e($fcfa($r['regularisation'])); ?></td></tr>
                <tr class="total"><td>Net verse</td><td class="num"><?php echo e($fcfa($p['net'])); ?></td></tr>
            </tbody>
        </table>
        <p class="doux" style="margin-top:24px">Releve genere le <?php echo date('d/m/Y a H:i'); ?>. Les parts artiste sont figees au moment de chaque vente.</p>
    </main>
</body>
</html>
