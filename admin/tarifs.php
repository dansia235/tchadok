<?php
/**
 * Grille tarifaire (DATA-04, amorce de DASH-09).
 *
 * Les prix ont quitte le code : cet ecran est le seul endroit ou ils changent.
 * Reserve a la permission tarif.modifier, et chaque modification passe par
 * Tarifs::enregistrer(), qui verifie la coherence et inscrit l'avant/apres au
 * journal d'audit -- une requete SQL passee a la main ne le ferait pas.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

Autorisations::exiger('tarif.modifier');

$pageTitle = 'Grille tarifaire';
$pageDescription = 'Prix planchers, prix suggeres, plafonds et commission';
$hideTopNav = true;
$hideFooter = true;

$succes = '';
$erreurs = [];
$utilisateur = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $resultat = Tarifs::enregistrer($id, [
        'min_price'       => $_POST['min_price'] ?? 0,
        'max_price'       => $_POST['max_price'] ?? 0,
        'suggested'       => $_POST['suggested'] ?? 0,
        'commission_rate' => $_POST['commission_rate'] ?? 0,
    ], $utilisateur['id'] ?? null);

    if ($resultat['succes']) {
        $succes = 'Tarif enregistre. Il s\'applique immediatement, sans deploiement.';
    } else {
        $erreurs = $resultat['erreurs'];
    }
}

$lignes = Tarifs::lignes();

$libelles = [
    'track:'                       => 'Titre a l\'unite',
    'release:single'               => 'Single',
    'release:maxi_single'          => 'Maxi single',
    'release:ep'                   => 'EP',
    'release:album'                => 'Album',
    'release:compilation'          => 'Compilation',
    'subscription:premium_monthly' => 'Premium mensuel',
    'subscription:premium_annual'  => 'Premium annuel',
];

$e = static fn ($valeur): string => htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
$sou = static fn ($valeur): string => number_format((float) $valeur, 0, ',', ' ');

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <section>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin</p>
                        <h1 class="mt-2 text-3xl font-display font-bold text-text">Grille tarifaire</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                            Le plancher protege la valeur du catalogue tchadien et garantit que la
                            commission couvre les frais de paiement mobile. Le plafond arrete les
                            saisies absurdes. Chaque modification est datee, attribuee et inscrite au
                            journal d'audit.
                        </p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                        Retour a la console
                    </a>
                </div>

                <?php if ($succes !== ''): ?>
                    <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                        <i class="fas fa-check-circle"></i> <?php echo $e($succes); ?>
                    </div>
                <?php endif; ?>
                <?php if ($erreurs !== []): ?>
                    <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $e(implode(' ', $erreurs)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($lignes === []): ?>
                    <p class="mt-8 rounded-2xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">
                        Aucune regle enregistree. La migration <code>DATA-04</code> n'a pas ete appliquee :
                        lancez <code>php scripts/migrate.php up</code>.
                    </p>
                <?php else: ?>
                    <!--
                        Une ligne = un formulaire. Un <form> place dans un <tr> serait sorti
                        du tableau par le navigateur : la mise en page repose donc sur une
                        grille, valide et lisible jusqu'au telephone.
                    -->
                    <div class="mt-8 hidden gap-4 px-4 pb-2 text-xs uppercase tracking-wider text-muted md:grid md:grid-cols-[minmax(10rem,1.6fr)_repeat(4,minmax(5rem,1fr))_auto]">
                        <span>Produit</span>
                        <span class="text-right">Plancher</span>
                        <span class="text-right">Suggere</span>
                        <span class="text-right">Plafond</span>
                        <span class="text-right">Commission</span>
                        <span></span>
                    </div>

                    <div class="space-y-3 md:mt-2">
                        <?php foreach ($lignes as $ligne): ?>
                            <?php $cle = $ligne['scope'] . ':' . (string) $ligne['format']; ?>
                            <form method="POST" class="grid grid-cols-2 items-center gap-3 rounded-2xl bg-white/5 px-4 py-4 md:grid-cols-[minmax(10rem,1.6fr)_repeat(4,minmax(5rem,1fr))_auto]">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $ligne['id']; ?>">

                                <div class="col-span-2 md:col-span-1">
                                    <span class="font-semibold text-text"><?php echo $e($libelles[$cle] ?? $cle); ?></span>
                                    <span class="ml-2 text-xs text-muted"><?php echo $e($ligne['currency']); ?></span>
                                </div>

                                <label class="flex items-center justify-between gap-2 md:justify-end">
                                    <span class="text-xs text-muted md:hidden">Plancher</span>
                                    <input type="number" name="min_price" step="50" min="0" value="<?php echo (int) $ligne['min_price']; ?>"
                                           class="w-24 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text">
                                </label>
                                <label class="flex items-center justify-between gap-2 md:justify-end">
                                    <span class="text-xs text-muted md:hidden">Suggere</span>
                                    <input type="number" name="suggested" step="50" min="0" value="<?php echo (int) $ligne['suggested']; ?>"
                                           class="w-24 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text">
                                </label>
                                <label class="flex items-center justify-between gap-2 md:justify-end">
                                    <span class="text-xs text-muted md:hidden">Plafond</span>
                                    <input type="number" name="max_price" step="50" min="0" value="<?php echo (int) $ligne['max_price']; ?>"
                                           class="w-24 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text">
                                </label>
                                <label class="flex items-center justify-between gap-2 md:justify-end">
                                    <span class="text-xs text-muted md:hidden">Commission</span>
                                    <span class="flex items-center gap-1">
                                        <input type="number" name="commission_rate" step="0.5" min="0" max="50" value="<?php echo $e($ligne['commission_rate']); ?>"
                                               class="w-20 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text">
                                        <span class="text-xs text-muted">%</span>
                                    </span>
                                </label>

                                <div class="col-span-2 text-right md:col-span-1">
                                    <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <p class="mt-6 text-xs leading-5 text-muted">
                        Un artiste qui saisit un prix hors bornes est refuse cote serveur, quel que soit
                        le point d'entree -- formulaire, console ou future application. Les ventes deja
                        conclues ne sont jamais reecrites : <code>DATA-05</code> figera le prix et le taux
                        de commission sur chaque ligne de commande.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
