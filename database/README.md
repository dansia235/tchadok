# Base de données Tchadok

## Installation

Le schéma est défini par les **migrations**, pas par un export. Sur une base
vide comme sur une base existante, une seule commande suffit :

```
php scripts/migrate.php up
```

La commande crée ce qui manque et n'écrase rien : chaque migration est écrite
pour pouvoir être rejouée sans effet.

`database/tchadok.sql` reste disponible pour une installation manuelle
(phpMyAdmin, import direct), mais ce fichier est **généré** — voir plus bas.

## Migrations

| Commande | Effet |
|---|---|
| `php scripts/migrate.php status` | liste ce qui est appliqué et ce qui attend |
| `php scripts/migrate.php up` | applique les migrations en attente, dans l'ordre |
| `php scripts/migrate.php down --steps=1` | annule la dernière migration appliquée |
| `php scripts/migrate.php verify` | signale une migration modifiée après son application |

Le script refuse de s'exécuter autrement qu'en ligne de commande.

### Écrire une migration

Un fichier par changement, dans `database/migrations/`, nommé
`AAAA_MM_JJ_NNNN_description.sql`, avec deux sections :

```sql
-- UP
CREATE TABLE IF NOT EXISTS `exemple` ( ... );

-- DOWN
DROP TABLE IF EXISTS `exemple`;
```

Trois règles :

1. **Idempotence.** `CREATE TABLE IF NOT EXISTS`, `DROP ... IF EXISTS`, et pour
   un `ALTER`, un test préalable sur `information_schema` — MySQL ne connaît pas
   `DROP COLUMN IF EXISTS`. Une migration interrompue doit pouvoir être relancée.
2. **Une section `DOWN`** dès que l'annulation a un sens. Sans elle, `down`
   refuse — c'est voulu : mieux vaut un refus clair qu'une annulation partielle.
3. **Ne jamais modifier une migration déjà appliquée.** `verify` compare
   l'empreinte du fichier à celle enregistrée : un fichier retouché signifie que
   le serveur ne porte pas ce que le dépôt décrit. La correction se fait par une
   **nouvelle** migration.

### Migrations existantes

| Fichier | Effet |
|---|---|
| `2026_09_23_0001_photographie_du_schema.sql` | Point de départ commun : tout le schéma, en créations conditionnelles. Sans section `DOWN` — revenir en arrière à ce niveau, c'est restaurer une sauvegarde. |
| `2026_09_23_0002_sec11_remember_tokens.sql` | Table `remember_tokens`, suppression de `users.remember_token` (`SEC-11`). Sans effet sur une base créée après. |
| `2026_09_23_0003_sec12_limitation_debit.sql` | Tables `login_attempts` et `rate_limit_hits` (`SEC-12`). |

## Fichier de référence

`database/tchadok.sql` est **généré** par :

```
php scripts/export-schema.php
```

Il est régénéré après chaque migration et ne doit pas être modifié à la main.
L'export retire le `DEFINER` des vues et des déclencheurs : cet attribut fige un
compte MySQL (`root@localhost`) qui n'existera pas sur le serveur.

## Données

Deux jeux, chargés par `scripts/seed.php` (ligne de commande uniquement) :

| Jeu | Fichier | Où | Contenu |
|---|---|---|---|
| `referentiel` | `database/seeds/referentiel.sql` | **toute installation**, production comprise | 6 catégories, 31 genres, 23 provinces |
| `demo` | `database/seeds/demo.sql` | **local uniquement** | deux comptes d'essai |

```
php scripts/seed.php referentiel
php scripts/seed.php demo
php scripts/seed.php status
```

Le référentiel est rejouable et n'écrase jamais une ligne existante. Une ligne
de `genres` **sans parent** est une catégorie : un titre se classe toujours dans
un genre, jamais dans une catégorie. La nomenclature des genres est une
proposition (audit, §6.3) à faire valider par un comité éditorial.

Le jeu de démonstration n'est **jamais** importé en production : `seed.php demo`
refuse hors environnement local, et `scripts/env-switch.php production` refuse
la bascule s'il est présent sur le serveur. Rôles, permissions et grille
tarifaire ne sont pas des seeds : ils sont portés par les migrations 0004 et 0008.

Le premier administrateur se crée avec :

```
php scripts/create-admin.php
```

## Tables clés

- `users`, `admins`, `artists`
- `tracks`, `albums`, `genres`
- `playlists`, `playlist_tracks`, `favorites`, `follows`
- `radio_shows`, `radio_live`
- `podcasts`, `podcast_episodes`
- `streams`, `purchases`, `transactions`
- `user_sessions`, `remember_tokens` (sessions et connexion automatique)
- `login_attempts`, `rate_limit_hits` (verrouillage et limitation de débit)
- `schema_migrations` (registre des migrations appliquées)
