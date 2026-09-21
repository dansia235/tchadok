<?php
/**
 * Dashboard secondary navigation
 */

if (empty($dashboardSecondaryNavItems) || !is_array($dashboardSecondaryNavItems)) {
    return;
}

$dashboardSecondaryNavVariant = $dashboardSecondaryNavVariant ?? 'default';
$navContainerClasses = 'mt-5 rounded-2xl border border-white/10 bg-black/10 p-2';
$navInnerClasses = 'flex flex-wrap items-center gap-2';
$navLabelClasses = 'px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-muted';
$navPillClasses = '';
$activeLinkClasses = 'border-accent/35 bg-accent/10 text-text';
$inactiveLinkClasses = 'border-white/10 bg-white/5 text-muted hover:text-text';
$linkBaseClasses = 'inline-flex items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-200';

if ($dashboardSecondaryNavVariant === 'admin-shell') {
    $navContainerClasses = 'mt-5 border-t border-slate-200/80 pt-3 dark:border-slate-700/80';
    $navInnerClasses = 'flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between';
    $navLabelClasses = 'px-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400';
    $navPillClasses = 'flex flex-wrap items-center gap-2';
    $activeLinkClasses = 'border-slate-200 bg-white text-blue-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-blue-300';
    $inactiveLinkClasses = 'border-transparent bg-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100';
    $linkBaseClasses = 'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold transition-all duration-200';
}
?>
<div
    class="<?php echo $navContainerClasses; ?>"
    data-dashboard-secondary-nav
    data-secondary-nav-active="<?php echo htmlspecialchars($activeLinkClasses, ENT_QUOTES, 'UTF-8'); ?>"
    data-secondary-nav-inactive="<?php echo htmlspecialchars($inactiveLinkClasses, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="<?php echo $navInnerClasses; ?>">
        <span class="<?php echo $navLabelClasses; ?>">
            <?php echo htmlspecialchars($dashboardSecondaryNavLabel ?? 'Navigation'); ?>
        </span>
        <div class="<?php echo $navPillClasses; ?>">
            <?php foreach ($dashboardSecondaryNavItems as $index => $item): ?>
                <?php
                    $target = ltrim((string) ($item['target'] ?? ''), '#');
                    if ($target === '') {
                        continue;
                    }
                    $isActive = $index === 0;
                ?>
                <a
                    href="#<?php echo htmlspecialchars($target); ?>"
                    class="<?php echo $linkBaseClasses; ?> <?php echo $isActive ? $activeLinkClasses : $inactiveLinkClasses; ?>"
                    data-secondary-nav-link
                    data-secondary-nav-target="<?php echo htmlspecialchars($target); ?>"
                    aria-current="<?php echo $isActive ? 'true' : 'false'; ?>"
                >
                    <?php if (!empty($item['icon'])): ?>
                        <i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars((string) ($item['label'] ?? 'Section')); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
