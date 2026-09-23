# Base de données Tchadok

Ce dossier fournit le dump SQL principal à importer pour initialiser la plateforme.

## Installation rapide

1. Créez la base `tchadok` (ou adaptez selon votre `.env`).
2. Importez `database/tchadok.sql` via phpMyAdmin ou la console MySQL.

## Migrations

`database/tchadok.sql` décrit le schéma **à jour**. Une base déjà installée se
met à niveau avec les fichiers de `database/migrations/`, à appliquer dans
l'ordre de leur date :

```
mysql -u <utilisateur> -p <base> < database/migrations/2026-09-22-sec11-remember-tokens.sql
```

| Fichier | Effet |
|---|---|
| `2026-09-22-sec11-remember-tokens.sql` | Table `remember_tokens` (connexion automatique, SEC-11) et suppression de `users.remember_token`. Les personnes qui avaient coché « se souvenir de moi » se reconnectent une fois. |
| `2026-09-23-sec12-limitation-debit.sql` | Tables `login_attempts` et `rate_limit_hits` (limitation de débit et verrouillage des connexions, SEC-12). |

## Données

Aucune donnée de démonstration n’est fournie (sauf comptes utilisateurs). Les contenus
artistes, albums, podcasts, émissions, playlists, etc. se créent depuis les pages admin.

## Tables clés

- `users`, `admins`, `artists`
- `tracks`, `albums`, `genres`
- `playlists`, `playlist_tracks`, `favorites`, `follows`
- `radio_shows`, `radio_live`
- `podcasts`, `podcast_episodes`
- `streams`, `purchases`, `transactions`
