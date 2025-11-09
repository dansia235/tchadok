<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Gérer la Radio';
include 'includes/header.php';
?>
<div class="container mt-5 pt-5">
    <h2 class="mb-4"><i class="fas fa-broadcast-tower me-2"></i>Gestion de la Radio Live</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white"><i class="fas fa-play-circle me-2"></i>Configuration Radio</div>
                <div class="card-body">
                    <form>
                        <div class="mb-3"><label>URL du Stream</label><input type="url" class="form-control" placeholder="https://stream.radio.com/..."></div>
                        <div class="mb-3"><label>Nom de l'Émission</label><input type="text" class="form-control" value="Tchadok Radio Live"></div>
                        <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="active" checked><label class="form-check-label" for="active">Radio Active</label></div>
                        <button class="btn btn-success"><i class="fas fa-save me-2"></i>Sauvegarder</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white"><i class="fas fa-list me-2"></i>Playlist En Direct</div>
                <div class="card-body">
                    <p class="text-muted">Chansons actuellement dans la rotation radio</p>
                    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Fonctionnalité en cours de développement</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
