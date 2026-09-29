<?php
/**
 * Tableau de bord anti-fraude (STAT-04).
 *
 * Anomalies detectees par la certification (STAT-03), par gravite, avec le
 * detail des signaux. Actions : valider (les ecoutes deviennent certifiees),
 * rejeter, mettre un artiste ou un compte sous surveillance. Chaque decision
 * est motivee et inscrite au journal d'audit : un moderateur instruit sans
 * acces direct a la base.
 *
 * Consultation : statistique.lire. Decisions : ecoute.moderer.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/certification.php';

if (!Autorisations::peut('statistique.lire') && !Autorisations::peut('ecoute.moderer')) {
    Autorisations::exiger('ecoute.moderer');
}
$moi = (int) $_SESSION['user_id'];
$peutDecider = Autorisations::peut('ecoute.moderer');
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Autorisations::exiger('ecoute.moderer');
    $action = (string) ($_POST['action'] ?? '');
    $motif = (string) ($_POST['motif'] ?? '');
    $resultat = match ($action) {
        'valider', 'rejeter' => Certification::decider((string) ($_POST['signaux'] ?? ''), (int) ($_POST['titre'] ?? 0), (string) ($_POST['jour'] ?? ''), $action, $motif, $moi),
        'surveiller'         => Certification::surveiller((string) ($_POST['type'] ?? ''), (int) ($_POST['cible'] ?? 0), $motif, $moi),
        'lever'              => Certification::leverSurveillance((int) ($_POST['surveillance'] ?? 0), $motif, $moi),
        'revoquer'           => Certification::revoquer((int) ($_POST['titre'] ?? 0), (string) ($_POST['jour'] ?? ''), (string) ($_POST['ip'] ?? ''), $motif, $moi),
        default              => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/admin/anti-fraude.php');
    }
    $erreur = $resultat['message'];
}

$sante = Certification::sante(30);
$alerte = $sante['part_quarantaine'] > Certification::seuilAlerte();
$anomalies = Certification::anomalies();
$surveillances = Certification::surveillances();
$titres = Certification::titresConcernes();
$evolution = Certification::evolution();
$maxEvolution = max(1, ...array_column($evolution, 'jugees') ?: [1]);
$libelleSignaux = static fn (string $s): string => implode(' + ', array_map(static fn ($x) => Certification::SIGNAUX[$x] ?? $x, explode(',', $s)));
$couleur = ['haute' => 'border-rose-400/40 bg-rose-400/10 text-rose-200', 'moyenne' => 'border-amber-400/40 bg-amber-400/10 text-amber-200', 'basse' => 'border-white/10 bg-white/5 text-muted'];
$nombre = static fn ($v): string => number_format((float) $v, 0, ',', ' ');

$pageTitle = 'Anti-fraude';
$pageDescription = 'Ecoutes en quarantaine et surveillance';
$hideTopNav = true;
$hideFooter = true;

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Mesure</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Anti-fraude des ecoutes</h1>
                    <p class="mt-3 max-w-3xl text-sm text-muted">Seules les ecoutes certifiees comptent pour les classements et la remuneration. Une ecoute en quarantaine n'est ni supprimee ni comptee : elle attend votre decision, motivee et tracee.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <?php if ($alerte): ?>
                <div class="alert alert-danger mt-6" role="alert" data-alerte-fraude><i class="fas fa-triangle-exclamation mt-0.5"></i>
                    <span><?php echo e($sante['part_quarantaine']); ?> % des ecoutes jugees sur 30 jours sont suspectes (seuil d'alerte : <?php echo e(Certification::seuilAlerte()); ?> %).</span></div>
            <?php endif; ?>

            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                <?php foreach (['brut' => 'Ecoutes brutes', 'certifiees' => 'Certifiees', 'exclues' => 'Exclues (propres titres)', 'quarantaine' => 'En quarantaine', 'rejetees' => 'Rejetees', 'en_attente' => 'Pas encore jugees'] as $cle => $libelle): ?>
                    <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted"><?php echo e($libelle); ?></dt><dd class="mt-1 text-xl font-semibold text-text"><?php echo e($nombre($sante[$cle])); ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <p class="mt-2 text-xs text-muted">30 derniers jours. Part suspecte : <?php echo e($sante['part_quarantaine']); ?> %. Volume certifie toujours inferieur ou egal au volume brut.</p>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-anomalies">
            <h2 id="titre-anomalies" class="text-lg font-semibold text-text">Anomalies a instruire <span class="text-muted">(<?php echo count($anomalies); ?>)</span></h2>
            <?php if ($anomalies === []): ?><p class="mt-3 text-sm text-muted">Aucune ecoute en quarantaine.</p><?php endif; ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($anomalies as $a): $g = Certification::gravite($a); ?>
                    <article class="rounded-2xl border p-4 <?php echo $couleur[$g]; ?>">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide">Gravite <?php echo e($g); ?> · <?php echo e(date('d/m/Y', strtotime((string) $a['jour']))); ?></p>
                                <h3 class="mt-1 text-sm font-semibold text-text"><?php echo e($a['title']); ?> — <?php echo e($a['stage_name']); ?></h3>
                                <p class="mt-1 text-sm"><?php echo e($libelleSignaux((string) $a['signals'])); ?></p>
                                <p class="mt-1 text-xs text-muted"><?php echo e($nombre($a['ecoutes'])); ?> ecoute(s), <?php echo (int) $a['adresses']; ?> adresse(s) IP, <?php echo (int) $a['auditeurs']; ?> auditeur(s) · de <?php echo e(date('H:i', strtotime((string) $a['premiere']))); ?> a <?php echo e(date('H:i', strtotime((string) $a['derniere']))); ?></p>
                            </div>
                            <?php if ($peutDecider): ?>
                                <form method="POST" class="flex min-w-[260px] flex-1 flex-wrap items-end gap-2 sm:max-w-md">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="signaux" value="<?php echo e($a['signals']); ?>">
                                    <input type="hidden" name="titre" value="<?php echo (int) $a['track_id']; ?>">
                                    <input type="hidden" name="jour" value="<?php echo e($a['jour']); ?>">
                                    <label class="w-full text-xs text-muted">Motif de la decision<input name="motif" required minlength="5" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text"></label>
                                    <button name="action" value="rejeter" class="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">Rejeter</button>
                                    <button name="action" value="valider" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Valider (certifier)</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-surveillance">
                <h2 id="titre-surveillance" class="text-lg font-semibold text-text">Sous surveillance</h2>
                <p class="mt-1 text-xs text-muted">Toutes les ecoutes d'un artiste (ses titres) ou d'un compte surveille passent en revue humaine.</p>
                <ul class="mt-3 divide-y divide-white/5 text-sm">
                    <?php if ($surveillances === []): ?><li class="py-2 text-muted">Personne.</li><?php endif; ?>
                    <?php foreach ($surveillances as $s): ?>
                        <li class="py-3">
                            <span class="text-text"><?php echo e($s['target_type'] === 'artiste' ? 'Artiste' : 'Compte'); ?> <?php echo e($s['nom'] ?? '#' . $s['target_id']); ?></span>
                            <span class="block text-xs text-muted"><?php echo e($s['reason']); ?> — depuis le <?php echo e(date('d/m/Y', strtotime((string) $s['created_at']))); ?><?php echo $s['auteur'] ? ' par ' . e($s['auteur']) : ''; ?></span>
                            <?php if ($peutDecider): ?>
                                <form method="POST" class="mt-2 flex gap-2">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="lever"><input type="hidden" name="surveillance" value="<?php echo (int) $s['id']; ?>">
                                    <input name="motif" required minlength="5" placeholder="Motif de la levee" class="w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text">
                                    <button class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Lever</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($peutDecider): ?>
                    <h3 class="mt-6 text-sm font-semibold text-text">Revoquer des ecoutes deja certifiees</h3>
                    <p class="mt-1 text-xs text-muted">Fraude constatee apres coup. Les ecoutes restent dans l'historique, mais ne comptent plus apres le recalcul de la nuit.</p>
                    <form method="POST" class="mt-2 grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[100px_140px_140px_1fr_auto] sm:items-end">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="revoquer">
                        <label class="text-xs text-muted">Titre (id)<input name="titre" type="number" min="1" required class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <label class="text-xs text-muted">Jour<input name="jour" type="date" required class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <label class="text-xs text-muted">Adresse IP (facultatif)<input name="ip" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <label class="text-xs text-muted">Motif<input name="motif" required minlength="5" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <button class="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">Revoquer</button>
                    </form>
                    <h3 class="mt-6 text-sm font-semibold text-text">Mettre sous surveillance</h3>
                    <form method="POST" class="mt-2 grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[120px_120px_1fr_auto] sm:items-end">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="surveiller">
                        <label class="text-xs text-muted">Type<select name="type" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"><option value="artiste">Artiste</option><option value="compte">Compte</option></select></label>
                        <label class="text-xs text-muted">Identifiant<input name="cible" type="number" min="1" required class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <label class="text-xs text-muted">Motif<input name="motif" required minlength="5" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text"></label>
                        <button class="rounded-full bg-accent px-3 py-1.5 text-xs font-semibold text-white">Surveiller</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-tendances">
                <h2 id="titre-tendances" class="text-lg font-semibold text-text">Evolution (14 jours)</h2>
                <ul class="mt-3 space-y-1 text-xs text-muted">
                    <?php if ($evolution === []): ?><li>Aucune ecoute jugee.</li><?php endif; ?>
                    <?php foreach ($evolution as $j): ?>
                        <li class="flex items-center gap-2"><span class="w-16"><?php echo e(date('d/m', strtotime($j['jour']))); ?></span>
                            <span class="relative h-2 flex-1 rounded-full bg-white/10" role="img" aria-label="<?php echo $j['jugees']; ?> jugees, <?php echo $j['suspectes']; ?> suspectes">
                                <span class="absolute inset-y-0 left-0 rounded-full bg-emerald-400/60" style="width: <?php echo round(100 * $j['jugees'] / $maxEvolution, 1); ?>%"></span>
                                <span class="absolute inset-y-0 left-0 rounded-full bg-rose-400" style="width: <?php echo round(100 * $j['suspectes'] / $maxEvolution, 1); ?>%"></span>
                            </span>
                            <span class="w-24 text-right"><?php echo e($nombre($j['suspectes'])); ?> / <?php echo e($nombre($j['jugees'])); ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <h3 class="mt-6 text-sm font-semibold text-text">Titres les plus concernes (30 jours)</h3>
                <ul class="mt-2 divide-y divide-white/5 text-xs text-muted">
                    <?php if ($titres === []): ?><li class="py-2">Aucun.</li><?php endif; ?>
                    <?php foreach ($titres as $t): ?>
                        <li class="py-2"><span class="text-text"><?php echo e($t['title']); ?></span> — <?php echo e($t['stage_name']); ?> : <?php echo e($nombre($t['suspectes'])); ?> suspecte(s) sur <?php echo e($nombre($t['ecoutes'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
