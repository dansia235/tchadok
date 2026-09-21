document.addEventListener('DOMContentLoaded', function () {
    var modalTriggers = document.querySelectorAll('[data-modal-open]');
    var modals = document.querySelectorAll('[data-modal]');
    var modalCloseButtons = document.querySelectorAll('[data-modal-close]');
    var activeModal = null;
    var previousFocus = null;

    function showToast(message, tone) {
        var existing = document.querySelectorAll('[data-ui-toast]');
        existing.forEach(function (item) {
            item.remove();
        });

        var palette = {
            info: 'border-white/10 bg-surface text-text',
            success: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100',
            warning: 'border-amber-400/30 bg-amber-500/10 text-amber-100'
        };

        var toast = document.createElement('div');
        toast.setAttribute('data-ui-toast', 'true');
        toast.className = 'fixed right-4 top-4 z-50 max-w-sm rounded-2xl border px-4 py-3 text-sm shadow-elev-2 transition duration-300 ' + (palette[tone] || palette.info);
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('opacity-0', 'translate-y-2');
            window.setTimeout(function () {
                toast.remove();
            }, 250);
        }, 2600);
    }

    function getModal(name) {
        return document.querySelector('[data-modal="' + name + '"]');
    }

    function getModalPanel(modal) {
        return modal ? modal.querySelector('[data-modal-panel]') : null;
    }

    function openModal(name) {
        var modal = getModal(name);
        if (!modal) {
            return;
        }

        previousFocus = document.activeElement;
        activeModal = modal;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        var panel = getModalPanel(modal);
        if (panel) {
            window.requestAnimationFrame(function () {
                panel.classList.remove('translate-y-4', 'scale-95', 'opacity-0');
            });
        }

        var focusTarget = modal.querySelector('[data-modal-focus], input, button, textarea, select');
        if (focusTarget) {
            window.setTimeout(function () {
                focusTarget.focus();
                if (typeof focusTarget.select === 'function' && focusTarget.tagName === 'INPUT') {
                    focusTarget.select();
                }
            }, 120);
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        var panel = getModalPanel(modal);
        if (panel) {
            panel.classList.add('translate-y-4', 'scale-95', 'opacity-0');
        }

        window.setTimeout(function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            if (activeModal === modal) {
                activeModal = null;
            }
            if (!document.querySelector('[data-modal].flex')) {
                document.body.classList.remove('overflow-hidden');
            }
            if (previousFocus && typeof previousFocus.focus === 'function') {
                previousFocus.focus();
            }
        }, 180);
    }

    modalTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            openModal(trigger.getAttribute('data-modal-open'));
        });
    });

    modalCloseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.closest('[data-modal]'));
        });
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeModal) {
            closeModal(activeModal);
        }
    });

    modals.forEach(function (modal) {
        if (modal.getAttribute('data-modal-auto-open') === 'true') {
            openModal(modal.getAttribute('data-modal'));
        }
    });

    function copyText(value, label) {
        if (!value) {
            return;
        }

        function complete(success) {
            showToast(success ? (label || 'Texte copie') : 'Copie impossible sur ce navigateur', success ? 'success' : 'warning');
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                complete(true);
            }).catch(function () {
                complete(false);
            });
            return;
        }

        var tempInput = document.createElement('textarea');
        tempInput.value = value;
        tempInput.setAttribute('readonly', 'readonly');
        tempInput.style.position = 'absolute';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();

        try {
            var copied = document.execCommand('copy');
            complete(copied);
        } catch (error) {
            complete(false);
        }

        document.body.removeChild(tempInput);
    }

    document.querySelectorAll('[data-copy-value]').forEach(function (button) {
        button.addEventListener('click', function () {
            copyText(button.getAttribute('data-copy-value'), button.getAttribute('data-copy-label'));
        });
    });

    document.querySelectorAll('[data-ui-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-ui-action');
            var parentCard = button.closest('.rounded-2xl');

            if (action === 'revoke-device') {
                var deviceLabel = button.getAttribute('data-device-label') || 'Cet appareil';
                if (parentCard) {
                    parentCard.classList.add('opacity-60');
                }
                button.disabled = true;
                showToast(deviceLabel + ' marque pour revocation. Liaison backend a finaliser.', 'warning');
                return;
            }

            if (action === 'disconnect-all-devices') {
                showToast('La deconnexion globale est preparee, il reste a connecter l action serveur.', 'warning');
                return;
            }

            if (action === 'view-security-history') {
                showToast('L historique detaille sera ajoute dans un prochain module.', 'info');
                return;
            }

            if (action === 'manage-2fa') {
                showToast('Le panneau de gestion 2FA detaille sera branche a la prochaine iteration.', 'info');
                return;
            }

            if (action === 'show-backup-codes') {
                showToast('Ouvrez la configuration TOTP pour copier les codes de recuperation.', 'info');
                return;
            }

            if (action === 'disable-2fa') {
                showToast('La desactivation 2FA doit encore etre securisee cote serveur.', 'warning');
            }
        });
    });

    var passwordInput = document.querySelector('[data-password-input]');
    var confirmInput = document.querySelector('[data-confirm-input]');
    var strengthWrapper = document.querySelector('[data-strength-wrapper]');
    var strengthBar = document.querySelector('[data-strength-bar]');
    var strengthText = document.querySelector('[data-strength-text]');
    var strengthFeedback = document.querySelector('[data-strength-feedback]');
    var confirmStatus = document.querySelector('[data-confirm-status]');
    var passwordRules = {
        length: document.querySelector('[data-password-rule="length"]'),
        mixed: document.querySelector('[data-password-rule="mixed"]'),
        number: document.querySelector('[data-password-rule="number"]'),
        symbol: document.querySelector('[data-password-rule="symbol"]')
    };

    function checkPasswordStrength(password) {
        var score = 0;
        var feedback = [];
        var hasLength = password.length >= 8;
        var hasLower = /[a-z]/.test(password);
        var hasUpper = /[A-Z]/.test(password);
        var hasNumber = /[0-9]/.test(password);
        var hasSymbol = /[^a-zA-Z0-9]/.test(password);

        if (hasLength) {
            score += 25;
        } else {
            feedback.push('Au moins 8 caracteres');
        }
        if (password.length >= 12) {
            score += 10;
        }
        if (hasLower) {
            score += 10;
        } else {
            feedback.push('Ajouter des lettres minuscules');
        }
        if (hasUpper) {
            score += 15;
        } else {
            feedback.push('Ajouter des lettres majuscules');
        }
        if (hasNumber) {
            score += 15;
        } else {
            feedback.push('Ajouter des chiffres');
        }
        if (hasSymbol) {
            score += 20;
        } else {
            feedback.push('Ajouter des symboles');
        }

        return {
            score: Math.max(0, Math.min(100, score)),
            feedback: feedback,
            rules: {
                length: hasLength,
                mixed: hasLower && hasUpper,
                number: hasNumber,
                symbol: hasSymbol
            }
        };
    }

    function setRuleState(element, ok) {
        if (!element) {
            return;
        }

        if (ok) {
            element.className = 'rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200';
            element.innerHTML = '<i class="fas fa-check-circle mr-2 text-[10px]"></i>' + element.textContent.trim();
        } else {
            var label = element.textContent.trim();
            element.className = 'rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted';
            element.innerHTML = '<i class="fas fa-circle-notch mr-2 text-[10px]"></i>' + label;
        }
    }

    function updateStrength() {
        if (!passwordInput || !strengthWrapper || !strengthBar || !strengthText || !strengthFeedback) {
            return;
        }

        var password = passwordInput.value || '';
        if (!password.length) {
            strengthWrapper.classList.add('hidden');
            strengthBar.style.width = '0%';
            strengthText.textContent = '';
            strengthFeedback.innerHTML = '';
            Object.keys(passwordRules).forEach(function (key) {
                setRuleState(passwordRules[key], false);
            });
            return;
        }

        strengthWrapper.classList.remove('hidden');
        var strength = checkPasswordStrength(password);
        strengthBar.style.width = strength.score + '%';
        strengthBar.className = 'h-2 rounded-full';

        if (strength.score < 50) {
            strengthBar.classList.add('bg-rose-400');
            strengthText.textContent = 'Faible';
            strengthText.className = 'text-xs font-semibold text-rose-200';
            passwordInput.setCustomValidity('Mot de passe trop faible');
        } else if (strength.score < 75) {
            strengthBar.classList.add('bg-amber-400');
            strengthText.textContent = 'Moyen';
            strengthText.className = 'text-xs font-semibold text-amber-300';
            passwordInput.setCustomValidity('');
        } else {
            strengthBar.classList.add('bg-emerald-400');
            strengthText.textContent = 'Fort';
            strengthText.className = 'text-xs font-semibold text-emerald-300';
            passwordInput.setCustomValidity('');
        }

        Object.keys(passwordRules).forEach(function (key) {
            setRuleState(passwordRules[key], !!strength.rules[key]);
        });

        strengthFeedback.innerHTML = '';
        strength.feedback.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = item;
            strengthFeedback.appendChild(li);
        });
    }

    function validateConfirm() {
        if (!confirmInput || !passwordInput || !confirmStatus) {
            return;
        }

        if (!confirmInput.value) {
            confirmStatus.classList.add('hidden');
            confirmStatus.textContent = '';
            confirmInput.setCustomValidity('');
            return;
        }

        confirmStatus.classList.remove('hidden');
        if (confirmInput.value !== passwordInput.value) {
            confirmInput.setCustomValidity('Les mots de passe ne correspondent pas');
            confirmStatus.className = 'mt-2 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-200';
            confirmStatus.textContent = 'Les mots de passe ne correspondent pas encore.';
        } else {
            confirmInput.setCustomValidity('');
            confirmStatus.className = 'mt-2 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200';
            confirmStatus.textContent = 'Confirmation validee.';
        }
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updateStrength();
            validateConfirm();
        });
        updateStrength();
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', validateConfirm);
    }

    var phoneInput = document.querySelector('[data-phone-input]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            var digits = phoneInput.value.replace(/\D/g, '');
            if (digits.indexOf('235') === 0) {
                digits = digits.slice(3);
            }
            digits = digits.slice(0, 8);
            var grouped = digits.match(/.{1,2}/g);
            phoneInput.value = '+235' + (grouped && grouped.length ? ' ' + grouped.join(' ') : '');
        });
    }
});
