<?php
/**
 * Facture d'une commande payee (SHOP-03).
 *
 *   facture.php?commande=TCHK-2026-XXXXXXXX
 *
 * Accessible a l'acheteur, et a l'equipe finance (finance.transaction.lire).
 * Page autonome et imprimable : « Imprimer » -> « Enregistrer en PDF » donne
 * le document a archiver. Une generation PDF cote serveur viendra avec la
 * gestion des dependances (QA-01).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/factures.php';
require_once 'includes/paiement/chargement.php';

$reference = (string) ($_GET['commande'] ?? '');
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/facture.php?commande=' . $reference));
}

$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT id, user_id FROM orders WHERE reference = ?');
$stmt->execute([$reference]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

// Une facture d'autrui n'existe pas, sauf pour la finance.
if (!$commande || ((int) $commande['user_id'] !== (int) $_SESSION['user_id'] && !Autorisations::peut('finance.transaction.lire'))) {
    show404();
}

$f = Factures::donnees((int) $commande['id']);
if ($f === null) {
    show404();
}

$c = $f['commande'];
$r = $f['reglement'];
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$client = $c['anonymized_at'] !== null ? 'Client (compte anonymise)' : trim($c['first_name'] . ' ' . $c['last_name']);
$mention = match ($c['status']) {
    'refunded' => 'Commande remboursee le ' . date('d/m/Y', strtotime((string) $c['refunded_at'])),
    'disputed' => 'Paiement conteste le ' . date('d/m/Y', strtotime((string) $c['refunded_at'])),
    default    => null,
};

header('Cache-Control: private, no-store');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Facture <?php echo e($c['invoice_number']); ?> — Tchadok</title>
    <style>
        :root { --texte: #1a1f36; --doux: #5b6478; --bord: #dde2ea; --accent: #2f6de0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f6; color: var(--texte); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .page { max-width: 820px; margin: 24px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px #0001; }
        .barre { max-width: 820px; margin: 24px auto 0; display: flex; gap: 8px; justify-content: flex-end; padding: 0 16px; }
        .barre a, .barre button { font: inherit; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--bord); background: #fff; color: var(--texte); text-decoration: none; cursor: pointer; }
        .barre button { background: var(--accent); color: #fff; border-color: var(--accent); }
        header { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 2px solid var(--texte); padding-bottom: 16px; }
        h1 { font-size: 26px; margin: 0; letter-spacing: .02em; }
        .doux { color: var(--doux); font-size: 13px; }
        .blocs { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--bord); text-align: left; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--doux); }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; font-size: 16px; border-bottom: 2px solid var(--texte); }
        .mention { margin-top: 16px; padding: 10px 12px; background: #fef3f2; color: #b42318; border-radius: 8px; }
        footer { margin-top: 32px; font-size: 12px; color: var(--doux); }
        @media (max-width: 600px) { .page { padding: 20px; margin: 12px; } .blocs { grid-template-columns: 1fr; } }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; max-width: none; } .barre { display: none; } }
    </style>
</head>
<body>
    <div class="barre">
        <a href="<?php echo SITE_URL; ?>/bibliotheque.php">Ma bibliotheque</a>
        <button type="button" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <main class="page">
        <header>
            <div>
                <strong style="font-size:18px"><?php echo e($f['editeur']['nom']); ?></strong>
                <div class="doux"><?php echo nl2br(e($f['editeur']['adresse'])); ?></div>
                <?php if ($f['editeur']['identifiants'] !== ''): ?><div class="doux"><?php echo e($f['editeur']['identifiants']); ?></div><?php endif; ?>
                <?php if ($f['editeur']['contact'] !== ''): ?><div class="doux"><?php echo e($f['editeur']['contact']); ?></div><?php endif; ?>
            </div>
            <div style="text-align:right">
                <h1>FACTURE</h1>
                <div><strong><?php echo e($c['invoice_number']); ?></strong></div>
                <div class="doux">Date : <?php echo e(date('d/m/Y', strtotime((string) $c['paid_at']))); ?></div>
                <div class="doux">Commande : <?php echo e($c['reference']); ?></div>
            </div>
        </header>

        <div class="blocs">
            <div>
                <div class="doux">Client</div>
                <strong><?php echo e($client); ?></strong>
            </div>
            <div>
                <div class="doux">Reglement</div>
                <strong><?php echo e(Factures::libelleMoyen($c['payment_method'])); ?></strong>
                <?php if ($r): ?>
                    <div class="doux">Reference operateur : <?php echo e($r['gateway_ref']); ?></div>
                    <?php if ($r['currency'] !== 'XAF'): ?>
                        <div class="doux">Debite : <?php echo e(Devises::formater((float) $r['amount'], (string) $r['currency'])); ?>
                            (1 <?php echo e($r['currency']); ?> = <?php echo e(number_format((float) $r['exchange_rate'], 0, ',', ' ')); ?> FCFA)</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead><tr><th>Designation</th><th>Artiste</th><th class="num">Qte</th><th class="num">Prix</th><th class="num">Montant</th></tr></thead>
            <tbody>
                <?php foreach ($f['lignes'] as $l): ?>
                    <tr>
                        <td><?php echo e($l['label']); ?><div class="doux"><?php echo e(['track' => 'Titre (achat numerique)', 'release' => 'Sortie complete (achat numerique)', 'subscription' => 'Abonnement'][$l['item_type']] ?? $l['item_type']); ?></div></td>
                        <td><?php echo e($l['stage_name'] ?? '—'); ?></td>
                        <td class="num"><?php echo (int) $l['quantity']; ?></td>
                        <td class="num"><?php echo e($fcfa($l['unit_price'])); ?></td>
                        <td class="num"><?php echo e($fcfa((float) $l['unit_price'] * (int) $l['quantity'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr><td colspan="4" class="num">Sous-total</td><td class="num"><?php echo e($fcfa($c['subtotal'])); ?></td></tr>
                <tr><td colspan="4" class="num">Frais de transaction</td><td class="num"><?php echo e($fcfa($c['gateway_fee'])); ?></td></tr>
                <tr class="total"><td colspan="4" class="num">Total paye</td><td class="num"><?php echo e($fcfa($c['total'])); ?></td></tr>
            </tbody>
        </table>

        <p class="doux"><?php echo e($f['editeur']['tva']); ?>.</p>
        <?php if ($mention): ?><p class="mention"><?php echo e($mention); ?></p><?php endif; ?>

        <footer>
            Achat de contenus numeriques, disponibles dans l'espace personnel de l'acheteur.
            Une part de chaque vente revient a l'artiste, qui peut en demander le versement.
            Document genere le <?php echo date('d/m/Y a H:i'); ?>.
        </footer>
    </main>
</body>
</html>
