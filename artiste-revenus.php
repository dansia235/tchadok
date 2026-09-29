<?php
/**
 * Revenus et versements, cote artiste (PAYOUT-01 a PAYOUT-03).
 *
 * Solde detaille, compte mobile money de versement, demande de versement,
 * historique et releves. Le versement se fait a la demande de l'artiste
 * (decision du 28/09/2026).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/artiste-revenus.php'));
}
$userId = (int) $_SESSION['user_id'];
$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$artiste) {
    show404();
}
$artistId = (int) $artiste['id'];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $resultat = match ($action) {
        'compte'   => Versements::enregistrerCompte($artistId, (string) ($_POST['method'] ?? ''), (string) ($_POST['msisdn'] ?? ''), (string) ($_POST['holder_name'] ?? '')),
        'demander' => Versements::demander($artistId, $userId),
        default    => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/artiste-revenus.php');
    }
    $erreur = $resultat['message'];
}

$solde = Versements::solde($artistId);
$compte = Versements::compte($artistId);
$historique = Versements::historique($artistId);
$contratRequis = Contrats::acceptationRequise($artistId);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

$pageTitle = 'Revenus et versements';
$pageDescription = 'Vos ventes et vos versements';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace artiste · <?php echo e($artiste['stage_name']); ?></p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Revenus et versements</h1>
                </div>
                <a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Tableau de bord</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <?php if ($contratRequis): ?>
                <div class="alert alert-warning mt-6" role="status"><i class="fas fa-file-signature mt-0.5"></i>
                    <span>Une version du contrat de distribution attend votre acceptation. <a class="underline" href="<?php echo SITE_URL; ?>/contrat.php?retour=%2Fartiste-revenus.php">Lire et accepter</a></span></div>
            <?php endif; ?>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-5">
                    <p class="text-xs text-muted">Disponible pour un versement</p>
                    <p class="mt-1 text-3xl font-bold text-text"><?php echo e($fcfa(max(0, $solde['disponible']))); ?></p>
                    <?php if ($solde['disponible'] < 0): ?><p class="mt-1 text-xs text-amber-300">Reprise en cours : <?php echo e($fcfa(-$solde['disponible'])); ?> a compenser sur vos prochaines ventes.</p><?php endif; ?>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <p class="text-xs text-muted">En periode de retention (<?php echo Versements::retention(); ?> jours)</p>
                    <p class="mt-1 text-2xl font-semibold text-text"><?php echo e($fcfa($solde['en_retention'])); ?></p>
                    <p class="mt-1 text-xs text-muted">Couvre remboursements et contestations.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <p class="text-xs text-muted">Deja verse</p>
                    <p class="mt-1 text-2xl font-semibold text-text"><?php echo e($fcfa($solde['verse'])); ?></p>
                    <?php if ($solde['engage'] > 0): ?><p class="mt-1 text-xs text-muted">+ <?php echo e($fcfa($solde['engage'])); ?> en cours de traitement</p><?php endif; ?>
                </div>
            </div>

            <dl class="mt-6 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt class="text-muted">Ventes (prix payes)</dt><dd class="text-text"><?php echo e($fcfa($solde['brut'])); ?></dd></div>
                <div class="flex justify-between"><dt class="text-muted">Commission Tchadok</dt><dd class="text-text">− <?php echo e($fcfa($solde['commission'])); ?></dd></div>
                <div class="flex justify-between"><dt class="text-muted">Votre part</dt><dd class="text-text"><?php echo e($fcfa($solde['net'])); ?></dd></div>
                <div class="flex justify-between"><dt class="text-muted">Ajustements et abonnements Premium</dt><dd class="text-text"><?php echo e($fcfa($solde['ajustements'])); ?></dd></div>
            </dl>
            <p class="mt-3 text-xs text-muted">Abonnements Premium : <?php echo e(RepartitionAbonnements::pourcent(RepartitionAbonnements::taux())); ?> % de chaque abonnement revient aux artistes que l'abonne a ecoutes, au prorata de ses ecoutes. Votre part est creditee chaque mois, une fois le mois termine.</p>

            <form method="POST" class="mt-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="demander">
                <button type="submit" class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white" <?php echo $solde['disponible'] < Versements::seuil() ? 'aria-describedby="note-seuil"' : ''; ?>>Demander un versement</button>
                <p id="note-seuil" class="mt-2 text-xs text-muted">Versement possible a partir de <?php echo e($fcfa(Versements::seuil())); ?>, sur votre compte mobile money verifie. Chaque demande est validee puis executee par deux personnes differentes de notre equipe.</p>
            </form>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-compte">
            <h2 id="titre-compte" class="text-lg font-semibold text-text">Compte de versement</h2>
            <?php if ($compte): ?>
                <p class="mt-2 text-sm text-muted"><?php echo e(Factures::libelleMoyen($compte['method'])); ?> · <?php echo e($compte['msisdn']); ?> · <?php echo e($compte['holder_name']); ?> —
                    <?php echo $compte['verified_at'] ? '<span class="text-emerald-300">verifie</span>' : '<span class="text-amber-300">en cours de verification</span>'; ?></p>
            <?php endif; ?>
            <form method="POST" class="mt-4 grid gap-3 sm:grid-cols-[160px_1fr_1fr_auto] sm:items-end">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="compte">
                <div><label class="text-xs text-muted" for="method">Operateur</label>
                    <select id="method" name="method" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                        <option value="airtel_money" <?php echo ($compte['method'] ?? '') === 'airtel_money' ? 'selected' : ''; ?>>Airtel Money</option>
                        <option value="moov_money" <?php echo ($compte['method'] ?? '') === 'moov_money' ? 'selected' : ''; ?>>Moov Money</option>
                    </select></div>
                <div><label class="text-xs text-muted" for="msisdn">Numero</label><input id="msisdn" name="msisdn" inputmode="tel" required value="<?php echo e($compte['msisdn'] ?? ''); ?>" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></div>
                <div><label class="text-xs text-muted" for="holder_name">Nom du titulaire</label><input id="holder_name" name="holder_name" required value="<?php echo e($compte['holder_name'] ?? ''); ?>" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></div>
                <button class="rounded-full border border-white/15 px-4 py-2 text-sm font-semibold text-text hover:bg-white/10">Enregistrer</button>
            </form>
            <p class="mt-2 text-xs text-muted">Le compte doit etre a votre nom. Tout changement de numero est reverifie par notre equipe avant le versement suivant.</p>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-historique">
            <h2 id="titre-historique" class="px-6 pt-6 text-lg font-semibold text-text">Mes versements</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted"><tr><th class="px-6 py-3">Demande</th><th class="px-6 py-3">Montant</th><th class="px-6 py-3">Etat</th><th class="px-6 py-3">Releve</th></tr></thead>
                    <tbody>
                        <?php if ($historique === []): ?><tr><td colspan="4" class="px-6 py-6 text-center text-muted">Aucun versement.</td></tr><?php endif; ?>
                        <?php foreach ($historique as $v): ?>
                            <tr class="border-b border-white/5">
                                <td class="px-6 py-3 text-xs text-muted"><?php echo e(date('d/m/Y', strtotime((string) ($v['requested_at'] ?? $v['created_at'])))); ?></td>
                                <td class="px-6 py-3 text-text"><?php echo e($fcfa($v['net'])); ?></td>
                                <td class="px-6 py-3 text-xs text-text"><?php echo e(Versements::LIBELLES[$v['status']] ?? $v['status']); ?><?php echo $v['decision_reason'] ? '<span class="block text-muted">' . e($v['decision_reason']) . '</span>' : ''; ?></td>
                                <td class="px-6 py-3 text-xs"><a class="underline" href="<?php echo SITE_URL; ?>/releve-versement.php?id=<?php echo (int) $v['id']; ?>">Voir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
