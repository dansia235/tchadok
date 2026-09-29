# Configuration des environnements — Tchadok

Tâche `CFG-06`. Dernière mise à jour : 22 septembre 2026.

Ce document remplace la version précédente, qui décrivait un mécanisme à
fichier `.env` unique désormais abandonné.

---

## 1. Le principe en une page

Deux environnements, jamais mélangés.

| Fichier | Versionné | Rôle | Sur le serveur de production |
|---|:--:|---|---|
| `.env.local` | **non** | Configuration du poste de développement | **retiré** |
| `.env.local.example` | oui | Modèle, sans valeur réelle | présent, sans effet |
| `.env.production` | **non** | Configuration réelle du serveur | présent |
| `.env.production.example` | oui | Modèle, sans valeur réelle | présent, sans effet |
| `.htaccess.local` | oui | Règles Apache de développement | **retiré** |
| `.htaccess.production` | oui | Règles Apache durcies | source de `.htaccess` |
| `.htaccess` | **non** | **Généré** — seul fichier lu par Apache | copie de `.htaccess.production` |

`config/env.php` résout dans cet ordre strict :

1. `.env.local` présent → environnement **local** ;
2. sinon `.env.production` → environnement **production** ;
3. sinon → **erreur fatale explicite**, jamais de valeur par défaut.

Retirer `.env.local` du serveur suffit donc à basculer la configuration.

### Le piège à connaître

**Apache ne lit que le fichier nommé exactement `.htaccess`.** Il ignore
totalement `.htaccess.local` et `.htaccess.production`.

Retirer `.htaccess.local` d'un serveur ne suffit pas : s'il ne reste aucun
`.htaccess`, Apache n'applique **aucune** règle — ni blocage des fichiers
sensibles, ni en-têtes de sécurité, ni redirection HTTPS. Le site répond
normalement et rien ne signale le problème.

D'où deux garde-fous :

- `scripts/env-switch.php` **génère** `.htaccess` depuis la bonne source ;
- `includes/environment-guard.php` refuse de servir l'application si la
  configuration active ne correspond pas à l'infrastructure.

### Les identifiants locaux peuvent rester simples

C'est un choix assumé. Le poste de développement n'est pas exposé, et des
identifiants mémorisables y font gagner du temps. Ce qui protège la
production n'est pas la complexité des valeurs locales : c'est
l'impossibilité technique qu'elles y arrivent — et c'est le rôle de
`env-switch` et du garde-fou.

---

## 2. Installation sur un poste de développement

### 2.1. Pré-requis

Voir `docs/exploitation/pre-requis.md` pour le détail. En résumé :

- XAMPP avec **PHP 8.2**, MariaDB 10.4+, Apache 2.4 ;
- extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `gd`,
  `intl`, `zip` ;
- `ffmpeg` / `ffprobe` (nécessaire aux lots 9 et 12).

### 2.2. Base de données

```sql
CREATE DATABASE tchadok_local
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis importer le schéma **avec un compte administrateur** — le dump contient
des `CREATE TRIGGER`, qu'un compte limité ne peut pas exécuter :

```
C:\xampp\mysql\bin\mysql.exe -u root tchadok_local < database\tchadok.sql
```

Attendu : 27 tables, 2 vues, 3 triggers.

> Les triggers `update_stream_stats`, `update_purchase_stats` et
> `update_album_tracks_count` maintiennent les compteurs directement depuis
> la donnée brute. Ils seront retirés à la tâche `STAT-06` au profit d'une
> chaîne d'agrégation explicite et reconstructible.

### 2.3. Configuration

```
copy .env.local.example .env.local
```

Renseigner au minimum `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Les
autres valeurs du modèle conviennent pour démarrer.

### 2.4. Règles Apache

```
php scripts/env-switch.php local
```

Cette commande génère `.htaccess`. **Elle n'est pas optionnelle** : sans
elle, aucune règle n'est appliquée.

### 2.4 bis. Hook Git anti-secrets

```
git config core.hooksPath scripts/git-hooks
```

À faire une fois par clone. Le hook refuse tout commit qui ajouterait un
fichier d'environnement réel, `.htaccess`, ou un motif de vrai secret. Voir
`docs/exploitation/secrets.md`, section 3.5.

### 2.4 ter. Données de référence

```
php scripts/migrate.php up
php scripts/seed.php referentiel
php scripts/seed.php status
```

Charge les catégories et genres musicaux et les 23 provinces (`DATA-08`).
**Sans cette étape, aucun genre n'est proposé au dépôt d'un titre.** Le
chargement est rejouable : il n'ajoute que ce qui manque et ne modifie jamais
une ligne existante. Il refuse de s'exécuter si des migrations sont en attente.

### 2.5. Comptes

Depuis `SEC-05`, le dump `database/tchadok.sql` **ne contient plus aucun
compte**. Deux façons d'en obtenir :

**Jeu de démonstration (local uniquement)**

```
php scripts/seed.php demo
```

Crée `admin` (super-administrateur) et `user_demo`, mot de passe
`tchadok2026`. Ce fichier ne doit **jamais** être importé en production :
`seed.php demo` refuse hors environnement local, et `env-switch production`
refuse la bascule tant qu'il est présent sur le serveur.

**Compte administrateur réel**

```
php scripts/create-admin.php
```

Saisie masquée du mot de passe. En automatisation, le mot de passe est lu
dans la variable `TCHADOK_ADMIN_PASSWORD` — jamais en argument, où il
finirait dans l'historique du shell :

```
set TCHADOK_ADMIN_PASSWORD=...
php scripts/create-admin.php --username=alice --email=alice@tchadok.td --first-name=Alice --last-name=Doe
```

La politique de mot de passe suit l'environnement :

| | Local | Production |
|---|---|---|
| Longueur minimale | 8 | 12 |
| 3 familles de caractères sur 4 | avertissement | **exigé** |
| Hors liste des mots de passe courants | avertissement | **exigé** |
| Ne reprend pas l'identifiant ni l'adresse | avertissement | **exigé** |

La commande refuse de s'exécuter par HTTP, et trace chaque création dans le
journal applicatif, sans jamais y inscrire le mot de passe.

### 2.6. Vérification

```
php scripts/env-switch.php --status
```

Attendu :

```
  [ok]    Configuration : local (.env.local)
  [ok]    Apache        : .htaccess genere depuis .htaccess.local
```

Puis ouvrir `http://localhost/tchadok`.

### 2.7. Paiements : simulateurs locaux

```
php scripts/migrate.php up
scripts\mock-gateways.bat
php scripts/paiements.php commande-essai user@tchadok.td 1500
```

Démarre les quatre simulateurs (Airtel Money, Moov Money, VISA, GIMAC) et le
distributeur de callbacks, sur `127.0.0.1` uniquement. La dernière commande
crée une commande d'essai et affiche l'adresse de sa page de paiement.
Numéros et cartes de test : `docs/paiement/jeux-de-test.md`. Arrêt :
`scripts\mock-gateways.bat stop`.

Les callbacks des simulateurs arrivent par Apache
(`PAYMENT_CALLBACK_BASE`) : Apache doit tourner.

---

## 3. Déploiement en production

> Procédure complète et automatisable : `docs/exploitation/deploiement.md`
> (tâche `DEPLOY-02`). Ce qui suit en est le noyau.

1. **Sauvegarder**, et vérifier la sauvegarde
   (`scripts/backup.ps1`, puis `scripts/restore.ps1` sur une base de contrôle).
2. Mettre le site en maintenance.
3. Récupérer le code.
4. **Retirer du serveur** : `.env.local`, `.htaccess.local`,
   `mock-gateways/`, `tests/`, `database/seeds/demo.sql`.
5. Déposer `.env.production` (jamais versionné) avec les vraies valeurs,
   droits `600`.
6. **Basculer** :
   ```
   php scripts/env-switch.php production
   ```
   La commande **refuse** de s'exécuter tant qu'un fichier de développement
   subsiste ou qu'un secret porte encore une valeur de modèle. Elle liste
   alors tout ce qui bloque, en une fois.
7. Appliquer les migrations (`php scripts/migrate.php up`, tâche `DATA-01`).
8. Contrôler :
   ```
   php scripts/env-switch.php --status
   curl -I https://<domaine>/
   ```
9. Sortir du mode maintenance et vérifier les cinq parcours critiques :
   inscription, connexion, publication d'un titre, achat, baromètre.

### Ce que `env-switch production` refuse

- `.env.local`, `.htaccess.local`, `mock-gateways/`, `tests/` ou
  `database/seeds/demo.sql` présents ;
- un secret laissé à `REMPLACER`, `CHANGE-ME`, `YOUR_`, `votre-`,
  `A_DEFINIR`, `mock-` ou `hackme` ;
- `APP_DEBUG` différent de `false` ;
- `PAYMENT_DRIVER` différent de `live` ;
- `ALLOW_DEV_TOOLS` différent de `false` ;
- `SESSION_SECURE` différent de `true` ;
- `APP_URL` ou `SITE_URL` qui ne sont pas en HTTPS.

### Ce que le garde-fou applicatif arrête

`includes/environment-guard.php` s'exécute à chaque requête. En production,
il renvoie une page 503 — sans aucun détail technique, les anomalies allant
dans les journaux — si :

- `.htaccess` est **absent** ;
- `.htaccess` provient de la mauvaise source ;
- `APP_DEBUG` ou `ALLOW_DEV_TOOLS` sont actifs ;
- `PAYMENT_DRIVER` vaut `mock` ;
- un secret porte encore une valeur de modèle ;
- `APP_URL` ou `SITE_URL` ne sont pas en HTTPS ;
- `SESSION_SECURE` est désactivé ;
- **`.env.local` est présent sur une infrastructure de production.**

Ce dernier point mérite l'attention : un `.env.local` oublié prend la
priorité sur `.env.production`, et l'application démarrerait en mode
développement — debug actif, paiements simulés — sans se croire en
production, donc sans déclencher les autres contrôles. Le garde détecte
l'infrastructure par l'empreinte du `.htaccess` en place et par le marqueur
`storage/.environment`, que ce fichier oublié ne peut pas masquer.

En local, ces mêmes anomalies s'affichent en bandeau et ne bloquent jamais.

---

## 4. Variables d'environnement

| Variable | Obligatoire | Local | Production |
|---|:--:|---|---|
| `APP_ENV` | oui | `local` | `production` |
| `APP_DEBUG` | oui | `true` | **`false`** |
| `APP_URL`, `SITE_URL` | oui | `http://localhost/tchadok` | **HTTPS obligatoire** |
| `APP_TIMEZONE` | non | `Africa/Ndjamena` | idem |
| `DB_HOST`, `DB_PORT` | oui | `127.0.0.1`, `3306` | selon l'hébergement |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | **oui** | valeurs locales | compte limité au DML |
| `BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD` | recommandé | identique au compte applicatif | **compte distinct en lecture seule** |
| `APP_KEY`, `SESSION_SECRET` | **oui** | valeurs simples | générées, différentes du local |
| `SESSION_SECURE` | oui | `false` (HTTP) | **`true`** |
| `SESSION_SAMESITE` | non | `Lax` | `Lax` |
| `SESSION_LIFETIME`, `ADMIN_SESSION_LIFETIME` | non | longues | 1800 / 900 s |
| `TRUSTED_PROXIES` | non | vide | IP du proxy, sinon **vide** |
| `STORAGE_PATH` | oui | `./storage` | chemin absolu, **hors racine web** |
| `MAIL_DRIVER` | oui | `log` | `smtp` |
| `PAYMENT_DRIVER` | oui | `mock` | **`live`** |
| `PAYMENT_CALLBACK_BASE` | oui | `http://localhost/tchadok` | URL publique **HTTPS** |
| `PAYMENT_INTENT_TTL` | non | `900` | `900` |
| `<PASSERELLE>_BASE_URL`, `_MERCHANT_ID`, `_API_KEY`, `_WEBHOOK_SECRET` (`AIRTEL`, `MOOV`, `VISA`, `GIMAC`) | oui | simulateurs locaux | fournis par le partenaire |
| `<PASSERELLE>_CALLBACK_IPS` | **oui en production** | vide (boucle locale) | adresses émettrices de l'opérateur ; **vide = tout callback refusé** |
| `FFPROBE_PATH`, `FFMPEG_PATH` | oui (lots 9, 12) | chemin local | chemin serveur |
| `ALLOW_DEV_TOOLS` | oui | `true` | **`false`** |
| `FORCE_HTTPS`, `HSTS_ENABLED` | non | `false` | `true` |
| `RADIO_*`, `ICECAST_*` | si radio activée | Icecast local | Icecast production, **HTTPS** |

Les secrets ne sont **jamais** exposés en constante PHP : seule une liste
blanche (`APP_NAME`, `APP_TIMEZONE`, `APP_URL`) est promue. Tout le reste se
lit par `env('CLE')`, ou `env_require('CLE')` quand l'absence doit être
fatale.

---

## 5. Dépannage

### « Aucun fichier d'environnement trouvé »

Ni `.env.local` ni `.env.production` n'existe. En local :
`copy .env.local.example .env.local`.

### Page 503 « Service momentanément indisponible » avec une référence

Le garde-fou a détecté une anomalie critique. Le détail est dans
`storage/logs/php-errors.log`, préfixé `[Tchadok][guard]`. La référence
affichée permet de retrouver l'entrée correspondante.

### « Configuration de base de données incomplète »

`DB_DATABASE`, `DB_USERNAME` ou `DB_PASSWORD` manque. Le message nomme les
variables absentes. Il n'y a plus de valeur de repli : c'est volontaire —
l'ancienne version se rabattait silencieusement sur des identifiants écrits
en dur.

### Le site fonctionne mais les fichiers sensibles sont accessibles

`.htaccess` n'a pas été généré. Lancer `php scripts/env-switch.php local`
(ou `production`), puis vérifier avec `--status`.

### Modifications du `.htaccess` perdues

`.htaccess` est un fichier **généré**, écrasé à chaque bascule. Reporter les
modifications dans `.htaccess.local` ou `.htaccess.production`. Si une
version non reconnue est détectée, `env-switch` la sauvegarde sous
`.htaccess.remplace-<horodatage>` avant de l'écraser.

### « SHOW VIEW command denied » pendant une sauvegarde

Le compte applicatif est volontairement limité au DML et ne peut pas lire
les définitions de vues et de triggers. Renseigner `BACKUP_DB_USERNAME` avec
un compte de sauvegarde dédié — voir `docs/exploitation/sauvegarde.md`.

### Les extensions PHP activées ne sont pas prises en compte

Redémarrer Apache. La version CLI de PHP relit `php.ini` à chaque appel,
pas le module Apache.

---

## 6. Documents liés

| Document | Contenu |
|---|---|
| `docs/exploitation/pre-requis.md` | Versions, extensions, modules Apache, base locale |
| `docs/exploitation/secrets.md` | Inventaire des secrets, rotation, procédure de fuite |
| `docs/exploitation/sauvegarde.md` | Sauvegarde, restauration, comptes MySQL, rétention |
| `AUDIT-PLATEFORME-TCHADOK.md` | Audit complet de la plateforme |
| `PLAN-ACHEVEMENT-TCHADOK.md` | Les 123 tâches vers la mise en production |
