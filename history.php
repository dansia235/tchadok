<?php
/**
 * Historique d'Écoute - Tchadok Platform
 * Affiche l'historique des morceaux écoutés par l'utilisateur
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=history');
    exit();
}

$pageTitle = 'Historique d\'Écoute';
$pageDescription = 'Votre historique d\'écoute musicale';

$user = getCurrentUser();

// Récupérer l'historique depuis la base de données
try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    $userId = $_SESSION['user_id'];

    // Pour l'instant, on simule l'historique (à implémenter plus tard avec une vraie table d'historique)
    $history = [];

} catch (Exception $e) {
    $history = [];
}

include 'includes/header.php';
?>

<div class="history-container">
    <!-- Header de la Page -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center">
                        <div class="header-icon-lg">
                            <i class="fas fa-history"></i>
                        </div>
                        <div>
                            <h1 class="mb-2">Historique d'Écoute</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-filter me-2"></i>Filtrer
                    </button>
                    <button class="btn btn-outline-danger ms-2">
                        <i class="fas fa-trash me-2"></i>Effacer
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenu -->
    <section class="history-content py-5">
        <div class="container">
            <?php if (empty($history)): ?>
                <!-- État Vide -->
                <div class="empty-state-large">
                    <div class="empty-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <h3 class="mb-3">Aucun historique d'écoute</h3>
                    <p class="text-muted mb-4">
                        Commencez à écouter de la musique tchadienne pour voir votre historique ici.
                    </p>
                    <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-compass me-2"></i>Découvrir de la Musique
                    </a>
                </div>
            <?php else: ?>
                <!-- Liste d'Historique -->
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                        <div class="history-item">
                            <div class="history-item-cover">
                                <img src="<?php echo htmlspecialchars($item['cover']); ?>" alt="Cover">
                                <div class="play-overlay">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                            <div class="history-item-info">
                                <h5 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($item['artist']); ?>
                                </p>
                            </div>
                            <div class="history-item-meta">
                                <span class="text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo htmlspecialchars($item['duration']); ?>
                                </span>
                            </div>
                            <div class="history-item-date">
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($item['played_at']); ?>
                                </span>
                            </div>
                            <div class="history-item-actions">
                                <button class="btn btn-sm btn-icon" title="Ajouter aux favoris">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="btn btn-sm btn-icon" title="Ajouter à une playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --gris-harmattan: #2C3E50;
}

.history-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding-bottom: 3rem;
}

/* Page Header */
.page-header {
    background: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-top: 80px;
}

.header-icon-lg {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 102, 204, 0.3);
}

.page-header h1 {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 2rem;
}

/* Empty State */
.empty-state-large {
    text-align: center;
    padding: 5rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.empty-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: white;
    box-shadow: 0 10px 40px rgba(0, 102, 204, 0.3);
}

.empty-state-large h3 {
    color: var(--gris-harmattan);
    font-weight: 600;
}

/* History List */
.history-list {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.history-item {
    display: flex;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
    gap: 1.5rem;
}

.history-item:last-child {
    border-bottom: none;
}

.history-item:hover {
    background: #f8f9fa;
}

.history-item-cover {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    cursor: pointer;
}

.history-item-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.history-item-cover:hover .play-overlay {
    opacity: 1;
}

.play-overlay i {
    color: white;
    font-size: 1.5rem;
}

.history-item-info {
    flex: 1;
    min-width: 0;
}

.history-item-info h5 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gris-harmattan);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.history-item-info p {
    font-size: 0.9rem;
    margin: 0;
}

.history-item-meta,
.history-item-date {
    flex-shrink: 0;
}

.history-item-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    border: none;
    background: #f0f0f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: var(--bleu-tchadien);
    color: white;
}

/* Responsive */
@media (max-width: 991px) {
    .page-header {
        margin-top: 70px;
    }

    .history-item-meta,
    .history-item-date {
        display: none;
    }
}

@media (max-width: 576px) {
    .history-item {
        padding: 1rem;
        gap: 1rem;
    }

    .history-item-cover {
        width: 50px;
        height: 50px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
