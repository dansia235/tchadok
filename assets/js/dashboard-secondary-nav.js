(function () {
    'use strict';

    var navs = document.querySelectorAll('[data-dashboard-secondary-nav]');

    if (!navs.length) {
        return;
    }

    function getClassTokens(value, fallback) {
        if (!value) {
            return fallback.slice();
        }

        return value
            .split(/\s+/)
            .map(function (token) {
                return token.trim();
            })
            .filter(Boolean);
    }

    function setActiveLink(nav, activeLink) {
        var links = nav.querySelectorAll('[data-secondary-nav-link]');
        var activeClasses = getClassTokens(
            nav.getAttribute('data-secondary-nav-active'),
            ['border-accent/35', 'bg-accent/10', 'text-text']
        );
        var inactiveClasses = getClassTokens(
            nav.getAttribute('data-secondary-nav-inactive'),
            ['border-white/10', 'bg-white/5', 'text-muted']
        );

        links.forEach(function (link) {
            var isActive = link === activeLink;

            activeClasses.forEach(function (className) {
                link.classList.toggle(className, isActive);
            });

            inactiveClasses.forEach(function (className) {
                link.classList.toggle(className, !isActive);
            });

            link.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
    }

    function getScrollOffset() {
        var header = document.querySelector('[data-dashboard-header]');
        if (!header) {
            return 24;
        }
        return Math.ceil(header.getBoundingClientRect().height) + 20;
    }

    navs.forEach(function (nav) {
        var links = Array.prototype.slice.call(nav.querySelectorAll('[data-secondary-nav-link]'));
        if (!links.length) {
            return;
        }

        var sections = links
            .map(function (link) {
                var targetId = link.getAttribute('data-secondary-nav-target');
                var section = targetId ? document.getElementById(targetId) : null;
                if (!section) {
                    return null;
                }

                return {
                    link: link,
                    section: section
                };
            })
            .filter(Boolean);

        if (!sections.length) {
            return;
        }

        links.forEach(function (link) {
            link.addEventListener('click', function (event) {
                var targetId = link.getAttribute('data-secondary-nav-target');
                var target = targetId ? document.getElementById(targetId) : null;

                if (!target) {
                    return;
                }

                event.preventDefault();
                setActiveLink(nav, link);

                var top = target.getBoundingClientRect().top + window.scrollY - getScrollOffset();
                window.scrollTo({
                    top: Math.max(0, top),
                    behavior: 'smooth'
                });
            });
        });

        function syncActiveSection() {
            var offset = getScrollOffset();
            var active = sections[0];

            sections.forEach(function (entry) {
                var top = entry.section.getBoundingClientRect().top;
                if (top <= offset + 24) {
                    active = entry;
                }
            });

            if (active) {
                setActiveLink(nav, active.link);
            }
        }

        syncActiveSection();

        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(function () {
                syncActiveSection();
                ticking = false;
            });
        }, { passive: true });

        window.addEventListener('resize', syncActiveSection);
    });
})();
