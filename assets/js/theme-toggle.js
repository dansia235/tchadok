(function () {
    'use strict';

    var storageKey = 'tchadok-theme';
    var root = document.documentElement;

    function getStoredTheme() {
        try {
            var value = localStorage.getItem(storageKey);
            if (value === 'light' || value === 'dark') {
                return value;
            }
        } catch (e) {}
        return null;
    }

    function applyTheme(theme) {
        root.classList.remove('theme-light', 'dark');
        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.add('theme-light');
        }

        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', theme === 'dark');
        });
    }

    function toggleTheme() {
        var current = root.classList.contains('dark') ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(storageKey, next);
        } catch (e) {}
        applyTheme(next);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-theme-toggle]');
        if (!button) return;
        event.preventDefault();
        toggleTheme();
    });

    document.addEventListener('DOMContentLoaded', function () {
        var stored = getStoredTheme();
        if (stored) {
            applyTheme(stored);
            return;
        }
        applyTheme(root.classList.contains('dark') ? 'dark' : 'light');
    });
})();
