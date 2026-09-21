document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-register-form]');
    if (!form) {
        return;
    }

    var progressBar = document.querySelector('[data-progress-bar]');
    var submitBtn = document.querySelector('[data-submit]');
    var btnText = submitBtn ? submitBtn.querySelector('[data-btn-text]') : null;
    var btnLoader = submitBtn ? submitBtn.querySelector('[data-btn-loader]') : null;

    var passwordInput = document.querySelector('[data-password-input]');
    var confirmInput = document.querySelector('[data-confirm-input]');
    var strengthWrapper = document.querySelector('[data-strength-wrapper]');
    var strengthBar = document.querySelector('[data-strength-bar]');
    var strengthText = document.querySelector('[data-strength-text]');
    var strengthFeedback = document.querySelector('[data-strength-feedback]');

    var artistField = document.querySelector('[data-artist-field]');
    var userTypeInputs = document.querySelectorAll('[data-user-type]');

    function togglePasswordVisibility(button) {
        var targetId = button.getAttribute('data-target');
        var targetInput = document.getElementById(targetId);
        if (!targetInput) {
            return;
        }
        var icon = button.querySelector('i');
        if (targetInput.type === 'password') {
            targetInput.type = 'text';
            if (icon) {
                icon.className = 'fas fa-eye-slash';
            }
        } else {
            targetInput.type = 'password';
            if (icon) {
                icon.className = 'fas fa-eye';
            }
        }
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            togglePasswordVisibility(button);
        });
    });

    function updateArtistField() {
        if (!artistField) {
            return;
        }
        var artistChecked = false;
        userTypeInputs.forEach(function (input) {
            if (input.checked && input.getAttribute('data-user-type') === 'artist') {
                artistChecked = true;
            }
        });
        artistField.classList.toggle('hidden', !artistChecked);
    }

    userTypeInputs.forEach(function (input) {
        input.addEventListener('change', updateArtistField);
    });
    updateArtistField();

    function checkPasswordStrength(password) {
        var score = 0;
        var feedback = [];

        if (password.length >= 8) {
            score += 25;
        } else {
            feedback.push('Au moins 8 caracteres');
        }
        if (password.length >= 12) {
            score += 10;
        }
        if (/[a-z]/.test(password)) {
            score += 15;
        } else {
            feedback.push('Ajouter des lettres minuscules');
        }
        if (/[A-Z]/.test(password)) {
            score += 15;
        } else {
            feedback.push('Ajouter des lettres majuscules');
        }
        if (/[0-9]/.test(password) || /[^a-zA-Z0-9]/.test(password)) {
            score += 20;
        } else {
            feedback.push('Ajouter des chiffres ou symboles');
        }

        score = Math.max(0, Math.min(100, score));

        return { score: score, feedback: feedback };
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
            return;
        }

        strengthWrapper.classList.remove('hidden');
        var strength = checkPasswordStrength(password);
        strengthBar.style.width = strength.score + '%';
        strengthBar.className = 'h-2 w-0 rounded-full';

        if (strength.score <= 25) {
            strengthBar.classList.add('bg-rose-400');
            strengthText.textContent = 'Faible';
            strengthText.className = 'text-xs font-semibold text-rose-200';
        } else if (strength.score <= 50) {
            strengthBar.classList.add('bg-amber-400');
            strengthText.textContent = 'Moyen';
            strengthText.className = 'text-xs font-semibold text-amber-300';
        } else if (strength.score <= 75) {
            strengthBar.classList.add('bg-yellow-300');
            strengthText.textContent = 'Bon';
            strengthText.className = 'text-xs font-semibold text-yellow-200';
        } else {
            strengthBar.classList.add('bg-emerald-400');
            strengthText.textContent = 'Excellent';
            strengthText.className = 'text-xs font-semibold text-emerald-300';
        }

        strengthFeedback.innerHTML = '';
        strength.feedback.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = item;
            strengthFeedback.appendChild(li);
        });
    }

    function validateConfirm() {
        if (!confirmInput || !passwordInput) {
            return;
        }
        if (confirmInput.value && confirmInput.value !== passwordInput.value) {
            confirmInput.setCustomValidity('Les mots de passe ne correspondent pas');
        } else {
            confirmInput.setCustomValidity('');
        }
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updateStrength();
            validateConfirm();
        });
    }
    if (confirmInput) {
        confirmInput.addEventListener('input', validateConfirm);
    }

    var progressFields = Array.from(form.querySelectorAll('[data-progress-field]'));
    var progressGroups = Array.from(form.querySelectorAll('[data-progress-group]'))
        .map(function (input) {
            return input.getAttribute('data-progress-group');
        })
        .filter(function (value, index, self) {
            return value && self.indexOf(value) === index;
        });

    function updateProgress() {
        if (!progressBar) {
            return;
        }
        var total = progressFields.length + progressGroups.length;
        var filled = 0;

        progressFields.forEach(function (field) {
            if (field.type === 'checkbox') {
                if (field.checked) {
                    filled += 1;
                }
            } else if (field.value && field.value.trim() !== '') {
                filled += 1;
            }
        });

        progressGroups.forEach(function (group) {
            if (form.querySelector('[data-progress-group="' + group + '"]:checked')) {
                filled += 1;
            }
        });

        var percent = total ? Math.round((filled / total) * 100) : 0;
        progressBar.style.width = percent + '%';
    }

    progressFields.forEach(function (field) {
        field.addEventListener('input', updateProgress);
        field.addEventListener('change', updateProgress);
    });
    progressGroups.forEach(function (group) {
        var groupInputs = form.querySelectorAll('[data-progress-group="' + group + '"]');
        groupInputs.forEach(function (input) {
            input.addEventListener('change', updateProgress);
        });
    });
    updateProgress();

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
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    });
});
