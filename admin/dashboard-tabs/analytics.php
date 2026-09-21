<?php
// Analytics et statistiques avancées
if ($dbConnected) {
    $last6Months = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $last6Months[] = $month;
    }

    $monthlyUsers = [];
    $monthlyTracks = [];
    $monthlyRevenue = [];

    foreach ($last6Months as $month) {
        $users = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $tracks = $pdo->query("SELECT COUNT(*) FROM tracks WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' AND status = 'completed'")->fetchColumn();

        $monthlyUsers[] = $users;
        $monthlyTracks[] = $tracks;
        $monthlyRevenue[] = $revenue;
    }

    $topTracks = $pdo->query("
        SELECT t.title, ar.stage_name, t.total_streams, al.title as album_title
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        ORDER BY t.total_streams DESC
        LIMIT 10
    ")->fetchAll();

    $genreStats = $pdo->query("
        SELECT a.genres, COUNT(*) as artist_count, COALESCE(SUM(t.total_streams), 0) as total_streams
        FROM artists a
        LEFT JOIN tracks t ON a.id = t.artist_id
        WHERE a.genres IS NOT NULL
        GROUP BY a.genres
        ORDER BY total_streams DESC
        LIMIT 8
    ")->fetchAll();

    $countryStats = $pdo->query("
        SELECT country, COUNT(*) as user_count
        FROM users
        WHERE country IS NOT NULL
        GROUP BY country
        ORDER BY user_count DESC
        LIMIT 10
    ")->fetchAll();

    $recentActivity = [
        'new_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'new_tracks' => $pdo->query("SELECT COUNT(*) FROM tracks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'new_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'total_streams_today' => $pdo->query("SELECT COALESCE(SUM(total_streams), 0) FROM tracks WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];

    $hourlyActivity = [];
    for ($i = 23; $i >= 0; $i--) {
        $hour = date('H', strtotime("-$i hours"));
        $activity = $pdo->query("
            SELECT COUNT(*) 
            FROM transactions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . ($i + 1) . " HOUR)
            AND created_at < DATE_SUB(NOW(), INTERVAL $i HOUR)
        ")->fetchColumn();
        $hourlyActivity[] = ['hour' => $hour . 'h', 'value' => $activity];
    }
}
?>

<section class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold text-text">Analyses & statistiques</h2>
        <p class="text-sm text-muted">Performance de la plateforme en temps reel.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Nouveaux users</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($recentActivity['new_users'] ?? 0); ?></p>
            <p class="text-xs text-muted">Dernieres 24h</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Nouvelles pistes</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($recentActivity['new_tracks'] ?? 0); ?></p>
            <p class="text-xs text-muted">Dernieres 24h</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Transactions</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($recentActivity['new_transactions'] ?? 0); ?></p>
            <p class="text-xs text-muted">Dernieres 24h</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Ecoutes</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($recentActivity['total_streams_today'] ?? 0); ?></p>
            <p class="text-xs text-muted">Aujourd'hui</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-text"><i class="fas fa-chart-area text-accent"></i> Evolution 6 mois</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                    <label class="cursor-pointer rounded-full border border-white/10 px-3 py-1 text-muted">
                        <input type="radio" name="chartType" id="users" class="hidden" checked>
                        Utilisateurs
                    </label>
                    <label class="cursor-pointer rounded-full border border-white/10 px-3 py-1 text-muted">
                        <input type="radio" name="chartType" id="tracks" class="hidden">
                        Pistes
                    </label>
                    <label class="cursor-pointer rounded-full border border-white/10 px-3 py-1 text-muted">
                        <input type="radio" name="chartType" id="revenue" class="hidden">
                        Revenus
                    </label>
                </div>
            </div>
            <div class="mt-4 h-64">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-broadcast-tower text-emerald-300"></i> Activite 24h</h3>
            <div class="mt-4 h-64">
                <canvas id="realtimeChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-trophy text-amber-300"></i> Top pistes</h3>
            <div class="mt-4 space-y-3">
                <?php if (!empty($topTracks)): ?>
                    <?php foreach ($topTracks as $index => $track): ?>
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                <p class="text-xs text-muted"><?php echo htmlspecialchars($track['stage_name']); ?></p>
                            </div>
                            <div class="text-right text-xs text-muted">
                                <?php echo number_format($track['total_streams']); ?> ecoutes
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Aucune piste disponible.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-palette text-sky-300"></i> Genres populaires</h3>
            <div class="mt-4 h-64">
                <canvas id="genreChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-globe-africa text-emerald-300"></i> Repartition geographique</h3>
            <?php if (!empty($countryStats)): ?>
                <div class="mt-4 space-y-3">
                    <?php $maxUsers = max(array_column($countryStats, 'user_count')); ?>
                    <?php foreach ($countryStats as $country): ?>
                        <?php $percentage = ($country['user_count'] / $maxUsers) * 100; ?>
                        <div>
                            <div class="flex items-center justify-between text-xs text-muted">
                                <span><?php echo htmlspecialchars($country['country']); ?></span>
                                <span><?php echo number_format($country['user_count']); ?></span>
                            </div>
                            <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                                <div class="h-2 rounded-full bg-accent" data-progress="<?php echo $percentage; ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mt-3 text-sm text-muted">Aucune donnee disponible.</p>
            <?php endif; ?>
        </div>

        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-lightbulb text-amber-300"></i> Insights</h3>
            <div class="mt-4 space-y-3 text-sm text-muted">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                    Croissance positive des utilisateurs ce mois.
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                    Les genres dominants meritent plus de mises en avant.
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                    Pic d'activite entre 18h et 22h.
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        return;
    }

    var monthlyData = {
        labels: <?php echo json_encode(array_map(function($m) { return date('M Y', strtotime($m)); }, $last6Months)); ?>,
        users: <?php echo json_encode($monthlyUsers); ?>,
        tracks: <?php echo json_encode($monthlyTracks); ?>,
        revenue: <?php echo json_encode($monthlyRevenue); ?>
    };

    var genreData = <?php echo json_encode($genreStats); ?>;
    var hourlyData = <?php echo json_encode($hourlyActivity); ?>;

    var evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    var evolutionChart = new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: monthlyData.labels,
            datasets: [{
                label: 'Utilisateurs',
                data: monthlyData.users,
                borderColor: '#2F6DE0',
                backgroundColor: 'rgba(47, 109, 224, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#2F6DE0',
                pointBorderColor: '#0B0F17',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.2)' }, ticks: { color: '#A4AEC2' } },
                x: { grid: { display: false }, ticks: { color: '#A4AEC2' } }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });

    document.querySelectorAll('input[name="chartType"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var newData = monthlyData.users;
            var newColor = '#2F6DE0';
            var newLabel = 'Utilisateurs';
            if (this.id === 'tracks') {
                newData = monthlyData.tracks;
                newColor = '#22c55e';
                newLabel = 'Pistes';
            } else if (this.id === 'revenue') {
                newData = monthlyData.revenue;
                newColor = '#f59e0b';
                newLabel = 'Revenus';
            }
            evolutionChart.data.datasets[0].data = newData;
            evolutionChart.data.datasets[0].label = newLabel;
            evolutionChart.data.datasets[0].borderColor = newColor;
            evolutionChart.data.datasets[0].backgroundColor = newColor + '30';
            evolutionChart.data.datasets[0].pointBackgroundColor = newColor;
            evolutionChart.update();
        });
    });

    var realtimeCtx = document.getElementById('realtimeChart').getContext('2d');
    new Chart(realtimeCtx, {
        type: 'bar',
        data: {
            labels: hourlyData.map(function(h) { return h.hour; }),
            datasets: [{
                label: 'Activite',
                data: hourlyData.map(function(h) { return h.value; }),
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                borderColor: '#22c55e',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.2)' }, ticks: { color: '#A4AEC2' } },
                x: { grid: { display: false }, ticks: { color: '#A4AEC2' } }
            }
        }
    });

    var genreCtx = document.getElementById('genreChart').getContext('2d');
    new Chart(genreCtx, {
        type: 'doughnut',
        data: {
            labels: genreData.map(function(g) { return g.genres; }),
            datasets: [{
                data: genreData.map(function(g) { return g.total_streams; }),
                backgroundColor: ['#FFC107', '#2F6DE0', '#22c55e', '#f43f5e', '#f59e0b', '#38bdf8', '#a855f7', '#f97316'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, color: '#A4AEC2' } }
            }
        }
    });

    document.querySelectorAll('[data-progress]').forEach(function(el) {
        var value = Number(el.getAttribute('data-progress')) || 0;
        el.style.width = Math.min(100, value) + '%';
    });
});
</script>
