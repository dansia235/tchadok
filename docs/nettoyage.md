# Nettoyage du code mort — relevé de preuves

**Tâche `CLEAN-01`.** Relevé du 23/09/2026. Ce document justifie chaque
suppression du LOT 3 et sert de trace si une régression était constatée plus
tard : il dit **ce qui a été retiré, pourquoi, et comment l'absence d'usage a
été établie**.

Tout ce qui est retiré reste récupérable dans l'historique Git
(`git show <commit>:<chemin>`).

---

## Méthode

Trois contrôles par fichier, appliqués par un script d'analyse sur l'ensemble
du projet (hors `docs/`, `tests/` et les deux documents de chantier, qui citent
des fichiers sans les utiliser) :

1. **Inclusion** — `require` / `include` portant le chemin du fichier ;
2. **Lien** — `href`, `action`, `src`, ou présence dans les tableaux
   `$additionalCSS` / `$additionalJS` des pages ;
3. **Chargement réel** — pour les feuilles de style, la preuve décisive :
   les pages ont été demandées au serveur et les balises `<link>` réellement
   présentes dans le HTML ont été relevées.

> **La correspondance se fait sur le chemin, pas sur le nom de fichier.** Une
> première analyse par nom seul concluait à tort que `admin/dashboard-tabs/artists.php`
> était utilisé : les liens trouvés pointaient vers la page publique
> `artists.php`. Même piège pour `settings.php`, `users.php` et `playlists.php`.

**Feuilles de style réellement chargées** par les douze pages publiques
testées : `tailwind-base.css` et les neuf `*-tailwind.css`. **Aucune autre.**
Une feuille qu'aucune page ne charge n'a aucun effet sur le rendu : sa
suppression ne peut pas en produire.

---

## 1. Tableaux de bord de la génération précédente (`CLEAN-02`)

| Fichier | Taille | Preuve de non-usage | Décision |
|---|---:|---|---|
| `admin/dashboard-tabs/analytics.php` | 14,4 Ko | Aucune inclusion, aucun lien | **Retiré** |
| `admin/dashboard-tabs/artists.php` | 10,8 Ko | Les liens trouvés visent `artists.php` (page publique) ; mention en commentaire dans `api/artist.php` | **Retiré** |
| `admin/dashboard-tabs/music.php` | 26,9 Ko | Aucune référence | **Retiré** |
| `admin/dashboard-tabs/overview.php` | 9,8 Ko | Aucune référence | **Retiré** |
| `admin/dashboard-tabs/payments.php` | 26,4 Ko | Seule mention : un commentaire de `api/transaction.php` | **Retiré** |
| `admin/dashboard-tabs/playlists.php` | 13,0 Ko | `api/playlist.php` inclut `api/playlists.php`, pas ce fichier | **Retiré** |
| `admin/dashboard-tabs/settings.php` | 18,3 Ko | Les liens trouvés visent `settings.php` et `security-settings.php` | **Retiré** |
| `admin/dashboard-tabs/users.php` | 13,9 Ko | Seule mention : un commentaire de `api/user.php` | **Retiré** |
| `pages/` | — | Répertoire **déjà absent** du dépôt | Sans objet |
| `admin/dashboard.php` | 0,3 Ko | Simple redirection vers `admin-dashboard.php` ; aucun lien interne, mais une adresse possiblement mémorisée | **Conservé** |

Ces huit fichiers contenaient **six injections SQL** (`$_GET['search']`,
`$_GET['artist']` concaténés dans des requêtes). Les retirer supprime la
vulnérabilité sans avoir à la corriger.

`manifest.json` déclarait un raccourci vers `/pages/user/playlists.php`, page
qui n'existe pas : **corrigé**.

---

## 2. Ancienne coquille et ses styles (`CLEAN-03`)

| Fichier | Taille | Preuve de non-usage | Décision |
|---|---:|---|---|
| `includes/header.php` | 9,9 Ko | Les 47 pages incluent `header-tailwind.php` ; aucune inclusion de ce fichier | **Retiré** |
| `includes/footer.php` | 4,6 Ko | Idem, `footer-tailwind.php` partout | **Retiré** |
| `includes/player.php` | 9,1 Ko | Inclus nulle part (voir ci-dessous) | **Retiré** |
| `assets/css/main.css` | 34,4 Ko | Référencé par `includes/header.php` (mort) et `sw.js` (corrigé) | **Retiré** |
| `assets/css/player.css` | 2,8 Ko | Référencé par `includes/header.php` seul | **Retiré** |
| `assets/js/main.js` | 29,1 Ko | Référencé par `includes/footer.php` (mort) et `sw.js` (corrigé) | **Retiré** |
| `assets/js/admin-dashboard.js` | 2,2 Ko | Aucune référence (`admin-panel.js` est le script actif) | **Retiré** |
| 21 feuilles de style | ~125 Ko | Aucune n'apparaît dans le HTML servi (voir *Méthode*) | **Retirées** |

Feuilles retirées : `admin-dashboard-legacy`, `admin-dashboard-pages`,
`admin-dashboard-widgets`, `admin-dashboard`, `admin-execute-sql`,
`admin-login`, `admin-reset-database`, `admin-update-passwords`, `albums`,
`artist-dashboard-pages`, `artist-dashboard`, `artists`, `blog`, `contact`,
`decouvrir`, `emissions`, `genres`, `home`, `radio-live`, `search`,
`user-dashboard`. Les variantes `*-tailwind.css` sont **conservées** : ce sont
elles qui sont chargées.

### Le lecteur audio — la situation n'est pas celle que le plan décrivait

Le plan annonçait « 15,3 Ko de JavaScript exécuté sans conteneur ni style sur
chaque page » et demandait de trancher : réintégrer ou retirer.

**Vérification faite, le lecteur fonctionne.** `assets/js/player.js` ne dépend
plus de `includes/player.php` : il **construit lui-même son interface**
(`createPlayerInterface()`), en classes Tailwind du système actif
(`rounded-3xl border-white/10 bg-surface/90`), et expose `window.playTrack`,
appelé par `home.js`, `decouvrir.js` et `radio-live.js`. Il a été recâblé sur
les URL signées de `media.php` en `SEC-06`.

C'est `includes/player.php` qui est l'ancienne version : son balisage utilise
des classes **Bootstrap** (`position-fixed`, `col-md-3`, `d-flex`), absentes de
la coquille actuelle. Le réintégrer produirait un lecteur cassé.

**Décision : garder `player.js`, retirer `includes/player.php` et
`player.css`.** Le lecteur est fonctionnel et stylé — l'état « entre les deux »
que le plan voulait éviter n'existe pas.

---

## 3. Scripts SQL obsolètes (`CLEAN-04`)

| Fichier | Preuve | Décision |
|---|---|---|
| `sql/` (9 fichiers) | Aucun n'est exécuté par le code ; correctifs successifs sans ordre d'application, dont deux se contredisent sur `password` / `password_hash` | **Retiré** |
| `sample-data.sql` | Aucune référence ; supplanté par `database/seeds/demo.sql` | **Retiré** |

L'état réel du schéma est désormais porté par `database/migrations/`
(`DATA-01`), dont la première migration est une photographie de la base, et par
`database/tchadok.sql`, régénéré par `scripts/export-schema.php`. Le contenu de
`sql/` n'apporte donc plus rien : ce qui était pertinent y est déjà.

Archive : l'état du schéma avant nettoyage est conservé dans
`docs/schema-reel-2026-09-23.sql`.

---

## 4. Racine du projet (`CLEAN-05`)

| Élément | Décision |
|---|---|
| `INSCRIPTION-FONCTIONNELLE.md`, `PLACEHOLDERS_GUIDE.md`, `migration.md`, `README-ENVIRONNEMENT.md` | **Déplacés** dans `docs/` |
| `api_server.log`, `php_errors.log`, `radio.log` | **Retirés** de la racine web ; les journaux vivent dans `storage/logs/`, hors du répertoire servi |
| `README.md` | **Réécrit** : il annonçait une soixantaine de fonctionnalités dont la plupart n'existent pas |
| `AUDIT-PLATEFORME-TCHADOK.md`, `PLAN-ACHEVEMENT-TCHADOK.md` | **Conservés à la racine** — voir ci-dessous |

> **Écart assumé.** Le plan prévoyait de déplacer l'audit et le plan dans
> `docs/`. Ils sont modifiés à chaque tâche et cités par des dizaines de
> références ; les déplacer en plein chantier produirait de la casse pour un
> gain nul. À faire à la clôture du chantier.

---

## 5. Statut incertain — à arbitrer

Aucun. Les seuls cas ambigus rencontrés (`admin/dashboard.php`, le lecteur
audio, `AUDIT`/`PLAN` à la racine) ont été tranchés ci-dessus, avec leur
raison.

---

## Comment vérifier qu'il n'y a pas eu de régression

Les douze pages publiques ont été capturées **avant** et **après** le
nettoyage, jetons et horodatages neutralisés, puis comparées : le HTML servi
est identique. Les seize suites de tests automatisés ont été rejouées.
