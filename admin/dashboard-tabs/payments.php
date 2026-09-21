<?php
// Gestion des paiements et transactions
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $dateFilter = $_GET['date_range'] ?? '';

    $whereConditions = [];
    if ($search) {
        $whereConditions[] = "(u.username LIKE '%$search%' OR t.reference LIKE '%$search%' OR t.description LIKE '%$search%')";
    }
    if ($statusFilter) {
        $whereConditions[] = "t.status = '$statusFilter'";
    }
    if ($dateFilter) {
        switch ($dateFilter) {
            case 'today':
                $whereConditions[] = "DATE(t.created_at) = CURDATE()";
                break;
            case 'week':
                $whereConditions[] = "t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $whereConditions[] = "t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $transactions = $pdo->query("
        SELECT t.*, u.username, u.first_name, u.last_name, u.email
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        $whereClause
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll();

    $totalTransactions = $pdo->query("SELECT COUNT(*) FROM transactions t LEFT JOIN users u ON t.user_id = u.id $whereClause")->fetchColumn();
    $totalPages = ceil($totalTransactions / $limit);

    $paymentStats = [
        'total_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
        'completed_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetchColumn(),
        'pending_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn(),
        'failed_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'failed'")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'")->fetchColumn(),
        'pending_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'pending'")->fetchColumn(),
        'today_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'avg_transaction' => $pdo->query("SELECT COALESCE(AVG(amount), 0) FROM transactions WHERE status = 'completed'")->fetchColumn(),
    ];

    $dailyRevenue = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(created_at) = '$date'")->fetchColumn();
        $dailyRevenue[] = ['date' => date('d/m', strtotime($date)), 'revenue' => $revenue];
    }

    $transactionTypes = $pdo->query("
        SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
        FROM transactions 
        WHERE status = 'completed'
        GROUP BY type
        ORDER BY total_amount DESC
    ")->fetchAll();

    $topUsers = $pdo->query("
        SELECT u.username, u.first_name, u.last_name, 
               COUNT(t.id) as transaction_count,
               COALESCE(SUM(t.amount), 0) as total_spent
        FROM users u
        JOIN transactions t ON u.id = t.user_id
        WHERE t.status = 'completed'
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 10
    ")->fetchAll();
}
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-text">Gestion des paiements</h2>
            <p class="text-sm text-muted">Suivi complet des transactions et revenus.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-modal-open="bulkPaymentModal">
                <i class="fas fa-layer-group"></i>
                Actions groupees
            </button>
            <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addTransactionModal">
                <i class="fas fa-plus"></i>
                Nouvelle transaction
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Revenus totaux</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($paymentStats['total_revenue'] ?? 0); ?></p>
            <p class="text-xs text-muted">XAF</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">En attente</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($paymentStats['pending_amount'] ?? 0); ?></p>
            <p class="text-xs text-muted"><?php echo $paymentStats['pending_transactions']; ?> transactions</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Aujourd'hui</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($paymentStats['today_revenue'] ?? 0); ?></p>
            <p class="text-xs text-muted">XAF</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Montant moyen</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($paymentStats['avg_transaction'] ?? 0); ?></p>
            <p class="text-xs text-muted">XAF</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-chart-area text-emerald-300"></i> Revenus 7 jours</h3>
            <div class="mt-4 h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-circle-nodes text-sky-300"></i> Statuts</h3>
            <div class="mt-4 h-48">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="mt-4 space-y-2 text-xs text-muted">
                <div class="flex items-center justify-between">
                    <span>Completees</span>
                    <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2 py-1 text-emerald-200"><?php echo $paymentStats['completed_transactions']; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span>En attente</span>
                    <span class="rounded-full border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-amber-200"><?php echo $paymentStats['pending_transactions']; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Echouees</span>
                    <span class="rounded-full border border-rose-400/30 bg-rose-400/10 px-2 py-1 text-rose-200"><?php echo $paymentStats['failed_transactions']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_180px_auto_auto] lg:items-center">
            <input type="hidden" name="tab" value="payments">
            <?php foreach (['status', 'date_range'] as $param): ?>
                <?php if (!empty($_GET[$param])): ?>
                    <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($_GET[$param]); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-2">
                <i class="fas fa-search text-muted"></i>
                <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" name="search" placeholder="Rechercher transaction..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="filterByStatus(this.value)">
                <option value="">Tous les statuts</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completee</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Echouee</option>
                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Annulee</option>
            </select>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="filterByDate(this.value)">
                <option value="">Toutes dates</option>
                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>7 jours</option>
                <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>30 jours</option>
            </select>
            <button type="button" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" onclick="exportTransactions()">
                Exporter
            </button>
        </form>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-text">Historique des transactions</h3>
            <span class="text-xs text-muted"><?php echo number_format($totalTransactions); ?> transactions</span>
        </div>

        <?php if (!empty($transactions)): ?>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm text-muted">
                    <thead class="border-b border-white/10 text-xs uppercase text-muted">
                        <tr>
                            <th class="py-3"><input type="checkbox" id="selectAllTransactions" class="h-4 w-4 rounded border-white/20 bg-bg text-accent"></th>
                            <th class="py-3">Reference</th>
                            <th class="py-3">Utilisateur</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Montant</th>
                            <th class="py-3">Statut</th>
                            <th class="py-3">Date</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="hover:bg-white/5">
                                <td class="py-3">
                                    <input type="checkbox" class="transaction-checkbox h-4 w-4 rounded border-white/20 bg-bg text-accent" value="<?php echo $transaction['id']; ?>">
                                </td>
                                <td class="py-3">
                                    <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($transaction['reference'] ?? 'N/A'); ?></p>
                                    <p class="text-xs text-muted">ID <?php echo $transaction['id']; ?></p>
                                </td>
                                <td class="py-3">
                                    <?php if ($transaction['username']): ?>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></p>
                                        <p class="text-xs text-muted">@<?php echo htmlspecialchars($transaction['username']); ?></p>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">Utilisateur supprime</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo getTransactionTypeBadge($transaction['type']); ?>">
                                        <?php echo ucfirst($transaction['type']); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <p class="text-sm font-semibold text-text"><?php echo number_format($transaction['amount'], 0, ',', ' '); ?> <?php echo $transaction['currency'] ?? 'XAF'; ?></p>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo getTransactionStatusBadge($transaction['status']); ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 text-xs text-muted">
                                    <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?>
                                    <div><?php echo date('H:i', strtotime($transaction['created_at'])); ?></div>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="inline-flex gap-2">
                                        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="viewTransaction(<?php echo $transaction['id']; ?>)" title="Voir">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        <?php if ($transaction['status'] === 'pending'): ?>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-emerald-200 hover:bg-emerald-500/20" onclick="approveTransaction(<?php echo $transaction['id']; ?>)" title="Approuver">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-rose-200 hover:bg-rose-500/20" onclick="rejectTransaction(<?php echo $transaction['id']; ?>)" title="Rejeter">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="downloadReceipt(<?php echo $transaction['id']; ?>)" title="Recu">
                                            <i class="fas fa-receipt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="rounded-full px-3 py-1 <?php echo $i === $page ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>"
                           href="?tab=payments&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&date_range=<?php echo urlencode($dateFilter); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                <i class="fas fa-credit-card text-3xl text-muted"></i>
                <p class="mt-3 text-sm text-muted">Aucune transaction trouvee.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($topUsers)): ?>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h3 class="text-sm font-semibold text-text"><i class="fas fa-users text-amber-300"></i> Top utilisateurs</h3>
                <div class="mt-4 space-y-3">
                    <?php foreach (array_slice($topUsers, 0, 5) as $index => $user): ?>
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p class="text-xs text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                            <div class="text-right text-xs text-muted">
                                <?php echo number_format($user['total_spent']); ?> XAF
                                <div><?php echo $user['transaction_count']; ?> transactions</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h3 class="text-sm font-semibold text-text"><i class="fas fa-chart-pie text-emerald-300"></i> Repartition par type</h3>
                <?php if (!empty($transactionTypes)): ?>
                    <div class="mt-4 space-y-3">
                        <?php $maxAmount = max(array_column($transactionTypes, 'total_amount')); ?>
                        <?php foreach ($transactionTypes as $type): ?>
                            <?php $percentage = $maxAmount ? ($type['total_amount'] / $maxAmount) * 100 : 0; ?>
                            <div>
                                <div class="flex items-center justify-between text-xs text-muted">
                                    <span><?php echo ucfirst($type['type']); ?></span>
                                    <span><?php echo number_format($type['total_amount']); ?> XAF</span>
                                </div>
                                <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                                    <div class="h-2 rounded-full bg-emerald-400" data-progress="<?php echo $percentage; ?>"></div>
                                </div>
                                <p class="mt-1 text-xs text-muted"><?php echo $type['count']; ?> transactions</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        return;
    }

    var dailyData = <?php echo json_encode($dailyRevenue); ?>;
    var paymentStats = <?php echo json_encode($paymentStats); ?>;

    var revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(function(d) { return d.date; }),
            datasets: [{
                label: 'Revenus (XAF)',
                data: dailyData.map(function(d) { return d.revenue; }),
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#22c55e',
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
            }
        }
    });

    var statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completees', 'En attente', 'Echouees'],
            datasets: [{
                data: [
                    paymentStats.completed_transactions,
                    paymentStats.pending_transactions,
                    paymentStats.failed_transactions
                ],
                backgroundColor: ['#22c55e', '#f59e0b', '#f43f5e'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    document.querySelectorAll('[data-progress]').forEach(function(el) {
        var value = Number(el.getAttribute('data-progress')) || 0;
        el.style.width = Math.min(100, value) + '%';
    });
});

function filterByStatus(status) {
    var currentUrl = new URL(window.location);
    if (status) {
        currentUrl.searchParams.set('status', status);
    } else {
        currentUrl.searchParams.delete('status');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function filterByDate(dateRange) {
    var currentUrl = new URL(window.location);
    if (dateRange) {
        currentUrl.searchParams.set('date_range', dateRange);
    } else {
        currentUrl.searchParams.delete('date_range');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function viewTransaction(transactionId) {
    fetch(`../api/transaction.php?action=get&id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var transaction = data.transaction;
                var bodyHtml = `
                    <div class="space-y-2 text-sm text-muted">
                        <div class="text-text font-semibold">Transaction ${transaction.reference || transaction.id}</div>
                        <div>Montant: ${transaction.amount} ${transaction.currency || 'XAF'}</div>
                        <div>Statut: ${transaction.status}</div>
                    </div>
                `;
                showDetailModal('Details transaction', bodyHtml, '');
            }
        });
}

function approveTransaction(transactionId) {
    if (confirm('Approuver cette transaction ?')) {
        fetch(`../api/transaction.php?action=approve&id=${transactionId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            });
    }
}

function rejectTransaction(transactionId) {
    if (confirm('Rejeter cette transaction ?')) {
        fetch(`../api/transaction.php?action=reject&id=${transactionId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            });
    }
}

function exportTransactions() {
    var currentUrl = new URL(window.location);
    currentUrl.pathname = currentUrl.pathname.replace('dashboard.php', '../api/export.php');
    currentUrl.searchParams.set('type', 'transactions');
    window.open(currentUrl.toString(), '_blank');
}

function downloadReceipt(transactionId) {
    window.open(`../api/receipt.php?id=${transactionId}`, '_blank');
}

document.getElementById('selectAllTransactions')?.addEventListener('change', function(event) {
    var checkboxes = document.querySelectorAll('.transaction-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = event.target.checked;
    });
});
</script>

<?php
function getTransactionStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return 'border border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
        case 'pending':
            return 'border border-amber-400/30 bg-amber-400/10 text-amber-200';
        case 'failed':
            return 'border border-rose-400/30 bg-rose-400/10 text-rose-200';
        case 'cancelled':
            return 'border border-white/10 bg-white/5 text-muted';
        default:
            return 'border border-white/10 bg-white/5 text-muted';
    }
}

function getTransactionTypeBadge($type) {
    switch ($type) {
        case 'purchase':
            return 'border border-accent/30 bg-accent/10 text-accent';
        case 'commission':
            return 'border border-sky-400/30 bg-sky-400/10 text-sky-200';
        case 'withdrawal':
            return 'border border-amber-400/30 bg-amber-400/10 text-amber-200';
        case 'deposit':
            return 'border border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
        case 'refund':
            return 'border border-rose-400/30 bg-rose-400/10 text-rose-200';
        default:
            return 'border border-white/10 bg-white/5 text-muted';
    }
}
?>
