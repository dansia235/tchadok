(function () {
    'use strict';

    var apiBase = window.ADMIN_API_BASE || '../api';

    function apiUrl(path) {
        return apiBase.replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
    }

    var sidebar = document.querySelector('[data-admin-sidebar]');
    var sidebarToggle = document.querySelector('[data-admin-toggle]');
    var sidebarBackdrop = document.querySelector('[data-admin-backdrop]');

    function openSidebar() {
        if (!sidebar) {
            return;
        }
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        if (sidebarBackdrop) {
            sidebarBackdrop.classList.remove('hidden');
        }
    }

    function closeSidebar() {
        if (!sidebar) {
            return;
        }
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        if (sidebarBackdrop) {
            sidebarBackdrop.classList.add('hidden');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (!sidebar) {
                return;
            }
            var isOpen = sidebar.classList.contains('translate-x-0');
            if (isOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    function resolveModal(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.getElementById(target) || document.querySelector('[data-modal="' + target + '"]');
        }
        return target;
    }

    function openModal(target) {
        var modal = resolveModal(target);
        if (!modal) {
            return;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(target) {
        var modal = resolveModal(target);
        if (!modal) {
            return;
        }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function closeAllModals() {
        document.querySelectorAll('[data-modal]').forEach(function (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
        });
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var target = trigger.getAttribute('data-modal-open');
            openModal(target);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = trigger.closest('[data-modal]');
            closeModal(modal);
        });
    });

    document.querySelectorAll('[data-modal]').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    document.addEventListener('click', function (event) {
        var closeButton = event.target.closest('[data-alert-close]');
        if (!closeButton) {
            return;
        }
        var alert = closeButton.closest('.alert');
        if (alert) {
            alert.remove();
        }
    });

    function showDetailModal(title, bodyHtml, actionsHtml) {
        var modal = resolveModal('detailModal');
        if (!modal) {
            return;
        }
        var titleEl = modal.querySelector('[data-detail-title]');
        var bodyEl = modal.querySelector('[data-detail-body]');
        var actionsEl = modal.querySelector('[data-detail-actions]');

        if (titleEl) {
            titleEl.textContent = title || 'Detail';
        }
        if (bodyEl) {
            bodyEl.innerHTML = bodyHtml || '';
        }
        if (actionsEl) {
            actionsEl.innerHTML = actionsHtml || '';
        }

        openModal(modal);
    }

    window.AdminModal = {
        open: openModal,
        close: closeModal,
        closeAll: closeAllModals
    };

    window.showDetailModal = showDetailModal;

    function submitForm(url, formId, modalId) {
        var form = document.getElementById(formId);
        if (!form) {
            return;
        }
        var formData = new FormData(form);
        fetch(url, { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    if (modalId) {
                        closeModal(modalId);
                    }
                    window.location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            });
    }

    window.submitUserForm = function () {
        submitForm(apiUrl('user.php?action=create'), 'addUserForm', 'addUserModal');
    };
    window.submitEditUserForm = function () {
        submitForm(apiUrl('user.php?action=update'), 'editUserForm', 'editUserModal');
    };
    window.submitArtistForm = function () {
        submitForm(apiUrl('artist.php?action=create'), 'addArtistForm', 'addArtistModal');
    };
    window.submitEditArtistForm = function () {
        submitForm(apiUrl('artist.php?action=update'), 'editArtistForm', 'editArtistModal');
    };
    window.submitTrackForm = function () {
        submitForm(apiUrl('track.php?action=create'), 'addTrackForm', 'addTrackModal');
    };
    window.submitEditTrackForm = function () {
        submitForm(apiUrl('track.php?action=update'), 'editTrackForm', 'editTrackModal');
    };
    window.submitAlbumForm = function () {
        submitForm(apiUrl('album.php?action=create'), 'addAlbumForm', 'addAlbumModal');
    };
    window.submitEditAlbumForm = function () {
        submitForm(apiUrl('album.php?action=update'), 'editAlbumForm', 'editAlbumModal');
    };
    window.submitPlaylistForm = function () {
        submitForm(apiUrl('playlist.php?action=create'), 'addPlaylistForm', 'addPlaylistModal');
    };
    window.submitEditPlaylistForm = function () {
        submitForm(apiUrl('playlist.php?action=update'), 'editPlaylistForm', 'editPlaylistModal');
    };
    window.submitTransactionForm = function () {
        submitForm(apiUrl('transaction.php?action=create'), 'addTransactionForm', 'addTransactionModal');
    };

    window.bulkMusicAction = function (action) {
        alert('Action musique: ' + action + ' (a implementer)');
    };
    window.bulkPaymentAction = function (action) {
        alert('Action paiement: ' + action + ' (a implementer)');
    };

    function loadUserForEdit(userId) {
        fetch(apiUrl('user.php?action=get&id=' + userId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var user = data.user;
                var fields = {
                    editUserId: user.id,
                    editFirstName: user.first_name,
                    editLastName: user.last_name,
                    editUsername: user.username,
                    editEmail: user.email,
                    editPhone: user.phone || '',
                    editCountry: user.country || '',
                    editCity: user.city || ''
                };
                Object.keys(fields).forEach(function (key) {
                    var el = document.getElementById(key);
                    if (el) {
                        el.value = fields[key];
                    }
                });
                var emailVerified = document.getElementById('editEmailVerified');
                if (emailVerified) {
                    emailVerified.checked = user.email_verified == 1;
                }
                var isActive = document.getElementById('editIsActive');
                if (isActive) {
                    isActive.checked = user.is_active == 1;
                }
                var isPremium = document.getElementById('editIsPremium');
                if (isPremium) {
                    isPremium.checked = user.is_premium == 1;
                }
            });
    }

    function loadArtistForEdit(artistId) {
        fetch(apiUrl('artist.php?action=get&id=' + artistId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var artist = data.artist;
                var fields = {
                    editArtistId: artist.id,
                    editArtistStageName: artist.stage_name,
                    editArtistRealName: artist.real_name || '',
                    editArtistGenres: artist.genres || '',
                    editArtistCountry: artist.country || '',
                    editArtistBio: artist.bio || ''
                };
                Object.keys(fields).forEach(function (key) {
                    var el = document.getElementById(key);
                    if (el) {
                        el.value = fields[key];
                    }
                });
            });
    }

    function loadTrackForEdit(trackId) {
        fetch(apiUrl('track.php?action=get&id=' + trackId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var track = data.track;
                var title = document.getElementById('editTrackTitle');
                var idField = document.getElementById('editTrackId');
                var statusField = document.getElementById('editTrackStatus');
                if (idField) {
                    idField.value = track.id;
                }
                if (title) {
                    title.value = track.title;
                }
                if (statusField) {
                    statusField.value = track.status || 'draft';
                }
            });
    }

    function loadAlbumForEdit(albumId) {
        fetch(apiUrl('album.php?action=get&id=' + albumId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var album = data.album;
                var idField = document.getElementById('editAlbumId');
                var titleField = document.getElementById('editAlbumTitle');
                var typeField = document.getElementById('editAlbumType');
                if (idField) {
                    idField.value = album.id;
                }
                if (titleField) {
                    titleField.value = album.title;
                }
                if (typeField) {
                    typeField.value = album.type || 'album';
                }
            });
    }

    function loadPlaylistForEdit(playlistId) {
        fetch(apiUrl('playlist.php?action=get&id=' + playlistId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var playlist = data.playlist;
                var idField = document.getElementById('editPlaylistId');
                var nameField = document.getElementById('editPlaylistName');
                var descField = document.getElementById('editPlaylistDescription');
                var publicField = document.getElementById('editPlaylistPublic');
                if (idField) {
                    idField.value = playlist.id;
                }
                if (nameField) {
                    nameField.value = playlist.name;
                }
                if (descField) {
                    descField.value = playlist.description || '';
                }
                if (publicField) {
                    publicField.checked = playlist.is_public == 1;
                }
            });
    }

    window.loadUserForEdit = loadUserForEdit;
    window.loadArtistForEdit = loadArtistForEdit;
    window.loadTrackForEdit = loadTrackForEdit;
    window.loadAlbumForEdit = loadAlbumForEdit;
    window.loadPlaylistForEdit = loadPlaylistForEdit;

    function initSelectOptions() {
        fetch(apiUrl('artist.php?action=list'))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var selects = document.querySelectorAll('#trackArtist, #albumArtist');
                selects.forEach(function (select) {
                    data.artists.forEach(function (artist) {
                        var option = document.createElement('option');
                        option.value = artist.id;
                        option.textContent = artist.stage_name;
                        select.appendChild(option);
                    });
                });
            });

        fetch(apiUrl('user.php?action=list'))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                var select = document.getElementById('transactionUser');
                var playlistSelect = document.getElementById('playlistUser');
                data.users.forEach(function (user) {
                    var option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.first_name + ' ' + user.last_name + ' (@' + user.username + ')';
                    if (select) {
                        select.appendChild(option.cloneNode(true));
                    }
                    if (playlistSelect) {
                        playlistSelect.appendChild(option);
                    }
                });
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSelectOptions);
    } else {
        initSelectOptions();
    }
})();
