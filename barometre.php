<?php
/**
 * Barometre Tchadok, page publique (CHART-03).
 *
 * Edition en cours et archives, par periode (semaine, mois, annee). Top 50
 * titres, Top 20 artistes, Top 20 sorties par format, classements des ventes.
 * Filtres par genre et categorie (le rang affiche reste le rang NATIONAL).
 * Vues analytiques (CHART-02), dernieres certifications (CHART-06), exports
 * et kit presse (CHART-04). URL stable par edition et par classement, apercu
 * WhatsApp / Facebook par le visuel du Top 10, donnees structurees JSON-LD.
 */

require_once 'includes/functions.php';
require_once 'includes/barometre.php';
require_once 'includes/kit-presse.php';

$type = array_key_exists($_GET['periode'] ?? '', Barometre::PERIODES) ? (string) $_GET['periode'] : 'weekly';
$slug = preg_match('/^[0-9]{4}(-S[0-9]{2}|-[0-9]{2})?$/', (string) ($_GET['edition'] ?? '')) ? (string) $_GET['edition'] : null;
$edition = Barometre::edition($slug, $type);
if ($slug !== null && $edition === null) {
    show404();
}
if ($edition) {
    $type = (string) $edition['period_type'];
}
$classement = array_key_exists($_GET['classement'] ?? '', Barometre::CLASSEMENTS) ? (string) $_GET['classement'] : 'titres';
$genre = ctype_digit((string) ($_GET['genre'] ?? '')) ? (int) $_GET['genre'] : null;
$categorie = ctype_digit((string) ($_GET['categorie'] ?? '')) ? (int) $_GET['categorie'] : null;
$vue = in_array($_GET['vue'] ?? '', ['genre', 'categorie', 'format', 'region'], true) ? (string) $_GET['vue'] : 'genre';
$imprimer = isset($_GET['imprimer']);

$def = Barometre::CLASSEMENTS[$classement];
$entrees = $edition ? Barometre::entrees((int) $edition['id'], $classement, $genre, $categorie) : [];
$archives = Barometre::archives($type, 60);
$db = TchadokDatabase::getInstance()->getConnection();
$genres = $db->query("SELECT id, COALESCE(name_french, name) AS nom, parent_id FROM genres WHERE status = 'active' ORDER BY sort_order, nom")->fetchAll(PDO::FETCH_ASSOC);
$categories = array_values(array_filter($genres, fn($g) => $g['parent_id'] === null));
$sousGenres = array_values(array_filter($genres, fn($g) => $g['parent_id'] !== null));
$analyse = $edition ? Barometre::vue($vue, (string) $edition['period_start'], (string) $edition['period_end']) : null;
$decouverte = $edition ? Barometre::indiceDecouverte((string) $edition['period_start'], (string) $edition['period_end']) : 0.0;
$recentes = CertificationsTchadok::recentes(6);
$certifs = [];
if ($def['objet'] === 'track' && $entrees !== []) {
    $ids = implode(',', array_map(fn($x) => (int) $x['item_id'], $entrees));
    foreach ($db->query("SELECT * FROM certifications WHERE track_id IN ({$ids}) ORDER BY FIELD(level, 'or', 'platine', 'diamant')")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $certifs[(int) $c['track_id']][] = $c;
    }
}

$url = static function (array $changes = []) use ($edition, $classement, $type): string {
    $p = array_filter(['edition' => $edition['slug'] ?? null, 'periode' => $edition ? null : $type, 'classement' => $classement] + [], fn($v) => $v !== null);
    $p = array_merge($p, $changes);
    return SITE_URL . '/barometre.php?' . http_build_query(array_filter($p, fn($v) => $v !== null && $v !== ''));
};
$nombre = static fn ($v): string => number_format((float) $v, 0, ',', ' ');
$kit = $edition ? KitPresse::dossier((string) $edition['slug']) : null;
$lienKit = static fn (string $fichier): string => SITE_URL . '/barometre-kit.php?edition=' . rawurlencode((string) $edition['slug']) . '&fichier=' . rawurlencode($fichier);
$permalien = $edition ? SITE_URL . '/barometre.php?edition=' . rawurlencode((string) $edition['slug']) . '&classement=' . $classement : SITE_URL . '/barometre.php';

$pageTitle = $edition ? 'Barometre Tchadok - ' . $def['libelle'] . ' - ' . Barometre::libellePeriode($edition) : 'Barometre Tchadok';
$pageDescription = $edition && $entrees !== []
    ? sprintf('%s : n°1 %s (%s). Classement arrete le %s, ecoutes certifiees.', $def['libelle'], $entrees[0]['titre'], $entrees[0]['artiste'] ?? $entrees[0]['titre'], date('d/m/Y', strtotime((string) $edition['arrete_at'])))
    : 'Le classement de reference de la musique tchadienne : ecoutes certifiees, ventes, certifications.';
$pageCanonical = $permalien;
if ($edition && $kit && is_file($kit . '/top10.png')) {
    $pageImage = $pageTwitterImage = $lienKit('top10.png');
}
if ($imprimer) {
    $hideTopNav = true;
    $hideFooter = true;
}

include 'includes/header-tailwind.php';
?>
<?php if ($edition && $entrees !== []): ?>
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $pageTitle, 'url' => $permalien,
    'numberOfItems' => count($entrees),
    'itemListElement' => array_map(fn($x) => ['@type' => 'ListItem', 'position' => (int) $x['rank'],
        'name' => trim($x['titre'] . ($x['artiste'] ? ' - ' . $x['artiste'] : ''))], array_slice($entrees, 0, 50)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
<?php endif; ?>

<main class="min-h-screen bg-bg pb-16 <?php echo $imprimer ? 'pt-6' : 'pt-24'; ?>">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6">
        <header class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <p class="text-xs uppercase tracking-[0.28em] text-muted">Barometre Tchadok · <?php echo e(Barometre::PERIODES[$type]); ?></p>
            <h1 class="mt-2 text-3xl font-display font-bold text-text"><?php echo $edition ? e(Barometre::libellePeriode($edition)) : 'Le premier classement arrive'; ?></h1>
            <?php if ($edition): ?>
                <p class="mt-2 text-sm text-muted">Classement arrete le <?php echo e(date('d/m/Y a H:i', strtotime((string) $edition['arrete_at']))); ?> — il ne change plus.
                    <?php echo e($nombre($edition['streams_total'])); ?> ecoutes certifiees, <?php echo e($nombre($edition['sales_total'])); ?> ventes.
                    <a class="underline" href="<?php echo SITE_URL; ?>/methodologie.php">Methodologie v<?php echo e($edition['methodology_version']); ?></a></p>
            <?php else: ?>
                <p class="mt-2 text-sm text-muted">Un classement est arrete 48 h apres la fin de chaque semaine, avec les seules ecoutes certifiees. <a class="underline" href="<?php echo SITE_URL; ?>/methodologie.php">Comment nous comptons</a></p>
            <?php endif; ?>
            <?php if (!$imprimer): ?>
                <nav class="mt-5 flex flex-wrap gap-2 text-xs" aria-label="Periodicite">
                    <?php foreach (Barometre::PERIODES as $cle => $libelle): ?>
                        <a href="<?php echo e(SITE_URL . '/barometre.php?periode=' . $cle . '&classement=' . $classement); ?>" class="rounded-full border px-3 py-1.5 <?php echo $cle === $type ? 'border-accent bg-accent text-white' : 'border-white/15 text-text'; ?>"<?php echo $cle === $type ? ' aria-current="page"' : ''; ?>><?php echo e($libelle); ?></a>
                    <?php endforeach; ?>
                    <?php if ($archives !== []): ?>
                        <form method="GET" class="ml-auto flex items-center gap-2">
                            <input type="hidden" name="classement" value="<?php echo e($classement); ?>">
                            <label class="text-muted" for="edition">Archives</label>
                            <select id="edition" name="edition" class="rounded-full border border-white/15 bg-bg px-3 py-1.5 text-text" onchange="this.form.submit()">
                                <?php foreach ($archives as $a): ?><option value="<?php echo e($a['slug']); ?>"<?php echo $edition && $a['id'] === $edition['id'] ? ' selected' : ''; ?>><?php echo e(Barometre::libellePeriode($a)); ?></option><?php endforeach; ?>
                            </select>
                            <noscript><button class="rounded-full border border-white/15 px-3 py-1.5 text-text">Voir</button></noscript>
                        </form>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </header>

        <?php if ($edition): ?>
            <?php if (!$imprimer): ?>
                <nav class="flex gap-2 overflow-x-auto pb-1 text-xs" aria-label="Classements">
                    <?php foreach (Barometre::CLASSEMENTS as $cle => $c): ?>
                        <a href="<?php echo e($url(['classement' => $cle])); ?>" class="whitespace-nowrap rounded-full border px-3 py-1.5 <?php echo $cle === $classement ? 'border-accent bg-accent/20 text-text' : 'border-white/10 text-muted'; ?>"<?php echo $cle === $classement ? ' aria-current="page"' : ''; ?>><?php echo e($c['libelle']); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-4 sm:p-6" aria-labelledby="titre-classement">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="titre-classement" class="text-xl font-semibold text-text"><?php echo e($def['libelle']); ?></h2>
                    <?php if (!$imprimer && $def['objet'] !== 'artist'): ?>
                        <form method="GET" class="flex flex-wrap items-center gap-2 text-xs">
                            <input type="hidden" name="edition" value="<?php echo e($edition['slug']); ?>"><input type="hidden" name="classement" value="<?php echo e($classement); ?>">
                            <label class="sr-only" for="categorie">Categorie</label>
                            <select id="categorie" name="categorie" class="rounded-full border border-white/15 bg-bg px-3 py-1.5 text-text">
                                <option value="">Toutes categories</option>
                                <?php foreach ($categories as $g): ?><option value="<?php echo (int) $g['id']; ?>"<?php echo $categorie === (int) $g['id'] ? ' selected' : ''; ?>><?php echo e($g['nom']); ?></option><?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="genre">Genre</label>
                            <select id="genre" name="genre" class="rounded-full border border-white/15 bg-bg px-3 py-1.5 text-text">
                                <option value="">Tous genres</option>
                                <?php foreach ($sousGenres as $g): ?><option value="<?php echo (int) $g['id']; ?>"<?php echo $genre === (int) $g['id'] ? ' selected' : ''; ?>><?php echo e($g['nom']); ?></option><?php endforeach; ?>
                            </select>
                            <button class="rounded-full border border-white/15 px-3 py-1.5 text-text">Filtrer</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($genre !== null || $categorie !== null): ?><p class="mt-2 text-xs text-muted">Filtre applique : le rang affiche reste le rang national.</p><?php endif; ?>

                <?php if ($entrees === []): ?>
                    <p class="mt-6 text-sm text-muted">Aucune entree pour ce classement sur la periode.</p>
                <?php else: ?>
                    <ol class="mt-4 divide-y divide-white/5">
                        <?php foreach ($entrees as $x): $evo = Barometre::evolution($x); ?>
                            <li class="flex items-center gap-3 py-3" data-rang="<?php echo (int) $x['rank']; ?>">
                                <span class="w-10 shrink-0 text-center text-2xl font-bold <?php echo (int) $x['rank'] <= 3 ? 'text-amber-300' : 'text-text'; ?>"><?php echo (int) $x['rank']; ?></span>
                                <span class="w-16 shrink-0 text-center text-xs font-semibold <?php echo $evo === 'nouveau' ? 'text-amber-300' : (str_starts_with($evo, '+') ? 'text-emerald-300' : (str_starts_with($evo, '-') ? 'text-rose-300' : 'text-muted')); ?>"
                                      aria-label="Evolution : <?php echo e($evo === '=' ? 'stable' : $evo); ?>"><?php echo e($evo === 'nouveau' ? 'NOUVEAU' : ($evo === 'retour' ? 'RETOUR' : $evo)); ?></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold text-text"><?php echo e($x['titre']); ?></span>
                                    <?php if ($x['artiste']): ?><span class="block truncate text-sm text-muted"><?php echo e($x['artiste']); ?></span><?php endif; ?>
                                    <?php foreach ($certifs[(int) $x['item_id']] ?? [] as $c): ?><span class="mr-1 mt-1 inline-block rounded-full border border-amber-300/40 px-2 py-0.5 text-[10px] text-amber-200"><?php echo e(CertificationsTchadok::libelle($c)); ?></span><?php endforeach; ?>
                                </span>
                                <span class="shrink-0 text-right text-xs text-muted">
                                    <span class="block text-sm font-semibold text-text"><?php echo e($nombre($x['value'])); ?></span>
                                    <?php echo $def['mesure'] === 'ventes' ? 'ventes' : 'ecoutes'; ?>
                                    <span class="block">meilleur <?php echo (int) $x['peak_rank']; ?> · <?php echo (int) $x['periods_on_chart']; ?> <?php echo $type === 'weekly' ? 'sem.' : 'per.'; ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <?php if (!$imprimer): ?>
                <section class="rounded-3xl border border-white/10 bg-surface/75 p-4 sm:p-6" aria-labelledby="titre-partage">
                    <h2 id="titre-partage" class="text-lg font-semibold text-text">Partager et reprendre</h2>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <a class="rounded-full bg-emerald-600 px-3 py-1.5 font-semibold text-white" href="https://wa.me/?text=<?php echo rawurlencode($pageTitle . ' ' . $permalien); ?>" rel="noopener" target="_blank">WhatsApp</a>
                        <a class="rounded-full bg-blue-600 px-3 py-1.5 font-semibold text-white" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($permalien); ?>" rel="noopener" target="_blank">Facebook</a>
                        <?php if ($kit && is_file($kit . '/top10.png')): ?><a class="rounded-full border border-white/15 px-3 py-1.5 text-text" href="<?php echo e($lienKit('top10.png')); ?>">Visuel Top 10</a><?php endif; ?>
                        <?php if ($kit && is_file($kit . '/communique.html')): ?><a class="rounded-full border border-white/15 px-3 py-1.5 text-text" href="<?php echo e($lienKit('communique.html')); ?>">Communique</a><?php endif; ?>
                        <?php if ($kit && is_file($kit . '/classement.csv')): ?><a class="rounded-full border border-white/15 px-3 py-1.5 text-text" href="<?php echo e($lienKit('classement.csv')); ?>">CSV</a><?php endif; ?>
                        <a class="rounded-full border border-white/15 px-3 py-1.5 text-text" href="<?php echo e($url(['imprimer' => 1])); ?>">Version imprimable / PDF</a>
                    </div>
                    <p class="mt-2 break-all text-xs text-muted">Lien permanent : <?php echo e($permalien); ?></p>
                    <p class="mt-1 text-xs text-muted">Reproduction libre avec la mention « Barometre Tchadok ». API pour les medias : sur demande.</p>
                </section>

                <section class="rounded-3xl border border-white/10 bg-surface/75 p-4 sm:p-6" aria-labelledby="titre-analyse">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="titre-analyse" class="text-lg font-semibold text-text">Analyse de la periode</h2>
                        <nav class="flex flex-wrap gap-2 text-xs" aria-label="Vues">
                            <?php foreach (['genre' => 'Genres', 'categorie' => 'Categories', 'format' => 'Formats', 'region' => 'Regions'] as $cle => $libelle): ?>
                                <a href="<?php echo e($url(['vue' => $cle])); ?>#titre-analyse" class="rounded-full border px-3 py-1.5 <?php echo $cle === $vue ? 'border-accent text-text' : 'border-white/10 text-muted'; ?>"><?php echo e($libelle); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                    <p class="mt-2 text-sm text-muted">Indice de decouverte : <strong class="text-text"><?php echo e($decouverte); ?> %</strong> des ecoutes vont a des artistes arrives depuis moins de 12 mois.</p>
                    <?php if ($vue === 'region'): ?>
                        <p class="mt-2 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-3 text-xs text-amber-200">La localisation des ecoutes n'est pas encore mesuree (base de geolocalisation a installer) : ni les provinces, ni le classement diaspora ne peuvent etre publies. Nous preferons ne rien afficher plutot qu'un chiffre invente.</p>
                    <?php endif; ?>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted"><tr><th class="py-2 pr-4">Segment</th><th class="py-2 pr-4 text-right">Ecoutes</th><th class="py-2 pr-4 text-right">Part</th><th class="py-2 pr-4 text-right">Croissance</th><th class="py-2 pr-4 text-right">Ventes</th><th class="py-2 pr-4 text-right">Artistes actifs</th><th class="py-2 text-right">Revenu / titre</th></tr></thead>
                            <tbody>
                                <?php if (($analyse['lignes'] ?? []) === []): ?><tr><td colspan="7" class="py-4 text-muted">Aucune donnee sur la periode.</td></tr><?php endif; ?>
                                <?php foreach ($analyse['lignes'] ?? [] as $l): ?>
                                    <tr class="border-b border-white/5"><td class="py-2 pr-4 text-text"><?php echo e($l['libelle']); ?></td><td class="py-2 pr-4 text-right"><?php echo e($nombre($l['ecoutes'])); ?></td><td class="py-2 pr-4 text-right"><?php echo e($l['part_ecoutes']); ?> %</td>
                                        <td class="py-2 pr-4 text-right"><?php echo $l['croissance'] === null ? '—' : e(($l['croissance'] > 0 ? '+' : '') . $l['croissance'] . ' %'); ?></td><td class="py-2 pr-4 text-right"><?php echo e($nombre($l['ventes'])); ?></td>
                                        <td class="py-2 pr-4 text-right"><?php echo (int) $l['artistes_actifs']; ?></td><td class="py-2 text-right"><?php echo e($nombre($l['revenu_par_titre'])); ?> FCFA</td></tr>
                                <?php endforeach; ?>
                                <?php if ($analyse): ?><tr class="font-semibold"><td class="py-2 pr-4 text-text">Total national</td><td class="py-2 pr-4 text-right"><?php echo e($nombre($analyse['total']['ecoutes'])); ?></td><td class="py-2 pr-4 text-right">100 %</td><td></td><td class="py-2 pr-4 text-right"><?php echo e($nombre($analyse['total']['ventes'])); ?></td><td colspan="2"></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if ($recentes !== []): ?>
                    <section class="rounded-3xl border border-white/10 bg-surface/75 p-4 sm:p-6" aria-labelledby="titre-certifs">
                        <h2 id="titre-certifs" class="text-lg font-semibold text-text">Dernieres certifications</h2>
                        <ul class="mt-3 divide-y divide-white/5 text-sm">
                            <?php foreach ($recentes as $c): ?>
                                <li class="py-2"><span class="text-amber-200"><?php echo e(CertificationsTchadok::libelle($c)); ?></span> — <?php echo e($c['title']); ?>, <?php echo e($c['stage_name']); ?>
                                    <a class="ml-1 text-xs underline" href="<?php echo SITE_URL; ?>/certification.php?id=<?php echo (int) $c['id']; ?>">attestation</a></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
