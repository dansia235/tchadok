<?php
// Gestion des utilisateurs
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $whereClause = $search ? "WHERE first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%'" : '';

    $users = $pdo->query("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users $whereClause")->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);

    $userStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'verified' => $pdo->query("SELECT COUNT(*) FROM users WHERE email_verified = 1")->fetchColumn(),
        'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];
}
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-text">Gestion des utilisateurs</h2>
            <p class="text-sm text-muted">Supervisez les comptes et leurs statuts.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-modal-open="bulkActionsModal">
                <i class="fas fa-layer-group"></i>
                Actions groupees
            </button>
            <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addUserModal">
                <i class="fas fa-user-plus"></i>
                Nouvel utilisateur
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Total</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($userStats['total'] ?? 0); ?></p>
            <p class="text-xs text-muted">Utilisateurs</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Actifs</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($userStats['active'] ?? 0); ?></p>
            <p class="text-xs text-muted">Comptes actifs</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Verifies</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($userStats['verified'] ?? 0); ?></p>
            <p class="text-xs text-muted">Emails confirmes</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Aujourd'hui</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($userStats['new_today'] ?? 0); ?></p>
            <p class="text-xs text-muted">Nouveaux comptes</p>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="users">
            <div class="flex flex-1 items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-2">
                <i class="fas fa-search text-muted"></i>
                <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" name="search" placeholder="Rechercher un utilisateur..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-text hover:bg-white/20">
                Rechercher
            </button>
        </form>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-text">Liste des utilisateurs</h3>
            <span class="text-xs text-muted"><?php echo number_format($totalUsers ?? 0); ?> comptes</span>
        </div>

        <?php if (!empty($users)): ?>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm text-muted">
                    <thead class="border-b border-white/10 text-xs uppercase text-muted">
                        <tr>
                            <th class="py-3">
                                <input type="checkbox" id="selectAllUsers" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                            </th>
                            <th class="py-3">Profil</th>
                            <th class="py-3">Email</th>
                            <th class="py-3">Localisation</th>
                            <th class="py-3">Statut</th>
                            <th class="py-3">Inscription</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-white/5">
                                <td class="py-3">
                                    <input type="checkbox" class="user-checkbox h-4 w-4 rounded border-white/20 bg-bg text-accent" value="<?php echo $user['id']; ?>">
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 place-items-center rounded-full bg-accent/20 text-sm font-semibold text-accent">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                            <p class="text-xs text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                        <?php if (!empty($user['email_verified'])): ?>
                                            <i class="fas fa-check-circle text-emerald-300"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-full border border-white/10 px-2 py-1 text-xs text-text">
                                        <?php echo htmlspecialchars($user['country'] ?? 'N/A'); ?>
                                    </span>
                                    <?php if (!empty($user['city'])): ?>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($user['city']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <?php if (!empty($user['is_active'])): ?>
                                        <span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-200">Actif</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full border border-rose-400/30 bg-rose-400/10 px-2.5 py-1 text-xs font-semibold text-rose-200">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-xs text-muted">
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                    <div><?php echo date('H:i', strtotime($user['created_at'])); ?></div>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="viewUser(<?php echo $user['id']; ?>)" title="Voir">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="editUser(<?php echo $user['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <?php if ($user['id'] > 1): ?>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-rose-200 hover:bg-rose-500/20" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        <?php endif; ?>
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
                           href="?tab=users&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                <i class="fas fa-users text-3xl text-muted"></i>
                <p class="mt-3 text-sm text-muted">Aucun utilisateur trouve.</p>
                <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addUserModal">
                    Ajouter le premier utilisateur
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function viewUser(userId) {
    fetch(`../api/user.php?action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var user = data.user;
                var bodyHtml = `
                    <div class="space-y-3 text-sm text-muted">
                        <div><span class="text-text font-semibold">${user.first_name} ${user.last_name}</span> (@${user.username})</div>
                        <div>Email: ${user.email}</div>
                        <div>Type: ${user.user_type || 'fan'}</div>
                        <div>Status: ${user.is_active == 1 ? 'Actif' : 'Inactif'}</div>
                    </div>
                `;
                showDetailModal('Profil utilisateur', bodyHtml, '');
            }
        });
}

function editUser(userId) {
    AdminModal.open('editUserModal');
    loadUserForEdit(userId);
}

function deleteUser(userId) {
    if (confirm('Supprimer cet utilisateur ?')) {
        fetch(`../api/user.php?action=delete&id=${userId}`, { method: 'DELETE' })
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

document.getElementById('selectAllUsers')?.addEventListener('change', function(event) {
    var checkboxes = document.querySelectorAll('.user-checkbox');
    var isChecked = event.target.checked;
    checkboxes.forEach(function(cb) {
        cb.checked = isChecked;
    });
});

function bulkAction(action) {
    var selected = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Veuillez selectionner au moins un utilisateur');
        return;
    }
    if (confirm(`Appliquer l'action "${action}" a ${selected.length} utilisateur(s) ?`)) {
        fetch('../api/user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
