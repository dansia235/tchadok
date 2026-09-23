# Tchadok

Plateforme de diffusion et de vente de musique tchadienne — PHP 8.2, MariaDB,
Apache.

> **État du projet.** Le chantier de remise à plat est en cours. Ce fichier
> décrit **ce qui existe réellement**, pas ce qui est prévu : la version
> précédente annonçait une soixantaine de fonctionnalités dont la plupart
> n'étaient pas écrites. Ce qui reste à faire est dans
> [`PLAN-ACHEVEMENT-TCHADOK.md`](PLAN-ACHEVEMENT-TCHADOK.md), et l'état des
> lieux qui l'a motivé dans
> [`AUDIT-PLATEFORME-TCHADOK.md`](AUDIT-PLATEFORME-TCHADOK.md).

---

## Ce qui fonctionne aujourd'hui

**Catalogue et lecture**
- Pages publiques : accueil, découverte, artistes, albums, genres, recherche,
  blog, émissions, radio en direct.
- Lecteur audio global, alimenté par des **URL signées à durée courte** : un
  fichier audio n'est jamais accessible par son adresse directe.
- Seul le contenu **publié d'artistes actifs** apparaît au catalogue public.

**Comptes**
- Inscription, connexion, « se souvenir de moi » par appareil, déconnexion.
- Écran « Appareils connectés » : sessions ouvertes et appareils mémorisés,
  révocables à l'unité.
- Double authentification (TOTP) avec codes de secours.
- Réinitialisation de mot de passe administrateur.

**Administration**
- Console, ajout de titres et d'albums, blog, playlists, radio, podcasts.
- **Rôles et permissions** (sept rôles), journal d'audit consultable.

**Non terminé** — vente, panier, paiement, versements aux artistes,
statistiques et baromètre, téléchargement hors ligne. Voir le plan.

---

## Installation en local (XAMPP)

```
git clone <dépôt> C:\xampp\htdocs\tchadok
cd C:\xampp\htdocs\tchadok
copy .env.local.example .env.local      # puis renseigner la base locale
php scripts/env-switch.php local        # génère le .htaccess
php scripts/migrate.php up              # crée ou met à jour le schéma
php database/seeds/demo.sql             # facultatif : deux comptes de démonstration
php scripts/create-admin.php            # premier administrateur
```

Le site répond alors sur `http://localhost/tchadok`.

**Pré-requis détaillés** : [`docs/exploitation/pre-requis.md`](docs/exploitation/pre-requis.md).

---

## Mise en production

Tout est rassemblé dans
[`docs/exploitation/mise-en-production-vps.md`](docs/exploitation/mise-en-production-vps.md) :
réglages à décider sur le serveur, migrations, tâches planifiées, et la liste
de vérifications à passer après la bascule.

En deux mots : `.env.production` remplace `.env.local` (qui doit être
**supprimé** du serveur), `php scripts/env-switch.php production` génère le
`.htaccess`, et `php scripts/migrate.php up` met le schéma à niveau.

---

## Organisation du dépôt

| Répertoire | Contenu |
|---|---|
| racine | points d'entrée web (pages `.php`) |
| `includes/` | couche applicative : session, autorisations, audit, médias, dépôts |
| `config/` | environnement et constantes |
| `api/` | points d'entrée JSON |
| `admin/` | console : connexion, journal d'audit, réinitialisation |
| `assets/` | styles, scripts, images |
| `database/` | migrations, schéma de référence, jeu de démonstration |
| `scripts/` | outils en ligne de commande (migrations, rôles, sauvegarde) |
| `storage/` | fichiers déposés et journaux — **jamais servis par le web** |
| `tests/` | tests d'intégration (sécurité, schéma) |
| `docs/` | exploitation, nettoyage, notes de migration |

---

## Tests

Seize suites, environ 670 contrôles, à exécuter contre le site local en
fonctionnement. Elles refusent de s'exécuter si `.env.local` est absent ou si
la base ne ressemble pas à une base locale.

```
C:\xampp\php\php.exe tests\securite\sec20-deux-facteurs.php
powershell -ExecutionPolicy Bypass -File tests\securite\sec12-limitation.ps1
```

La liste complète et ce que chaque suite couvre : [`tests/README.md`](tests/README.md).

---

## Conventions

- Le code et les commentaires sont en **français**.
- Aucun secret dans le dépôt : `.env.local` et `.env.production` sont ignorés,
  seuls les `*.example` sont versionnés.
- Toute modification de schéma passe par une **migration** ; `database/tchadok.sql`
  est un export généré, jamais modifié à la main.
- Une correction de sécurité s'accompagne d'un test qui échouerait sans elle.
