document.addEventListener('DOMContentLoaded', function () {
    var planRadios = document.querySelectorAll('[data-plan-radio]');
    var phoneInput = document.querySelector('[data-phone-input]');

    planRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var plan = radio.value;
            var baseUrl = (window.TCHADOK && window.TCHADOK.SITE_URL) ? window.TCHADOK.SITE_URL : '';
            window.location.href = baseUrl + '/premium-payment.php?plan=' + encodeURIComponent(plan);
        });
    });

    if (phoneInput) {
        phoneInput.addEventListener('input', function (event) {
            var value = event.target.value.replace(/\s/g, '');
            var grouped = value.match(/.{1,2}/g);
            event.target.value = grouped ? grouped.join(' ') : value;
        });
    }
});
