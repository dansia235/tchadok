(function () {
    'use strict';

    var headers = document.querySelectorAll('[data-dashboard-header]');

    if (!headers.length) {
        return;
    }

    headers.forEach(function (header) {
        var shell = header.querySelector('[data-dashboard-header-shell]');
        var links = header.querySelector('[data-dashboard-header-links]');
        var title = header.querySelector('[data-dashboard-header-title]');
        var meta = header.querySelector('[data-dashboard-header-meta]');
        var compactThreshold = 96;
        var expandThreshold = 18;
        var ticking = false;

        if (!shell) {
            return;
        }

        header.style.overflowAnchor = 'none';

        var expandedPadding = '';
        var expandedBackground = '';
        var expandedBorder = '';
        var expandedShadow = '';
        var expandedBackdrop = '';
        var expandedMargin = links ? window.getComputedStyle(links).marginTop : '0px';
        var compactPadding = shell.getAttribute('data-compact-padding') || '0.85rem 1rem';
        var compactTitleSpacing = shell.getAttribute('data-compact-title-spacing') || '0.18em';
        var compactMetaGap = shell.getAttribute('data-compact-meta-gap') || '0.35rem';

        function resolveCompactValue(attributeName, fallback) {
            var darkAttributeName = attributeName + '-dark';
            var darkValue = shell.getAttribute(darkAttributeName);
            var baseValue = shell.getAttribute(attributeName);

            if (document.documentElement.classList.contains('dark') && darkValue) {
                return darkValue;
            }

            return baseValue || fallback;
        }

        if (links) {
            links.style.maxHeight = links.scrollHeight + 'px';
            links.style.opacity = '1';
            links.style.transform = 'translateY(0)';
            links.style.pointerEvents = 'auto';
        }

        function setCompactState(isCompact) {
            if (header.dataset.compact === String(isCompact)) {
                return;
            }

            header.dataset.compact = String(isCompact);

            if (isCompact) {
                var compactBackground = resolveCompactValue('data-compact-background', 'rgba(11, 15, 23, 0.92)');
                var compactBorder = resolveCompactValue('data-compact-border', 'rgba(255, 255, 255, 0.14)');
                var compactShadow = resolveCompactValue('data-compact-shadow', '0 18px 40px rgba(0, 0, 0, 0.28)');
                var compactBackdrop = resolveCompactValue('data-compact-backdrop', 'blur(18px)');

                shell.style.padding = compactPadding;
                shell.style.backgroundColor = compactBackground;
                shell.style.borderColor = compactBorder;
                shell.style.boxShadow = compactShadow;
                shell.style.backdropFilter = compactBackdrop;

                if (title) {
                    title.style.letterSpacing = compactTitleSpacing;
                }

                if (meta) {
                    meta.style.gap = compactMetaGap;
                }

                if (links) {
                    links.style.maxHeight = '0px';
                    links.style.opacity = '0';
                    links.style.marginTop = '0px';
                    links.style.transform = 'translateY(-8px)';
                    links.style.pointerEvents = 'none';
                }
            } else {
                shell.style.padding = expandedPadding;
                shell.style.backgroundColor = expandedBackground;
                shell.style.borderColor = expandedBorder;
                shell.style.boxShadow = expandedShadow;
                shell.style.backdropFilter = expandedBackdrop;

                if (title) {
                    title.style.letterSpacing = '';
                }

                if (meta) {
                    meta.style.gap = '';
                }

                if (links) {
                    links.style.maxHeight = links.scrollHeight + 'px';
                    links.style.opacity = '1';
                    links.style.marginTop = expandedMargin;
                    links.style.transform = 'translateY(0)';
                    links.style.pointerEvents = 'auto';
                }
            }
        }

        function syncState() {
            var shouldCompact = header.dataset.compact === 'true'
                ? window.scrollY > expandThreshold
                : window.scrollY >= compactThreshold;

            setCompactState(shouldCompact);
        }

        syncState();

        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(function () {
                syncState();
                ticking = false;
            });
        }, { passive: true });
        window.addEventListener('resize', function () {
            if (!links || header.dataset.compact === 'true') {
                return;
            }
            links.style.maxHeight = links.scrollHeight + 'px';
        });
    });
})();
