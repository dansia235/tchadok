<?php
/**
 * Page Conditions d'Utilisation - Tchadok Platform
 * Conditions générales d'utilisation et mentions légales
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Conditions d\'Utilisation';
$pageDescription = 'Consultez les conditions générales d\'utilisation de la plateforme Tchadok. Droits, obligations et modalités d\'usage.';

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container" style="padding-top: 100px;">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mb-4">Conditions d'Utilisation</h1>
            <p class="lead mb-5">Ces conditions d'utilisation régissent l'accès et l'utilisation de la plateforme Tchadok. En utilisant nos services, vous acceptez ces conditions dans leur intégralité.</p>
            
            <div class="text-muted mb-5">
                <p><i class="fas fa-calendar"></i> Dernière mise à jour : 15 Décembre 2024</p>
                <p><i class="fas fa-gavel"></i> Droit applicable : République du Tchad</p>
            </div>
        </div>
    </div>

    <!-- Table of Contents -->
    <div class="row mb-5">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Sommaire</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><a href="#article1">1. Définitions</a></li>
                                <li class="mb-2"><a href="#article2">2. Acceptation des Conditions</a></li>
                                <li class="mb-2"><a href="#article3">3. Accès aux Services</a></li>
                                <li class="mb-2"><a href="#article4">4. Compte Utilisateur</a></li>
                                <li class="mb-2"><a href="#article5">5. Contenu et Propriété Intellectuelle</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><a href="#article6">6. Obligations des Utilisateurs</a></li>
                                <li class="mb-2"><a href="#article7">7. Paiements et Abonnements</a></li>
                                <li class="mb-2"><a href="#article8">8. Responsabilité</a></li>
                                <li class="mb-2"><a href="#article9">9. Résiliation</a></li>
                                <li class="mb-2"><a href="#article10">10. Dispositions Générales</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Content -->
    <div class="row">
        <div class="col-lg-12">
            
            <!-- Article 1 -->
            <article class="mb-5" id="article1">
                <h2 class="h3 mb-3">Article 1 : Définitions</h2>
                <p>Aux fins des présentes conditions d'utilisation, les termes suivants sont définis comme suit :</p>
                <ul>
                    <li><strong>"Tchadok" ou "la Plateforme"</strong> désigne le service de streaming musical en ligne accessible via le site web tchadok.td et ses applications mobiles.</li>
                    <li><strong>"Utilisateur"</strong> désigne toute personne physique ou morale utilisant les services de Tchadok.</li>
                    <li><strong>"Artiste"</strong> désigne tout créateur de contenu musical publiant ses œuvres sur la plateforme.</li>
                    <li><strong>"Contenu"</strong> désigne toutes les œuvres musicales, vidéos, images, textes et autres éléments disponibles sur la plateforme.</li>
                    <li><strong>"Services"</strong> désigne l'ensemble des fonctionnalités offertes par Tchadok, incluant l'écoute de musique, le téléchargement, les abonnements Premium, etc.</li>
                </ul>
            </article>

            <!-- Article 2 -->
            <article class="mb-5" id="article2">
                <h2 class="h3 mb-3">Article 2 : Acceptation des Conditions</h2>
                <p>L'accès et l'utilisation de Tchadok impliquent l'acceptation pleine et entière des présentes conditions d'utilisation. Si vous n'acceptez pas ces conditions, vous ne devez pas utiliser nos services.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Ces conditions peuvent être modifiées à tout moment. Les utilisateurs seront informés des modifications importantes par email ou via une notification sur la plateforme.
                </div>
                
                <p>En créant un compte ou en utilisant nos services, vous confirmez :</p>
                <ul>
                    <li>Avoir lu et compris ces conditions d'utilisation</li>
                    <li>Être majeur ou avoir l'autorisation parentale nécessaire</li>
                    <li>Disposer de la capacité juridique pour contracter</li>
                    <li>Accepter de respecter toutes les lois applicables</li>
                </ul>
            </article>

            <!-- Article 3 -->
            <article class="mb-5" id="article3">
                <h2 class="h3 mb-3">Article 3 : Accès aux Services</h2>
                <p>Tchadok propose différents niveaux d'accès à ses services :</p>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-music"></i> Accès Gratuit</h4>
                                <ul>
                                    <li>Écoute de musique avec publicités</li>
                                    <li>Qualité audio standard</li>
                                    <li>Accès limité aux fonctionnalités</li>
                                    <li>Création de playlists de base</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h4 class="card-title text-warning"><i class="fas fa-crown"></i> Abonnement Premium</h4>
                                <ul>
                                    <li>Écoute illimitée sans publicité</li>
                                    <li>Qualité audio haute définition</li>
                                    <li>Téléchargement pour écoute hors ligne</li>
                                    <li>Accès prioritaire aux nouveautés</li>
                                    <li>Support client prioritaire</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p>Tchadok se réserve le droit de modifier, suspendre ou interrompre tout ou partie de ses services à tout moment, temporairement ou définitivement, avec ou sans préavis.</p>
            </article>

            <!-- Article 4 -->
            <article class="mb-5" id="article4">
                <h2 class="h3 mb-3">Article 4 : Compte Utilisateur</h2>
                <p>Pour accéder à certains services, vous devez créer un compte utilisateur. Vous vous engagez à :</p>
                <ul>
                    <li>Fournir des informations exactes et à jour</li>
                    <li>Maintenir la confidentialité de vos identifiants</li>
                    <li>Vous déconnecter à la fin de chaque session</li>
                    <li>Notifier immédiatement toute utilisation non autorisée</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Important :</strong> Vous êtes entièrement responsable de toutes les activités qui se déroulent sur votre compte. Tchadok ne peut être tenu responsable des pertes résultant d'une utilisation non autorisée de votre compte.
                </div>
                
                <h4>Types de Comptes</h4>
                <ul>
                    <li><strong>Compte Mélomane :</strong> Pour écouter et acheter de la musique</li>
                    <li><strong>Compte Artiste :</strong> Pour publier et commercialiser du contenu musical</li>
                    <li><strong>Compte Partenaire :</strong> Pour les labels et distributeurs</li>
                </ul>
            </article>

            <!-- Article 5 -->
            <article class="mb-5" id="article5">
                <h2 class="h3 mb-3">Article 5 : Contenu et Propriété Intellectuelle</h2>
                <p>Tout le contenu disponible sur Tchadok est protégé par les lois sur la propriété intellectuelle.</p>
                
                <h4>Contenu de Tchadok</h4>
                <p>La plateforme Tchadok, son interface, ses fonctionnalités et tous les éléments qui la composent sont la propriété exclusive de Tchadok ou de ses partenaires.</p>
                
                <h4>Contenu des Utilisateurs</h4>
                <p>En publiant du contenu sur Tchadok, vous :</p>
                <ul>
                    <li>Conservez vos droits de propriété intellectuelle</li>
                    <li>Accordez à Tchadok une licence non-exclusive pour diffuser votre contenu</li>
                    <li>Garantissez posséder tous les droits nécessaires</li>
                    <li>Acceptez que votre contenu soit soumis à modération</li>
                </ul>
                
                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i> Tchadok respecte les droits d'auteur et dispose d'une politique de signalement pour les violations. Tout contenu contrefaisant sera retiré immédiatement.
                </div>
            </article>

            <!-- Article 6 -->
            <article class="mb-5" id="article6">
                <h2 class="h3 mb-3">Article 6 : Obligations des Utilisateurs</h2>
                <p>En utilisant Tchadok, vous vous engagez à :</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h4 class="card-title text-success"><i class="fas fa-check-circle"></i> Autorisé</h4>
                                <ul>
                                    <li>Utiliser les services dans le respect des lois</li>
                                    <li>Respecter les droits des autres utilisateurs</li>
                                    <li>Publier du contenu original ou licencié</li>
                                    <li>Signaler les contenus inappropriés</li>
                                    <li>Maintenir un comportement respectueux</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h4 class="card-title text-danger"><i class="fas fa-times-circle"></i> Interdit</h4>
                                <ul>
                                    <li>Violer les droits de propriété intellectuelle</li>
                                    <li>Publier du contenu illégal ou offensant</li>
                                    <li>Harceler ou menacer d'autres utilisateurs</li>
                                    <li>Utiliser des moyens automatisés d'accès</li>
                                    <li>Contourner les mesures de sécurité</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Article 7 -->
            <article class="mb-5" id="article7">
                <h2 class="h3 mb-3">Article 7 : Paiements et Abonnements</h2>
                
                <h4>Tarification</h4>
                <p>Les prix affichés sur Tchadok sont indiqués en Franc CFA (XAF) et incluent toutes les taxes applicables. Tchadok se réserve le droit de modifier ses tarifs à tout moment.</p>
                
                <h4>Moyens de Paiement</h4>
                <ul>
                    <li>Cartes bancaires (Visa, Mastercard)</li>
                    <li>Mobile Money (Airtel Money, Moov Money)</li>
                    <li>Virements bancaires</li>
                    <li>Solutions de paiement partenaires</li>
                </ul>
                
                <h4>Abonnements Premium</h4>
                <p>Les abonnements sont renouvelés automatiquement à la fin de chaque période. Vous pouvez annuler votre abonnement à tout moment depuis votre compte. L'annulation prend effet à la fin de la période d'abonnement en cours.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Une période d'essai gratuite peut être proposée aux nouveaux utilisateurs. Les conditions spécifiques seront précisées lors de l'inscription.
                </div>
            </article>

            <!-- Article 8 -->
            <article class="mb-5" id="article8">
                <h2 class="h3 mb-3">Article 8 : Responsabilité</h2>
                
                <h4>Limitation de Responsabilité</h4>
                <p>Tchadok s'efforce de fournir un service de qualité mais ne peut garantir :</p>
                <ul>
                    <li>Un fonctionnement ininterrompu ou sans erreur</li>
                    <li>L'exactitude ou la fiabilité de tout contenu</li>
                    <li>La disponibilité permanente du service</li>
                    <li>L'absence de virus ou d'éléments nuisibles</li>
                </ul>
                
                <h4>Indemnisation</h4>
                <p>Vous acceptez d'indemniser et de dégager Tchadok de toute responsabilité en cas de réclamation résultant de votre utilisation du service ou de votre violation des présentes conditions.</p>
            </article>

            <!-- Article 9 -->
            <article class="mb-5" id="article9">
                <h2 class="h3 mb-3">Article 9 : Résiliation</h2>
                
                <h4>Résiliation par l'Utilisateur</h4>
                <p>Vous pouvez fermer votre compte à tout moment en nous contactant ou via les paramètres de votre compte. La résiliation est effective immédiatement.</p>
                
                <h4>Résiliation par Tchadok</h4>
                <p>Tchadok peut suspendre ou résilier votre compte en cas de :</p>
                <ul>
                    <li>Violation des présentes conditions</li>
                    <li>Comportement frauduleux ou illégal</li>
                    <li>Non-paiement des services</li>
                    <li>Inactivité prolongée</li>
                </ul>
                
                <h4>Conséquences de la Résiliation</h4>
                <p>En cas de résiliation :</p>
                <ul>
                    <li>L'accès à votre compte sera désactivé</li>
                    <li>Vos playlists et préférences seront supprimées</li>
                    <li>Aucun remboursement ne sera effectué pour la période non utilisée</li>
                    <li>Certaines dispositions de ces conditions resteront applicables</li>
                </ul>
            </article>

            <!-- Article 10 -->
            <article class="mb-5" id="article10">
                <h2 class="h3 mb-3">Article 10 : Dispositions Générales</h2>
                
                <h4>Droit Applicable</h4>
                <p>Les présentes conditions sont régies par le droit de la République du Tchad. Tout litige sera soumis à la compétence exclusive des tribunaux tchadiens.</p>
                
                <h4>Intégralité de l'Accord</h4>
                <p>Ces conditions constituent l'intégralité de l'accord entre vous et Tchadok concernant l'utilisation de nos services.</p>
                
                <h4>Divisibilité</h4>
                <p>Si une disposition de ces conditions est jugée invalide ou inapplicable, les autres dispositions resteront en vigueur.</p>
                
                <h4>Contact</h4>
                <p>Pour toute question concernant ces conditions d'utilisation :</p>
                <ul>
                    <li>Email : legal@tchadok.td</li>
                    <li>Téléphone : +235 66 12 34 56</li>
                    <li>Adresse : Avenue Charles de Gaulle, N'Djamena, Tchad</li>
                </ul>
            </article>

            <!-- Support Section -->
            <div class="card mb-5 border-primary">
                <div class="card-body text-center">
                    <h3 class="card-title">Des Questions sur nos Conditions ?</h3>
                    <p class="card-text">Notre équipe juridique est à votre disposition pour clarifier tout point de ces conditions d'utilisation.</p>
                    <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Nous Contacter
                    </a>
                    <a href="<?php echo SITE_URL; ?>/aide.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-question-circle me-2"></i>Centre d'Aide
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>