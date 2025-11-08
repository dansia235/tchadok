<?php
// Gestion des paiements et transactions
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $dateFilter = $_GET['date_range'] ?? '';
    
    // Construction des conditions WHERE
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
    
    // Récupération des transactions
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
    
    // Statistiques des paiements
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
    
    // Données pour graphiques
    $dailyRevenue = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(created_at) = '$date'")->fetchColumn();
        $dailyRevenue[] = ['date' => date('d/m', strtotime($date)), 'revenue' => $revenue];
    }
    
    // Répartition par type de transaction
    $transactionTypes = $pdo->query("
        SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
        FROM transactions 
        WHERE status = 'completed'
        GROUP BY type
        ORDER BY total_amount DESC
    ")->fetchAll();
    
    // Top utilisateurs par montant
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

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-credit-card me-3 text-success"></i>
            Gestion des Paiements
            <span class="badge bg-success ms-3"><?php echo number_format($paymentStats['total_revenue'] ?? 0); ?> XAF</span>
        </h2>
        <p class="text-muted">Suivi et gestion de toutes les transactions financières</p>
    </div>
</div>

<!-- Statistiques des paiements -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="stat-card text-center">
            <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($paymentStats['total_revenue'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Revenus Totaux (XAF)</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> Toutes transactions
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="stat-card text-center">
            <i class="fas fa-clock fa-2x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($paymentStats['pending_amount'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">En Attente (XAF)</h6>
            <small class="text-warning">
                <i class="fas fa-hourglass-half"></i> <?php echo $paymentStats['pending_transactions']; ?> transactions
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="stat-card text-center">
            <i class="fas fa-calendar-day fa-2x text-info mb-3"></i>
            <div class="stat-number"><?php echo number_format($paymentStats['today_revenue'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Aujourd'hui (XAF)</h6>
            <small class="text-info">
                <i class="fas fa-calendar"></i> Revenus du jour
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="stat-card text-center">
            <i class="fas fa-calculator fa-2x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($paymentStats['avg_transaction'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Montant Moyen (XAF)</h6>
            <small class="text-primary">
                <i class="fas fa-balance-scale"></i> Par transaction
            </small>
        </div>
    </div>
</div>

<!-- Graphiques des revenus -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-chart-area me-2 text-primary"></i>
                Évolution des Revenus (7 derniers jours)
            </h6>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-pie-chart me-2 text-info"></i>
                Répartition des Statuts
            </h6>
            <div class="chart-container" style="height: 250px;">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-2">
                    <span>Complétées</span>
                    <span class="badge bg-success"><?php echo $paymentStats['completed_transactions']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>En attente</span>
                    <span class="badge bg-warning text-dark"><?php echo $paymentStats['pending_transactions']; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Échouées</span>
                    <span class="badge bg-danger"><?php echo $paymentStats['failed_transactions']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="tab" value="payments">
                        <?php foreach (['status', 'date_range'] as $param): ?>
                            <?php if (!empty($_GET[$param])): ?>
                                <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($_GET[$param]); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Rechercher transaction..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-admin" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-2">
                    <select class="form-select" onchange="filterByStatus(this.value)">
                        <option value="">Tous les statuts</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Complétée</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Échouée</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" onchange="filterByDate(this.value)">
                        <option value="">Toutes les dates</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                        <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>7 derniers jours</option>
                        <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>30 derniers jours</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="exportTransactions()">
                        <i class="fas fa-download me-2"></i>Exporter
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <div class="btn-group">
                        <button class="btn btn-success-admin" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                            <i class="fas fa-plus me-2"></i>
                            Nouvelle Transaction
                        </button>
                        <button class="btn btn-warning-admin" data-bs-toggle="modal" data-bs-target="#bulkPaymentModal">
                            <i class="fas fa-tasks me-2"></i>
                            Actions Groupées
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des transactions -->
<div class="row">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-list me-2"></i>
                Historique des Transactions
                <span class="badge bg-secondary ms-2"><?php echo number_format($totalTransactions); ?> total</span>
            </h6>
            
            <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllTransactions"></th>
                            <th>Référence</th>
                            <th>Utilisateur</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr class="transaction-row" data-id="<?php echo $transaction['id']; ?>">
                            <td><input type="checkbox" class="transaction-checkbox" value="<?php echo $transaction['id']; ?>"></td>
                            <td>
                                <div class="transaction-ref">
                                    <strong><?php echo htmlspecialchars($transaction['reference'] ?? 'N/A'); ?></strong>
                                    <br>
                                    <small class="text-muted">ID: <?php echo $transaction['id']; ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($transaction['username']): ?>
                                <div class="user-info">
                                    <div class="user-avatar-mini">
                                        <?php echo strtoupper(substr($transaction['first_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($transaction['username']); ?></small>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted">Utilisateur supprimé</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getTransactionTypeBadge($transaction['type']); ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="amount-display">
                                    <strong class="amount-value"><?php echo number_format($transaction['amount'], 0, ',', ' '); ?></strong>
                                    <small class="currency"><?php echo $transaction['currency'] ?? 'XAF'; ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo getTransactionStatusBadge($transaction['status']); ?>">
                                    <?php echo ucfirst($transaction['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="transaction-date">
                                    <span><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></span>
                                    <br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewTransaction(<?php echo $transaction['id']; ?>)" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($transaction['status'] === 'pending'): ?>
                                    <button class="btn btn-outline-success" onclick="approveTransaction(<?php echo $transaction['id']; ?>)" title="Approuver">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="rejectTransaction(<?php echo $transaction['id']; ?>)" title="Rejeter">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-info" onclick="downloadReceipt(<?php echo $transaction['id']; ?>)" title="Reçu">
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?tab=payments&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&date_range=<?php echo urlencode($dateFilter); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune transaction trouvée</h5>
                <p class="text-muted">Aucune transaction ne correspond à vos critères de recherche.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top utilisateurs -->
<?php if (!empty($topUsers)): ?>
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-users me-2 text-warning"></i>
                Top Utilisateurs par Montant
            </h6>
            <div class="top-users-list">
                <?php foreach (array_slice($topUsers, 0, 5) as $index => $user): ?>
                <div class="top-user-item d-flex align-items-center mb-3">
                    <div class="rank-badge-mini me-3">
                        <span><?php echo $index + 1; ?></span>
                    </div>
                    <div class="user-avatar-mini me-3">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info flex-grow-1">
                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                        <br>
                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                    </div>
                    <div class="user-stats text-end">
                        <div class="amount-spent"><?php echo number_format($user['total_spent']); ?> XAF</div>
                        <small class="text-muted"><?php echo $user['transaction_count']; ?> transactions</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-chart-pie me-2 text-success"></i>
                Répartition par Type
            </h6>
            <?php if (!empty($transactionTypes)): ?>
            <div class="transaction-types">
                <?php foreach ($transactionTypes as $type): ?>
                <div class="type-item mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="type-name"><?php echo ucfirst($type['type']); ?></span>
                        <span class="type-amount"><?php echo number_format($type['total_amount']); ?> XAF</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo ($type['total_amount'] / max(array_column($transactionTypes, 'total_amount'))) * 100; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $type['count']; ?> transactions</small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.user-info {
    display: flex;
    align-items: center;
}

.user-avatar-mini {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
}

.user-details {
    margin-left: 0.75rem;
}

.amount-display {
    text-align: right;
}

.amount-value {
    font-size: 1.1rem;
    color: var(--success-color);
}

.currency {
    color: var(--secondary-color);
    font-weight: 600;
}

.transaction-ref strong {
    color: var(--accent-color);
}

.rank-badge-mini {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--warning-color), #e0a800);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--dark-color);
    font-weight: bold;
    font-size: 0.8rem;
}

.amount-spent {
    font-weight: 700;
    color: var(--success-color);
}

.type-item {
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(0, 102, 204, 0.05);
    transition: all 0.3s ease;
}

.type-item:hover {
    background: rgba(0, 102, 204, 0.1);
}

.type-name {
    font-weight: 600;
    color: var(--secondary-color);
}

.type-amount {
    font-weight: 700;
    color: var(--success-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données pour les graphiques
    const dailyData = <?php echo json_encode($dailyRevenue); ?>;
    const paymentStats = <?php echo json_encode($paymentStats); ?>;
    
    // Graphique des revenus
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Revenus (XAF)',
                data: dailyData.map(d => d.revenue),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#28a745',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' XAF';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            tooltips: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y.toLocaleString() + ' XAF';
                    }
                }
            }
        }
    });
    
    // Graphique des statuts
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Complétées', 'En attente', 'Échouées'],
            datasets: [{
                data: [
                    paymentStats.completed_transactions,
                    paymentStats.pending_transactions,
                    paymentStats.failed_transactions
                ],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});

// Fonctions de gestion des paiements
function filterByStatus(status) {
    const currentUrl = new URL(window.location);
    if (status) {
        currentUrl.searchParams.set('status', status);
    } else {
        currentUrl.searchParams.delete('status');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function filterByDate(dateRange) {
    const currentUrl = new URL(window.location);
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
                showTransactionModal(data.transaction);
            }
        });
}

function approveTransaction(transactionId) {
    if (confirm('Approuver cette transaction ?')) {
        fetch(`../api/transaction.php?action=approve&id=${transactionId}`, {
            method: 'POST'
        })
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
        fetch(`../api/transaction.php?action=reject&id=${transactionId}`, {
            method: 'POST'
        })
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
    const currentUrl = new URL(window.location);
    currentUrl.pathname = currentUrl.pathname.replace('dashboard.php', '../api/export.php');
    currentUrl.searchParams.set('type', 'transactions');
    window.open(currentUrl.toString(), '_blank');
}

function downloadReceipt(transactionId) {
    window.open(`../api/receipt.php?id=${transactionId}`, '_blank');
}

// Sélection multiple
document.getElementById('selectAllTransactions').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.transaction-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

<?php
function getTransactionStatusBadge($status) {
    switch ($status) {
        case 'completed': return 'bg-success';
        case 'pending': return 'bg-warning text-dark';
        case 'failed': return 'bg-danger';
        case 'cancelled': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

function getTransactionTypeBadge($type) {
    switch ($type) {
        case 'purchase': return 'bg-primary';
        case 'commission': return 'bg-info text-dark';
        case 'withdrawal': return 'bg-warning text-dark';
        case 'deposit': return 'bg-success';
        case 'refund': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>