<?php
/**
 * Page Politique de Confidentialité - Tchadok Platform
 * Politique de protection des données personnelles
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Politique de Confidentialité';
$pageDescription = 'Découvrez comment Tchadok protège vos données personnelles. Politique transparente de collecte, utilisation et protection de vos informations.';

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container" style="padding-top: 100px;">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="mb-4">Politique de Confidentialité</h1>
            <p class="lead mb-5">Chez Tchadok, la protection de vos données personnelles est notre priorité. Découvrez comment nous collectons, utilisons et protégeons vos informations dans le respect de votre vie privée.</p>
            
            <div class="text-muted mb-5">
                <p><i class="fas fa-calendar"></i> Dernière mise à jour : 15 Décembre 2024</p>
                <p><i class="fas fa-shield-alt"></i> Protection renforcée des données</p>
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
                                <li class="mb-2"><a href="#section1">1. Introduction</a></li>
                                <li class="mb-2"><a href="#section2">2. Données Collectées</a></li>
                                <li class="mb-2"><a href="#section3">3. Utilisation des Données</a></li>
                                <li class="mb-2"><a href="#section4">4. Partage des Données</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2"><a href="#section5">5. Conservation des Données</a></li>
                                <li class="mb-2"><a href="#section6">6. Mesures de Sécurité</a></li>
                                <li class="mb-2"><a href="#section7">7. Vos Droits</a></li>
                                <li class="mb-2"><a href="#section8">8. Contact</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Content -->
    <div class="row">
        <div class="col-lg-12">
            
            <!-- Section 1 -->
            <section class="mb-5" id="section1">
                <h2 class="h3 mb-3">1. Introduction</h2>
                <p>Tchadok s'engage à protéger la vie privée de ses utilisateurs. Cette politique de confidentialité explique comment nous collectons, utilisons, stockons et protégeons vos données personnelles lorsque vous utilisez notre plateforme de streaming musical.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> En utilisant Tchadok, vous acceptez les pratiques décrites dans cette politique de confidentialité.
                </div>
                
                <h4>Nos Engagements</h4>
                <ul>
                    <li>Transparence totale sur l'utilisation de vos données</li>
                    <li>Collecte limitée au strict nécessaire</li>
                    <li>Protection maximale de vos informations</li>
                    <li>Respect de vos droits et préférences</li>
                </ul>
            </section>

            <!-- Section 2 -->
            <section class="mb-5" id="section2">
                <h2 class="h3 mb-3">2. Données Collectées</h2>
                <p>Nous collectons différents types d'informations pour vous offrir une expérience personnalisée et sécurisée.</p>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-user"></i> Données d'Identification</h4>
                                <ul>
                                    <li>Nom et prénom</li>
                                    <li>Adresse email</li>
                                    <li>Numéro de téléphone</li>
                                    <li>Date de naissance</li>
                                    <li>Photo de profil (optionnelle)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-music"></i> Données d'Usage</h4>
                                <ul>
                                    <li>Historique d'écoute</li>
                                    <li>Playlists créées</li>
                                    <li>Artistes suivis</li>
                                    <li>Préférences musicales</li>
                                    <li>Interactions avec le contenu</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-desktop"></i> Données Techniques</h4>
                                <ul>
                                    <li>Adresse IP</li>
                                    <li>Type d'appareil</li>
                                    <li>Système d'exploitation</li>
                                    <li>Navigateur web</li>
                                    <li>Données de localisation (avec permission)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-credit-card"></i> Données de Paiement</h4>
                                <ul>
                                    <li>Méthode de paiement</li>
                                    <li>Historique des transactions</li>
                                    <li>Informations de facturation</li>
                                    <li>Statut d'abonnement</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 3 -->
            <section class="mb-5" id="section3">
                <h2 class="h3 mb-3">3. Utilisation des Données</h2>
                <p>Vos données nous permettent de vous offrir une expérience musicale personnalisée et de qualité.</p>
                
                <h4>Finalités Principales</h4>
                <ul>
                    <li><strong>Fourniture du Service :</strong> Permettre l'accès à notre catalogue musical et aux fonctionnalités de la plateforme</li>
                    <li><strong>Personnalisation :</strong> Recommander de la musique selon vos goûts et habitudes d'écoute</li>
                    <li><strong>Communication :</strong> Vous informer des nouveautés, offres et mises à jour importantes</li>
                    <li><strong>Support Client :</strong> Répondre à vos questions et résoudre les problèmes techniques</li>
                    <li><strong>Sécurité :</strong> Protéger votre compte contre les accès non autorisés et les fraudes</li>
                    <li><strong>Amélioration :</strong> Analyser l'utilisation pour améliorer nos services et développer de nouvelles fonctionnalités</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Remarque :</strong> Nous ne vendons jamais vos données personnelles à des tiers. Vos informations sont utilisées uniquement pour améliorer votre expérience sur Tchadok.
                </div>
            </section>

            <!-- Section 4 -->
            <section class="mb-5" id="section4">
                <h2 class="h3 mb-3">4. Partage des Données</h2>
                <p>Nous partageons vos données uniquement dans des cas spécifiques et avec des garanties appropriées.</p>
                
                <h4>Avec qui partageons-nous vos données ?</h4>
                
                <div class="mb-3">
                    <h5><i class="fas fa-handshake"></i> Partenaires de Service</h5>
                    <ul>
                        <li>Prestataires de paiement (pour traiter vos transactions)</li>
                        <li>Services d'hébergement et d'infrastructure</li>
                        <li>Outils d'analyse et de statistiques</li>
                        <li>Services de support client</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h5><i class="fas fa-microphone"></i> Artistes et Labels</h5>
                    <ul>
                        <li>Statistiques d'écoute anonymisées</li>
                        <li>Données démographiques agrégées</li>
                        <li>Informations de performance de leurs contenus</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h5><i class="fas fa-gavel"></i> Obligations Légales</h5>
                    <ul>
                        <li>Autorités judiciaires (sur demande légale valide)</li>
                        <li>Protection contre la fraude et les abus</li>
                        <li>Respect des obligations légales et réglementaires</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i> Tous nos partenaires sont contractuellement tenus de protéger vos données et de les utiliser uniquement aux fins spécifiées.
                </div>
            </section>

            <!-- Section 5 -->
            <section class="mb-5" id="section5">
                <h2 class="h3 mb-3">5. Conservation des Données</h2>
                <p>Nous conservons vos données uniquement pendant la durée nécessaire aux finalités pour lesquelles elles ont été collectées.</p>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h4 class="card-title">Durées de Conservation</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type de Données</th>
                                    <th>Durée de Conservation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Données de compte actif</td>
                                    <td>Pendant toute la durée d'utilisation + 3 ans</td>
                                </tr>
                                <tr>
                                    <td>Historique d'écoute</td>
                                    <td>2 ans à compter de l'écoute</td>
                                </tr>
                                <tr>
                                    <td>Données de paiement</td>
                                    <td>Durée légale de conservation comptable (10 ans)</td>
                                </tr>
                                <tr>
                                    <td>Logs techniques</td>
                                    <td>6 mois</td>
                                </tr>
                                <tr>
                                    <td>Cookies</td>
                                    <td>13 mois maximum</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <p>À l'expiration de ces délais, vos données sont soit supprimées, soit anonymisées de manière irréversible.</p>
            </section>

            <!-- Section 6 -->
            <section class="mb-5" id="section6">
                <h2 class="h3 mb-3">6. Mesures de Sécurité</h2>
                <p>Tchadok met en œuvre des mesures de sécurité techniques et organisationnelles appropriées pour protéger vos données.</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-lock"></i> Sécurité Technique</h4>
                                <ul>
                                    <li>Chiffrement des données en transit et au repos</li>
                                    <li>Authentification à deux facteurs disponible</li>
                                    <li>Pare-feu et systèmes de détection d'intrusion</li>
                                    <li>Sauvegardes sécurisées et redondantes</li>
                                    <li>Mise à jour régulière des systèmes de sécurité</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-users"></i> Sécurité Organisationnelle</h4>
                                <ul>
                                    <li>Accès limité aux données selon le principe du besoin d'en connaître</li>
                                    <li>Formation régulière du personnel à la sécurité</li>
                                    <li>Clauses de confidentialité dans tous les contrats</li>
                                    <li>Audit et surveillance continue des accès</li>
                                    <li>Plan de réponse aux incidents de sécurité</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-circle"></i> En Cas d'Incident</h5>
                    <p>Si une violation de données se produit, nous nous engageons à :</p>
                    <ul class="mb-0">
                        <li>Contenir l'incident dans les plus brefs délais</li>
                        <li>Évaluer les risques pour vos données</li>
                        <li>Notifier les autorités compétentes si nécessaire</li>
                        <li>Vous informer dans les meilleurs délais si vos données sont affectées</li>
                        <li>Prendre toutes les mesures correctives appropriées</li>
                    </ul>
                </div>
            </section>

            <!-- Section 7 -->
            <section class="mb-5" id="section7">
                <h2 class="h3 mb-3">7. Vos Droits</h2>
                <p>Vous disposez de droits importants concernant vos données personnelles.</p>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-eye"></i> Droit d'Accès</h4>
                                <p>Consulter les données que nous détenons sur vous et obtenir une copie de vos informations.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-edit"></i> Droit de Rectification</h4>
                                <p>Corriger ou mettre à jour vos données personnelles si elles sont inexactes ou incomplètes.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-trash"></i> Droit à l'Effacement</h4>
                                <p>Demander la suppression de vos données dans certaines circonstances légales.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-pause"></i> Droit à la Limitation</h4>
                                <p>Limiter le traitement de vos données dans certaines situations spécifiques.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-download"></i> Droit à la Portabilité</h4>
                                <p>Recevoir vos données dans un format structuré et les transférer à un autre service.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-hand-paper"></i> Droit d'Opposition</h4>
                                <p>Vous opposer au traitement de vos données pour des raisons légitimes.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Pour exercer vos droits, contactez notre Délégué à la Protection des Données à l'adresse : privacy@tchadok.td
                </div>
            </section>

            <!-- Section 8 -->
            <section class="mb-5" id="section8">
                <h2 class="h3 mb-3">8. Contact</h2>
                <p>Pour toute question concernant cette politique de confidentialité ou vos données personnelles :</p>
                
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Délégué à la Protection des Données</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-envelope"></i> Email : privacy@tchadok.td</li>
                            <li><i class="fas fa-phone"></i> Téléphone : +235 66 12 34 56</li>
                            <li><i class="fas fa-map-marker-alt"></i> Adresse : Avenue Charles de Gaulle, N'Djamena, Tchad</li>
                        </ul>
                        
                        <h5 class="mt-4">Autorité de Contrôle</h5>
                        <p>Vous avez également le droit de déposer une plainte auprès de l'autorité de protection des données compétente si vous estimez que le traitement de vos données personnelles viole la réglementation applicable.</p>
                    </div>
                </div>
            </section>

            <!-- Cookies Section -->
            <section class="mb-5">
                <h2 class="h3 mb-3">Gestion des Cookies</h2>
                <p>Tchadok utilise des cookies pour améliorer votre expérience. Vous pouvez gérer vos préférences de cookies à tout moment.</p>
                
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Types de Cookies Utilisés</h4>
                        <ul>
                            <li><strong>Cookies Essentiels :</strong> Nécessaires au fonctionnement du site</li>
                            <li><strong>Cookies de Performance :</strong> Nous aident à comprendre comment vous utilisez notre service</li>
                            <li><strong>Cookies de Fonctionnalité :</strong> Mémorisent vos préférences</li>
                            <li><strong>Cookies de Marketing :</strong> Utilisés pour vous proposer des contenus pertinents</li>
                        </ul>
                        
                        <button class="btn btn-primary mt-3">
                            <i class="fas fa-cookie me-2"></i>Gérer mes Préférences de Cookies
                        </button>
                    </div>
                </div>
            </section>

            <!-- Updates Section -->
            <div class="card mb-5 border-primary">
                <div class="card-body text-center">
                    <h3 class="card-title">Modifications de cette Politique</h3>
                    <p class="card-text">Cette politique de confidentialité peut être mise à jour périodiquement. Nous vous informerons de tout changement important par email ou via une notification sur la plateforme.</p>
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