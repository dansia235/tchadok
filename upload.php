<?php
/**
 * Interface d'upload pour artistes - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un artiste
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=upload');
    exit();
}

// Vérifier le statut artiste (simulation)
$isArtist = $_SESSION['user_type'] ?? 'listener' === 'artist';
if (!$isArtist) {
    $_SESSION['error'] = 'Seuls les artistes peuvent uploader des titres.';
    header('Location: ' . SITE_URL . '/artist-signup.php');
    exit();
}

$pageTitle = 'Upload de Musique';
$pageDescription = 'Partagez votre musique avec le monde';

include 'includes/header.php';
?>

<div class="upload-page">
    <!-- Hero Section -->
    <section class="upload-hero py-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row align-items-center text-white">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="fas fa-cloud-upload-alt me-3"></i>
                        Upload de Musique
                    </h1>
                    <p class="lead mb-0">
                        Partagez votre talent avec des milliers d'auditeurs tchadiens
                    </p>
                </div>
                <div class="col-lg-4 text-end">
                    <div class="upload-stats">
                        <div class="stat-item">
                            <h3><?php echo rand(15, 35); ?></h3>
                            <p>Titres uploadés</p>
                        </div>
                        <div class="stat-item">
                            <h3><?php echo formatNumber(rand(10000, 50000)); ?></h3>
                            <p>Écoutes totales</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upload Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Upload Steps -->
                    <div class="upload-steps mb-5">
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Informations</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Fichiers</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Finalisation</div>
                        </div>
                    </div>

                    <form id="uploadForm" method="POST" enctype="multipart/form-data" class="upload-form">
                        <!-- Étape 1: Informations -->
                        <div class="form-step active" data-step="1">
                            <h3 class="mb-4">Informations du titre</h3>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre de la chanson *</label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       placeholder="Ex: Sahara Beat" maxlength="100">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="artist" class="form-label">Nom d'artiste *</label>
                                    <input type="text" class="form-control" id="artist" name="artist" required
                                           value="<?php echo htmlspecialchars($_SESSION['artist_name'] ?? ''); ?>"
                                           placeholder="Votre nom d'artiste">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="featuring" class="form-label">Featuring (optionnel)</label>
                                    <input type="text" class="form-control" id="featuring" name="featuring"
                                           placeholder="Ex: Artiste 1, Artiste 2">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="genre" class="form-label">Genre *</label>
                                    <select class="form-select" id="genre" name="genre" required>
                                        <option value="">Sélectionnez un genre</option>
                                        <option value="afrobeat">Afrobeat</option>
                                        <option value="hip-hop">Hip-Hop</option>
                                        <option value="gospel">Gospel</option>
                                        <option value="traditionnel">Traditionnel</option>
                                        <option value="reggae">Reggae</option>
                                        <option value="rnb">R&B</option>
                                        <option value="folk">Folk</option>
                                        <option value="zouk">Zouk</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="album" class="form-label">Album (optionnel)</label>
                                    <input type="text" class="form-control" id="album" name="album"
                                           placeholder="Nom de l'album">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"
                                          placeholder="Parlez de votre titre, de son inspiration..." maxlength="1000"></textarea>
                                <small class="text-muted">0/1000 caractères</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="release_date" class="form-label">Date de sortie</label>
                                    <input type="date" class="form-control" id="release_date" name="release_date"
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="language" class="form-label">Langue principale</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="fr">Français</option>
                                        <option value="ar">Arabe</option>
                                        <option value="sara">Sara</option>
                                        <option value="kanembou">Kanembou</option>
                                        <option value="other">Autre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="explicit" name="explicit">
                                <label class="form-check-label" for="explicit">
                                    Contenu explicite (paroles inappropriées pour les mineurs)
                                </label>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary btn-lg" onclick="nextStep()">
                                    Suivant
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Étape 2: Fichiers -->
                        <div class="form-step" data-step="2">
                            <h3 class="mb-4">Upload des fichiers</h3>

                            <!-- Audio File -->
                            <div class="file-upload-zone mb-4" id="audioDropZone">
                                <input type="file" id="audioFile" name="audio_file" accept="audio/*" required hidden>
                                <div class="upload-icon">
                                    <i class="fas fa-music fa-3x text-primary"></i>
                                </div>
                                <h5>Fichier Audio *</h5>
                                <p class="text-muted">Glissez-déposez ou cliquez pour sélectionner</p>
                                <small class="text-muted">MP3, WAV, M4A • Max 50MB • Haute qualité recommandée</small>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="btn btn-sm btn-link" onclick="removeFile('audio')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Cover Image -->
                            <div class="file-upload-zone mb-4" id="coverDropZone">
                                <input type="file" id="coverFile" name="cover_image" accept="image/*" hidden>
                                <div class="upload-icon">
                                    <i class="fas fa-image fa-3x text-info"></i>
                                </div>
                                <h5>Image de Couverture</h5>
                                <p class="text-muted">Glissez-déposez ou cliquez pour sélectionner</p>
                                <small class="text-muted">JPG, PNG • Min 500x500px • Max 5MB</small>
                                <div class="file-info" style="display: none;">
                                    <img class="preview-image" alt="Preview">
                                    <button type="button" class="btn btn-sm btn-link" onclick="removeFile('cover')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Lyrics (optional) -->
                            <div class="mb-4">
                                <label for="lyrics" class="form-label">Paroles (optionnel)</label>
                                <textarea class="form-control" id="lyrics" name="lyrics" rows="6"
                                          placeholder="Ajoutez les paroles de votre chanson..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary btn-lg" onclick="previousStep()">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Précédent
                                </button>
                                <button type="button" class="btn btn-primary btn-lg" onclick="nextStep()">
                                    Suivant
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Étape 3: Finalisation -->
                        <div class="form-step" data-step="3">
                            <h3 class="mb-4">Finalisation</h3>

                            <!-- Pricing & Distribution -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Options de distribution</h5>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="distribution" id="free" value="free" checked>
                                        <label class="form-check-label" for="free">
                                            <strong>Gratuit</strong> - Accessible à tous les utilisateurs
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="distribution" id="premium" value="premium">
                                        <label class="form-check-label" for="premium">
                                            <strong>Premium uniquement</strong> - Réservé aux abonnés Premium
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="distribution" id="paid" value="paid">
                                        <label class="form-check-label" for="paid">
                                            <strong>Payant</strong> - Vente à l'unité
                                        </label>
                                    </div>
                                    
                                    <div class="price-input mt-3" style="display: none;">
                                        <label for="price" class="form-label">Prix (FCFA)</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               min="500" max="10000" step="500" placeholder="Ex: 1000">
                                        <small class="text-muted">Prix entre 500 et 10,000 FCFA</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Rights & Permissions -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Droits et permissions</h5>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            J'accepte les <a href="<?php echo SITE_URL; ?>/terms" target="_blank">conditions d'utilisation</a> *
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="copyright" name="copyright" required>
                                        <label class="form-check-label" for="copyright">
                                            Je confirme être le propriétaire des droits de cette œuvre *
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" checked>
                                        <label class="form-check-label" for="newsletter">
                                            Recevoir des notifications sur les performances de mon titre
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="upload-summary mb-4">
                                <h5>Résumé de l'upload</h5>
                                <div class="summary-content">
                                    <p><strong>Titre:</strong> <span id="summaryTitle">-</span></p>
                                    <p><strong>Artiste:</strong> <span id="summaryArtist">-</span></p>
                                    <p><strong>Genre:</strong> <span id="summaryGenre">-</span></p>
                                    <p><strong>Distribution:</strong> <span id="summaryDistribution">Gratuit</span></p>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary btn-lg" onclick="previousStep()">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Précédent
                                </button>
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>
                                    Publier le titre
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Tips Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="text-center mb-5">Conseils pour un upload réussi</h3>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-music text-primary"></i>
                        </div>
                        <h5>Qualité Audio</h5>
                        <p>Utilisez des fichiers audio de haute qualité (320kbps minimum) pour une meilleure expérience d'écoute.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-image text-info"></i>
                        </div>
                        <h5>Image Attractive</h5>
                        <p>Une belle pochette attire plus d'auditeurs. Utilisez des images de haute résolution et visuellement accrocheuses.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-tags text-success"></i>
                        </div>
                        <h5>Métadonnées Complètes</h5>
                        <p>Remplissez toutes les informations pour améliorer la découvrabilité de votre musique.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Upload Progress Modal -->
<div class="modal fade" id="uploadProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div class="upload-progress-icon mb-4">
                    <i class="fas fa-cloud-upload-alt fa-4x text-primary"></i>
                </div>
                <h4 class="mb-3">Upload en cours...</h4>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%">0%</div>
                </div>
                <p class="text-muted mb-0">Veuillez ne pas fermer cette fenêtre</p>
            </div>
        </div>
    </div>
</div>

<style>
.upload-hero {
    position: relative;
    overflow: hidden;
}

.upload-stats {
    display: flex;
    gap: 2rem;
    justify-content: flex-end;
}

.upload-stats .stat-item {
    text-align: center;
}

.upload-stats h3 {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.upload-stats p {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 0;
}

.upload-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.upload-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 50px;
    right: 50px;
    height: 2px;
    background: #e0e0e0;
    z-index: 0;
}

.step {
    position: relative;
    text-align: center;
    z-index: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0e0e0;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-weight: bold;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: #007bff;
    color: white;
}

.step.completed .step-number {
    background: #28a745;
    color: white;
}

.step-label {
    font-size: 0.9rem;
    color: #666;
}

.step.active .step-label {
    color: #007bff;
    font-weight: 600;
}

.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

.file-upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.file-upload-zone:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.file-upload-zone.dragover {
    border-color: #007bff;
    background: #e7f3ff;
}

.file-upload-zone.has-file {
    border-style: solid;
    border-color: #28a745;
    background: #f8fff9;
}

.upload-icon {
    margin-bottom: 1rem;
}

.file-info {
    margin-top: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.file-info .file-name {
    flex: 1;
    text-align: left;
    font-weight: 500;
}

.preview-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}

.price-input {
    max-width: 300px;
}

.upload-summary {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
}

.tip-card {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 10px;
    height: 100%;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.tip-card:hover {
    transform: translateY(-5px);
}

.tip-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.upload-progress-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

@media (max-width: 768px) {
    .upload-stats {
        justify-content: center;
        gap: 3rem;
        margin-top: 2rem;
    }
    
    .upload-steps::before {
        display: none;
    }
    
    .file-upload-zone {
        padding: 2rem;
    }
}
</style>

<script>
let currentStep = 1;
const totalSteps = 3;

// Navigation entre étapes
function nextStep() {
    if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

function showStep(step) {
    // Masquer l'étape actuelle
    document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    
    // Marquer comme complété si on avance
    if (step > currentStep) {
        document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
    }
    
    // Afficher la nouvelle étape
    currentStep = step;
    document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('active');
    
    // Mettre à jour le résumé si on est à la dernière étape
    if (currentStep === 3) {
        updateSummary();
    }
}

// Validation des étapes
function validateCurrentStep() {
    const form = document.getElementById('uploadForm');
    const currentFields = form.querySelectorAll(`.form-step[data-step="${currentStep}"] [required]`);
    
    for (let field of currentFields) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            field.focus();
            return false;
        }
        field.classList.remove('is-invalid');
    }
    
    return true;
}

// Mise à jour du résumé
function updateSummary() {
    document.getElementById('summaryTitle').textContent = document.getElementById('title').value || '-';
    document.getElementById('summaryArtist').textContent = document.getElementById('artist').value || '-';
    document.getElementById('summaryGenre').textContent = document.getElementById('genre').options[document.getElementById('genre').selectedIndex].text || '-';
    
    const distribution = document.querySelector('input[name="distribution"]:checked');
    let distText = 'Gratuit';
    if (distribution) {
        if (distribution.value === 'premium') distText = 'Premium uniquement';
        else if (distribution.value === 'paid') distText = `Payant (${document.getElementById('price').value || '0'} FCFA)`;
    }
    document.getElementById('summaryDistribution').textContent = distText;
}

// Gestion des fichiers
document.addEventListener('DOMContentLoaded', function() {
    // Audio file handling
    const audioDropZone = document.getElementById('audioDropZone');
    const audioFileInput = document.getElementById('audioFile');
    
    audioDropZone.addEventListener('click', () => audioFileInput.click());
    
    audioDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        audioDropZone.classList.add('dragover');
    });
    
    audioDropZone.addEventListener('dragleave', () => {
        audioDropZone.classList.remove('dragover');
    });
    
    audioDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        audioDropZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('audio/')) {
            handleAudioFile(files[0]);
        }
    });
    
    audioFileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleAudioFile(e.target.files[0]);
        }
    });
    
    // Cover image handling
    const coverDropZone = document.getElementById('coverDropZone');
    const coverFileInput = document.getElementById('coverFile');
    
    coverDropZone.addEventListener('click', () => coverFileInput.click());
    
    coverDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        coverDropZone.classList.add('dragover');
    });
    
    coverDropZone.addEventListener('dragleave', () => {
        coverDropZone.classList.remove('dragover');
    });
    
    coverDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        coverDropZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            handleCoverFile(files[0]);
        }
    });
    
    coverFileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleCoverFile(e.target.files[0]);
        }
    });
    
    // Distribution type change
    document.querySelectorAll('input[name="distribution"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const priceInput = document.querySelector('.price-input');
            if (e.target.value === 'paid') {
                priceInput.style.display = 'block';
                document.getElementById('price').required = true;
            } else {
                priceInput.style.display = 'none';
                document.getElementById('price').required = false;
            }
        });
    });
    
    // Character counter for description
    const description = document.getElementById('description');
    const charCounter = description.nextElementSibling;
    description.addEventListener('input', () => {
        charCounter.textContent = `${description.value.length}/1000 caractères`;
    });
    
    // Form submission
    document.getElementById('uploadForm').addEventListener('submit', handleUpload);
});

function handleAudioFile(file) {
    const audioDropZone = document.getElementById('audioDropZone');
    const fileInfo = audioDropZone.querySelector('.file-info');
    const fileName = fileInfo.querySelector('.file-name');
    
    // Validation
    if (file.size > 50 * 1024 * 1024) { // 50MB
        alert('Le fichier audio ne doit pas dépasser 50MB');
        return;
    }
    
    fileName.textContent = file.name;
    fileInfo.style.display = 'flex';
    audioDropZone.classList.add('has-file');
    audioDropZone.querySelector('.upload-icon').style.display = 'none';
    audioDropZone.querySelector('h5').style.display = 'none';
    audioDropZone.querySelector('p').style.display = 'none';
    audioDropZone.querySelector('small').style.display = 'none';
}

function handleCoverFile(file) {
    const coverDropZone = document.getElementById('coverDropZone');
    const fileInfo = coverDropZone.querySelector('.file-info');
    const preview = fileInfo.querySelector('.preview-image');
    
    // Validation
    if (file.size > 5 * 1024 * 1024) { // 5MB
        alert('L\'image ne doit pas dépasser 5MB');
        return;
    }
    
    // Preview
    const reader = new FileReader();
    reader.onload = (e) => {
        preview.src = e.target.result;
        fileInfo.style.display = 'flex';
        coverDropZone.classList.add('has-file');
        coverDropZone.querySelector('.upload-icon').style.display = 'none';
        coverDropZone.querySelector('h5').style.display = 'none';
        coverDropZone.querySelector('p').style.display = 'none';
        coverDropZone.querySelector('small').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeFile(type) {
    if (type === 'audio') {
        const audioDropZone = document.getElementById('audioDropZone');
        const fileInput = document.getElementById('audioFile');
        
        fileInput.value = '';
        audioDropZone.classList.remove('has-file');
        audioDropZone.querySelector('.file-info').style.display = 'none';
        audioDropZone.querySelector('.upload-icon').style.display = 'block';
        audioDropZone.querySelector('h5').style.display = 'block';
        audioDropZone.querySelector('p').style.display = 'block';
        audioDropZone.querySelector('small').style.display = 'block';
    } else if (type === 'cover') {
        const coverDropZone = document.getElementById('coverDropZone');
        const fileInput = document.getElementById('coverFile');
        
        fileInput.value = '';
        coverDropZone.classList.remove('has-file');
        coverDropZone.querySelector('.file-info').style.display = 'none';
        coverDropZone.querySelector('.upload-icon').style.display = 'block';
        coverDropZone.querySelector('h5').style.display = 'block';
        coverDropZone.querySelector('p').style.display = 'block';
        coverDropZone.querySelector('small').style.display = 'block';
    }
}

function handleUpload(e) {
    e.preventDefault();
    
    if (!validateCurrentStep()) {
        return;
    }
    
    // Afficher le modal de progression
    const modal = new bootstrap.Modal(document.getElementById('uploadProgressModal'));
    modal.show();
    
    const progressBar = document.querySelector('.progress-bar');
    let progress = 0;
    
    // Simulation de l'upload
    const uploadInterval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        progressBar.style.width = progress + '%';
        progressBar.textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(uploadInterval);
            
            // Succès après 1 seconde
            setTimeout(() => {
                modal.hide();
                
                // Notification de succès
                const notification = document.createElement('div');
                notification.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                        <div>
                            <div><strong>Upload réussi !</strong></div>
                            <small>Votre titre est maintenant en ligne</small>
                        </div>
                    </div>
                `;
                notification.style.cssText = `
                    position: fixed; 
                    top: 20px; 
                    right: 20px; 
                    background: #28a745; 
                    color: white; 
                    padding: 15px 20px; 
                    border-radius: 8px; 
                    z-index: 10000;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    max-width: 300px;
                `;
                document.body.appendChild(notification);
                
                // Redirection après 3 secondes
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>/artist-dashboard.php';
                }, 3000);
            }, 1000);
        }
    }, 500);
}
</script>

<?php include 'includes/footer.php'; ?>