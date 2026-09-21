<?php
/**
 * Page Conditions d'Utilisation - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = "Conditions d'Utilisation";
$pageDescription = "Consultez les conditions générales d'utilisation de la plateforme Tchadok.";

$lastUpdated = '15 Décembre 2024';
$jurisdiction = 'République du Tchad';

$toc = [
    ['id' => 'article1', 'label' => 'Définitions'],
    ['id' => 'article2', 'label' => 'Acceptation des Conditions'],
    ['id' => 'article3', 'label' => 'Accès aux Services'],
    ['id' => 'article4', 'label' => 'Compte Utilisateur'],
    ['id' => 'article5', 'label' => 'Contenu et Propriete Intellectuelle'],
    ['id' => 'article6', 'label' => 'Obligations des Utilisateurs'],
    ['id' => 'article7', 'label' => 'Paiements et Abonnements'],
    ['id' => 'article8', 'label' => 'Responsabilité'],
    ['id' => 'article9', 'label' => 'Résiliation'],
    ['id' => 'article10', 'label' => 'Dispositions Générales'],
];

$additionalCSS = [
    SITE_URL . '/assets/css/conditions-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/conditions.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-display font-bold text-text sm:text-4xl">Conditions d'utilisation</h1>
                <p class="mt-3 text-sm text-muted">
                    Ces conditions régissent l'accès et l'utilisation de la plateforme Tchadok. En utilisant nos services,
                    vous acceptez ces conditions dans leur intégralité.
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs text-muted">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                        <i class="fas fa-calendar"></i> Dernière mise à jour : <?php echo htmlspecialchars($lastUpdated); ?>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                        <i class="fas fa-gavel"></i> Droit applicable : <?php echo htmlspecialchars($jurisdiction); ?>
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h2 class="text-lg font-semibold text-text">Sommaire</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($toc as $item): ?>
                        <a class="toc-link inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-text hover:bg-white/10" href="#<?php echo $item['id']; ?>">
                            <span class="text-xs text-muted">#</span>
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-14">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-10">
            <article class="space-y-3 scroll-mt-24" id="article1">
                <h2 class="text-2xl font-display font-bold text-text">Article 1 : Définitions</h2>
                <p class="text-sm text-muted">Aux fins des présentes conditions d'utilisation, les termes suivants sont définis comme suit :</p>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li><strong>Tchadok</strong> désigne le service de streaming musical en ligne accessible via le site web tchadok.td et ses applications mobiles.</li>
                    <li><strong>Utilisateur</strong> désigne toute personne physique ou morale utilisant les services de Tchadok.</li>
                    <li><strong>Artiste</strong> désigne tout créateur de contenu musical publiant ses oeuvres sur la plateforme.</li>
                    <li><strong>Contenu</strong> désigne toutes les oeuvres musicales, vidéos, images, textes et autres éléments disponibles sur la plateforme.</li>
                    <li><strong>Services</strong> désigne l'ensemble des fonctionnalités offertes par Tchadok, incluant l'écoute de musique, le téléchargement et les abonnements Premium.</li>
                </ul>
            </article>

            <article class="space-y-3 scroll-mt-24" id="article2">
                <h2 class="text-2xl font-display font-bold text-text">Article 2 : Acceptation des Conditions</h2>
                <p class="text-sm text-muted">L'acces et l'utilisation de Tchadok impliquent l'acceptation pleine et entiere des presentes conditions d'utilisation.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Ces conditions peuvent etre modifiees a tout moment. Les utilisateurs seront informes des changements importants.
                </div>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Avoir lu et compris ces conditions d'utilisation.</li>
                    <li>Etre majeur ou avoir l'autorisation parentale necessaire.</li>
                    <li>Disposer de la capacite juridique pour contracter.</li>
                    <li>Accepter de respecter toutes les lois applicables.</li>
                </ul>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article3">
                <h2 class="text-2xl font-display font-bold text-text">Article 3 : Acces aux Services</h2>
                <p class="text-sm text-muted">Tchadok propose differents niveaux d'acces a ses services :</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-music text-accent"></i> Acces Gratuit</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Ecoute de musique avec publicites.</li>
                            <li>Qualite audio standard.</li>
                            <li>Acces limite aux fonctionnalites.</li>
                            <li>Creation de playlists de base.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-amber-400/30 bg-amber-400/10 p-5">
                        <h3 class="text-base font-semibold text-amber-200"><i class="fas fa-crown"></i> Abonnement Premium</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-100">
                            <li>Ecoute illimitee sans publicite.</li>
                            <li>Qualite audio haute definition.</li>
                            <li>Telechargement pour ecoute hors ligne.</li>
                            <li>Acces prioritaire aux nouveautes.</li>
                            <li>Support client prioritaire.</li>
                        </ul>
                    </div>
                </div>
                <p class="text-sm text-muted">Tchadok se reserve le droit de modifier, suspendre ou interrompre tout ou partie de ses services a tout moment.</p>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article4">
                <h2 class="text-2xl font-display font-bold text-text">Article 4 : Compte Utilisateur</h2>
                <p class="text-sm text-muted">Pour acceder a certains services, vous devez creer un compte utilisateur. Vous vous engagez a :</p>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Fournir des informations exactes et a jour.</li>
                    <li>Maintenir la confidentialite de vos identifiants.</li>
                    <li>Vous deconnecter a la fin de chaque session.</li>
                    <li>Notifier immediatement toute utilisation non autorisee.</li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Vous etes responsable de toutes les activites qui se deroulent sur votre compte.
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <h4 class="text-sm font-semibold text-text">Types de comptes</h4>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                        <li><strong>Compte Melomane :</strong> ecouter et acheter de la musique.</li>
                        <li><strong>Compte Artiste :</strong> publier et commercialiser du contenu musical.</li>
                        <li><strong>Compte Partenaire :</strong> labels et distributeurs.</li>
                    </ul>
                </div>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article5">
                <h2 class="text-2xl font-display font-bold text-text">Article 5 : Contenu et Propriete Intellectuelle</h2>
                <p class="text-sm text-muted">Tout le contenu disponible sur Tchadok est protege par les lois sur la propriete intellectuelle.</p>
                <h4 class="text-base font-semibold text-text">Contenu de Tchadok</h4>
                <p class="text-sm text-muted">La plateforme, son interface, ses fonctionnalites et tous les elements qui la composent sont la propriete exclusive de Tchadok ou de ses partenaires.</p>
                <h4 class="text-base font-semibold text-text">Contenu des utilisateurs</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Vous conservez vos droits de propriete intellectuelle.</li>
                    <li>Vous accordez a Tchadok une licence non-exclusive pour diffuser votre contenu.</li>
                    <li>Vous garantissez posseder tous les droits necessaires.</li>
                    <li>Votre contenu peut etre soumis a moderation.</li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    Tchadok respecte les droits d'auteur et dispose d'une politique de signalement pour les violations.
                </div>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article6">
                <h2 class="text-2xl font-display font-bold text-text">Article 6 : Obligations des Utilisateurs</h2>
                <p class="text-sm text-muted">En utilisant Tchadok, vous vous engagez a :</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-emerald-400/30 bg-emerald-400/10 p-5">
                        <h3 class="text-base font-semibold text-emerald-200"><i class="fas fa-check-circle"></i> Autorise</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-emerald-100">
                            <li>Utiliser les services dans le respect des lois.</li>
                            <li>Respecter les droits des autres utilisateurs.</li>
                            <li>Publier du contenu original ou licencie.</li>
                            <li>Signaler les contenus inappropries.</li>
                            <li>Maintenir un comportement respectueux.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-rose-400/30 bg-rose-400/10 p-5">
                        <h3 class="text-base font-semibold text-rose-200"><i class="fas fa-times-circle"></i> Interdit</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-rose-100">
                            <li>Violer les droits de propriete intellectuelle.</li>
                            <li>Publier du contenu illegal ou offensant.</li>
                            <li>Harceler ou menacer d'autres utilisateurs.</li>
                            <li>Utiliser des moyens automatises d'acces.</li>
                            <li>Contourner les mesures de sécurité.</li>
                        </ul>
                    </div>
                </div>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article7">
                <h2 class="text-2xl font-display font-bold text-text">Article 7 : Paiements et Abonnements</h2>
                <h4 class="text-base font-semibold text-text">Tarification</h4>
                <p class="text-sm text-muted">Les prix affiches sont indiques en Franc CFA (XAF) et incluent toutes les taxes applicables.</p>
                <h4 class="text-base font-semibold text-text">Moyens de paiement</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Cartes bancaires (Visa, Mastercard).</li>
                    <li>Mobile Money (Airtel Money, Moov Money).</li>
                    <li>Virements bancaires.</li>
                    <li>Solutions de paiement partenaires.</li>
                </ul>
                <h4 class="text-base font-semibold text-text">Abonnements Premium</h4>
                <p class="text-sm text-muted">Les abonnements sont renouveles automatiquement. Vous pouvez annuler a tout moment depuis votre compte.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Une periode d'essai gratuite peut etre proposee aux nouveaux utilisateurs.
                </div>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article8">
                <h2 class="text-2xl font-display font-bold text-text">Article 8 : Responsabilite</h2>
                <h4 class="text-base font-semibold text-text">Limitation de responsabilite</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Fonctionnement ininterrompu ou sans erreur.</li>
                    <li>Exactitude ou fiabilite de tout contenu.</li>
                    <li>Disponibilite permanente du service.</li>
                    <li>Absence de virus ou d'elements nuisibles.</li>
                </ul>
                <h4 class="text-base font-semibold text-text">Indemnisation</h4>
                <p class="text-sm text-muted">Vous acceptez d'indemniser Tchadok en cas de reclamation resultant de votre utilisation du service.</p>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article9">
                <h2 class="text-2xl font-display font-bold text-text">Article 9 : Resiliation</h2>
                <h4 class="text-base font-semibold text-text">Resiliation par l'utilisateur</h4>
                <p class="text-sm text-muted">Vous pouvez fermer votre compte a tout moment via les parametres de votre compte.</p>
                <h4 class="text-base font-semibold text-text">Resiliation par Tchadok</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Violation des presentes conditions.</li>
                    <li>Comportement frauduleux ou illegal.</li>
                    <li>Non-paiement des services.</li>
                    <li>Inactivite prolongee.</li>
                </ul>
                <h4 class="text-base font-semibold text-text">Consequences</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Acces au compte desactive.</li>
                    <li>Suppression des playlists et préférences.</li>
                    <li>Aucun remboursement sur la periode non utilisee.</li>
                    <li>Certaines dispositions restent applicables.</li>
                </ul>
            </article>

            <article class="space-y-4 scroll-mt-24" id="article10">
                <h2 class="text-2xl font-display font-bold text-text">Article 10 : Dispositions Generales</h2>
                <h4 class="text-base font-semibold text-text">Droit applicable</h4>
                <p class="text-sm text-muted">Les presentes conditions sont regies par le droit de la Republique du Tchad.</p>
                <h4 class="text-base font-semibold text-text">Integralite de l'accord</h4>
                <p class="text-sm text-muted">Ces conditions constituent l'integralite de l'accord entre vous et Tchadok.</p>
                <h4 class="text-base font-semibold text-text">Divisibilite</h4>
                <p class="text-sm text-muted">Si une disposition est jugee invalide, les autres dispositions restent en vigueur.</p>
                <h4 class="text-base font-semibold text-text">Contact</h4>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Email : legal@tchadok.td</li>
                    <li>Telephone : +235 66 12 34 56</li>
                    <li>Adresse : Avenue Charles de Gaulle, N'Djamena, Tchad</li>
                </ul>
            </article>

            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center">
                <h3 class="text-xl font-semibold text-text">Des questions sur nos conditions ?</h3>
                <p class="mt-2 text-sm text-muted">Notre équipe juridique est à votre disposition pour clarifier tout point.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="<?php echo SITE_URL; ?>/contact.php" class="rounded-full bg-accent px-5 py-2 text-xs font-semibold text-white shadow-elev-1">
                        <i class="fas fa-envelope"></i> Nous contacter
                    </a>
                    <a href="<?php echo SITE_URL; ?>/aide.php" class="rounded-full border border-white/10 bg-white/5 px-5 py-2 text-xs font-semibold text-text">
                        <i class="fas fa-question-circle"></i> Centre d'aide
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
