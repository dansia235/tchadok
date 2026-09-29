/**
 * Boutons « Ajouter au panier » (SHOP-01).
 *
 * <button data-panier-ajouter data-type="track|release" data-id="42">
 *
 * Seuls le type et l'identifiant partent au serveur : le prix est relu en
 * base, jamais repris de la page. Le jeton CSRF est ajoute par le correctif
 * fetch() de l'en-tete.
 */
(function () {
    'use strict';

    // Meme source que le lecteur (assets/js/player.js).
    const base = (window.TCHADOK && window.TCHADOK.SITE_URL) ? window.TCHADOK.SITE_URL : '';

    function annoncer(texte, erreur) {
        let zone = document.getElementById('panier-annonce');
        if (!zone) {
            zone = document.createElement('div');
            zone.id = 'panier-annonce';
            zone.setAttribute('role', 'status');
            zone.setAttribute('aria-live', 'polite');
            zone.style.cssText = 'position:fixed;left:50%;bottom:96px;transform:translateX(-50%);z-index:60;max-width:90vw;'
                + 'padding:10px 16px;border-radius:999px;font-size:14px;color:#fff;box-shadow:0 8px 24px #0005;transition:opacity .3s';
            document.body.appendChild(zone);
        }
        zone.style.background = erreur ? '#b42318' : '#1f7a4d';
        zone.textContent = texte;
        zone.style.opacity = '1';
        clearTimeout(zone._minuterie);
        zone._minuterie = setTimeout(function () { zone.style.opacity = '0'; }, 3500);
    }

    function mettreAJourCompteur(nombre) {
        document.querySelectorAll('[data-panier-compte]').forEach(function (badge) {
            badge.textContent = String(nombre);
            badge.hidden = !(nombre > 0);
        });
    }

    document.addEventListener('click', function (evenement) {
        const bouton = evenement.target.closest('[data-panier-ajouter]');
        if (!bouton) {
            return;
        }
        evenement.preventDefault();
        evenement.stopPropagation();
        bouton.disabled = true;

        fetch(base + '/api/panier.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'ajouter', type: bouton.dataset.type, id: Number(bouton.dataset.id) })
        })
            .then(function (r) { return r.json(); })
            .then(function (resultat) {
                annoncer(resultat.message || (resultat.succes ? 'Ajoute au panier.' : 'Ajout impossible.'), !resultat.succes);
                if (typeof resultat.nombre === 'number') {
                    mettreAJourCompteur(resultat.nombre);
                }
            })
            .catch(function () { annoncer('Connexion impossible. Reessayez.', true); })
            .finally(function () { bouton.disabled = false; });
    });
})();
