/**
 * Test SEC-09 : correctif de fetch() de includes/header-tailwind.php.
 *
 * Extrait le code EXACT du correctif depuis l'en-tete, l'execute dans un DOM
 * simule, puis verifie son comportement. Pas de copie du code a tester : une
 * modification de l'en-tete est donc toujours prise en compte.
 *
 * Usage : node tests/securite/sec09-fetch-patch.js
 * Necessite Node 18 ou plus (fetch, Headers, URL natifs).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const entete = fs.readFileSync(path.join(__dirname, '..', '..', 'includes', 'header-tailwind.php'), 'utf8');
const debut = entete.indexOf('(function () {\n            if (!window.fetch)');
const finMarqueur = '})();';
const fin = entete.indexOf(finMarqueur, debut);
if (debut < 0 || fin < 0) {
    console.error('Correctif de fetch() introuvable dans includes/header-tailwind.php');
    process.exit(1);
}
const code = entete.slice(debut, fin + finMarqueur.length);

const JETON = 'a'.repeat(64);
const ORIGINE = 'http://localhost';
const appels = [];

function nouveauContexte() {
    appels.length = 0;
    const window = {
        location: { href: ORIGINE + '/tchadok/decouvrir.php', origin: ORIGINE },
        fetch: function (ressource, options) {
            appels.push({ ressource, options });
            return Promise.resolve({ ok: true });
        },
    };
    const document = {
        querySelector: (sel) => sel === 'meta[name="csrf-token"]'
            ? { getAttribute: () => JETON }
            : null,
    };
    const ctx = { window, document, Headers, URL, Object, String, Request };
    vm.createContext(ctx);
    vm.runInContext(code, ctx);
    return window;
}

let ok = 0;
let ko = 0;
function verif(libelle, condition, detail) {
    if (condition) { ok++; console.log('  OK  ' + libelle); }
    else { ko++; console.log('  !!  ' + libelle + (detail ? '  -> ' + detail : '')); }
}

function jetonEnvoye(appel) {
    const h = appel.options && appel.options.headers;
    if (!h) return null;
    return typeof h.get === 'function' ? h.get('X-CSRF-Token') : (h['X-CSRF-Token'] || null);
}

(async () => {
    let w = nouveauContexte();

    console.log('\n=== Requetes modifiantes vers le site ===');
    for (const methode of ['POST', 'PUT', 'PATCH', 'DELETE', 'post']) {
        await w.fetch('/tchadok/api/playlists.php', { method: methode, body: '{}' });
        verif(`${methode} meme origine (chemin relatif) : jeton ajoute`, jetonEnvoye(appels.at(-1)) === JETON);
    }
    await w.fetch(ORIGINE + '/tchadok/api/stream.php', { method: 'POST' });
    verif('POST meme origine (URL absolue) : jeton ajoute', jetonEnvoye(appels.at(-1)) === JETON);

    console.log('\n=== Ce qui ne doit PAS recevoir le jeton ===');
    await w.fetch('/tchadok/api/track.php?id=1');
    verif('GET sans options : aucun jeton', jetonEnvoye(appels.at(-1)) === null);
    await w.fetch('/tchadok/api/track.php?id=1', { method: 'GET' });
    verif('GET explicite : aucun jeton', jetonEnvoye(appels.at(-1)) === null);
    await w.fetch('https://tiers.example/collecte', { method: 'POST', body: 'x' });
    verif('POST vers une AUTRE origine : aucun jeton (il y fuirait)', jetonEnvoye(appels.at(-1)) === null);
    await w.fetch('http://localhost:8080/autre', { method: 'POST' });
    verif('POST meme hote, autre port : aucun jeton', jetonEnvoye(appels.at(-1)) === null);

    console.log('\n=== Respect de l\'appelant ===');
    await w.fetch('/tchadok/api/playlists.php', { method: 'POST', headers: { 'X-CSRF-Token': 'fourni' } });
    verif('Jeton deja fourni par l\'appelant : conserve', jetonEnvoye(appels.at(-1)) === 'fourni');

    await w.fetch('/tchadok/api/playlists.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
    const h = appels.at(-1).options.headers;
    verif('Autres en-tetes de l\'appelant conserves', h.get('Content-Type') === 'application/json' && h.get('X-CSRF-Token') === JETON);

    const opts = { method: 'POST', headers: { 'Content-Type': 'application/json' } };
    await w.fetch('/tchadok/api/playlists.php', opts);
    verif('Objet d\'options de l\'appelant non modifie', !(opts.headers instanceof Headers) && !('X-CSRF-Token' in opts.headers));

    const corps = JSON.stringify({ a: 1 });
    await w.fetch('/tchadok/api/playlists.php', { method: 'POST', body: corps });
    verif('Corps de la requete transmis intact', appels.at(-1).options.body === corps);

    console.log('\n=== Objet Request en argument ===');
    const req = new Request(ORIGINE + '/tchadok/api/follows.php', { method: 'POST', headers: { 'X-Test': '1' } });
    await w.fetch(req);
    const a = appels.at(-1);
    verif('Request POST : jeton ajoute', jetonEnvoye(a) === JETON);
    verif('Request POST : en-tetes d\'origine conserves', a.options.headers.get('X-Test') === '1');

    console.log('\n=== Absence de jeton dans la page ===');
    const ctx = { window: { location: { href: ORIGINE + '/', origin: ORIGINE }, fetch: () => Promise.resolve() },
                  document: { querySelector: () => null }, Headers, URL, Object, String, Request };
    const fetchAvant = ctx.window.fetch;
    vm.createContext(ctx);
    vm.runInContext(code, ctx);
    verif('Sans balise meta : fetch() laisse intact', ctx.window.fetch === fetchAvant);

    console.log(`\nResultat : ${ok} reussi(s), ${ko} echec(s)`);
    process.exit(ko === 0 ? 0 : 1);
})();
