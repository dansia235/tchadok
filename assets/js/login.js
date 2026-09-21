document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-login-form]');
    var submitBtn = document.querySelector('[data-submit]');
    var btnText = submitBtn ? submitBtn.querySelector('[data-btn-text]') : null;
    var btnLoader = submitBtn ? submitBtn.querySelector('[data-btn-loader]') : null;

    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }

            if (btnText) {
                btnText.classList.add('hidden');
            }
            if (btnLoader) {
                btnLoader.classList.remove('hidden');
            }
            submitBtn.disabled = true;
        });
    }

    var toggleBtn = document.querySelector('[data-password-toggle]');
    var passwordInput = document.querySelector('[data-password-input]');
    var passwordIcon = toggleBtn ? toggleBtn.querySelector('[data-password-icon]') : null;

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                if (passwordIcon) {
                    passwordIcon.className = 'fas fa-eye-slash';
                }
                toggleBtn.setAttribute('aria-label', 'Masquer le mot de passe');
            } else {
                passwordInput.type = 'password';
                if (passwordIcon) {
                    passwordIcon.className = 'fas fa-eye';
                }
                toggleBtn.setAttribute('aria-label', 'Afficher le mot de passe');
            }
        });
    }

    var socialButtons = document.querySelectorAll('[data-social-notice]');
    socialButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var message = button.getAttribute('data-social-notice') || 'Fonctionnalite bientot disponible';
            showToast(message);
        });
    });

    function showToast(message) {
        var toast = document.createElement('div');
        toast.className = 'fixed top-6 right-6 z-50 flex max-w-xs items-start gap-2 rounded-2xl border border-white/10 bg-surface px-4 py-3 text-sm text-text shadow-elev-2';
        toast.innerHTML = '<i class="fas fa-info-circle mt-0.5 text-accent"></i><span>' + message + '</span>';
        document.body.appendChild(toast);

        setTimeout(function () {
            toast.remove();
        }, 3000);
    }
});
