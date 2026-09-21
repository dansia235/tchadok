document.addEventListener('DOMContentLoaded', function () {
    var chartEl = document.querySelector('[data-chart="earnings"]');
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
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
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
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    },
                    ticks: {
                        color: '#A4AEC2',
                        callback: function (value) {
                            return new Intl.NumberFormat('fr-FR', {
                                style: 'currency',
                                currency: 'XAF',
                                minimumFractionDigits: 0
                            }).format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#A4AEC2'
                    }
                }
            }
        }
    });
});
