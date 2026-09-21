<?php
/**
 * Shared admin shell header
 */

if (empty($user) || !is_array($user)) {
    return;
}

$adminShellDisplayName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($adminShellDisplayName === '') {
    $adminShellDisplayName = $user['username'] ?? 'Administrateur';
}

$adminShellCurrentScript = strtolower((string) basename($_SERVER['SCRIPT_NAME'] ?? ''));
$adminShellMetrics = isset($adminShellMetrics) && is_array($adminShellMetrics) ? $adminShellMetrics : [];
$dashboardSecondaryNavLabel = $dashboardSecondaryNavLabel ?? 'Navigation admin';
$dashboardSecondaryNavItems = isset($dashboardSecondaryNavItems) && is_array($dashboardSecondaryNavItems) ? $dashboardSecondaryNavItems : [];
$adminShellQuickLinks = [
    [
        'href' => SITE_URL . '/admin-dashboard.php',
        'label' => 'Gouvernance utilisateurs',
        'icon' => 'users-cog',
        'icon_tone' => 'bg-blue-50 text-[#005BB5] dark:bg-blue-500/10 dark:text-blue-300',
        'matches' => ['admin-dashboard.php']
    ],
    [
        'href' => SITE_URL . '/admin-add-song.php',
        'label' => 'Catalogue titres',
        'icon' => 'music',
        'icon_tone' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        'matches' => ['admin-add-song.php']
    ],
    [
        'href' => SITE_URL . '/admin-manage-radio.php',
        'label' => 'Regie radio',
        'icon' => 'broadcast-tower',
        'icon_tone' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        'matches' => ['admin-manage-radio.php']
    ],
    [
        'href' => SITE_URL . '/admin-blog.php',
        'label' => 'Studio editorial',
        'icon' => 'newspaper',
        'icon_tone' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
        'matches' => ['admin-blog.php']
    ],
    [
        'href' => SITE_URL . '/admin-playlists.php',
        'label' => 'Curation playlists',
        'icon' => 'list',
        'icon_tone' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
        'matches' => ['admin-playlists.php']
    ],
    [
        'href' => SITE_URL . '/admin-podcasts.php',
        'label' => 'Studio podcasts',
        'icon' => 'podcast',
        'icon_tone' => 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-500/10 dark:text-fuchsia-300',
        'matches' => ['admin-podcasts.php']
    ],
    [
        'href' => SITE_URL . '/admin-add-album.php',
        'label' => 'Catalogue albums',
        'icon' => 'compact-disc',
        'icon_tone' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300',
        'matches' => ['admin-add-album.php']
    ],
    [
        'href' => SITE_URL . '/security-settings.php',
        'label' => 'Securite',
        'icon' => 'user-shield',
        'icon_tone' => 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200',
        'matches' => ['security-settings.php', 'execute-sql.php']
    ]
];

$adminShellMetricClasses = function (array $metric) {
    $signal = strtolower(trim((string) (($metric['tone'] ?? '') . ' ' . ($metric['label'] ?? '') . ' ' . ($metric['value'] ?? ''))));

    if (strpos($signal, 'rose') !== false || strpos($signal, 'alerte') !== false) {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300';
    }

    if (
        strpos($signal, 'amber') !== false ||
        strpos($signal, 'xaf') !== false ||
        strpos($signal, 'revenu') !== false ||
        strpos($signal, 'premium') !== false
    ) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (
        strpos($signal, 'emerald') !== false ||
        strpos($signal, 'active') !== false ||
        strpos($signal, 'actif') !== false
    ) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (strpos($signal, 'cyan') !== false || strpos($signal, 'album') !== false) {
        return 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-cyan-300';
    }

    if (strpos($signal, 'fuchsia') !== false || strpos($signal, 'podcast') !== false) {
        return 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700 dark:border-fuchsia-500/20 dark:bg-fuchsia-500/10 dark:text-fuchsia-300';
    }

    if (
        strpos($signal, 'stream') !== false ||
        strpos($signal, 'auditeur') !== false ||
        strpos($signal, 'radio') !== false
    ) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (
        strpos($signal, 'utilisateur') !== false ||
        strpos($signal, 'profil') !== false ||
        strpos($signal, 'compte') !== false
    ) {
        return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300';
};

$adminShellMetricIcon = function (array $metric) {
    $signal = strtolower(trim((string) (($metric['tone'] ?? '') . ' ' . ($metric['label'] ?? '') . ' ' . ($metric['value'] ?? ''))));

    if (
        strpos($signal, 'utilisateur') !== false ||
        strpos($signal, 'profil') !== false ||
        strpos($signal, 'compte') !== false
    ) {
        return 'users';
    }

    if (
        strpos($signal, 'stream') !== false ||
        strpos($signal, 'auditeur') !== false
    ) {
        return 'play-circle';
    }

    if (
        strpos($signal, 'xaf') !== false ||
        strpos($signal, 'revenu') !== false ||
        strpos($signal, 'premium') !== false
    ) {
        return 'wallet';
    }

    if (strpos($signal, 'radio') !== false) {
        return 'broadcast-tower';
    }

    if (strpos($signal, 'podcast') !== false) {
        return 'podcast';
    }

    if (strpos($signal, 'securit') !== false || strpos($signal, '2fa') !== false) {
        return 'shield-alt';
    }

    if (strpos($signal, 'album') !== false) {
        return 'compact-disc';
    }

    if (strpos($signal, 'maintenance') !== false || strpos($signal, 'console') !== false) {
        return 'terminal';
    }

    if (strpos($signal, 'alerte') !== false) {
        return 'triangle-exclamation';
    }

    return 'chart-line';
};
?>
<header class="-mt-8 sticky top-0 z-40 block w-full" data-dashboard-header>
    <div
        class="w-full overflow-hidden border-b border-slate-200/80 bg-white/95 shadow-[0_10px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl transition-all duration-300 dark:border-slate-700/80 dark:bg-slate-800/95 dark:shadow-[0_14px_34px_rgba(0,0,0,0.32)]"
        data-dashboard-header-shell
        data-compact-padding="0.95rem 1rem"
        data-compact-background="rgba(255, 255, 255, 0.97)"
        data-compact-background-dark="rgba(30, 41, 59, 0.96)"
        data-compact-border="rgba(148, 163, 184, 0.24)"
        data-compact-border-dark="rgba(148, 163, 184, 0.16)"
        data-compact-shadow="0 14px 34px rgba(15, 23, 42, 0.12)"
        data-compact-shadow-dark="0 18px 40px rgba(0, 0, 0, 0.35)"
        data-compact-backdrop="blur(18px)"
        data-compact-title-spacing="0.2em"
        data-compact-meta-gap="0.45rem"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="py-5 sm:py-6">
                <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-5 lg:flex-row lg:items-center lg:justify-between dark:border-slate-700/80">
                    <div class="flex items-center gap-4">
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-[#005BB5] text-white shadow-sm dark:bg-accent">
                            <i class="fas fa-shield-alt text-lg"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-500 transition-all duration-300 dark:text-slate-400" data-dashboard-header-title>Tchadok Control Center</p>
                            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($adminShellDisplayName); ?></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                        <?php if (!empty($adminShellMetrics)): ?>
                            <div class="flex flex-wrap items-center gap-2 transition-all duration-300" data-dashboard-header-meta>
                                <?php foreach ($adminShellMetrics as $metric): ?>
                                    <?php
                                        $metricClasses = $adminShellMetricClasses($metric);
                                        $metricIcon = $adminShellMetricIcon($metric);
                                        $label = (string) ($metric['label'] ?? '');
                                        $value = (string) ($metric['value'] ?? '');
                                    ?>
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-[12px] font-semibold <?php echo $metricClasses; ?>">
                                        <i class="fas fa-<?php echo htmlspecialchars($metricIcon); ?> text-[11px]"></i>
                                        <?php echo htmlspecialchars(trim($value . ($label !== '' ? ' ' . $label : ''))); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a
                            href="<?php echo SITE_URL; ?>"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#005BB5]/30 hover:text-[#005BB5] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-blue-400/30 dark:hover:text-blue-300"
                        >
                            <i class="fas fa-globe text-xs"></i>
                            Voir Tchadok
                        </a>

                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-600 transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            data-theme-toggle
                            aria-label="Basculer le theme"
                            aria-pressed="false"
                        >
                            <i class="fas fa-sun theme-toggle-icon--sun"></i>
                            <i class="fas fa-moon theme-toggle-icon--moon"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden transition-all duration-300" data-dashboard-header-links>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <?php foreach ($adminShellQuickLinks as $link): ?>
                            <?php
                                $isActive = in_array($adminShellCurrentScript, $link['matches'], true);
                                $linkClasses = $isActive
                                    ? 'border-[#005BB5]/20 bg-blue-50 shadow-sm text-slate-900 dark:border-blue-400/20 dark:bg-slate-900 dark:text-slate-100'
                                    : 'border-slate-200/80 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-[#005BB5]/25 hover:text-[#005BB5] dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-blue-400/30 dark:hover:text-blue-300';
                            ?>
                            <a
                                href="<?php echo htmlspecialchars($link['href']); ?>"
                                class="flex items-center gap-3 rounded-2xl border px-4 py-4 text-sm font-semibold transition-all duration-200 <?php echo $linkClasses; ?>"
                            >
                                <span class="grid h-10 w-10 place-items-center rounded-xl <?php echo htmlspecialchars($link['icon_tone']); ?>">
                                    <i class="fas fa-<?php echo htmlspecialchars($link['icon']); ?>"></i>
                                </span>
                                <span class="leading-tight"><?php echo htmlspecialchars($link['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php $dashboardSecondaryNavVariant = 'admin-shell'; ?>
                <?php include __DIR__ . '/dashboard-secondary-nav.php'; ?>
                <?php unset($dashboardSecondaryNavVariant); ?>
            </div>
        </div>
    </div>
</header>
