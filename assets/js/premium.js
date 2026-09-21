document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-subscribe]');

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var plan = button.getAttribute('data-plan') || 'monthly';
            var planLabel = getPlanLabel(plan);
            showToast('Souscription Premium', planLabel + '. Redirection vers le paiement...');

            var baseUrl = (window.TCHADOK && window.TCHADOK.SITE_URL) ? window.TCHADOK.SITE_URL : '';
            setTimeout(function () {
                window.location.href = baseUrl + '/premium-payment.php?plan=' + encodeURIComponent(plan);
            }, 1800);
        });
    });

    function getPlanLabel(plan) {
        var labels = {
            monthly: 'Formule mensuelle',
            yearly: 'Formule annuelle'
        };
        return labels[plan] || 'Premium';
    }

    function showToast(title, message) {
        var toast = document.createElement('div');
        toast.className = 'fixed right-6 top-6 z-50 max-w-sm rounded-2xl border border-white/10 bg-surface px-4 py-3 text-sm text-text shadow-elev-2';
        toast.innerHTML = '<div class="flex items-start gap-3">' +
            '<span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-400/20 text-amber-300">' +
            '<i class="fas fa-crown"></i>' +
            '</span>' +
            '<div>' +
            '<p class="text-sm font-semibold">' + title + '</p>' +
            '<p class="mt-1 text-xs text-muted">' + message + '</p>' +
            '</div>' +
            '</div>';

        document.body.appendChild(toast);
        setTimeout(function () {
            toast.remove();
        }, 3200);
    }
});
