<?php
/**
 * Page Politique de Confidentialité - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Politique de Confidentialité';
$pageDescription = 'Découvrez comment Tchadok protège vos données personnelles.';

$lastUpdated = '15 Décembre 2024';

$toc = [
    ['id' => 'section1', 'label' => 'Introduction'],
    ['id' => 'section2', 'label' => 'Données collectées'],
    ['id' => 'section3', 'label' => 'Utilisation des données'],
    ['id' => 'section4', 'label' => 'Partage des données'],
    ['id' => 'section5', 'label' => 'Conservation des données'],
    ['id' => 'section6', 'label' => 'Mesures de sécurité'],
    ['id' => 'section7', 'label' => 'Vos Droits'],
    ['id' => 'section8', 'label' => 'Contact'],
];

$additionalCSS = [
    SITE_URL . '/assets/css/confidentialite-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/confidentialite.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-display font-bold text-text sm:text-4xl">Politique de confidentialité</h1>
                <p class="mt-3 text-sm text-muted">
                    Chez Tchadok, la protection de vos données personnelles est notre priorité. Cette politique explique
                    comment nous collectons, utilisons, stockons et protégeons vos informations.
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs text-muted">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                        <i class="fas fa-calendar"></i> Dernière mise à jour : <?php echo htmlspecialchars($lastUpdated); ?>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                        <i class="fas fa-shield-alt"></i> Protection renforcée des données
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
            <section class="space-y-3 scroll-mt-24" id="section1">
                <h2 class="text-2xl font-display font-bold text-text">1. Introduction</h2>
                <p class="text-sm text-muted">
                    Tchadok s'engage à protéger la vie privée de ses utilisateurs. En utilisant Tchadok, vous acceptez
                    les pratiques décrites dans cette politique de confidentialité.
                </p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Nous collectons uniquement les données nécessaires à votre expérience musicale.
                </div>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li>Transparence totale sur l'utilisation de vos donnees.</li>
                    <li>Collecte limitee au strict necessaire.</li>
                    <li>Protection maximale de vos informations.</li>
                    <li>Respect de vos droits et préférences.</li>
                </ul>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section2">
                <h2 class="text-2xl font-display font-bold text-text">2. Donnees Collectees</h2>
                <p class="text-sm text-muted">Nous collectons differents types d'informations pour vous offrir une experience personnalisee.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-user text-accent"></i> Donnees d'identification</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Nom et prenom.</li>
                            <li>Adresse email.</li>
                            <li>Numero de telephone.</li>
                            <li>Date de naissance.</li>
                            <li>Photo de profil (optionnelle).</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-music text-accent"></i> Donnees d'usage</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Historique d'ecoute.</li>
                            <li>Playlists creees.</li>
                            <li>Artistes suivis.</li>
                            <li>Preferences musicales.</li>
                            <li>Interactions avec le contenu.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-desktop text-accent"></i> Donnees techniques</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Adresse IP.</li>
                            <li>Type d'appareil.</li>
                            <li>Systeme d'exploitation.</li>
                            <li>Navigateur web.</li>
                            <li>Localisation (avec permission).</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-credit-card text-accent"></i> Donnees de paiement</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Methode de paiement.</li>
                            <li>Historique des transactions.</li>
                            <li>Informations de facturation.</li>
                            <li>Statut d'abonnement.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section3">
                <h2 class="text-2xl font-display font-bold text-text">3. Utilisation des Donnees</h2>
                <ul class="list-disc space-y-2 pl-5 text-sm text-muted">
                    <li><strong>Fourniture du service :</strong> acces au catalogue et aux fonctionnalites.</li>
                    <li><strong>Personnalisation :</strong> recommandations selon vos gouts.</li>
                    <li><strong>Communication :</strong> informations sur les nouveautes et offres.</li>
                    <li><strong>Support client :</strong> reponse a vos questions.</li>
                    <li><strong>Securite :</strong> protection contre les acces non autorises.</li>
                    <li><strong>Amelioration :</strong> analyse pour optimiser nos services.</li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Nous ne vendons jamais vos donnees personnelles a des tiers.
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section4">
                <h2 class="text-2xl font-display font-bold text-text">4. Partage des Donnees</h2>
                <p class="text-sm text-muted">Nous partageons vos donnees uniquement dans des cas specifiques et avec des garanties appropriees.</p>
                <div class="space-y-4">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-handshake text-accent"></i> Partenaires de service</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Prestataires de paiement.</li>
                            <li>Services d'hebergement et d'infrastructure.</li>
                            <li>Outils d'analyse et statistiques.</li>
                            <li>Support client.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-microphone text-accent"></i> Artistes et labels</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Statistiques d'ecoute anonymisees.</li>
                            <li>Donnees demographiques agregees.</li>
                            <li>Informations de performance.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-gavel text-accent"></i> Obligations legales</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Autorites judiciaires (demande valide).</li>
                            <li>Protection contre la fraude.</li>
                            <li>Respect des obligations legales.</li>
                        </ul>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    Tous nos partenaires sont contractuellement tenus de proteger vos donnees.
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section5">
                <h2 class="text-2xl font-display font-bold text-text">5. Conservation des Donnees</h2>
                <p class="text-sm text-muted">Nous conservons vos donnees uniquement pendant la duree necessaire.</p>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-muted">
                            <thead class="text-xs uppercase text-text">
                                <tr>
                                    <th class="py-2">Type de donnees</th>
                                    <th class="py-2">Duree de conservation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr>
                                    <td class="py-2">Donnees de compte actif</td>
                                    <td class="py-2">Pendant l'utilisation + 3 ans</td>
                                </tr>
                                <tr>
                                    <td class="py-2">Historique d'ecoute</td>
                                    <td class="py-2">2 ans a compter de l'ecoute</td>
                                </tr>
                                <tr>
                                    <td class="py-2">Donnees de paiement</td>
                                    <td class="py-2">10 ans (obligations legales)</td>
                                </tr>
                                <tr>
                                    <td class="py-2">Logs techniques</td>
                                    <td class="py-2">6 mois</td>
                                </tr>
                                <tr>
                                    <td class="py-2">Cookies</td>
                                    <td class="py-2">13 mois maximum</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section6">
                <h2 class="text-2xl font-display font-bold text-text">6. Mesures de Securite</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-lock text-accent"></i> Securite technique</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Chiffrement des donnees en transit et au repos.</li>
                            <li>Authentification a deux facteurs disponible.</li>
                            <li>Pare-feu et detection d'intrusion.</li>
                            <li>Sauvegardes securisees.</li>
                            <li>Mises a jour regulieres.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-users text-accent"></i> Securite organisationnelle</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                            <li>Acces limite aux donnees.</li>
                            <li>Formation du personnel.</li>
                            <li>Clauses de confidentialite.</li>
                            <li>Audit et surveillance continue.</li>
                            <li>Plan de reponse aux incidents.</li>
                        </ul>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    En cas d'incident, nous vous informerons dans les meilleurs delais.
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section7">
                <h2 class="text-2xl font-display font-bold text-text">7. Vos Droits</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-eye text-accent"></i> Droit d'acces</h3>
                        <p class="mt-2 text-sm text-muted">Consulter les donnees que nous detenons sur vous.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-edit text-accent"></i> Droit de rectification</h3>
                        <p class="mt-2 text-sm text-muted">Corriger vos donnees personnelles.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-trash text-accent"></i> Droit a l'effacement</h3>
                        <p class="mt-2 text-sm text-muted">Demander la suppression de vos donnees.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-pause text-accent"></i> Droit a la limitation</h3>
                        <p class="mt-2 text-sm text-muted">Limiter le traitement de vos donnees.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-download text-accent"></i> Droit a la portabilite</h3>
                        <p class="mt-2 text-sm text-muted">Recevoir vos donnees dans un format structure.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                        <h3 class="text-base font-semibold text-text"><i class="fas fa-hand-paper text-accent"></i> Droit d'opposition</h3>
                        <p class="mt-2 text-sm text-muted">Vous opposer au traitement de vos donnees.</p>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Pour exercer vos droits, contactez : privacy@tchadok.td
                </div>
            </section>

            <section class="space-y-4 scroll-mt-24" id="section8">
                <h2 class="text-2xl font-display font-bold text-text">8. Contact</h2>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <h3 class="text-base font-semibold text-text">Delegue a la protection des donnees</h3>
                    <ul class="mt-3 space-y-2 text-sm text-muted">
                        <li><i class="fas fa-envelope"></i> Email : privacy@tchadok.td</li>
                        <li><i class="fas fa-phone"></i> Telephone : +235 66 12 34 56</li>
                        <li><i class="fas fa-map-marker-alt"></i> Adresse : Avenue Charles de Gaulle, N'Djamena, Tchad</li>
                    </ul>
                    <p class="mt-4 text-sm text-muted">
                        Vous pouvez egalement deposer une plainte aupres de l'autorite de protection des donnees competente.
                    </p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-2xl font-display font-bold text-text">Gestion des cookies</h2>
                <p class="text-sm text-muted">Tchadok utilise des cookies pour ameliorer votre experience.</p>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <h3 class="text-base font-semibold text-text">Types de cookies utilises</h3>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted">
                        <li><strong>Essentiels :</strong> necessaires au fonctionnement du site.</li>
                        <li><strong>Performance :</strong> comprennent comment vous utilisez notre service.</li>
                        <li><strong>Fonctionnalité :</strong> mémorisent vos préférences.</li>
                        <li><strong>Marketing :</strong> contenus pertinents.</li>
                    </ul>
                    <button class="mt-4 rounded-full bg-accent px-5 py-2 text-xs font-semibold text-white shadow-elev-1" data-action="cookies" type="button">
                        <i class="fas fa-cookie"></i> Gérer mes préférences
                    </button>
                </div>
            </section>

            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center">
                <h3 class="text-xl font-semibold text-text">Modifications de cette politique</h3>
                <p class="mt-2 text-sm text-muted">Nous vous informerons de tout changement important par email ou notification.</p>
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
