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
| `securite/sec08-catalogue-public.php` | `SEC-08` | Exécute chaque fonction publique de `includes/database.php` avec des titres et albums dans chaque statut et un artiste désactivé : recherche, listes, albums, statistiques, compteurs, écoutes récentes, enregistrement d'écoute. Seul le contenu publié d'artistes actifs doit apparaître. Contrôles positifs et surveillance du journal d'erreurs, car ces fonctions masquent les erreurs SQL. **36 contrôles.** |
| `securite/sec09-csrf.ps1` | `SEC-09` | Chaque point d'entrée (26) refuse une requête modifiante sans jeton, avec un jeton faux, ou avec le jeton d'une autre session ; jeton valide accepté par champ, par en-tête et par corps JSON ; GET non affectés ; jeton renouvelé à la connexion ; 413 et non 403 au-delà de `post_max_size` ; réponses JSON pour les API, HTML pour les pages ; déconnexion en POST, y compris pour les comptes « se souvenir de moi », sans redirection ouverte. **64 contrôles.** Utilise les comptes du jeu de démonstration (`database/seeds/demo.sql`). |
| `securite/sec09-fetch-patch.js` | `SEC-09` | Exécute, dans un DOM simulé, le code exact du correctif de `fetch()` extrait de `includes/header-tailwind.php` : jeton ajouté aux requêtes modifiantes vers le site, jamais vers une autre origine ni un autre port, en-têtes et options de l'appelant respectés, objet `Request` géré. **17 contrôles.** Nécessite Node 18+. |
| `securite/sec10-session.ps1` | `SEC-10` | Cookie `TCHADOKSESSID` (`HttpOnly`, `SameSite=Lax`, sans `Secure` en HTTP local) ; identifiant régénéré à la connexion ; identifiant forgé refusé ; fixation de session ; registre des sessions sans copie des données ; changement de mot de passe fermant les autres appareils et leur connexion automatique ; pas de connexion automatique pour un administrateur ; délai d'inactivité propre à l'administration ; réinitialisation du mot de passe administrateur ; refus d'un non-administrateur sur la console sans casser le formulaire suivant. **35 contrôles**, dont une attente réelle de 65 s : le script abaisse `ADMIN_SESSION_LIFETIME` à 60 dans `.env.local` et restaure la valeur d'origine en fin de script, même en cas d'échec. |

### Exécution

```
powershell -ExecutionPolicy Bypass -File tests\securite\sec06-acces-media.ps1
C:\xampp\php\php.exe tests\securite\sec08-catalogue-public.php
powershell -ExecutionPolicy Bypass -File tests\securite\sec09-csrf.ps1
node tests\securite\sec09-fetch-patch.js
powershell -ExecutionPolicy Bypass -File tests\securite\sec10-session.ps1
```

### Pièges de PowerShell 5.1 rencontrés

Quatre comportements de PowerShell 5.1 ont faussé des résultats de test avant
d'être identifiés. Ils valent pour tout script qui appelle `curl.exe` :

- l'en-tête `Range` est refusé dans `Invoke-WebRequest -Headers` ;
- **les guillemets doubles sont supprimés** des arguments passés à un
  exécutable natif : `{"a":1}` arrive sous la forme `{a:1}`. Passer tout corps
  JSON par fichier (`--data-binary @fichier`) ;
- **les arguments vides sont supprimés** : `-d ''` fait consommer l'URL par
  `-d` ;
- un paramètre de fonction typé `[string]` transforme `$null` en chaîne vide.

### Rechercher du texte dans une page : penser à l'échappement HTML

Les messages affichés passent par `htmlspecialchars` : une apostrophe devient
`&#039;`. Une expression comme `d.inactivite` ne trouve donc pas
« d'inactivite » dans la page. Chercher une portion sans apostrophe, ou
prévoir les deux formes (`d(&#039;|')inactivite`).

### Leçon retenue : exécuter le SQL, pas seulement le PHP

`php -l` ne valide pas le SQL contenu dans une chaîne. Et les fonctions de
`includes/database.php` interceptent les erreurs SQL pour renvoyer un tableau
vide. Une requête cassée passe donc **tous** les contrôles statiques, et ne se
traduit à l'écran que par une liste vide — indiscernable d'un catalogue sans
contenu. Tout test portant sur une requête doit l'exécuter, inclure au moins
un contrôle positif (« tel élément est présent »), et surveiller le journal
d'erreurs.

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
