<?php
/**
 * Activation de la double authentification (SEC-20).
 *
 * L'ecran precedent, retire en SEC-14, n'enregistrait rien et transmettait un
 * secret d'exemple a un service tiers pour fabriquer un QR code. Celui-ci
 * enregistre reellement, et le secret ne sort pas du serveur : il s'affiche
 * ici, et se recopie dans l'application d'authentification.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=2fa');
}

$userId = (int) $_SESSION['user_id'];
$user = getCurrentUser();
if (!$user) {
    redirect(SITE_URL . '/login.php?redirect=2fa');
}

$pageTitle = 'Double authentification';
$pageDescription = 'Ajoutez un second facteur a votre compte';
$hideTopNav = true;
$hideFooter = true;

$message = '';
$typeMessage = '';
$codesRemis = [];
$active = DeuxFacteurs::estActive($userId);
$exigee = DeuxFacteurs::exigee($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'activer') {
        LimiteDebit::appliquer('second-facteur');

        $resultat = DeuxFacteurs::activer($userId, (string) ($_POST['secret'] ?? ''), (string) ($_POST['code'] ?? ''));
        $message = $resultat['message'];
        $typeMessage = $resultat['succes'] ? 'succes' : 'erreur';
        $codesRemis = $resultat['codes'];
        $active = DeuxFacteurs::estActive($userId);
    } elseif ($action === 'desactiver') {
        // Le mot de passe est redemande : desactiver un second facteur depuis
        // une session laissee ouverte annulerait la protection.
        $db = TchadokDatabase::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(password_hash, ''), password) FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $empreinte = (string) $stmt->fetchColumn();

        if (!verifyPassword((string) ($_POST['mot_de_passe'] ?? ''), $empreinte)) {
            $message = 'Mot de passe incorrect.';
            $typeMessage = 'erreur';
        } elseif ($exigee) {
            $message = 'Votre role impose la double authentification : elle ne peut pas etre retiree.';
            $typeMessage = 'erreur';
        } else {
            DeuxFacteurs::desactiver($userId);
            $message = 'Double authentification desactivee.';
            $typeMessage = 'succes';
            $active = false;
        }
    }
}

// Nouveau secret propose tant que rien n'est active. Il n'est enregistre qu'au
// moment ou un premier code valide le prouve.
$secret = $active ? '' : (string) ($_POST['secret'] ?? DeuxFacteurs::genererSecret());
$uri = $active ? '' : DeuxFacteurs::uriOtpauth($secret, (string) ($user['email'] ?? 'compte'));

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <section>
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-muted">Securite</p>
                        <h1 class="mt-2 text-3xl font-display font-bold text-text">Double authentification</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                            Un code a six chiffres, renouvele toutes les trente secondes par une application
                            installee sur votre telephone. Meme si votre mot de passe fuit, il ne suffit plus.
                        </p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/security-settings.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                        <i class="fas fa-arrow-left mr-2"></i>Securite
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert <?php echo $typeMessage === 'succes' ? 'alert-success' : 'alert-danger'; ?> mt-6" role="status">
                        <i class="fas <?php echo $typeMessage === 'succes' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5"></i>
                        <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($codesRemis): ?>
                    <div class="mt-6 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-5">
                        <h2 class="text-sm font-semibold text-amber-100">Vos dix codes de secours</h2>
                        <p class="mt-2 text-xs text-amber-100/80">
                            Ils ne seront plus jamais affiches. Notez-les maintenant, et gardez-les ailleurs que
                            sur le telephone qui genere les codes. Chacun ne sert qu'une fois.
                        </p>
                        <ul class="mt-4 grid gap-2 font-mono text-sm text-amber-50 sm:grid-cols-2">
                            <?php foreach ($codesRemis as $code): ?>
                                <li class="rounded-xl bg-black/20 px-3 py-2"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($exigee && !$active): ?>
                    <div class="alert alert-warning mt-6" role="status">
                        <i class="fas fa-triangle-exclamation mt-0.5"></i>
                        <span>Votre role donne des droits d'ecriture en administration : la double authentification est obligatoire pour y acceder.</span>
                    </div>
                <?php endif; ?>

                <?php if ($active): ?>
                    <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-5">
                        <p class="text-sm font-semibold text-emerald-100">Double authentification active</p>
                        <p class="mt-2 text-xs text-emerald-100/80">
                            Codes de secours restants : <?php echo DeuxFacteurs::codesDeSecoursRestants($userId); ?> sur <?php echo DeuxFacteurs::CODES_DE_SECOURS; ?>.
                        </p>
                    </div>

                    <?php if (!$exigee): ?>
                        <details class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-text">Desactiver</summary>
                            <form method="POST" action="" class="mt-4 space-y-3">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="desactiver">
                                <label for="mot_de_passe" class="text-sm text-muted">Confirmez avec votre mot de passe</label>
                                <input id="mot_de_passe" name="mot_de_passe" type="password" required autocomplete="current-password"
                                       class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                                <button type="submit" class="rounded-full border border-rose-400/40 bg-rose-500/10 px-5 py-2 text-sm font-semibold text-rose-200">
                                    Desactiver
                                </button>
                            </form>
                        </details>
                    <?php endif; ?>
                <?php else: ?>
                    <ol class="mt-6 space-y-5">
                        <li class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm font-semibold text-text">1. Installez une application d'authentification</p>
                            <p class="mt-1 text-xs text-muted">
                                Google Authenticator, Authy, FreeOTP ou Aegis. Elles fonctionnent sans connexion.
                            </p>
                        </li>
                        <li class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm font-semibold text-text">2. Ajoutez ce compte, par saisie manuelle</p>
                            <p class="mt-1 text-xs text-muted">
                                Choisissez « saisir une cle de configuration », puis recopiez la cle ci-dessous.
                                Type : « base sur le temps ».
                            </p>
                            <p class="mt-3 select-all rounded-xl bg-black/30 px-4 py-3 font-mono text-base tracking-widest text-text">
                                <?php echo htmlspecialchars(DeuxFacteurs::secretLisible($secret), ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <p class="mt-2 break-all text-[11px] text-muted">
                                Adresse equivalente : <span class="select-all"><?php echo htmlspecialchars($uri, ENT_QUOTES, 'UTF-8'); ?></span>
                            </p>
                            <p class="mt-2 text-[11px] text-muted">
                                Pas de QR code : le fabriquer demanderait d'envoyer cette cle a un service
                                exterieur, ce que nous ne faisons pas.
                            </p>
                        </li>
                        <li class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm font-semibold text-text">3. Confirmez avec le code affiche</p>
                            <form method="POST" action="" class="mt-3 space-y-3">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="activer">
                                <input type="hidden" name="secret" value="<?php echo htmlspecialchars($secret, ENT_QUOTES, 'UTF-8'); ?>">
                                <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                                       placeholder="123456"
                                       class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-center text-lg tracking-[0.3em] text-text focus:outline-none focus:ring-2 focus:ring-accent/60">
                                <button type="submit" class="w-full rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">
                                    Activer la double authentification
                                </button>
                            </form>
                        </li>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
