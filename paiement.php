<?php
/**
 * Paiement d'une commande (LOT 5, point d'arrivee du tunnel SHOP-02).
 *
 *   paiement.php?commande=TCHK-2026-XXXXXXXX[&tentative=ID]
 *
 * Le client choisit son moyen de paiement ; la page lance le paiement
 * (api/payments/initier.php) puis suit son etat (api/payments/suivi.php).
 *
 *   - Airtel Money, Moov Money, GIMAC : confirmation sur le telephone, la page
 *     attend le callback de l'operateur.
 *   - VISA : redirection vers la page de l'acquereur. Au retour, la page
 *     attend la confirmation : le retour du navigateur n'est PAS une preuve
 *     de paiement, seul le callback signe l'est.
 *
 * Aucun champ de carte ici, ni jamais : la carte se saisit chez l'acquereur.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

$reference = (string) ($_GET['commande'] ?? '');

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/paiement.php?commande=' . $reference));
}

$userId = (int) $_SESSION['user_id'];
// MOD-07 : pas d'achat sans adresse confirmee (l'API d'initiation le refuse aussi).
require_once __DIR__ . '/includes/comptes.php';
Comptes::exigerEmailVerifie('/paiement.php?commande=' . $reference);
$db = TchadokDatabase::getInstance()->getConnection();

$commande = null;
if ($db && preg_match('/^TCHK-\d{4}-[A-F0-9]{8}$/', $reference)) {
    $stmt = $db->prepare('SELECT id, reference, status, total, currency, invoice_number, payment_method FROM orders WHERE reference = ? AND user_id = ?');
    $stmt->execute([$reference, $userId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$commande) {
    show404();
}

// SHOP-06 : reglement par le portefeuille, sans passerelle.
$erreurPortefeuille = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'portefeuille') {
    $reglement = Portefeuille::payer($commande['reference'], $userId);
    if ($reglement['succes']) {
        redirect(SITE_URL . '/paiement.php?commande=' . urlencode($commande['reference']));
    }
    $erreurPortefeuille = (string) $reglement['erreur'];
}
$estRecharge = Portefeuille::estRecharge($db, (int) $commande['id']);
$stmt = $db->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND item_type = 'subscription' LIMIT 1");
$stmt->execute([$commande['id']]);
$estAbonnement = (bool) $stmt->fetchColumn();
$soldePortefeuille = Portefeuille::solde($userId);

$stmt = $db->prepare('SELECT label, item_type, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$commande['id']]);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retour de la page de l'acquereur, ou rechargement pendant une attente.
$tentative = null;
if (isset($_GET['tentative'])) {
    $suivi = Paiements::suivre((int) $_GET['tentative'], $userId);
    if ($suivi !== null && $suivi['commande'] === $commande['reference']) {
        $tentative = $suivi;
        $commande['status'] = $suivi['etat_commande'];
    }
}

$passerelles = FabriquePasserelles::disponibles();
$payable = in_array($commande['status'], ['cart', 'awaiting_payment', 'failed', 'cancelled', 'expired'], true);
$fcfa = static fn ($montant): string => number_format((float) $montant, 0, ',', ' ') . ' FCFA';

// PAY-07 : la diaspora peut regler par carte en dollar US, au taux en vigueur.
$tauxUsd = Devises::taux('USD');
$montantUsd = $tauxUsd !== null ? Devises::convertir((float) $commande['total'], 'USD') : null;

$pageTitle = 'Paiement';
$pageDescription = 'Reglez votre commande Tchadok';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_340px]">

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8" aria-labelledby="titre-paiement">
            <h1 id="titre-paiement" class="text-2xl font-display font-semibold text-text">Paiement</h1>
            <p class="mt-1 text-sm text-muted">Commande <span class="font-mono text-text"><?php echo e($commande['reference']); ?></span></p>

            <?php if ($commande['status'] === 'paid'): ?>
                <?php
                    // Le numero a pu etre attribue pendant l'affichage (retour de la banque).
                    $facture = (string) $db->query('SELECT invoice_number FROM orders WHERE id = ' . (int) $commande['id'])->fetchColumn();
                ?>
                <div class="alert alert-success mt-6" role="status">
                    <i class="fas fa-check-circle mt-0.5"></i>
                    <span>Paiement confirme. Facture <strong><?php echo e($facture); ?></strong>.
                        <?php echo $estRecharge ? 'Votre portefeuille est credite de ' . e($fcfa($commande['total'])) . '.'
                            : ($estAbonnement ? 'Votre abonnement Premium est actif.' : 'Vos achats sont dans votre bibliotheque.'); ?></span>
                </div>
                <?php [$lienSuite, $libelleSuite] = $estRecharge ? ['wallet.php', 'Mon portefeuille'] : ($estAbonnement ? ['abonnement.php', 'Mon abonnement'] : ['bibliotheque.php', 'Ma bibliotheque']); ?>
                <div class="mt-6 flex flex-wrap gap-3"><a href="<?php echo SITE_URL . '/' . $lienSuite; ?>" class="inline-flex rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white"><?php echo e($libelleSuite); ?></a><a href="<?php echo SITE_URL; ?>/facture.php?commande=<?php echo e(rawurlencode((string) $commande['reference'])); ?>" class="inline-flex rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">Voir la facture</a></div>

            <?php elseif (!$payable): ?>
                <div class="alert alert-warning mt-6" role="status">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <span><?php echo e(match ($commande['status']) {
                        'review'   => 'Votre paiement a ete recu et fait l\'objet d\'une verification par notre equipe. Vous serez informe(e) de son issue.',
                        'refunded' => 'Cette commande a ete remboursee.',
                        'disputed' => 'Ce paiement a ete conteste aupres de votre banque. Les achats correspondants ne sont plus disponibles.',
                        default    => 'Cette commande ne peut plus etre payee.',
                    }); ?></span>
                </div>

            <?php elseif ($passerelles === []): ?>
                <div class="alert alert-danger mt-6" role="alert">
                    <i class="fas fa-triangle-exclamation mt-0.5"></i>
                    <span>Le paiement est momentanement indisponible. Reessayez plus tard.</span>
                </div>

            <?php else: ?>
                <div id="zone-etat" class="mt-6" aria-live="polite"
                     data-tentative="<?php echo $tentative !== null ? (int) $tentative['tentative'] : ''; ?>"
                     data-statut="<?php echo $tentative !== null ? e($tentative['statut']) : ''; ?>"
                     data-message="<?php echo $tentative !== null ? e($tentative['message']) : ''; ?>"></div>

                <?php if (!$estRecharge): ?>
                    <div class="mt-6 rounded-2xl border border-white/10 bg-bg/60 p-4">
                        <?php if ($erreurPortefeuille): ?>
                            <div class="alert alert-danger mb-3" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreurPortefeuille); ?></span></div>
                        <?php endif; ?>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-text"><i class="fas fa-wallet mr-2 text-accent"></i>Mon portefeuille Tchadok</p>
                                <p class="text-xs text-muted">Solde : <?php echo e($fcfa($soldePortefeuille)); ?> — reglement immediat, sans frais.</p>
                            </div>
                            <?php if ($soldePortefeuille >= (float) $commande['total']): ?>
                                <form method="POST" class="m-0">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="portefeuille">
                                    <button type="submit" class="rounded-full bg-accent px-4 py-2 text-sm font-semibold text-white">Payer avec mon solde</button>
                                </form>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/wallet.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">Recharger mon portefeuille</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-muted">Ou payer directement</p>
                <?php endif; ?>

                <form id="formulaire-paiement" class="mt-6 space-y-6" novalidate>
                    <fieldset>
                        <legend class="text-sm font-semibold text-text">Moyen de paiement</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <?php foreach ($passerelles as $code => $p): ?>
                                <label class="group relative flex cursor-pointer items-center gap-4 rounded-2xl border border-white/10 bg-bg/60 p-4 transition hover:border-accent/60 has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/40">
                                    <input type="radio" name="passerelle" value="<?php echo e($code); ?>"
                                           data-parcours="<?php echo e($p->parcours()); ?>"
                                           class="sr-only" <?php echo ($commande['payment_method'] === $code) ? 'checked' : ''; ?>>
                                    <span class="grid h-14 w-24 shrink-0 place-items-center rounded-xl bg-white p-2">
                                        <img src="<?php echo SITE_URL . '/' . e($p->logo()); ?>" alt="" class="max-h-10 max-w-full object-contain" loading="lazy">
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-text"><?php echo e($p->libelle()); ?></span>
                                        <span class="block text-xs text-muted">
                                            <?php echo $p->parcours() === 'push' ? 'Confirmation sur votre telephone' : 'Carte bancaire, paiement securise 3-D Secure'; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div id="bloc-numero" hidden>
                        <label for="numero" class="text-sm font-semibold text-text">Numero de telephone du compte</label>
                        <input id="numero" name="numero" type="tel" inputmode="tel" autocomplete="tel" placeholder="66 00 00 00"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-text focus:outline-none focus:ring-2 focus:ring-accent/60">
                        <p class="mt-2 text-xs text-muted">Vous recevrez une demande de confirmation sur ce telephone. Validez-la avec votre code secret.</p>
                    </div>

                    <div id="note-carte" hidden>
                        <?php if ($montantUsd !== null): ?>
                            <fieldset>
                                <legend class="text-sm font-semibold text-text">Devise du paiement</legend>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-bg/60 px-4 py-3 has-[:checked]:border-accent">
                                        <input type="radio" name="devise" value="XAF" checked class="accent-[var(--color-accent,#2f6de0)]">
                                        <span class="text-sm text-text">Franc CFA <span class="text-muted">— <?php echo e($fcfa($commande['total'])); ?></span></span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-bg/60 px-4 py-3 has-[:checked]:border-accent">
                                        <input type="radio" name="devise" value="USD" data-montant="<?php echo e(Devises::formater($montantUsd, 'USD')); ?>">
                                        <span class="text-sm text-text">Dollar US <span class="text-muted">— <?php echo e(Devises::formater($montantUsd, 'USD')); ?></span></span>
                                    </label>
                                </div>
                                <p class="mt-2 text-xs text-muted">
                                    Taux applique : 1 $ US = <?php echo e(number_format($tauxUsd, 2, ',', ' ')); ?> FCFA
                                    (<?php echo Devises::origine('USD') === 'api' ? 'cours du jour' : (Devises::origine('USD') === 'secours' ? 'taux de reference, cours du jour indisponible' : 'taux fixe par Tchadok'); ?>),
                                    fige au moment du paiement, montant arrondi au cent superieur.
                                    Votre banque peut ajouter ses propres frais de change.
                                </p>
                            </fieldset>
                        <?php endif; ?>
                        <p class="mt-3 text-xs text-muted">
                            <i class="fas fa-lock"></i> Vous allez etre redirige(e) vers la page securisee de notre banque. Tchadok ne voit jamais les donnees de votre carte.
                        </p>
                    </div>

                    <button id="bouton-payer" type="submit" class="w-full rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">
                        Payer <?php echo e($fcfa($commande['total'])); ?>
                    </button>
                </form>

                <?php if (!FabriquePasserelles::modeReel()): ?>
                    <details class="mt-8 rounded-2xl border border-dashed border-amber-400/40 bg-amber-400/5 p-4 text-xs text-muted">
                        <summary class="cursor-pointer font-semibold text-amber-300">Mode simulateur : numeros et cartes de test</summary>
                        <p class="mt-3">Airtel <code>660000NN</code>, Moov <code>650000NN</code>, GIMAC <code>620000NN</code> :
                            01 succes · 02 succes lent · 03 solde insuffisant · 04 code errone · 05 sans reponse (expiration) ·
                            06 annule · 07 callback triple · 08 callback mal signe · 09 montant altere · 10 panne operateur.</p>
                        <p class="mt-2">VISA : <code>4111 1111 1111 1111</code> succes · <code>4000 0000 0000 3220</code> 3-D Secure (code 123456) ·
                            <code>4000 0000 0000 0002</code> refusee · <code>4000 0000 0000 0259</code> contestee apres 30 s.</p>
                        <p class="mt-2">Detail : <code>docs/paiement/jeux-de-test.md</code></p>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <aside class="h-fit rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-recap">
            <h2 id="titre-recap" class="text-sm font-semibold uppercase tracking-wide text-muted">Recapitulatif</h2>
            <ul class="mt-4 divide-y divide-white/5 text-sm">
                <?php foreach ($articles as $article): ?>
                    <li class="flex items-start justify-between gap-4 py-3">
                        <span class="text-text"><?php echo e((string) ($article['label'] ?: ucfirst($article['item_type']))); ?></span>
                        <span class="whitespace-nowrap text-muted"><?php echo e($fcfa((float) $article['unit_price'] * (int) $article['quantity'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-4">
                <span class="text-sm font-semibold text-text">Total</span>
                <span class="text-lg font-semibold text-text"><?php echo e($fcfa($commande['total'])); ?></span>
            </div>
        </aside>
    </div>
</main>

<?php if ($payable && $passerelles !== []): ?>
<script>
(function () {
    const base = <?php echo json_encode(SITE_URL); ?>;
    const commande = <?php echo json_encode($commande['reference']); ?>;
    const formulaire = document.getElementById('formulaire-paiement');
    const zone = document.getElementById('zone-etat');
    const bouton = document.getElementById('bouton-payer');
    const blocNumero = document.getElementById('bloc-numero');
    const noteCarte = document.getElementById('note-carte');
    const finaux = { succeeded: 'success', review: 'warning', failed: 'danger', cancelled: 'danger', expired: 'danger' };
    let minuterie = null;

    function afficher(type, texte, attente) {
        zone.replaceChildren();
        const boite = document.createElement('div');
        boite.className = 'alert alert-' + type;
        boite.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        const icone = document.createElement('i');
        icone.className = attente ? 'fas fa-spinner fa-spin mt-0.5' : 'fas fa-circle-info mt-0.5';
        const span = document.createElement('span');
        span.textContent = texte;
        boite.append(icone, span);
        zone.append(boite);
    }

    function choix() {
        const coche = formulaire.querySelector('input[name="passerelle"]:checked');
        const push = coche && coche.dataset.parcours === 'push';
        blocNumero.hidden = !push;
        noteCarte.hidden = !coche || push;
        return coche;
    }

    function verrouiller(oui) {
        bouton.disabled = oui;
        formulaire.querySelectorAll('input').forEach(function (i) { i.disabled = oui; });
    }

    function suivre(id) {
        clearTimeout(minuterie);
        fetch(base + '/api/payments/suivi.php?tentative=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (s) {
                if (s.statut === 'succeeded') {
                    afficher('success', s.message, false);
                    window.location.replace(base + '/paiement.php?commande=' + encodeURIComponent(commande));
                    return;
                }
                if (finaux[s.statut]) {
                    afficher(finaux[s.statut], s.message, false);
                    verrouiller(false);
                    return;
                }
                afficher('info', s.message + ' Cette page se mettra a jour automatiquement.', true);
                minuterie = setTimeout(function () { suivre(id); }, 3000);
            })
            .catch(function () { minuterie = setTimeout(function () { suivre(id); }, 5000); });
    }

    formulaire.addEventListener('change', choix);
    choix();

    formulaire.addEventListener('submit', function (evenement) {
        evenement.preventDefault();
        const coche = choix();
        if (!coche) {
            afficher('danger', 'Choisissez un moyen de paiement.', false);
            return;
        }
        const numero = document.getElementById('numero').value.trim();
        if (coche.dataset.parcours === 'push' && numero === '') {
            afficher('danger', 'Saisissez le numero de telephone du compte a debiter.', false);
            document.getElementById('numero').focus();
            return;
        }

        // Devise : uniquement pour la carte ; le mobile money debite en XAF.
        const choixDevise = formulaire.querySelector('input[name="devise"]:checked');
        const devise = coche.dataset.parcours === 'hosted' && choixDevise ? choixDevise.value : 'XAF';

        verrouiller(true);
        afficher('info', 'Connexion a l\'operateur...', true);

        fetch(base + '/api/payments/initier.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ commande: commande, passerelle: coche.value, numero: numero, devise: devise })
        })
            .then(function (r) { return r.json(); })
            .then(function (resultat) {
                if (!resultat.succes) {
                    afficher('danger', resultat.erreur || 'Le paiement n\'a pas pu etre lance.', false);
                    verrouiller(false);
                    return;
                }
                const t = resultat.tentative;
                if (t.redirection) {
                    afficher('info', 'Redirection vers la page securisee de la banque...', true);
                    window.location.assign(t.redirection);
                    return;
                }
                afficher('info', 'Demande envoyee. Validez le paiement sur votre telephone.', true);
                suivre(t.id);
            })
            .catch(function () {
                afficher('danger', 'Connexion impossible. Verifiez votre reseau et reessayez.', false);
                verrouiller(false);
            });
    });

    // Retour de la page de la banque, ou rechargement pendant une attente.
    if (zone.dataset.tentative) {
        if (finaux[zone.dataset.statut]) {
            afficher(finaux[zone.dataset.statut], zone.dataset.message, false);
        } else {
            verrouiller(true);
            suivre(zone.dataset.tentative);
        }
    }
})();
</script>
<?php endif; ?>

<?php include 'includes/footer-tailwind.php'; ?>
