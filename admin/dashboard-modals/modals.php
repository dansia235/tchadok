<?php
$inputClass = 'w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/40';
$labelClass = 'text-xs font-semibold text-muted';
$checkboxClass = 'h-4 w-4 rounded border-white/20 bg-bg text-accent';
?>

<!-- MODALS START -->
<!-- Modal: Ajouter utilisateur -->
<div id="addUserModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvel utilisateur</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addUserForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="<?php echo $labelClass; ?>">Prenom
                <input type="text" class="<?php echo $inputClass; ?>" id="firstName" name="first_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom
                <input type="text" class="<?php echo $inputClass; ?>" id="lastName" name="last_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom d'utilisateur
                <input type="text" class="<?php echo $inputClass; ?>" id="username" name="username" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Email
                <input type="email" class="<?php echo $inputClass; ?>" id="email" name="email" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Telephone
                <input type="tel" class="<?php echo $inputClass; ?>" id="phone" name="phone">
            </label>
            <label class="<?php echo $labelClass; ?>">Pays
                <input type="text" class="<?php echo $inputClass; ?>" id="country" name="country" placeholder="Tchad">
            </label>
            <label class="<?php echo $labelClass; ?>">Ville
                <input type="text" class="<?php echo $inputClass; ?>" id="city" name="city">
            </label>
            <label class="<?php echo $labelClass; ?>">Type
                <select class="<?php echo $inputClass; ?>" id="userType" name="user_type">
                    <option value="fan">Fan</option>
                    <option value="artist">Artiste</option>
                    <option value="admin">Administrateur</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="emailVerified" name="email_verified">
                Email deja verifie
            </label>
        </form>
        <p class="mt-4 text-xs text-muted">
            Mot de passe initial genere par la plateforme: <span class="font-semibold text-text">12345678</span>
        </p>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitUserForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Editer utilisateur -->
<div id="editUserModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Modifier utilisateur</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editUserForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" id="editUserId" name="user_id">
            <label class="<?php echo $labelClass; ?>">Prenom
                <input type="text" class="<?php echo $inputClass; ?>" id="editFirstName" name="first_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom
                <input type="text" class="<?php echo $inputClass; ?>" id="editLastName" name="last_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom d'utilisateur
                <input type="text" class="<?php echo $inputClass; ?>" id="editUsername" name="username" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Email
                <input type="email" class="<?php echo $inputClass; ?>" id="editEmail" name="email" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Telephone
                <input type="tel" class="<?php echo $inputClass; ?>" id="editPhone" name="phone">
            </label>
            <label class="<?php echo $labelClass; ?>">Pays
                <input type="text" class="<?php echo $inputClass; ?>" id="editCountry" name="country">
            </label>
            <label class="<?php echo $labelClass; ?>">Ville
                <input type="text" class="<?php echo $inputClass; ?>" id="editCity" name="city">
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="editEmailVerified" name="email_verified">
                Email verifie
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="editIsActive" name="is_active">
                Compte actif
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="editIsPremium" name="is_premium">
                Compte premium
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-black" onclick="submitEditUserForm()">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- MODALS END -->
<!-- Modal: Ajouter transaction -->
<div id="addTransactionModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvelle transaction</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addTransactionForm" class="mt-4 grid gap-4">
            <label class="<?php echo $labelClass; ?>">Utilisateur
                <select class="<?php echo $inputClass; ?>" id="transactionUser" name="user_id"></select>
            </label>
            <label class="<?php echo $labelClass; ?>">Montant (XAF)
                <input type="number" class="<?php echo $inputClass; ?>" id="transactionAmount" name="amount" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Type
                <select class="<?php echo $inputClass; ?>" id="transactionType" name="type">
                    <option value="purchase">Purchase</option>
                    <option value="commission">Commission</option>
                    <option value="withdrawal">Withdrawal</option>
                </select>
            </label>
            <label class="<?php echo $labelClass; ?>">Statut
                <select class="<?php echo $inputClass; ?>" id="transactionStatus" name="status">
                    <option value="pending">En attente</option>
                    <option value="completed">Completee</option>
                    <option value="failed">Echouee</option>
                </select>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitTransactionForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Actions groupees utilisateurs -->
<div id="bulkActionsModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-md rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Actions groupees</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-4 space-y-2 text-sm text-muted">
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkAction('activate')">Activer</button>
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkAction('deactivate')">Desactiver</button>
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkAction('verify')">Verifier</button>
            <button class="w-full rounded-full border border-rose-400/30 bg-rose-400/10 px-4 py-2 text-rose-200" onclick="bulkAction('delete')">Supprimer</button>
        </div>
    </div>
</div>

<!-- Modal: Actions groupees musique -->
<div id="bulkMusicModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-md rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Actions groupees musique</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-4 space-y-2 text-sm text-muted">
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkMusicAction('publish')">Publier</button>
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkMusicAction('archive')">Archiver</button>
            <button class="w-full rounded-full border border-rose-400/30 bg-rose-400/10 px-4 py-2 text-rose-200" onclick="bulkMusicAction('delete')">Supprimer</button>
        </div>
    </div>
</div>

<!-- Modal: Actions groupees paiements -->
<div id="bulkPaymentModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-md rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Actions groupees paiements</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-4 space-y-2 text-sm text-muted">
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkPaymentAction('approve')">Approuver</button>
            <button class="w-full rounded-full border border-white/10 px-4 py-2 text-text" onclick="bulkPaymentAction('reject')">Rejeter</button>
        </div>
    </div>
</div>

<!-- Modal generique -->
<div id="detailModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text" data-detail-title>Detail</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-4" data-detail-body></div>
        <div class="mt-6 flex justify-end gap-2" data-detail-actions></div>
    </div>
</div>

<!-- MODALS END -->
<!-- Modal: Ajouter playlist -->
<div id="addPlaylistModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvelle playlist</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addPlaylistForm" class="mt-4 grid gap-4">
            <label class="<?php echo $labelClass; ?>">Nom
                <input type="text" class="<?php echo $inputClass; ?>" id="playlistName" name="name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Utilisateur
                <select class="<?php echo $inputClass; ?>" id="playlistUser" name="user_id"></select>
            </label>
            <label class="<?php echo $labelClass; ?>">Description
                <textarea class="<?php echo $inputClass; ?> h-24" id="playlistDescription" name="description"></textarea>
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="playlistPublic" name="is_public" checked>
                Playlist publique
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitPlaylistForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Editer playlist -->
<div id="editPlaylistModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Modifier playlist</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editPlaylistForm" class="mt-4 grid gap-4">
            <input type="hidden" id="editPlaylistId" name="playlist_id">
            <label class="<?php echo $labelClass; ?>">Nom
                <input type="text" class="<?php echo $inputClass; ?>" id="editPlaylistName" name="name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Description
                <textarea class="<?php echo $inputClass; ?> h-24" id="editPlaylistDescription" name="description"></textarea>
            </label>
            <label class="flex items-center gap-2 text-xs text-muted">
                <input class="<?php echo $checkboxClass; ?>" type="checkbox" id="editPlaylistPublic" name="is_public">
                Playlist publique
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-black" onclick="submitEditPlaylistForm()">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- MODALS END -->
<!-- Modal: Ajouter album -->
<div id="addAlbumModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvel album</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addAlbumForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="<?php echo $labelClass; ?>">Titre
                <input type="text" class="<?php echo $inputClass; ?>" id="albumTitle" name="title" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Artiste
                <select class="<?php echo $inputClass; ?>" id="albumArtist" name="artist_id"></select>
            </label>
            <label class="<?php echo $labelClass; ?>">Type
                <select class="<?php echo $inputClass; ?>" id="albumType" name="type">
                    <option value="album">Album</option>
                    <option value="ep">EP</option>
                    <option value="single">Single</option>
                </select>
            </label>
            <label class="<?php echo $labelClass; ?>">Date de sortie
                <input type="date" class="<?php echo $inputClass; ?>" id="albumRelease" name="release_date">
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitAlbumForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Editer album -->
<div id="editAlbumModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Modifier album</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editAlbumForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" id="editAlbumId" name="album_id">
            <label class="<?php echo $labelClass; ?>">Titre
                <input type="text" class="<?php echo $inputClass; ?>" id="editAlbumTitle" name="title" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Type
                <select class="<?php echo $inputClass; ?>" id="editAlbumType" name="type">
                    <option value="album">Album</option>
                    <option value="ep">EP</option>
                    <option value="single">Single</option>
                </select>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-black" onclick="submitEditAlbumForm()">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- MODALS END -->
<!-- Modal: Ajouter piste -->
<div id="addTrackModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvelle piste</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addTrackForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="<?php echo $labelClass; ?>">Titre
                <input type="text" class="<?php echo $inputClass; ?>" id="trackTitle" name="title" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Artiste
                <select class="<?php echo $inputClass; ?>" id="trackArtist" name="artist_id"></select>
            </label>
            <label class="<?php echo $labelClass; ?>">Album
                <select class="<?php echo $inputClass; ?>" id="trackAlbum" name="album_id">
                    <option value="">Single</option>
                </select>
            </label>
            <label class="<?php echo $labelClass; ?>">Duree (sec)
                <input type="number" class="<?php echo $inputClass; ?>" id="trackDuration" name="duration" value="180">
            </label>
            <label class="<?php echo $labelClass; ?>">Statut
                <select class="<?php echo $inputClass; ?>" id="trackStatus" name="status">
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approuve</option>
                </select>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitTrackForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Editer piste -->
<div id="editTrackModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Modifier piste</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editTrackForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" id="editTrackId" name="track_id">
            <label class="<?php echo $labelClass; ?>">Titre
                <input type="text" class="<?php echo $inputClass; ?>" id="editTrackTitle" name="title" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Statut
                <select class="<?php echo $inputClass; ?>" id="editTrackStatus" name="status">
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approuve</option>
                </select>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-black" onclick="submitEditTrackForm()">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- MODALS END -->
<!-- Modal: Ajouter artiste -->
<div id="addArtistModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Nouvel artiste</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addArtistForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="<?php echo $labelClass; ?>">Nom de scene
                <input type="text" class="<?php echo $inputClass; ?>" id="artistStageName" name="stage_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom reel
                <input type="text" class="<?php echo $inputClass; ?>" id="artistRealName" name="real_name">
            </label>
            <label class="<?php echo $labelClass; ?>">Genres
                <input type="text" class="<?php echo $inputClass; ?>" id="artistGenres" name="genres" placeholder="Afrobeat, Rap...">
            </label>
            <label class="<?php echo $labelClass; ?>">Pays
                <input type="text" class="<?php echo $inputClass; ?>" id="artistCountry" name="country">
            </label>
            <label class="<?php echo $labelClass; ?> md:col-span-2">Bio
                <textarea class="<?php echo $inputClass; ?> h-24" id="artistBio" name="bio"></textarea>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" onclick="submitArtistForm()">Creer</button>
        </div>
    </div>
</div>

<!-- Modal: Editer artiste -->
<div id="editArtistModal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-surface p-6 shadow-elev-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text">Modifier artiste</h3>
            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editArtistForm" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" id="editArtistId" name="artist_id">
            <label class="<?php echo $labelClass; ?>">Nom de scene
                <input type="text" class="<?php echo $inputClass; ?>" id="editArtistStageName" name="stage_name" required>
            </label>
            <label class="<?php echo $labelClass; ?>">Nom reel
                <input type="text" class="<?php echo $inputClass; ?>" id="editArtistRealName" name="real_name">
            </label>
            <label class="<?php echo $labelClass; ?>">Genres
                <input type="text" class="<?php echo $inputClass; ?>" id="editArtistGenres" name="genres">
            </label>
            <label class="<?php echo $labelClass; ?>">Pays
                <input type="text" class="<?php echo $inputClass; ?>" id="editArtistCountry" name="country">
            </label>
            <label class="<?php echo $labelClass; ?> md:col-span-2">Bio
                <textarea class="<?php echo $inputClass; ?> h-24" id="editArtistBio" name="bio"></textarea>
            </label>
        </form>
        <div class="mt-6 flex justify-end gap-2">
            <button class="rounded-full border border-white/10 px-4 py-2 text-xs text-muted" data-modal-close>Annuler</button>
            <button type="button" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-black" onclick="submitEditArtistForm()">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- MODALS END -->
