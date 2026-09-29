<?php
/**
 * Panier (SHOP-01) et passage en commande (SHOP-02).
 *
 * Les prix affiches sont relus en base ; un changement depuis l'ajout est
 * annonce, et le passage en commande est refuse tant que le client ne l'a pas
 * vu. Passer commande fige le panier puis conduit a paiement.php.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;
$erreur = '';
$avertissementsCommande = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'retirer') {
        Panier::retirer($userId, (string) ($_POST['type'] ?? ''), (int) ($_POST['id'] ?? 0));
        redirect(SITE_URL . '/panier.php');
    }

    if ($action === 'commander') {
        if ($userId === null) {
            redirect(SITE_URL . '/login.php?redirect=' . urlencode('/panier.php'));
        }
        $resultat = Panier::commander($userId);
        if ($resultat['succes']) {
            redirect(SITE_URL . '/paiement.php?commande=' . urlencode((string) $resultat['reference']));
        }
        $erreur = (string) $resultat['erreur'];
        $avertissementsCommande = $resultat['avertissements'];
    }
}

$panier = Panier::contenu($userId);
$avertissements = array_values(array_unique(array_merge($avertissementsCommande, $panier['avertissements'])));
$aRegler = $userId !== null ? Panier::commandesARegler($userId) : [];
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$libelleType = static fn (array $l): string => $l['type'] === 'track' ? 'Titre' : ucfirst(str_replace('_', ' ', (string) ($l['format'] ?? 'sortie')));

$pageTitle = 'Mon panier';
$pageDescription = 'Vos achats de musique tchadienne';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_320px]">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8" aria-labelledby="titre-panier">
            <h1 id="titre-panier" class="text-2xl font-display font-semibold text-text">Mon panier</h1>

            <?php if ($erreur): ?>
                <div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div>
            <?php endif; ?>
            <?php foreach ($avertissements as $a): ?>
                <div class="alert alert-warning mt-4" role="status"><i class="fas fa-circle-info mt-0.5"></i><span><?php echo e($a); ?></span></div>
            <?php endforeach; ?>

            <?php if ($panier['lignes'] === []): ?>
                <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
                    <i class="fas fa-basket-shopping text-2xl text-muted"></i>
                    <p class="mt-3 text-sm text-muted">Votre panier est vide.</p>
                    <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="mt-4 inline-flex rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">Decouvrir la musique</a>
                </div>
            <?php else: ?>
                <ul class="mt-6 divide-y divide-white/5">
                    <?php foreach ($panier['lignes'] as $l): ?>
                        <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-text"><?php echo e($l['libelle']); ?></p>
                                <p class="text-xs text-muted"><?php echo e($libelleType($l)); ?> · <?php echo e($l['artiste']); ?></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm font-semibold text-text"><?php echo e($fcfa($l['prix'])); ?></span>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="retirer">
                                    <input type="hidden" name="type" value="<?php echo e($l['type']); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $l['id']; ?>">
                                    <button type="submit" class="grid h-9 w-9 place-items-center rounded-full border border-white/10 text-muted hover:bg-white/10 hover:text-text" aria-label="Retirer <?php echo e($l['libelle']); ?>">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <aside class="h-fit space-y-6">
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-total">
                <h2 id="titre-total" class="text-sm font-semibold uppercase tracking-wide text-muted">Total</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="text-text"><?php echo e($fcfa($panier['sous_total'])); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Frais de transaction</dt><dd class="text-text"><?php echo $panier['frais'] > 0 ? e($fcfa($panier['frais'])) : 'offerts'; ?></dd></div>
                    <div class="flex justify-between border-t border-white/10 pt-3 text-base font-semibold"><dt class="text-text">A payer</dt><dd class="text-text"><?php echo e($fcfa($panier['total'])); ?></dd></div>
                </dl>
                <?php if ($panier['lignes'] !== []): ?>
                    <form method="POST" class="mt-6">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="commander">
                        <button type="submit" class="w-full rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">
                            <?php echo $userId === null ? 'Se connecter pour payer' : 'Passer au paiement'; ?>
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-muted">Airtel Money, Moov Money, GIMAC ou carte VISA. Une part de chaque achat revient a l'artiste.</p>
                <?php endif; ?>
            </section>

            <?php if ($aRegler !== []): ?>
                <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-a-regler">
                    <h2 id="titre-a-regler" class="text-sm font-semibold uppercase tracking-wide text-muted">Commandes a regler</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <?php foreach ($aRegler as $o): ?>
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-text"><?php echo e($fcfa($o['total'])); ?> <span class="block text-xs text-muted"><?php echo e(date('d/m/Y', strtotime((string) $o['created_at']))); ?></span></span>
                                <a class="rounded-full border border-white/15 px-3 py-1 text-xs font-semibold text-text hover:bg-white/10" href="<?php echo SITE_URL; ?>/paiement.php?commande=<?php echo e(rawurlencode((string) $o['reference'])); ?>">Payer</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
