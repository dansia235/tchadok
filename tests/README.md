# Tests — Tchadok

Ce répertoire n'est **jamais déployé en production** : `scripts/env-switch.php production`
refuse la bascule tant qu'il est présent sur le serveur.

## Tests de sécurité

Tests d'intégration exécutés contre le site local en fonctionnement (XAMPP démarré).
Chaque script met en place son propre jeu d'essai et le retire à la fin, y compris
en cas d'échec. Ils refusent de s'exécuter si `.env.local` est absent ou si la base
configurée ne ressemble pas à une base locale.

| Script | Tâche | Couvre |
|---|---|---|
| `securite/sec06-acces-media.ps1` | `SEC-06` | Accès direct aux fichiers audio interdit ; aucun chemin de fichier exposé par l'API ; lecture complète et partielle via `media.php` ; rejet des URL falsifiées, expirées, détournées ou copiées dans une autre session ; décision d'accès selon le statut (gratuit, payant, acheté, Premium actif ou expiré, propriétaire, brouillon) ; refus des URL externes et de la traversée de répertoire. **39 contrôles.** |

### Exécution

```
powershell -ExecutionPolicy Bypass -File tests\securite\sec06-acces-media.ps1
```

Code de sortie `0` si tous les contrôles passent, `1` sinon — exploitable en
intégration continue (`QA-04`).

### Réservation d'identifiants

Pour éviter toute collision avec des données réelles, les jeux d'essai utilisent
des plages réservées :

| Table | Plage |
|---|---|
| `users` | 901 – 999 |
| `artists` | 901 – 999 |
| `tracks` | 9001 – 9999 |

### Pourquoi `curl.exe` pour les requêtes média

PowerShell 5.1 refuse l'en-tête `Range` dans `Invoke-WebRequest -Headers`. Or la
lecture audio repose entièrement sur les requêtes partielles. Les requêtes vers
`media.php` passent donc par `curl.exe`, fourni avec Windows 10 et suivants, en
lui transmettant le cookie de session de la `WebSession` PowerShell.
