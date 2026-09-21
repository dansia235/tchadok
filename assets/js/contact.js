/* Contact page interactions - Tailwind migration */
(function() {
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

    const form = document.querySelector('[data-contact-form]');
    const fields = Array.from(document.querySelectorAll('[data-field]'));
    const messageInput = document.querySelector('[data-message]');
    const charCount = document.querySelector('[data-char-count]');

    function isEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function setFieldState(field, isValid) {
        field.classList.toggle('is-invalid', !isValid);
        field.classList.toggle('is-valid', isValid);
    }

    function validateField(field) {
        const value = field.value.trim();
        let valid = true;

        if (field.hasAttribute('required') && value === '') {
            valid = false;
        }
        if (valid && field.type === 'email' && value && !isEmail(value)) {
            valid = false;
        }
        if (valid && field === messageInput && value.length < 10) {
            valid = false;
        }

        setFieldState(field, valid);
        return valid;
    }

    fields.forEach((field) => {
        field.addEventListener('blur', () => validateField(field));
    });

    function updateCharCount() {
        if (!messageInput || !charCount) return;
        const length = messageInput.value.length;
        charCount.textContent = String(length);
        charCount.classList.remove('text-muted', 'text-amber-300', 'text-rose-400');

        if (length > 900) {
            charCount.classList.add('text-rose-400');
        } else if (length > 700) {
            charCount.classList.add('text-amber-300');
        } else {
            charCount.classList.add('text-muted');
        }
    }

    if (messageInput) {
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = `${messageInput.scrollHeight}px`;
            updateCharCount();
        });
        updateCharCount();
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            let allValid = true;
            fields.forEach((field) => {
                if (!validateField(field)) {
                    allValid = false;
                }
            });
            if (!allValid) {
                event.preventDefault();
                showToast('Veuillez corriger les champs en rouge.');
            }
        });
    }

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'open-map') {
            const address = "Avenue Charles de Gaulle, Quartier Klemat, N'Djamena, Tchad";
            const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
            window.open(url, '_blank');
            showToast('Ouverture de la carte...');
        }

        if (action === 'call') {
            window.location.href = 'tel:+23566123456';
            showToast('Appel en cours...');
        }

        if (action === 'email') {
            window.location.href = 'mailto:contact@tchadok.td?subject=Contact depuis le site web';
            showToast('Ouverture de votre client email...');
        }
    });

    function showToast(message) {
        document.querySelectorAll('.tchadok-toast').forEach((toast) => toast.remove());
        const toast = document.createElement('div');
        toast.className = 'tchadok-toast';
        toast.textContent = message;
        toast.setAttribute('role', 'status');
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 300);
        }, 2600);
    }
})();
