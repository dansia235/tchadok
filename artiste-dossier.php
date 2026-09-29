<?php
/**
 * Dossier artiste verifie (MOD-06), cote artiste.
 *
 * Etapes : compte (e-mail, telephone), identite (piece, selfie), profil,
 * droits, encaissement, fiscal, puis soumission a la validation humaine.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/comptes.php';
require_once 'includes/taxonomie.php';
require_once 'includes/paiement/chargement.php';
require_once 'includes/dossier-artiste.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/artiste-dossier.php'));
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
    $r = match ((string) ($_POST['action'] ?? '')) {
        'email'          => Comptes::envoyerVerification($userId),
        'telephone'      => Comptes::envoyerCodeTelephone($userId, (string) ($_POST['numero'] ?? '')),
        'code'           => Comptes::verifierCodeTelephone($userId, (string) ($_POST['code'] ?? '')),
        'identite'       => DossierArtiste::deposerPiece($artistId, 'identite', $_FILES['piece'] ?? []),
        'selfie'         => DossierArtiste::deposerPiece($artistId, 'selfie', $_FILES['piece'] ?? []),
        'profil'         => DossierArtiste::mettreAJourProfil($artistId, $_POST),
        'photo'          => (static function () use ($artistId): array {
            $d = uploadFile($_FILES['photo'] ?? [], __DIR__ . '/' . IMAGES_PATH, ALLOWED_IMAGE_TYPES, MAX_IMAGE_SIZE);
            if (!$d['success']) {
                return ['succes' => false, 'message' => $d['message']];
            }
            TchadokDatabase::getInstance()->getConnection()->prepare('UPDATE artists SET profile_image = ? WHERE id = ?')->execute([IMAGES_PATH . $d['filename'], $artistId]);
            return ['succes' => true, 'message' => 'Photo enregistree.'];
        })(),
        'droits'         => DossierArtiste::declarerDroits($artistId, !empty($_POST['titularite']), !empty($_POST['exclusivite'])),
        'fiscal'         => DossierArtiste::renseignerFiscal($artistId, (string) ($_POST['regime'] ?? ''), (string) ($_POST['identifiant'] ?? '')),
        'soumettre'      => DossierArtiste::soumettre($artistId),
        default          => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/artiste-dossier.php');
    }
    $erreur = $r['message'];
}

$stmt->execute([$userId]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);
$dossier = DossierArtiste::dossier($artistId);
$etapes = DossierArtiste::etapes($artistId);
$stmt = $db->prepare('SELECT email, email_verified, phone, phone_verified_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$compte = $stmt->fetch(PDO::FETCH_ASSOC);
$genres = getGenresSelectionnables();
$principal = Taxonomie::genrePrincipal($artistId);
$secondaires = Taxonomie::genresSecondaires($artistId);
$modifiable = in_array($dossier['status'], ['brouillon', 'a_completer'], true);
$champ = 'mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text';
$coche = static fn (bool $ok): string => $ok ? '<span class="text-emerald-300" aria-label="fait">✔</span>' : '<span class="text-amber-300" aria-label="a faire">•</span>';
$statuts = ['brouillon' => 'En preparation', 'soumis' => 'En cours de verification', 'valide' => 'Valide', 'a_completer' => 'A completer', 'refuse' => 'Refuse'];

$pageTitle = 'Mon dossier artiste';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6">
        <header class="rounded-3xl border border-white/10 bg-surface/75 p-6">
            <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace artiste</p>
            <h1 class="mt-2 text-2xl font-display font-bold text-text">Mon dossier artiste</h1>
            <p class="mt-2 text-sm text-muted" data-statut-dossier="<?php echo e($dossier['status']); ?>">Statut : <strong class="text-text"><?php echo e($statuts[$dossier['status']]); ?></strong>
                <?php if ($dossier['status'] === 'valide'): ?> · niveau <strong class="text-text"><?php echo e(DossierArtiste::NIVEAUX[$dossier['level']]); ?></strong><?php endif; ?></p>
            <?php if ($dossier['decision_reason'] && in_array($dossier['status'], ['a_completer', 'refuse'], true)): ?><p class="mt-2 text-sm text-amber-200">Motif : <?php echo e($dossier['decision_reason']); ?></p><?php endif; ?>
            <p class="mt-2 text-xs text-muted">Niveaux : <strong>Decouverte</strong> (publication gratuite, <?php echo DossierArtiste::PLAFOND_DECOUVERTE; ?> titres par 30 jours), <strong>Verifie</strong> (vente et versements, identite controlee, badge), <strong>Partenaire</strong> (mise en avant, commission negociee).</p>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-4" role="alert"><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </header>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e1">
            <h2 id="e1" class="text-lg font-semibold text-text"><?php echo $coche($etapes['compte']); ?> 1. Compte</h2>
            <p class="mt-2 text-sm text-muted">E-mail <?php echo e($compte['email']); ?> : <?php echo (int) $compte['email_verified'] === 1 ? 'confirme' : 'a confirmer'; ?></p>
            <?php if ((int) $compte['email_verified'] !== 1): ?><form method="POST" class="mt-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="email"><button class="text-xs underline">Renvoyer le lien de confirmation</button></form><?php endif; ?>
            <p class="mt-3 text-sm text-muted">Telephone : <?php echo $compte['phone_verified_at'] ? e($compte['phone']) . ' (verifie)' : 'a verifier par SMS'; ?></p>
            <?php if (!$compte['phone_verified_at']): ?>
                <form method="POST" class="mt-2 flex flex-wrap items-end gap-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="telephone">
                    <label class="text-xs text-muted">Numero<input name="numero" inputmode="tel" value="<?php echo e($compte['phone'] ?? ''); ?>" class="<?php echo $champ; ?>"></label><button class="rounded-full border border-white/15 px-3 py-2 text-xs font-semibold text-text">Recevoir un code</button></form>
                <form method="POST" class="mt-2 flex flex-wrap items-end gap-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="code">
                    <label class="text-xs text-muted">Code recu<input name="code" inputmode="numeric" maxlength="6" class="<?php echo $champ; ?>"></label><button class="rounded-full bg-accent px-3 py-2 text-xs font-semibold text-white">Verifier</button></form>
            <?php endif; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e2">
            <h2 id="e2" class="text-lg font-semibold text-text"><?php echo $coche($etapes['identite']); ?> 2. Identite</h2>
            <p class="mt-1 text-xs text-muted">Piece d'identite et selfie en la tenant. Fichiers chiffres, conserves hors du site, consultes par la seule equipe de validation (chaque consultation est tracee).</p>
            <?php if ($modifiable): ?>
                <?php foreach (['identite' => 'Piece d\'identite', 'selfie' => 'Selfie avec la piece'] as $type => $libelle): $fait = $dossier[$type === 'identite' ? 'identity_doc' : 'selfie_doc'] !== null; ?>
                    <form method="POST" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="<?php echo $type; ?>">
                        <label class="text-xs text-muted"><?php echo e($libelle); ?><?php echo $fait ? ' — deposee' : ''; ?><input type="file" name="piece" accept=".jpg,.jpeg,.png,.pdf" required class="mt-1 block text-sm text-text"></label>
                        <button class="rounded-full border border-white/15 px-3 py-2 text-xs font-semibold text-text">Envoyer</button></form>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="profil">
            <h2 id="profil" class="text-lg font-semibold text-text"><?php echo $coche($etapes['profil']); ?> 3. Profil artiste</h2>
            <form method="POST" class="mt-3 space-y-3"><?php echo csrfField(); ?><input type="hidden" name="action" value="profil">
                <label class="block text-xs text-muted">Nom de scene<input name="stage_name" required value="<?php echo e($artiste['stage_name']); ?>" class="<?php echo $champ; ?>"></label>
                <label class="block text-xs text-muted">Biographie (30 caracteres au moins)<textarea name="bio" rows="4" class="<?php echo $champ; ?>"><?php echo e($artiste['bio'] ?? ''); ?></textarea></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs text-muted">Genre principal<select name="genre_principal" required class="<?php echo $champ; ?>"><option value="">—</option><?php echo optionsGenres($genres, $principal['id'] ?? null); ?></select></label>
                    <label class="text-xs text-muted">Genres secondaires (3 au plus)<select name="genres_secondaires[]" multiple size="4" class="<?php echo $champ; ?>"><?php foreach ($genres as $g): ?><option value="<?php echo (int) $g['id']; ?>"<?php echo in_array((int) $g['id'], $secondaires, true) ? ' selected' : ''; ?>><?php echo e($g['name']); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'website' => 'Site web'] as $r => $l): ?>
                        <label class="text-xs text-muted"><?php echo e($l); ?><input name="<?php echo $r; ?>" type="url" value="<?php echo e($artiste[$r] ?? ''); ?>" class="<?php echo $champ; ?>"></label>
                    <?php endforeach; ?>
                </div>
                <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Enregistrer le profil</button>
                <a class="ml-2 text-xs underline" href="<?php echo SITE_URL; ?>/proposer-genre.php">Votre genre manque ?</a>
            </form>
            <form method="POST" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="photo">
                <label class="text-xs text-muted">Photo<?php echo $artiste['profile_image'] ? ' — deposee' : ''; ?><input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" required class="mt-1 block text-sm text-text"></label>
                <button class="rounded-full border border-white/15 px-3 py-2 text-xs font-semibold text-text">Envoyer</button></form>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e4">
            <h2 id="e4" class="text-lg font-semibold text-text"><?php echo $coche($etapes['droits']); ?> 4. Droits</h2>
            <?php if (Contrats::acceptationRequise($artistId)): ?><p class="mt-2 text-sm"><a class="underline" href="<?php echo SITE_URL; ?>/contrat.php?retour=%2Fartiste-dossier.php">Lire et accepter le contrat de distribution</a></p><?php endif; ?>
            <?php if ($dossier['rights_declared_at']): ?>
                <p class="mt-2 text-sm text-muted">Declaration enregistree le <?php echo e(date('d/m/Y', strtotime((string) $dossier['rights_declared_at']))); ?>.</p>
            <?php else: ?>
                <form method="POST" class="mt-3 space-y-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="droits">
                    <label class="flex items-start gap-2 text-sm text-text"><input type="checkbox" name="titularite" value="1" class="mt-1"> Je declare etre titulaire des droits (ou autorise par leurs titulaires) sur les oeuvres que je publierai.</label>
                    <label class="flex items-start gap-2 text-sm text-text"><input type="checkbox" name="exclusivite" value="1" class="mt-1"> J'atteste qu'aucune cession exclusive a un tiers n'interdit leur diffusion sur Tchadok.</label>
                    <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Enregistrer la declaration</button></form>
            <?php endif; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e5">
            <h2 id="e5" class="text-lg font-semibold text-text"><?php echo $coche($etapes['encaissement']); ?> 5. Encaissement <span class="text-xs font-normal text-muted">(pour les versements)</span></h2>
            <p class="mt-2 text-sm"><a class="underline" href="<?php echo SITE_URL; ?>/artiste-revenus.php">Declarer mon compte mobile money</a> — il doit etre a votre nom ; il est verifie par notre equipe.</p>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e6">
            <h2 id="e6" class="text-lg font-semibold text-text"><?php echo $coche($etapes['fiscal']); ?> 6. Fiscal <span class="text-xs font-normal text-muted">(exige au-dela de <?php echo number_format(DossierArtiste::SEUIL_FISCAL, 0, ',', ' '); ?> FCFA verses)</span></h2>
            <form method="POST" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"><?php echo csrfField(); ?><input type="hidden" name="action" value="fiscal">
                <label class="text-xs text-muted">Regime<select name="regime" class="<?php echo $champ; ?>"><?php foreach (['particulier' => 'Particulier', 'entreprise_individuelle' => 'Entreprise individuelle', 'societe' => 'Societe', 'association' => 'Association'] as $c => $l): ?><option value="<?php echo $c; ?>"<?php echo $dossier['fiscal_regime'] === $c ? ' selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-muted">NIF (si applicable)<input name="identifiant" value="<?php echo e($dossier['fiscal_id'] ?? ''); ?>" class="<?php echo $champ; ?>"></label>
                <button class="rounded-full border border-white/15 px-3 py-2 text-xs font-semibold text-text">Enregistrer</button></form>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="e7">
            <h2 id="e7" class="text-lg font-semibold text-text">7. Validation</h2>
            <?php if ($modifiable): ?>
                <p class="mt-2 text-sm text-muted">Etapes 1 a 4 completes, soumettez votre dossier : notre equipe le verifie et vous repond par e-mail.</p>
                <form method="POST" class="mt-3"><?php echo csrfField(); ?><input type="hidden" name="action" value="soumettre"><button class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Soumettre mon dossier</button></form>
            <?php else: ?>
                <p class="mt-2 text-sm text-muted"><?php echo e($statuts[$dossier['status']]); ?>.</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
