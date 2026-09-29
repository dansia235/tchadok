<?php
/**
 * Methodologie du barometre (CHART-05) : publiee, datee, versionnee, liee
 * depuis chaque classement. Reprend docs/methodologie/ecoute-comptabilisee.md
 * (STAT-01) ; les seuils de certification viennent de la base.
 */

require_once 'includes/functions.php';
require_once 'includes/ecoutes.php';
require_once 'includes/barometre.php';
require_once 'includes/kit-presse.php';

$seuils = CertificationsTchadok::seuils();
$nombre = static fn ($v): string => number_format((float) $v, 0, ',', ' ');
$pageTitle = 'Methodologie du barometre';
$pageDescription = 'Comment le Barometre Tchadok compte les ecoutes : definition, anti-fraude, arrete, limites connues.';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <article class="mx-auto max-w-3xl space-y-6 px-4 text-sm leading-7 text-muted sm:px-6">
        <header>
            <p class="text-xs uppercase tracking-[0.28em]">Barometre Tchadok</p>
            <h1 class="mt-2 text-3xl font-display font-bold text-text">Methodologie</h1>
            <p class="mt-2" data-version-methodologie="<?php echo e(Barometre::VERSION_METHODOLOGIE); ?>">Version <?php echo e(Barometre::VERSION_METHODOLOGIE); ?> — en vigueur depuis le 28 septembre 2026. Toute evolution ouvre une nouvelle version, sans effet sur les classements deja arretes.</p>
        </header>

        <section>
            <h2 class="text-lg font-semibold text-text">1. Ce qui compte comme une ecoute</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Au moins <strong class="text-text"><?php echo Ecoutes::DUREE_MINIMALE; ?> secondes de lecture effective</strong> du titre complet, ou la lecture complete d'un titre plus court.</li>
                <li>Un extrait de 30 secondes n'est pas une ecoute.</li>
                <li>Une ecoute par auditeur et par titre <strong class="text-text">par heure</strong>.</li>
                <li>Un visiteur sans compte est compte, reconnu par une empreinte anonyme valable une journee.</li>
                <li>Les ecoutes d'un artiste sur ses propres titres sont exclues.</li>
                <li>Les ecoutes radio sont comptees a part.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-text">2. Comment elle est mesuree</h2>
            <p>Le serveur remet un jeton a usage unique a l'ouverture du titre ; le seuil de 30 secondes est mesure par le serveur, jamais par le navigateur. La duree d'un titre est lue dans son fichier audio.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-text">3. Filtrage anti-fraude</h2>
            <p>Chaque ecoute est observee au moins deux heures, puis <strong class="text-text">certifiee</strong>, <strong class="text-text">exclue</strong> ou mise en <strong class="text-text">quarantaine</strong> : rafales depuis une meme adresse, trop d'ecoutes par auditeur, concentration sur un meme reseau ou un meme navigateur, comptes crees en serie. Une quarantaine attend une decision humaine motivee et tracee. Seules les ecoutes certifiees comptent. Une fraude decouverte apres coup est revoquee ; elle ne compte plus dans les classements a venir.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-text">4. Arrete des classements</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Semaine du <strong class="text-text">lundi 00:00 au dimanche 23:59</strong>, heure de N'Djamena ; editions mensuelles et annuelles.</li>
                <li>Arrete <strong class="text-text"><?php echo Barometre::CONSOLIDATION; ?> heures</strong> apres la fin de la periode. Un classement arrete ne change plus.</li>
                <li>Une ecoute certifiee apres l'arrete (quarantaine levee plus tard) compte pour l'edition suivante.</li>
                <li>Ventes : achats payes dans la periode, non rembourses a l'arrete. Ventes et ecoutes forment des classements separes ; les sorties concourent par format.</li>
                <li>En cas d'egalite : le plus d'auditeurs uniques, puis l'anteriorite.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-text">5. Certifications Tchadok</h2>
            <p>Decernees automatiquement, definitives, sur le cumul des ecoutes certifiees ou des ventes nettes d'un titre :</p>
            <table class="mt-2 w-full text-left">
                <thead class="text-xs uppercase text-muted"><tr><th class="py-1">Palier</th><th class="py-1 text-right">Ecoutes certifiees</th><th class="py-1 text-right">Ventes</th></tr></thead>
                <tbody>
                    <?php foreach (CertificationsTchadok::NIVEAUX as $cle => $libelle): ?>
                        <tr class="border-t border-white/10"><td class="py-1 text-text"><?php echo e($libelle); ?></td><td class="py-1 text-right"><?php echo e($nombre($seuils['ecoutes'][$cle] ?? 0)); ?></td><td class="py-1 text-right"><?php echo e($nombre($seuils['ventes'][$cle] ?? 0)); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-text">6. Perimetre et limites connues</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Perimetre : les ecoutes et ventes realisees sur Tchadok uniquement.</li>
                <li>La localisation des ecoutes n'est pas encore mesuree : pas de classement par province ni de classement diaspora pour l'instant.</li>
                <li>Les seuils de certification et la presente definition sont soumis a validation de la direction et figureront au contrat de distribution.</li>
            </ul>
        </section>

        <p class="text-xs">Reproduction des classements libre, avec la mention « Barometre Tchadok » et la date d'arrete. <a class="underline" href="<?php echo SITE_URL; ?>/barometre.php">Voir le barometre</a></p>
    </article>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
