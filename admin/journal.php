<?php
/**
 * Journal d'audit (SEC-19).
 *
 * Reserve a la permission journal.lire, c'est-a-dire au super-administrateur.
 * Lecture seule : aucune action de cette page ne modifie le journal, et le
 * code n'ecrit jamais d'UPDATE ni de DELETE sur audit_log.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

Autorisations::exiger('journal.lire');

$pageTitle = 'Journal d\'audit';
$pageDescription = 'Qui a fait quoi, et quand';
$hideTopNav = true;
$hideFooter = true;

$filtres = [
    'action'     => trim((string) ($_GET['action'] ?? '')),
    'acteur'     => (int) ($_GET['acteur'] ?? 0),
    'cible_type' => trim((string) ($_GET['cible_type'] ?? '')),
    'depuis'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['depuis'] ?? '')) ? $_GET['depuis'] : '',
    'jusqu_a'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['jusqu_a'] ?? '')) ? $_GET['jusqu_a'] : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$parPage = 50;

$resultat = JournalAudit::lire(array_filter($filtres), $page, $parPage);
$pages = max(1, (int) ceil($resultat['total'] / $parPage));
$actions = JournalAudit::actionsUtilisees();

$e = static fn ($valeur): string => htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
$lien = static function (array $remplacements) use ($filtres, $page): string {
    $parametres = array_filter(array_merge($filtres, ['page' => $page], $remplacements));

    return '?' . http_build_query($parametres);
};

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <section>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin</p>
                        <h1 class="mt-2 text-3xl font-display font-bold text-text">Journal d'audit</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                            Les actions sensibles de l'administration, avec leur auteur, leur cible et
                            l'adresse d'ou elles ont ete faites. Ce journal ne se modifie pas : il ne
                            recoit que des ajouts.
                        </p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                        <i class="fas fa-arrow-left mr-2"></i>Console
                    </a>
                </div>

                <form method="GET" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="action" class="text-xs font-semibold text-muted">Action</label>
                        <select id="action" name="action" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                            <option value="">Toutes</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $e($action); ?>" <?php echo $filtres['action'] === $action ? 'selected' : ''; ?>>
                                    <?php echo $e(JournalAudit::libelle($action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="acteur" class="text-xs font-semibold text-muted">Auteur (identifiant)</label>
                        <input id="acteur" type="number" min="1" name="acteur" value="<?php echo $filtres['acteur'] ?: ''; ?>"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                    </div>
                    <div>
                        <label for="cible_type" class="text-xs font-semibold text-muted">Type de cible</label>
                        <input id="cible_type" type="text" name="cible_type" value="<?php echo $e($filtres['cible_type']); ?>"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                    </div>
                    <div>
                        <label for="depuis" class="text-xs font-semibold text-muted">Du</label>
                        <input id="depuis" type="date" name="depuis" value="<?php echo $e($filtres['depuis']); ?>"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                    </div>
                    <div>
                        <label for="jusqu_a" class="text-xs font-semibold text-muted">Au</label>
                        <input id="jusqu_a" type="date" name="jusqu_a" value="<?php echo $e($filtres['jusqu_a']); ?>"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                        <button type="submit" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                        <a href="journal.php" class="rounded-full border border-white/15 px-5 py-2 text-sm font-semibold text-text hover:bg-white/10">Tout afficher</a>
                        <span class="ml-auto text-xs text-muted"><?php echo (int) $resultat['total']; ?> entree(s)</span>
                    </div>
                </form>
            </div>

            <div class="mt-6 overflow-x-auto rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Auteur</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Cible</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Adresse</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$resultat['lignes']): ?>
                            <tr><td colspan="6" class="px-4 py-6 text-center text-muted">Aucune entree pour ces criteres.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($resultat['lignes'] as $entree): ?>
                            <tr class="border-b border-white/5 align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted">
                                    <?php echo date('d/m/Y \a H\hi\m\i\n\s', strtotime((string) $entree['created_at'])); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($entree['actor_id']): ?>
                                        <span class="font-semibold text-text"><?php echo $e($entree['username'] ?? ('#' . $entree['actor_id'])); ?></span>
                                        <span class="block text-xs text-muted"><?php echo $e($entree['actor_role'] ?: 'sans role'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">anonyme</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-text"><?php echo $e(JournalAudit::libelle((string) $entree['action'])); ?></td>
                                <td class="px-4 py-3 text-xs text-muted">
                                    <?php echo $e($entree['target_type'] ?: '-'); ?>
                                    <?php if ($entree['target_id']): ?>
                                        <span class="text-text">#<?php echo $e($entree['target_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted">
                                    <?php if ($entree['reason']): ?>
                                        <p><?php echo $e($entree['reason']); ?></p>
                                    <?php endif; ?>
                                    <?php foreach (['before_state' => 'avant', 'after_state' => 'apres'] as $colonne => $libelle): ?>
                                        <?php if (!empty($entree[$colonne])): ?>
                                            <p class="mt-1"><span class="text-text"><?php echo $libelle; ?></span> : <?php echo $e($entree[$colonne]); ?></p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted"><?php echo $e($entree['ip_address'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-sm">
                    <?php if ($page > 1): ?>
                        <a class="rounded-full border border-white/15 px-4 py-2 text-text hover:bg-white/10" href="<?php echo $e($lien(['page' => $page - 1])); ?>">Precedent</a>
                    <?php endif; ?>
                    <span class="text-muted">page <?php echo $page; ?> sur <?php echo $pages; ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="rounded-full border border-white/15 px-4 py-2 text-text hover:bg-white/10" href="<?php echo $e($lien(['page' => $page + 1])); ?>">Suivant</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
