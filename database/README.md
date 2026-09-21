# Base de données Tchadok

Ce dossier fournit le dump SQL principal à importer pour initialiser la plateforme.

## Installation rapide

1. Créez la base `tchadok` (ou adaptez selon votre `.env`).
2. Importez `database/tchadok.sql` via phpMyAdmin ou la console MySQL.

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
