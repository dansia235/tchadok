<?php
/**
 * Remuneration des artistes (decision du 28/09/2026).
 *
 * Un seul ecran pour ce que touche un artiste :
 *   - ventes a l'unite : part = 100 % - commission de la grille (admin/tarifs.php) ;
 *   - abonnements Premium : pourcentage reverse aux artistes, regle ICI,
 *     et repartition mensuelle au prorata des ecoutes de chaque abonne.
 *
 * Consultation : tarif.modifier ou finance.rapport.lire.
 * Changer le pourcentage : tarif.modifier.
 * Cloturer un mois : finance.versement.creer.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';

if (!Autorisations::peut('tarif.modifier') && !Autorisations::peut('finance.rapport.lire')) {
    Autorisations::exiger('tarif.modifier');
}
$moi = (int) $_SESSION['user_id'];
$peutRegler = Autorisations::peut('tarif.modifier');
$peutCloturer = Autorisations::peut('finance.versement.creer');
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'taux') {
        Autorisations::exiger('tarif.modifier');
        $resultat = RepartitionAbonnements::definirTaux(
            (float) str_replace(',', '.', (string) ($_POST['taux'] ?? '')),
            (string) ($_POST['mois'] ?? ''),
            (string) ($_POST['motif'] ?? ''),
            $moi
        );
    } elseif ($action === 'cloturer') {
        Autorisations::exiger('finance.versement.creer');
        $resultat = RepartitionAbonnements::cloturer((string) ($_POST['mois'] ?? ''), $moi);
    } else {
        $resultat = ['succes' => false, 'message' => 'Action inconnue.'];
    }
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/admin/remuneration.php');
    }
    $erreur = $resultat['message'];
}

$tauxActuel = RepartitionAbonnements::taux();
$historique = RepartitionAbonnements::historique();
$aVenir = array_values(array_filter($historique, static fn ($h) => $h['effective_from'] > date('Y-m-d')));
$premierMois = RepartitionAbonnements::premierMoisModifiable();
$enAttente = RepartitionAbonnements::moisEnAttente();
$moisApercu = (string) ($_GET['mois'] ?? ($enAttente[count($enAttente) - 1] ?? date('Y-m')));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $moisApercu)) {
    $moisApercu = date('Y-m');
}
$apercu = RepartitionAbonnements::cloturee($moisApercu);
$dejaClos = $apercu !== null;
$apercu ??= RepartitionAbonnements::calculer($moisApercu);
$repartitions = RepartitionAbonnements::repartitions();

$libelles = [
    'track:' => 'Titre a l\'unite', 'release:single' => 'Single', 'release:maxi_single' => 'Maxi single',
    'release:ep' => 'EP', 'release:album' => 'Album', 'release:compilation' => 'Compilation',
];
$ventes = [];
foreach (Tarifs::grille() as $cle => $regle) {
    if (isset($libelles[$cle])) {
        $ventes[] = ['libelle' => $libelles[$cle], 'commission' => (float) $regle['commission']];
    }
}
$pct = static fn ($v): string => RepartitionAbonnements::pourcent((float) $v) . ' %';
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

$pageTitle = 'Remuneration des artistes';
$pageDescription = 'Part des artistes sur les ventes et les abonnements';
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
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Remuneration des artistes</h1>
                    <p class="mt-3 max-w-3xl text-sm text-muted">Ce que touche un artiste sur chaque vente et sur les abonnements Premium. Tout changement est date, motive, inscrit au journal d'audit, et ne touche jamais une vente conclue ni un mois deja reparti.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-abonnements">
                <h2 id="titre-abonnements" class="text-lg font-semibold text-text">Abonnements Premium</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-5">
                        <p class="text-xs text-muted">Part des artistes ce mois-ci</p>
                        <p class="mt-1 text-4xl font-bold text-text" data-taux-artistes><?php echo e($pct($tauxActuel)); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="text-xs text-muted">Part de la plateforme</p>
                        <p class="mt-1 text-4xl font-semibold text-text"><?php echo e($pct(100 - $tauxActuel)); ?></p>
                        <p class="mt-1 text-xs text-muted">Frais de paiement, hebergement, diffusion.</p>
                    </div>
                </div>
                <?php foreach ($aVenir as $h): ?>
                    <p class="mt-3 text-sm text-amber-200"><i class="fas fa-clock mr-1"></i>Programme : <?php echo e($pct($h['artist_rate'])); ?> a partir du <?php echo e(date('d/m/Y', strtotime((string) $h['effective_from']))); ?>.</p>
                <?php endforeach; ?>
                <p class="mt-4 text-sm leading-6 text-muted">
                    Chaque mois, la part d'un abonne (son abonnement du mois &times; ce pourcentage) va aux artistes <strong>qu'il a ecoutes</strong>, au prorata de ses ecoutes d'au moins <?php echo RepartitionAbonnements::ecouteMinimale(); ?> s.
                    Une ecoute fabriquee ne detourne ainsi que l'abonnement de celui qui la fabrique. La part d'un abonne qui n'a rien ecoute reste a la plateforme.
                </p>

                <?php if ($peutRegler): ?>
                    <form method="POST" class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[120px_160px_1fr_auto] sm:items-end">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="taux">
                        <label class="text-xs text-muted">Part artistes (%)
                            <input name="taux" type="number" min="0" max="100" step="0.5" required value="<?php echo e(RepartitionAbonnements::pourcent($tauxActuel)); ?>" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                        <label class="text-xs text-muted">A partir de
                            <input name="mois" type="month" required min="<?php echo e($premierMois); ?>" value="<?php echo e(date('Y-m', strtotime(date('Y-m-01') . ' +1 month'))); ?>" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                        <label class="text-xs text-muted">Motif (communique aux artistes)
                            <input name="motif" required minlength="5" maxlength="300" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Programmer</button>
                        <p class="text-xs text-muted sm:col-span-4">Une baisse prend effet au plus tot le mois prochain (preavis). Premier mois modifiable : <?php echo e(RepartitionAbonnements::libelleMois($premierMois)); ?>. Les artistes actifs sont prevenus par e-mail.</p>
                    </form>
                <?php endif; ?>

                <h3 class="mt-6 text-sm font-semibold text-text">Historique</h3>
                <ul class="mt-2 divide-y divide-white/5 text-xs text-muted">
                    <?php foreach ($historique as $h): ?>
                        <li class="py-2"><span class="text-text"><?php echo e($pct($h['artist_rate'])); ?></span> a partir du <?php echo e(date('d/m/Y', strtotime((string) $h['effective_from']))); ?> — <?php echo e($h['reason']); ?>
                            <span class="block">decide le <?php echo e(date('d/m/Y', strtotime((string) $h['created_at']))); ?><?php echo $h['username'] ? ' par ' . e($h['username']) : ''; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-ventes">
                <h2 id="titre-ventes" class="text-lg font-semibold text-text">Ventes a l'unite</h2>
                <p class="mt-1 text-xs text-muted">Part artiste = 100 % - commission de la grille. Figee sur chaque vente.</p>
                <table class="mt-4 w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="py-2 text-left">Produit</th><th class="py-2 text-right">Artiste</th><th class="py-2 text-right">Plateforme</th></tr></thead>
                    <tbody>
                        <?php foreach ($ventes as $v): ?>
                            <tr class="border-t border-white/5"><td class="py-2 text-text"><?php echo e($v['libelle']); ?></td><td class="py-2 text-right font-semibold text-emerald-300"><?php echo e($pct(100 - $v['commission'])); ?></td><td class="py-2 text-right text-muted"><?php echo e($pct($v['commission'])); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($peutRegler): ?><a href="<?php echo SITE_URL; ?>/admin/tarifs.php" class="mt-4 inline-block rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">Modifier dans la grille tarifaire</a><?php endif; ?>
            </section>
        </div>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-repartition">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="titre-repartition" class="text-lg font-semibold text-text">Repartition de <?php echo e(RepartitionAbonnements::libelleMois($moisApercu)); ?></h2>
                    <p class="mt-1 text-xs text-muted"><?php echo $dejaClos ? 'Mois cloture : chiffres figes au moment de la cloture ci-dessous.' : 'Apercu calcule a l\'instant, rien n\'est encore credite.'; ?></p>
                </div>
                <form method="GET" class="flex items-end gap-2">
                    <label class="text-xs text-muted">Mois<input type="month" name="mois" value="<?php echo e($moisApercu); ?>" class="mt-1 block rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                    <button class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text">Afficher</button>
                </form>
            </div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Revenu d'abonnement du mois</dt><dd class="mt-1 text-lg font-semibold text-text"><?php echo e($fcfa($apercu['revenu'])); ?></dd></div>
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Part artistes (<?php echo e($pct($apercu['taux'])); ?>)</dt><dd class="mt-1 text-lg font-semibold text-text"><?php echo e($fcfa($apercu['part'])); ?></dd></div>
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Repartie selon les ecoutes</dt><dd class="mt-1 text-lg font-semibold text-emerald-300"><?php echo e($fcfa($apercu['reparti'])); ?></dd></div>
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Non attribuee (abonnes sans ecoute)</dt><dd class="mt-1 text-lg font-semibold text-text"><?php echo e($fcfa($apercu['non_attribue'])); ?></dd></div>
            </dl>
            <p class="mt-3 text-xs text-muted"><?php echo (int) $apercu['abonnes']; ?> abonne(s), dont <?php echo (int) $apercu['auditeurs']; ?> avec des ecoutes retenues · <?php echo (int) $apercu['ecoutes']; ?> ecoute(s) · <?php echo count($apercu['lignes']); ?> artiste(s).
                La mesure des ecoutes n'est pas encore certifiee (LOT 9) : verifier les montants inhabituels avant de cloturer.</p>

            <?php if ($apercu['lignes'] !== []): ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-2">Artiste</th><th class="px-4 py-2 text-right">Ecoutes</th><th class="px-4 py-2 text-right">Montant</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($apercu['lignes'], 0, 50) as $l): ?>
                                <tr class="border-b border-white/5"><td class="px-4 py-2 text-text"><?php echo e($l['stage_name']); ?></td><td class="px-4 py-2 text-right text-muted"><?php echo (int) $l['ecoutes']; ?></td><td class="px-4 py-2 text-right text-text"><?php echo e($fcfa($l['montant'])); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($peutCloturer && !$dejaClos && $moisApercu < date('Y-m')): ?>
                <form method="POST" class="mt-6" onsubmit="return confirm('Cloturer <?php echo e(RepartitionAbonnements::libelleMois($moisApercu)); ?> ? Les montants seront credites aux artistes et ne pourront plus changer.');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="cloturer">
                    <input type="hidden" name="mois" value="<?php echo e($moisApercu); ?>">
                    <button class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white">Cloturer et crediter les artistes</button>
                </form>
            <?php elseif ($moisApercu >= date('Y-m')): ?>
                <p class="mt-4 text-xs text-muted">Le mois en cours se cloture une fois termine.</p>
            <?php endif; ?>
            <?php if ($enAttente !== []): ?>
                <p class="mt-4 text-xs text-amber-200">Mois termines non repartis :
                    <?php foreach ($enAttente as $m): ?><a class="underline" href="?mois=<?php echo e($m); ?>"><?php echo e(RepartitionAbonnements::libelleMois($m)); ?></a> <?php endforeach; ?></p>
            <?php endif; ?>

            <h3 class="mt-8 text-sm font-semibold text-text">Mois clotures</h3>
            <ul class="mt-2 divide-y divide-white/5 text-xs text-muted">
                <?php if ($repartitions === []): ?><li class="py-2">Aucun.</li><?php endif; ?>
                <?php foreach ($repartitions as $r): ?>
                    <li class="py-2"><a class="text-text underline" href="?mois=<?php echo e($r['period']); ?>"><?php echo e(RepartitionAbonnements::libelleMois($r['period'])); ?></a> — <?php echo e($pct($r['artist_rate'])); ?>, <?php echo e($fcfa($r['distributed'])); ?> repartis, <?php echo e($fcfa($r['unallocated'])); ?> non attribues · clos le <?php echo e(date('d/m/Y', strtotime((string) $r['closed_at']))); ?><?php echo $r['username'] ? ' par ' . e($r['username']) : ''; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
