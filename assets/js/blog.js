(function () {
    'use strict';

    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('[data-nav-menu]');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const isOpen = !navMenu.classList.contains('hidden');
            navMenu.classList.toggle('hidden');
            navToggle.setAttribute('aria-expanded', String(!isOpen));
        });
    }

    function showToast(message) {
        document.querySelectorAll('.tchadok-toast').forEach((toast) => toast.remove());
        const toast = document.createElement('div');
        toast.className = 'tchadok-toast';
        toast.textContent = message;
        toast.setAttribute('role', 'status');
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 280);
        }, 1800);
    }

    function trackShare(postId, platform) {
        if (!postId) return;
        fetch((window.TCHADOK?.SITE_URL || '') + '/api/blog/share.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                post_id: postId,
                platform: platform || 'copy',
                csrf_token: window.TCHADOK?.CSRF_TOKEN || ''
            })
        }).catch(() => {});
    }

    function nativeShare(url, title) {
        if (navigator.share) {
            navigator.share({
                title: title || document.title,
                text: title || document.title,
                url
            }).catch(() => {});
            return Promise.resolve(true);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(url).then(() => {
                showToast('Lien copie dans le presse-papiers');
                return true;
            }).catch(() => false);
        }

        return Promise.resolve(false);
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-share-url]');
        if (!button) return;

        const shareUrl = button.getAttribute('data-share-url') || window.location.href;
        const shareTitle = button.getAttribute('data-share-title') || document.title;
        const sharePostId = parseInt(button.getAttribute('data-share-post') || '0', 10);
        const sharePlatform = button.getAttribute('data-share-platform') || 'copy';
        nativeShare(shareUrl, shareTitle).then((ok) => {
            if (!ok) {
                window.prompt('Copiez ce lien :', shareUrl);
            }
            trackShare(sharePostId, sharePlatform);
        });
    });

    document.querySelectorAll('[data-share-track][data-share-platform]').forEach((link) => {
        link.addEventListener('click', () => {
            const postId = parseInt(link.getAttribute('data-share-track') || '0', 10);
            const platform = link.getAttribute('data-share-platform') || 'copy';
            trackShare(postId, platform);
        });
    });
})();
