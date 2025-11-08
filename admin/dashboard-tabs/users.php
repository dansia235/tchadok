<?php
// Gestion des utilisateurs
if ($dbConnected) {
    // Récupération des utilisateurs avec pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $whereClause = $search ? "WHERE first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%'" : '';
    
    $users = $pdo->query("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users $whereClause")->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
    
    // Statistiques utilisateurs
    $userStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'verified' => $pdo->query("SELECT COUNT(*) FROM users WHERE email_verified = 1")->fetchColumn(),
        'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-users me-3 text-primary"></i>
            Gestion des Utilisateurs
            <span class="badge bg-primary ms-3"><?php echo number_format($userStats['total'] ?? 0); ?> total</span>
        </h2>
        <p class="text-muted">Gérer tous les utilisateurs de la plateforme</p>
    </div>
</div>

<!-- Statistiques utilisateurs -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-users fa-2x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($userStats['total'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Total Utilisateurs</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-user-check fa-2x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($userStats['active'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Actifs</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-user-shield fa-2x text-info mb-3"></i>
            <div class="stat-number"><?php echo number_format($userStats['verified'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Vérifiés</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-user-plus fa-2x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($userStats['new_today'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Aujourd'hui</h6>
        </div>
    </div>
</div>

<!-- Barre d'outils -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="tab" value="users">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Rechercher un utilisateur..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-admin" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-success-admin" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>
                        Nouvel Utilisateur
                    </button>
                    <button class="btn btn-warning-admin" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                        <i class="fas fa-list me-2"></i>
                        Actions Groupées
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des utilisateurs -->
<div class="row">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-table me-2"></i>
                Liste des Utilisateurs
            </h6>
            
            <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Avatar</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Pays/Ville</th>
                            <th>Statut</th>
                            <th>Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>"></td>
                            <td>
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23<?php echo substr(md5($user['username']), 0, 6); ?>'/%3E%3Ctext x='50' y='60' text-anchor='middle' font-size='25' fill='white'%3E<?php echo strtoupper(substr($user['first_name'], 0, 1)); ?><?php echo strtoupper(substr($user['last_name'], 0, 1)); ?>%3C/text%3E%3C/svg%3E" 
                                     width="40" height="40" class="rounded-circle">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                <br>
                                <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                            </td>
                            <td>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                                <?php if ($user['email_verified']): ?>
                                    <i class="fas fa-check-circle text-success ms-1" title="Email vérifié"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($user['country'] ?? 'N/A'); ?></span>
                                <?php if ($user['city']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($user['city']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
                                <br>
                                <small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewUser(<?php echo $user['id']; ?>)" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="editUser(<?php echo $user['id']; ?>)" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] > 1): // Ne pas permettre la suppression de l'admin ?>
                                    <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
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
                        <a class="page-link" href="?tab=users&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun utilisateur trouvé</h5>
                <p class="text-muted">Aucun utilisateur ne correspond à vos critères de recherche.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    Ajouter le premier utilisateur
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Gestion des utilisateurs
function viewUser(userId) {
    // Charger les détails de l'utilisateur dans un modal
    fetch(`../api/user.php?action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showUserDetails(data.user);
            }
        });
}

function editUser(userId) {
    // Ouvrir le modal d'édition
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
    // Charger les données utilisateur
    loadUserForEdit(userId);
}

function deleteUser(userId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
        fetch(`../api/user.php?action=delete&id=${userId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la suppression: ' + data.error);
            }
        });
    }
}

// Sélection multiple
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Actions groupées
function bulkAction(action) {
    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Veuillez sélectionner au moins un utilisateur');
        return;
    }
    
    if (confirm(`Appliquer l'action "${action}" à ${selected.length} utilisateur(s) ?`)) {
        fetch('../api/user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'bulk',
                operation: action,
                users: selected
            })
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
</script>