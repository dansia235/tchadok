<?php
/**
 * Attestation d'une certification Tchadok (CHART-06). Page publique et
 * imprimable (« Imprimer -> Enregistrer en PDF ») : l'artiste la partage.
 */

require_once 'includes/functions.php';
require_once 'includes/barometre.php';
require_once 'includes/kit-presse.php';

$stmt = TchadokDatabase::getInstance()->getConnection()->prepare(
    'SELECT c.*, t.title, a.stage_name FROM certifications c JOIN tracks t ON t.id = c.track_id JOIN artists a ON a.id = t.artist_id
      WHERE c.id = ? AND t.deleted_at IS NULL'
);
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c) {
    show404();
}
$couleurs = ['or' => '#d4a72c', 'platine' => '#9aa5b1', 'diamant' => '#4fb3d9'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certification <?php echo e(CertificationsTchadok::NIVEAUX[$c['level']]); ?> — <?php echo e($c['title']); ?> — Tchadok</title>
    <meta property="og:title" content="<?php echo e($c['title'] . ' - ' . CertificationsTchadok::libelle($c)); ?>">
    <style>
        body { margin: 0; background: #eef1f6; color: #1a1f36; font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .page { max-width: 720px; margin: 32px auto; background: #fff; padding: 48px 40px; border-radius: 12px; text-align: center; border-top: 12px solid <?php echo $couleurs[$c['level']] ?? '#2f6de0'; ?>; }
        h1 { font-size: 30px; margin: 8px 0; } .doux { color: #5b6478; font-size: 13px; }
        .barre { max-width: 720px; margin: 24px auto 0; text-align: right; padding: 0 16px; }
        button { font: inherit; padding: 8px 14px; border-radius: 999px; border: 0; background: #2f6de0; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .barre { display: none; } .page { margin: 0; } }
    </style>
</head>
<body>
    <div class="barre"><button type="button" onclick="window.print()">Imprimer / PDF</button></div>
    <main class="page">
        <p class="doux">BAROMETRE TCHADOK — ATTESTATION N° <?php echo (int) $c['id']; ?></p>
        <h1>Certifie <?php echo e(CertificationsTchadok::NIVEAUX[$c['level']]); ?></h1>
        <p style="font-size:22px;margin:24px 0 4px"><strong><?php echo e($c['title']); ?></strong></p>
        <p style="font-size:18px;margin:0"><?php echo e($c['stage_name']); ?></p>
        <p style="margin-top:28px"><?php echo e(number_format((float) $c['value_at_award'], 0, ',', ' ')); ?> <?php echo e(CertificationsTchadok::BASES[$c['basis']]); ?>
            (seuil : <?php echo e(number_format((float) $c['threshold'], 0, ',', ' ')); ?>)</p>
        <p class="doux">Decernee le <?php echo e(date('d/m/Y', strtotime((string) $c['awarded_at']))); ?>, selon la methodologie publiee : <?php echo e(SITE_URL); ?>/methodologie.php</p>
    </main>
</body>
</html>
