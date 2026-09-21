<?php
// Vue d'ensemble - Dashboard principal
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                <i class="fas fa-signal"></i>
                Systeme actif
            </div>
            <h2 class="mt-3 text-xl font-semibold text-text">Vue d'ensemble</h2>
            <p class="text-sm text-muted">Suivi global de la plateforme Tchadok.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-modal-open="addUserModal">
                <i class="fas fa-user-plus"></i>
                Nouvel utilisateur
            </button>
            <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addTrackModal">
                <i class="fas fa-music"></i>
                Nouvelle piste
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4 shadow-elev-1">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Utilisateurs</p>
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-accent/20 text-accent">
                    <i class="fas fa-users"></i>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['users'] ?? 0); ?></p>
            <p class="mt-2 text-xs text-emerald-200"><i class="fas fa-arrow-up"></i> +12% ce mois</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4 shadow-elev-1">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Artistes</p>
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-amber-400/20 text-amber-200">
                    <i class="fas fa-microphone"></i>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['artists'] ?? 0); ?></p>
            <p class="mt-2 text-xs text-emerald-200"><i class="fas fa-arrow-up"></i> +8% ce mois</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4 shadow-elev-1">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Pistes</p>
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky-400/20 text-sky-200">
                    <i class="fas fa-music"></i>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['tracks'] ?? 0); ?></p>
            <p class="mt-2 text-xs text-emerald-200"><i class="fas fa-arrow-up"></i> +25% ce mois</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4 shadow-elev-1">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Revenus</p>
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-emerald-400/20 text-emerald-200">
                    <i class="fas fa-coins"></i>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['revenue'] ?? 0); ?></p>
            <p class="mt-2 text-xs text-emerald-200"><i class="fas fa-arrow-up"></i> +18% ce mois</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-text"><i class="fas fa-chart-area text-accent"></i> Croissance utilisateurs</h3>
                <span class="text-xs text-muted">6 derniers mois</span>
            </div>
            <div class="mt-4 h-64">
                <canvas id="usersChart"></canvas>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-star text-amber-300"></i> Top artistes</h3>
            <div class="mt-4 space-y-3">
                <?php if (!empty($topArtists)): ?>
                    <?php foreach (array_slice($topArtists, 0, 5) as $index => $artist): ?>
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                            <div class="flex items-center gap-3">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-accent/20 text-xs font-semibold text-accent"><?php echo $index + 1; ?></span>
                                <div>
                                    <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                                    <p class="text-xs text-muted"><?php echo number_format($artist['total_streams']); ?> ecoutes</p>
                                </div>
                            </div>
                            <?php if (!empty($artist['verified'])): ?>
                                <i class="fas fa-check-circle text-accent"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Aucun artiste disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-user-clock text-sky-300"></i> Utilisateurs recents</h3>
            <div class="mt-4 space-y-3">
                <?php if (!empty($recentUsers)): ?>
                    <?php foreach ($recentUsers as $user): ?>
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p class="text-xs text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                            <p class="text-xs text-muted"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Aucune activite recente.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-receipt text-emerald-300"></i> Transactions recentes</h3>
            <div class="mt-4 space-y-3">
                <?php if (!empty($recentTransactions)): ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo number_format($transaction['amount']); ?> XAF</p>
                                <p class="text-xs text-muted"><?php echo htmlspecialchars($transaction['description']); ?></p>
                            </div>
                            <p class="text-xs text-muted"><?php echo date('d/m H:i', strtotime($transaction['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Aucune transaction recente.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chartEl = document.getElementById('usersChart');
    if (!chartEl || typeof Chart === 'undefined') {
        return;
    }
    var ctx = chartEl.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin'],
            datasets: [{
                label: 'Nouveaux utilisateurs',
                data: [12, 19, 8, 25, 32, 45],
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
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.2)' },
                    ticks: { color: '#A4AEC2' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#A4AEC2' }
                }
            }
        }
    });
});
</script>
