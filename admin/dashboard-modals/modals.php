<!-- Modal pour ajouter un utilisateur -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="addUserModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Ajouter un Nouvel Utilisateur
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="country" class="form-label">Pays</label>
                            <select class="form-select" id="country" name="country">
                                <option value="Cameroun">Cameroun</option>
                                <option value="France">France</option>
                                <option value="Canada">Canada</option>
                                <option value="Sénégal">Sénégal</option>
                                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="city" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>
                        <div class="col-md-6">
                            <label for="userType" class="form-label">Type d'utilisateur</label>
                            <select class="form-select" id="userType" name="user_type">
                                <option value="user">Utilisateur standard</option>
                                <option value="artist">Artiste</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailVerified" name="email_verified">
                                <label class="form-check-label" for="emailVerified">
                                    Email déjà vérifié
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="submitUserForm()">
                    <i class="fas fa-save me-2"></i>Créer l'utilisateur
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-warning text-dark">
                <h5 class="modal-title" id="editUserModalLabel">
                    <i class="fas fa-user-edit me-2"></i>Modifier l'Utilisateur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editUsername" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editPhone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="editCountry" class="form-label">Pays</label>
                            <select class="form-select" id="editCountry" name="country">
                                <option value="Cameroun">Cameroun</option>
                                <option value="France">France</option>
                                <option value="Canada">Canada</option>
                                <option value="Sénégal">Sénégal</option>
                                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="editEmailVerified" name="email_verified">
                                        <label class="form-check-label" for="editEmailVerified">
                                            Email vérifié
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                                        <label class="form-check-label" for="editIsActive">
                                            Compte actif
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="editIsPremium" name="is_premium">
                                        <label class="form-check-label" for="editIsPremium">
                                            Compte premium
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" onclick="submitEditUserForm()">
                    <i class="fas fa-save me-2"></i>Sauvegarder les modifications
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un artiste -->
<div class="modal fade" id="addArtistModal" tabindex="-1" aria-labelledby="addArtistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-warning text-dark">
                <h5 class="modal-title" id="addArtistModalLabel">
                    <i class="fas fa-microphone-alt me-2"></i>Ajouter un Nouvel Artiste
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addArtistForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="artistStageName" class="form-label">Nom de scène *</label>
                            <input type="text" class="form-control" id="artistStageName" name="stage_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="artistRealName" class="form-label">Nom réel</label>
                            <input type="text" class="form-control" id="artistRealName" name="real_name">
                        </div>
                        <div class="col-md-6">
                            <label for="artistGenres" class="form-label">Genres musicaux</label>
                            <select class="form-select" id="artistGenres" name="genres">
                                <option value="Afrobeat">Afrobeat</option>
                                <option value="Makossa">Makossa</option>
                                <option value="Bikutsi">Bikutsi</option>
                                <option value="Hip Hop">Hip Hop</option>
                                <option value="R&B">R&B</option>
                                <option value="Gospel">Gospel</option>
                                <option value="Jazz">Jazz</option>
                                <option value="Reggae">Reggae</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="artistCountry" class="form-label">Pays d'origine</label>
                            <select class="form-select" id="artistCountry" name="country">
                                <option value="Cameroun">Cameroun</option>
                                <option value="France">France</option>
                                <option value="Canada">Canada</option>
                                <option value="Sénégal">Sénégal</option>
                                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="artistBio" class="form-label">Biographie</label>
                            <textarea class="form-control" id="artistBio" name="bio" rows="3" placeholder="Biographie de l'artiste..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="artistWebsite" class="form-label">Site web</label>
                            <input type="url" class="form-control" id="artistWebsite" name="website" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label for="artistInstagram" class="form-label">Instagram</label>
                            <input type="text" class="form-control" id="artistInstagram" name="instagram" placeholder="@username">
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="artistVerified" name="verified">
                                        <label class="form-check-label" for="artistVerified">
                                            Artiste vérifié
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="artistFeatured" name="featured">
                                        <label class="form-check-label" for="artistFeatured">
                                            En vedette
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="artistActive" name="is_active" checked>
                                        <label class="form-check-label" for="artistActive">
                                            Compte actif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" onclick="submitArtistForm()">
                    <i class="fas fa-save me-2"></i>Créer l'artiste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un artiste -->
<div class="modal fade" id="editArtistModal" tabindex="-1" aria-labelledby="editArtistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title" id="editArtistModalLabel">
                    <i class="fas fa-edit me-2"></i>Modifier l'Artiste
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editArtistForm">
                    <input type="hidden" id="editArtistId" name="artist_id">
                    <!-- Mêmes champs que pour l'ajout, avec préfixe "edit" -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-info" onclick="submitEditArtistForm()">
                    <i class="fas fa-save me-2"></i>Sauvegarder
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une piste -->
<div class="modal fade" id="addTracksModal" tabindex="-1" aria-labelledby="addTracksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title" id="addTracksModalLabel">
                    <i class="fas fa-music me-2"></i>Ajouter une Nouvelle Piste
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTrackForm" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="trackTitle" class="form-label">Titre de la piste *</label>
                            <input type="text" class="form-control" id="trackTitle" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="trackArtist" class="form-label">Artiste *</label>
                            <select class="form-select" id="trackArtist" name="artist_id" required>
                                <option value="">Sélectionner un artiste</option>
                                <!-- Options chargées dynamiquement -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="trackAlbum" class="form-label">Album</label>
                            <select class="form-select" id="trackAlbum" name="album_id">
                                <option value="">Aucun album (Single)</option>
                                <!-- Options chargées dynamiquement -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="trackGenre" class="form-label">Genre</label>
                            <select class="form-select" id="trackGenre" name="genre">
                                <option value="Afrobeat">Afrobeat</option>
                                <option value="Makossa">Makossa</option>
                                <option value="Bikutsi">Bikutsi</option>
                                <option value="Hip Hop">Hip Hop</option>
                                <option value="R&B">R&B</option>
                                <option value="Gospel">Gospel</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="trackDuration" class="form-label">Durée (mm:ss)</label>
                            <input type="text" class="form-control" id="trackDuration" name="duration" placeholder="03:45">
                        </div>
                        <div class="col-md-4">
                            <label for="trackPrice" class="form-label">Prix (XAF)</label>
                            <input type="number" class="form-control" id="trackPrice" name="price" min="0" value="500">
                        </div>
                        <div class="col-md-4">
                            <label for="trackStatus" class="form-label">Statut</label>
                            <select class="form-select" id="trackStatus" name="status">
                                <option value="pending">En attente</option>
                                <option value="approved">Approuvée</option>
                                <option value="rejected">Rejetée</option>
                                <option value="draft">Brouillon</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="trackFile" class="form-label">Fichier audio *</label>
                            <input type="file" class="form-control" id="trackFile" name="audio_file" accept="audio/*" required>
                            <div class="form-text">Formats acceptés: MP3, WAV, FLAC (max 50MB)</div>
                        </div>
                        <div class="col-12">
                            <label for="trackCover" class="form-label">Pochette</label>
                            <input type="file" class="form-control" id="trackCover" name="cover_image" accept="image/*">
                            <div class="form-text">Formats acceptés: JPG, PNG (max 5MB)</div>
                        </div>
                        <div class="col-12">
                            <label for="trackDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="trackDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="trackFeatured" name="is_featured">
                                <label class="form-check-label" for="trackFeatured">
                                    Mettre en vedette
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-info" onclick="submitTrackForm()">
                    <i class="fas fa-upload me-2"></i>Ajouter la piste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un album -->
<div class="modal fade" id="addAlbumsModal" tabindex="-1" aria-labelledby="addAlbumsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-success text-white">
                <h5 class="modal-title" id="addAlbumsModalLabel">
                    <i class="fas fa-compact-disc me-2"></i>Ajouter un Nouvel Album
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAlbumForm" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="albumTitle" class="form-label">Titre de l'album *</label>
                            <input type="text" class="form-control" id="albumTitle" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="albumArtist" class="form-label">Artiste *</label>
                            <select class="form-select" id="albumArtist" name="artist_id" required>
                                <option value="">Sélectionner un artiste</option>
                                <!-- Options chargées dynamiquement -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="albumType" class="form-label">Type *</label>
                            <select class="form-select" id="albumType" name="type" required>
                                <option value="album">Album</option>
                                <option value="ep">EP</option>
                                <option value="single">Single</option>
                                <option value="maxi_single">Maxi Single</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="albumReleaseDate" class="form-label">Date de sortie</label>
                            <input type="date" class="form-control" id="albumReleaseDate" name="release_date">
                        </div>
                        <div class="col-md-6">
                            <label for="albumPrice" class="form-label">Prix (XAF)</label>
                            <input type="number" class="form-control" id="albumPrice" name="price" min="0" value="2000">
                        </div>
                        <div class="col-md-6">
                            <label for="albumStatus" class="form-label">Statut</label>
                            <select class="form-select" id="albumStatus" name="status">
                                <option value="pending">En attente</option>
                                <option value="approved">Approuvé</option>
                                <option value="rejected">Rejeté</option>
                                <option value="draft">Brouillon</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="albumCover" class="form-label">Pochette de l'album *</label>
                            <input type="file" class="form-control" id="albumCover" name="cover_image" accept="image/*" required>
                            <div class="form-text">Formats acceptés: JPG, PNG (max 5MB, recommandé: 1000x1000px)</div>
                        </div>
                        <div class="col-12">
                            <label for="albumDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="albumDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="albumFeatured" name="is_featured">
                                <label class="form-check-label" for="albumFeatured">
                                    Mettre en vedette
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="submitAlbumForm()">
                    <i class="fas fa-save me-2"></i>Créer l'album
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une transaction -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-success text-white">
                <h5 class="modal-title" id="addTransactionModalLabel">
                    <i class="fas fa-plus me-2"></i>Nouvelle Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTransactionForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="transactionUser" class="form-label">Utilisateur *</label>
                            <select class="form-select" id="transactionUser" name="user_id" required>
                                <option value="">Sélectionner un utilisateur</option>
                                <!-- Options chargées dynamiquement -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="transactionType" class="form-label">Type *</label>
                            <select class="form-select" id="transactionType" name="type" required>
                                <option value="purchase">Achat</option>
                                <option value="commission">Commission</option>
                                <option value="withdrawal">Retrait</option>
                                <option value="deposit">Dépôt</option>
                                <option value="refund">Remboursement</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="transactionAmount" class="form-label">Montant (XAF) *</label>
                            <input type="number" class="form-control" id="transactionAmount" name="amount" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="transactionCurrency" class="form-label">Devise</label>
                            <select class="form-select" id="transactionCurrency" name="currency">
                                <option value="XAF">XAF</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="transactionStatus" class="form-label">Statut *</label>
                            <select class="form-select" id="transactionStatus" name="status" required>
                                <option value="pending">En attente</option>
                                <option value="completed">Complétée</option>
                                <option value="failed">Échouée</option>
                                <option value="cancelled">Annulée</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="transactionMethod" class="form-label">Méthode de paiement</label>
                            <select class="form-select" id="transactionMethod" name="payment_method">
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Virement bancaire</option>
                                <option value="paypal">PayPal</option>
                                <option value="card">Carte bancaire</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="transactionReference" class="form-label">Référence</label>
                            <input type="text" class="form-control" id="transactionReference" name="reference" placeholder="Généré automatiquement si vide">
                        </div>
                        <div class="col-12">
                            <label for="transactionDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="transactionDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="submitTransactionForm()">
                    <i class="fas fa-save me-2"></i>Créer la transaction
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour actions groupées -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-labelledby="bulkActionsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-gradient-warning text-dark">
                <h5 class="modal-title" id="bulkActionsModalLabel">
                    <i class="fas fa-tasks me-2"></i>Actions Groupées
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Sélectionnez l'action à appliquer aux éléments sélectionnés :</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-success" onclick="bulkAction('activate')">
                        <i class="fas fa-check me-2"></i>Activer
                    </button>
                    <button class="btn btn-outline-warning" onclick="bulkAction('deactivate')">
                        <i class="fas fa-pause me-2"></i>Désactiver
                    </button>
                    <button class="btn btn-outline-info" onclick="bulkAction('verify')">
                        <i class="fas fa-shield-alt me-2"></i>Vérifier
                    </button>
                    <button class="btn btn-outline-danger" onclick="bulkAction('delete')">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour statistiques détaillées -->
<div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title" id="statsModalLabel">
                    <i class="fas fa-chart-line me-2"></i>Statistiques Détaillées
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="statsContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="exportStats()">
                    <i class="fas fa-download me-2"></i>Exporter
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-header.bg-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color)) !important;
}

.modal-header.bg-gradient-success {
    background: linear-gradient(135deg, var(--success-color), #20c997) !important;
}

.modal-header.bg-gradient-warning {
    background: linear-gradient(135deg, var(--warning-color), #ffc107) !important;
}

.modal-header.bg-gradient-info {
    background: linear-gradient(135deg, var(--info-color), #17a2b8) !important;
}

.modal-body {
    background-color: #f8f9fa;
}

.form-label {
    font-weight: 600;
    color: var(--secondary-color);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-group .btn {
    margin-right: 0.5rem;
}
</style>

<script>
// Fonctions pour soumettre les formulaires
function submitUserForm() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    
    fetch('../api/user.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

function submitEditUserForm() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    
    fetch('../api/user.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

function submitArtistForm() {
    const form = document.getElementById('addArtistForm');
    const formData = new FormData(form);
    
    fetch('../api/artist.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addArtistModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

function submitTrackForm() {
    const form = document.getElementById('addTrackForm');
    const formData = new FormData(form);
    
    fetch('../api/track.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addTracksModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

function submitAlbumForm() {
    const form = document.getElementById('addAlbumForm');
    const formData = new FormData(form);
    
    fetch('../api/album.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addAlbumsModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

function submitTransactionForm() {
    const form = document.getElementById('addTransactionForm');
    const formData = new FormData(form);
    
    fetch('../api/transaction.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addTransactionModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + data.error);
        }
    });
}

// Charger les options dynamiquement lors de l'ouverture des modaux
document.addEventListener('DOMContentLoaded', function() {
    // Charger les artistes pour les selects
    fetch('../api/artist.php?action=list')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const selects = document.querySelectorAll('#trackArtist, #albumArtist');
            selects.forEach(select => {
                data.artists.forEach(artist => {
                    const option = document.createElement('option');
                    option.value = artist.id;
                    option.textContent = artist.stage_name;
                    select.appendChild(option);
                });
            });
        }
    });
    
    // Charger les utilisateurs pour les selects
    fetch('../api/user.php?action=list')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('transactionUser');
            data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.first_name} ${user.last_name} (@${user.username})`;
                select.appendChild(option);
            });
        }
    });
});

function loadUserForEdit(userId) {
    fetch(`../api/user.php?action=get&id=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.user;
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFirstName').value = user.first_name;
            document.getElementById('editLastName').value = user.last_name;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editPhone').value = user.phone || '';
            document.getElementById('editCountry').value = user.country || '';
            document.getElementById('editEmailVerified').checked = user.email_verified == 1;
            document.getElementById('editIsActive').checked = user.is_active == 1;
            document.getElementById('editIsPremium').checked = user.is_premium == 1;
        }
    });
}
</script>