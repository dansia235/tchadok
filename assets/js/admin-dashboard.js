document.addEventListener('DOMContentLoaded', function () {
    var exportButton = document.querySelector('[data-action="export"]');
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            var url = exportButton.getAttribute('data-export-url');
            if (url) {
                window.open(url, '_blank');
            }
        });
    }

    var refreshButton = document.querySelector('[data-action="refresh"]');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            window.location.reload();
        });
    }

    var chartEl = document.querySelector('[data-chart="revenue"]');
    if (!chartEl || typeof Chart === 'undefined') {
        return;
    }

    var labels = [];
    var values = [];
    try {
        labels = JSON.parse(chartEl.getAttribute('data-labels') || '[]');
        values = JSON.parse(chartEl.getAttribute('data-values') || '[]');
    } catch (error) {
        labels = [];
        values = [];
    }

    var ctx = chartEl.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenus (XAF)',
                    data: values,
                    borderColor: 'rgb(47, 109, 224)',
                    backgroundColor: 'rgba(47, 109, 224, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('fr-FR', {
                                style: 'currency',
                                currency: 'XAF',
                                minimumFractionDigits: 0
                            }).format(value);
                        }
                    }
                }
            }
        }
    });
});
