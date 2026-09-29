<?php
/**
 * Administration de la taxonomie (TAXO-03). Permission : taxonomie.gerer.
 *
 * Creer, renommer (le lien ne change pas), decrire, colorer, rattacher,
 * reordonner, archiver, fusionner (contenus reaffectes, ancien lien redirige),
 * instruire les propositions des artistes. Tout est journalise.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/taxonomie.php';

Autorisations::exiger('taxonomie.gerer');
$moi = (int) $_SESSION['user_id'];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['genre'] ?? 0);
    $motif = (string) ($_POST['motif'] ?? '');
    $categorie = ctype_digit((string) ($_POST['categorie'] ?? '')) ? (int) $_POST['categorie'] : null;
    $resultat = match ((string) ($_POST['action'] ?? '')) {
        'creer'     => Taxonomie::creer((string) ($_POST['nom'] ?? ''), $categorie, (string) ($_POST['description'] ?? ''), $moi),
        'modifier'  => Taxonomie::modifier($id, [
            'nom' => (string) ($_POST['nom'] ?? ''), 'description' => (string) ($_POST['description'] ?? ''), 'couleur' => (string) ($_POST['couleur'] ?? ''),
            'icone' => (string) ($_POST['icone'] ?? ''), 'ordre' => (int) ($_POST['ordre'] ?? 0), 'categorie' => $categorie,
        ], $moi),
        'fusionner' => Taxonomie::fusionner($id, (int) ($_POST['cible'] ?? 0), $motif, $moi),
        'archiver'  => Taxonomie::archiver($id, $motif, $moi),
        'accepter', 'refuser' => Taxonomie::deciderProposition((int) ($_POST['proposition'] ?? 0), $_POST['action'] === 'accepter', $motif, $categorie, $moi),
        default     => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/admin/taxonomie.php');
    }
    $erreur = $resultat['message'];
}

$arbre = Taxonomie::arbre();
$propositions = Taxonomie::propositionsEnAttente();
$sansGenre = Taxonomie::artistesSansGenre();
$categoriesActives = array_values(array_filter($arbre, fn($c) => $c['status'] === 'active'));
$genresActifs = [];
foreach ($categoriesActives as $c) {
    foreach ($c['genres'] as $g) {
        if ($g['status'] === 'active') {
            $genresActifs[] = $g + ['categorie' => $c['name']];
        }
    }
}
$statut = ['active' => '', 'archived' => 'archive', 'merged' => 'fusionne'];
$champ = 'w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text';

$pageTitle = 'Taxonomie';
$pageDescription = 'Genres et categories';
$hideTopNav = true;
$hideFooter = true;

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Catalogue</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Genres et categories</h1>
                    <p class="mt-3 max-w-3xl text-sm text-muted">Un genre n'est jamais supprime : il est archive ou fusionne. Un renommage ne change pas son lien. Chaque modification est journalisee.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <?php if ($sansGenre !== []): ?>
                <p class="mt-4 text-xs text-amber-200">Artistes actifs sans genre principal (<?php echo count($sansGenre); ?>) : <?php echo e(implode(', ', array_slice(array_column($sansGenre, 'stage_name'), 0, 10))); ?><?php echo count($sansGenre) > 10 ? '…' : ''; ?>. Ils ne peuvent pas publier tant qu'ils ne l'ont pas choisi.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="titre-propositions">
            <h2 id="titre-propositions" class="text-lg font-semibold text-text">Propositions des artistes <span class="text-muted">(<?php echo count($propositions); ?>)</span></h2>
            <?php if ($propositions === []): ?><p class="mt-2 text-sm text-muted">Aucune.</p><?php endif; ?>
            <?php foreach ($propositions as $p): ?>
                <form method="POST" class="mt-3 grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[1fr_180px_1fr_auto_auto] sm:items-end">
                    <?php echo csrfField(); ?><input type="hidden" name="proposition" value="<?php echo (int) $p['id']; ?>">
                    <div><p class="font-semibold text-text"><?php echo e($p['name']); ?></p><p class="text-xs text-muted">par <?php echo e($p['stage_name']); ?> · <?php echo e($p['categorie'] ?? 'sans categorie'); ?><?php echo $p['note'] ? ' · ' . e($p['note']) : ''; ?></p></div>
                    <label class="text-xs text-muted">Categorie<select name="categorie" class="<?php echo $champ; ?>"><?php foreach ($categoriesActives as $c): ?><option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === (int) $p['category_id'] ? ' selected' : ''; ?>><?php echo e($c['name']); ?></option><?php endforeach; ?></select></label>
                    <label class="text-xs text-muted">Motif (transmis a l'artiste)<input name="motif" required minlength="5" class="<?php echo $champ; ?>"></label>
                    <button name="action" value="accepter" class="rounded-full bg-accent px-3 py-1.5 text-xs font-semibold text-white">Accepter</button>
                    <button name="action" value="refuser" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Refuser</button>
                </form>
            <?php endforeach; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="titre-arbre">
            <h2 id="titre-arbre" class="text-lg font-semibold text-text">Referentiel</h2>
            <div class="mt-4 space-y-5">
                <?php foreach ($arbre as $c): ?>
                    <div class="rounded-2xl border border-white/10 p-4">
                        <p class="font-semibold text-text"><?php echo e($c['name']); ?> <span class="text-xs text-muted">/<?php echo e($c['slug']); ?> <?php echo e($statut[$c['status']]); ?></span></p>
                        <ul class="mt-3 divide-y divide-white/5">
                            <?php foreach ($c['genres'] as $g): ?>
                                <li class="py-3" data-genre="<?php echo e($g['slug']); ?>">
                                    <details>
                                        <summary class="cursor-pointer text-sm">
                                            <span class="<?php echo $g['status'] === 'active' ? 'text-text' : 'text-muted line-through'; ?>"><?php echo e($g['name']); ?></span>
                                            <span class="text-xs text-muted">/<?php echo e($g['slug']); ?> · <?php echo (int) $g['titres']; ?> titre(s), <?php echo (int) $g['sorties']; ?> sortie(s), <?php echo (int) $g['artistes']; ?> artiste(s)
                                                <?php echo $g['status'] === 'merged' ? '· fusionne dans ' . e($g['cible']) : e($statut[$g['status']]); ?></span>
                                        </summary>
                                        <?php if ($g['status'] === 'active'): ?>
                                            <form method="POST" class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_110px_110px_80px_auto] sm:items-end">
                                                <?php echo csrfField(); ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="genre" value="<?php echo (int) $g['id']; ?>">
                                                <label class="text-xs text-muted">Nom<input name="nom" value="<?php echo e($g['name']); ?>" class="<?php echo $champ; ?>"></label>
                                                <label class="text-xs text-muted">Categorie<select name="categorie" class="<?php echo $champ; ?>"><?php foreach ($categoriesActives as $cc): ?><option value="<?php echo (int) $cc['id']; ?>"<?php echo (int) $cc['id'] === (int) $g['parent_id'] ? ' selected' : ''; ?>><?php echo e($cc['name']); ?></option><?php endforeach; ?></select></label>
                                                <label class="text-xs text-muted">Couleur<input name="couleur" value="<?php echo e($g['color'] ?? ''); ?>" placeholder="#RRGGBB" class="<?php echo $champ; ?>"></label>
                                                <label class="text-xs text-muted">Icone<input name="icone" value="<?php echo e($g['icon'] ?? ''); ?>" placeholder="fa-music" class="<?php echo $champ; ?>"></label>
                                                <label class="text-xs text-muted">Ordre<input name="ordre" type="number" value="<?php echo (int) $g['sort_order']; ?>" class="<?php echo $champ; ?>"></label>
                                                <button class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Enregistrer</button>
                                                <label class="text-xs text-muted sm:col-span-6">Description editoriale<textarea name="description" rows="2" class="<?php echo $champ; ?>"><?php echo e($g['description'] ?? ''); ?></textarea></label>
                                            </form>
                                            <form method="POST" class="mt-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                                <?php echo csrfField(); ?><input type="hidden" name="genre" value="<?php echo (int) $g['id']; ?>">
                                                <label class="text-xs text-muted">Fusionner dans<select name="cible" class="<?php echo $champ; ?>"><option value="">—</option><?php foreach ($genresActifs as $cible): if ((int) $cible['id'] !== (int) $g['id']): ?><option value="<?php echo (int) $cible['id']; ?>"><?php echo e($cible['categorie'] . ' / ' . $cible['name']); ?></option><?php endif; endforeach; ?></select></label>
                                                <label class="text-xs text-muted">Motif (fusion ou archivage)<input name="motif" minlength="5" class="<?php echo $champ; ?>"></label>
                                                <span class="flex gap-2"><button name="action" value="fusionner" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Fusionner</button>
                                                    <button name="action" value="archiver" class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-text">Archiver</button></span>
                                            </form>
                                        <?php endif; ?>
                                    </details>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" class="mt-6 grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[1fr_200px_1fr_auto] sm:items-end">
                <?php echo csrfField(); ?><input type="hidden" name="action" value="creer">
                <label class="text-xs text-muted">Nouveau genre ou categorie<input name="nom" required minlength="2" maxlength="50" class="<?php echo $champ; ?>"></label>
                <label class="text-xs text-muted">Dans la categorie<select name="categorie" class="<?php echo $champ; ?>"><option value="">(nouvelle categorie)</option><?php foreach ($categoriesActives as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo e($c['name']); ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-muted">Description<input name="description" class="<?php echo $champ; ?>"></label>
                <button class="rounded-full bg-accent px-3 py-1.5 text-xs font-semibold text-white">Creer</button>
            </form>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
