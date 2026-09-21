# Audit complet — Plateforme Tchadok

**Date de l'audit :** 21 septembre 2026
**Périmètre :** intégralité du dépôt `c:\xampp\htdocs\tchadok` (104 fichiers PHP, 37 CSS, 30 JS, 14 SQL)
**Branche analysée :** `main` (HEAD `8bed209`)
**Nature :** audit technique, sécurité, données, fonctionnel, design/UX, accessibilité, performance
**Ambition cible retenue :** référence de la **vente** de musique d'artistes tchadiens + **baromètre national des écoutes** par genre et catégorie

---

## 1. Résumé exécutif

Tchadok dispose d'une **base sérieuse** : un schéma de données relationnel correct (29 tables, 34 clés étrangères, index pertinents sur `streams` et `purchases`), une direction artistique cohérente et moderne sur la partie publique, un moteur radio réellement intégré (Icecast/Liquidsoap), un module blog abouti, et un début de console d'administration.

Mais l'état actuel **ne permet ni la mise en production, ni la tenue de la promesse produit**. Trois constats structurants :

### 1.1. La plateforme est ouverte à une prise de contrôle totale, sans authentification

Une seule requête HTTP anonyme crée un compte **administrateur** avec un mot de passe connu :

```
POST /api/user.php
action=create&user_type=admin&username=x&email=x@x.td&first_name=X&last_name=Y
→ compte créé, mot de passe « 12345678 », ligne insérée dans la table `admins`
```

Les endpoints `api/user.php`, `api/artist.php` et `api/transaction.php` n'ont **aucun contrôle d'accès**. Ils exposent publiquement : la liste des utilisateurs avec e-mails et téléphones, la suppression unitaire et **en masse** de comptes, la création d'artistes « vérifiés », et la **validation de transactions financières** (`action=approve` passe une transaction en `completed`). À cela s'ajoutent `install.php` accessible en racine qui recrée un admin `admin@tchadok.td / password123` **et affiche les identifiants à l'écran**, et `admin/update-passwords.php` qui réinitialise le mot de passe de **tous** les utilisateurs à `12345678`.

**Aucune mise en ligne n'est envisageable avant correction de ce bloc.** Voir §3.1.

### 1.2. Le cœur du modèle économique n'existe pas

La table `purchases` est **lue** par quatre écrans (dashboard artiste, dashboard fan, wallet) mais **jamais écrite** : il n'y a aucun panier, aucun tunnel d'achat, aucun bouton « Acheter », aucune vérification de droits au téléchargement, aucun versement aux artistes. Les fichiers audio sont servis en direct depuis `/uploads/audio/` sans contrôle : **tout titre « payant » est téléchargeable librement par URL**.

Le tunnel Premium existe visuellement mais s'arrête à l'insertion d'une ligne `status = 'pending'` : **aucun webhook, aucun callback, aucun encaissement**. Personne ne peut devenir Premium.

Conséquence : la plateforme **ne peut vendre ni un single, ni un maxi, ni un album**. C'était la demande centrale.

### 1.3. Le baromètre des écoutes est non fonctionnel et falsifiable

- `tracks.total_streams`, `tracks.total_sales`, `albums.total_streams`, `artists.total_streams`, `artists.total_earnings` : **jamais incrémentés par le code**. Vérifié par recherche exhaustive — zéro occurrence d'écriture. Tous les KPI de tous les dashboards affichent donc 0, ou la valeur figée du dump SQL.
- Les tables `charts` et `site_stats`, prévues pour les classements, ne sont **jamais alimentées**. Aucune page classement/baromètre n'existe.
- `POST /api/stream.php` enregistre une écoute **sans authentification, sans CSRF, sans limitation de débit, avec `Access-Control-Allow-Origin: *`**, et accepte `duration`, `country` et `city` **fournis par le client**. Une boucle de trois lignes en JavaScript peut fabriquer un million d'écoutes attribuées à N'Djamena.

Un baromètre qui se prétend référence nationale et dont la métrique est forgeable en dix secondes perd toute valeur — commerciale, éditoriale et institutionnelle. C'est le risque le plus lourd pour la crédibilité du projet.

### 1.4. Notation par domaine

| Domaine | Note | Commentaire |
|---|---:|---|
| Sécurité applicative | **2 / 20** | Prise de contrôle anonyme, secrets versionnés, 0 CSRF sur 22 formulaires, 0 limitation de débit |
| Conformité fonctionnelle à la vision | **5 / 20** | Ni vente, ni classements, ni gestion des genres, ni modération, ni versements |
| Modèle de données | **12 / 20** | Bon socle relationnel, mais taxonomie dédoublée, compteurs non maintenus, 3 schémas concurrents |
| Architecture & qualité de code | **7 / 20** | ~340 Ko de code mort, dashboards dupliqués, 2 couches d'accès BD, logique métier dans les vues |
| Design & cohérence visuelle | **11 / 20** | Direction artistique réussie côté public, mais 4 systèmes de style superposés et dashboards incohérents entre rôles |
| Accessibilité | **5 / 20** | 166 champs sans libellé programmatique, thème clair fragile, aucune gestion de `prefers-reduced-motion` |
| Performance | **6 / 20** | Tailwind CDN compilé dans le navigateur, 4 origines tierces bloquantes, requêtes N+1 |
| Exploitabilité / industrialisation | **4 / 20** | Aucun test, aucune migration versionnée, aucun CI, `display_errors` actif |
| **Global** | **6,5 / 20** | Prototype avancé — pas un produit |

### 1.5. Les cinq décisions à prendre cette semaine

1. **Geler tout déploiement** et traiter les 11 vulnérabilités critiques du §3.1 (estimation : 3 à 5 jours).
2. **Faire tourner la clé de base de données et tous les secrets**, puis purger `.env` de l'historique Git.
3. **Décider le modèle commercial** : catalogue à l'unité (achat/téléchargement) + abonnement, ou l'un des deux. Le code actuel amorce les deux sans terminer aucun. Recommandation en §6.2.
4. **Choisir une seule chaîne de rendu** : Tailwind compilé localement, et suppression des trois autres systèmes de style.
5. **Nommer un propriétaire de la donnée** : sans une définition officielle et défendable de ce qu'est « une écoute », le baromètre ne sera jamais opposable.

---

## 2. Périmètre et méthode

### 2.1. Ce qui a été examiné

| Catégorie | Éléments |
|---|---|
| Configuration | `.env`, `.env.production`, `config/env.php`, `config/constants.php`, `config/database.php`, `config/payment.php`, `.htaccess`, `.gitignore` |
| Authentification | `includes/auth.php`, `includes/advanced-auth.php`, `login.php`, `register.php`, `admin/login.php`, `admin/reset-password.php`, `logout.php`, `security-settings.php` |
| Accès aux données | `includes/database.php` (48 Ko, 51 fonctions), `config/database.php`, `includes/blog-manager.php` |
| API | 22 endpoints sous `api/` |
| Dashboards | `admin-dashboard.php`, `artist-dashboard.php`, `user-dashboard.php`, `pages/admin/dashboard.php`, `pages/artist/dashboard.php`, `admin/dashboard-tabs/*` (8 fichiers), `admin/dashboard-modals/modals.php` |
| Chaîne de publication | `upload.php`, `artist-add-song.php`, `artist-add-album.php`, `admin-add-song.php`, `admin-add-album.php` |
| Paiement | `includes/payment.php`, `premium.php`, `premium-payment.php`, `wallet.php`, `api/payment*.php` |
| Données | `database/tchadok.sql`, 14 scripts `sql/`, `sample-data.sql` |
| Front | `assets/css/*` (37), `assets/js/*` (30), `includes/header-tailwind.php`, `includes/footer-tailwind.php`, `includes/admin-shell-header.php`, `sw.js` |
| Outillage exposé | `install.php`, `admin/execute-sql.php`, `admin/update-passwords.php`, `api/docs.php`, `validate-placeholders.php` |

### 2.2. Ce qui n'a pas pu être vérifié

- **Aucune exécution runtime** : XAMPP n'a pas été démarré, la base n'a pas été interrogée. Les constats portent sur le code et le schéma. Les comportements décrits comme « exploitables » l'ont été déduits par lecture ; ils doivent être confirmés par un test sur environnement isolé avant tout reporting externe.
- **Aucune revue des dépendances tierces** : le projet n'a ni `composer.json` ni `package.json`. Tout est chargé depuis des CDN, donc non versionné et non auditable.
- Contenu réel de la base de production (volumétrie, qualité des données, artistes présents).

### 2.3. Échelle de sévérité utilisée

| Niveau | Définition |
|---|---|
| **P0 — Critique** | Compromission totale, perte de données ou fraude financière possible sans authentification. Blocage de mise en production. |
| **P1 — Élevé** | Compromission d'un compte, contournement d'un contrôle métier, ou fuite de données personnelles. |
| **P2 — Moyen** | Défaut exploitable sous conditions, ou défaut structurel qui produira des incidents. |
| **P3 — Faible** | Dette technique, incohérence, ou écart aux bonnes pratiques sans exploitation directe. |

---

## 3. Sécurité

### 3.1. Vulnérabilités critiques (P0)

#### P0-1 — Création anonyme d'un compte administrateur

**Fichier :** `api/user.php` (action `create`, ~lignes 85-140)
**Exposition :** Internet, sans authentification

L'endpoint n'effectue **aucune vérification de session**. Le champ `user_type` est accepté depuis la requête et validé uniquement contre la liste `['fan','artist','admin']` :

```php
$defaultPassword = password_hash('12345678', PASSWORD_DEFAULT);
$normalizedType = in_array($userType, ['fan','artist','admin'], true) ? $userType : 'fan';
// ...
if ($normalizedType === 'admin' && tableExists('admins')) {
    $adminStmt = $pdo->prepare("
        INSERT INTO admins (user_id, role, permissions, created_at)
        VALUES (?, 'admin', ?, NOW())
    ");
}
```

Un attaquant crée un administrateur, se connecte avec `12345678`, et dispose de la console complète. **Impact : perte de contrôle de la plateforme, de la base clients et des données financières.**

**Correction :** exiger `isLoggedIn() && isAdmin()` en tête de fichier ; retirer `user_type` des champs acceptés depuis le client pour la promotion admin (la promotion admin doit être une action séparée, journalisée, réservée au rôle `super_admin`) ; supprimer tout mot de passe par défaut au profit d'un jeton d'invitation à usage unique envoyé par e-mail.

---

#### P0-2 — Suppression anonyme de comptes, à l'unité et en masse

**Fichier :** `api/user.php` (actions `delete` et `bulk`, ~lignes 213-285)

```
GET  /api/user.php?action=delete&id=42
POST /api/user.php   action=bulk&operation=delete&users[]=2&users[]=3&…
```

Seul l'identifiant `1` est protégé. Les opérations `activate`, `deactivate`, `verify` sont également ouvertes — un attaquant peut **désactiver l'ensemble des comptes** ou **valider en masse des e-mails non vérifiés**.

**Impact : destruction irréversible de la base utilisateurs.** La suppression casse au passage les playlists et détache les transactions (`UPDATE transactions SET user_id = NULL`), ce qui rend la reconstruction comptable impossible.

**Correction :** authentification + rôle ; passer en suppression logique (`deleted_at`) plutôt que `DELETE` ; journal d'audit obligatoire (qui, quoi, quand, depuis quelle IP) ; confirmation forte pour les actions de masse ; plafond sur le nombre d'identifiants par appel.

---

#### P0-3 — Validation anonyme de transactions financières

**Fichier :** `api/transaction.php` (actions `create`, `approve`, `reject`, `delete`)

```
GET /api/transaction.php?action=approve&id=57
→ UPDATE transactions SET status = 'completed' WHERE id = ? AND status = 'pending'
```

`create` permet d'injecter une transaction arbitraire (`user_id`, `amount`, `status` tous fournis par le client), `approve` de la passer en `completed`, `delete` de la faire disparaître. Le chiffre d'affaires affiché sur le dashboard admin (`SELECT SUM(amount) FROM transactions WHERE status = 'completed'`) devient **entièrement pilotable depuis l'extérieur**.

**Impact : fraude, blanchiment de commandes fictives, corruption de la comptabilité, et destruction de la piste d'audit.**

**Correction :** authentification + rôle finance ; **interdire toute écriture de `status` depuis un client** — seul un callback signé de l'opérateur (Airtel/Moov) doit faire passer une transaction en `completed` ; rendre les transactions **immuables** (pas de `DELETE`, pas de `UPDATE` du montant : uniquement des écritures d'annulation/remboursement) ; idempotence sur la référence opérateur.

---

#### P0-4 — Réinitialisation de masse des mots de passe

**Fichier :** `admin/update-passwords.php` (lignes 8-40)

```php
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
// ...
$newPasswordHash = password_hash('12345678', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE 1");
```

Trois défauts cumulés : le garde-fou porte sur `$_SESSION['admin_id']`, **variable que le flux d'authentification réel ne définit jamais** (`includes/auth.php` pose `user_type` et `admin_role`) — le contrôle est donc incohérent avec le reste de l'application ; il n'y a **aucun jeton CSRF**, donc un administrateur authentifié visitant une page hostile déclenche l'action ; et l'action elle-même est une **mise à zéro de la sécurité de tous les comptes** vers un mot de passe public.

Le même comportement est dupliqué dans `admin/execute-sql.php` (branche `update_all_passwords`, lignes ~70-77) **et** exposé depuis la carte « Maintenance » du dashboard admin (`admin-dashboard.php` ligne ~80).

**Correction : supprimer purement et simplement ces deux fichiers.** Aucun besoin d'exploitation ne justifie leur existence. Un besoin légitime de réinitialisation se traite par le flux « mot de passe oublié » existant (`admin/reset-password.php`, qui est correctement écrit).

---

#### P0-5 — Console SQL web et outil de création d'admin

**Fichier :** `admin/execute-sql.php` (13,4 Ko), référencé depuis le dashboard admin

Outre `update_all_passwords`, ce fichier expose `create_admin` (crée `admin_tchadok / 12345678`), `add_missing_columns` (exécute des `ALTER TABLE`), et ouvre sa propre connexion PDO avec **identifiants en dur** :

```php
$pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
```

Aucun jeton CSRF. Le contrôle d'accès repose uniquement sur `isAdmin()` — or P0-1 permet d'obtenir ce statut anonymement. La chaîne complète est donc : appel anonyme → admin → console SQL → contrôle du serveur de base.

**Correction :** supprimer le fichier. Les migrations de schéma se font par scripts versionnés exécutés hors du serveur web (§5.4).

---

#### P0-6 — Installeur public créant un administrateur à identifiants publiés

**Fichier :** `install.php` (29,8 Ko, racine web, aucune authentification, aucun verrou d'installation)

```php
$config = ['host'=>'localhost','username'=>'root','password'=>'','database'=>'tchadok_db'];
// ...
['admin', 'admin@tchadok.td', 'Admin', 'Systeme', 'admin', TRUE, TRUE],
$stmt->execute(array_merge($user, [password_hash('password123'), TRUE]));
// ...
<li><strong>Admin:</strong> admin@tchadok.td / password123</li>
```

Le fichier est accessible à `https://<domaine>/install.php` par n'importe qui, se connecte en `root` sans mot de passe, et **affiche les identifiants administrateur dans la page de résultat**. Il crée par ailleurs une base `tchadok_db` **distincte** de la base applicative `tchadok`, avec un schéma `users` incompatible (colonne `user_type`, pas de colonne `password`) et une table `payments` absente du schéma de référence — c'est le **troisième schéma concurrent** du projet (§5.1).

Note : l'appel `password_hash('password123')` avec un seul argument lève une `ArgumentCountError` sous PHP 8 — le script échoue donc probablement en cours de route, ce qui n'atténue pas le risque d'exposition mais laisse la base dans un état partiel.

**Correction :** supprimer `install.php`, `validate-placeholders.php` et `api/docs.php` de la racine web. Documenter l'installation dans `database/README.md` (import du dump + exécution des migrations).

---

#### P0-7 — Secrets versionnés dans Git

**Constat :** `git ls-files` confirme que **`.env` est suivi par Git**, malgré sa présence dans `.gitignore` (un fichier déjà indexé n'est pas désindexé par `.gitignore`). `.env.production` est explicitement exclu de l'ignore (`!.env.production`) et contient :

```
DB_USERNAME=dansia
DB_PASSWORD=dansia
ICECAST_ADMIN_PASSWORD=CHANGE-ME
```

Par ailleurs, des secrets sont écrits **en dur dans le code applicatif** :

| Fichier | Ligne | Secret |
|---|---:|---|
| `config/database.php` | 11-12 | `dansia` / `dansia` |
| `config/constants.php` | 108 | `JWT_SECRET = 'tchadok_jwt_secret_key_2024'` |
| `config/constants.php` | 82 | `SMTP_PASSWORD = 'your_email_password'` |
| `admin/execute-sql.php` | 22 | `dansia` / `dansia` |
| `admin/update-passwords.php` | 27, 42 | `dansia` / `dansia` |
| `install.php` | 13 | `root` / *(vide)* |

**Correction, dans cet ordre :**
1. **Considérer tous ces secrets comme compromis** et les faire tourner (mot de passe MySQL, `JWT_SECRET`, `APP_KEY`, mot de passe admin Icecast, identifiants SMTP).
2. `git rm --cached .env` puis commit ; remplacer `.env.production` par un `.env.example` **sans valeur réelle**.
3. Purger l'historique (`git filter-repo --path .env --invert-paths`) et forcer la rotation des accès de tous les collaborateurs du dépôt.
4. Supprimer `config/database.php` (fichier mort, §4.2) et faire lire tous les secrets par `env()` uniquement.

---

#### P0-8 — Compte super-administrateur livré dans le dump SQL

**Fichier :** `database/tchadok.sql`

```sql
INSERT INTO `users` (…) VALUES
(1, 'admin', 'admin@tchadok.td', '$2y$12$44Eg1vk9c72lCYqRWv9SG…', …),
(2, 'user_demo', 'user@tchadok.td', '$2y$12$44Eg1vk9c72lCYqRWv9SG…', …);

INSERT INTO `admins` (…) VALUES (1, 1, 'super_admin', '["all"]', …);
```

Les deux comptes partagent **le même hash bcrypt**, ce qui indique un mot de passe unique et connu. `sql/README-COMPTES-TEST.md` confirme la pratique : « Tous les comptes utilisent le même mot de passe […] Le mot de passe est simple et connu publiquement ». Toute installation issue de ce dump démarre avec un `super_admin` à identifiants publics.

**Correction :** retirer les `INSERT` de comptes du dump de référence ; faire créer le premier administrateur par une commande CLI interactive (hors web) qui exige un mot de passe fort ; placer les comptes de démonstration dans un `seeds/demo.sql` séparé, jamais importé en production.

---

#### P0-9 — Absence totale de protection CSRF sur les formulaires sensibles

**Constat :** 22 fichiers traitent des requêtes `POST`. Le jeton CSRF (`verifyCSRFToken()`, pourtant disponible dans `includes/functions.php`) n'est vérifié que dans **4** d'entre eux (`admin-blog.php`, `admin/reset-password.php`, `api/blog/share.php`, `api/blog/upload-image.php`).

**Sans protection :** `login.php`, `register.php`, `upload.php`, `artist-add-song.php`, `artist-add-album.php`, `admin-add-song.php`, `admin-add-album.php`, `admin-manage-radio.php`, `admin-playlists.php`, `admin-podcasts.php`, `edit-profile.php`, `settings.php`, `security-settings.php`, `create-playlist.php`, `premium-payment.php`, `contact.php`, `admin/execute-sql.php`, `admin/update-passwords.php`.

Combiné à l'en-tête `Access-Control-Allow-Origin: *` posé **globalement dans `.htaccess`** (donc sur toutes les réponses, pages HTML incluses) et à l'absence de `SameSite` sur les cookies (§3.2), la surface CSRF est maximale.

**Correction :** vérification centralisée — un `require_once 'includes/csrf-guard.php'` en tête de chaque point d'entrée qui, sur `POST`/`PUT`/`DELETE`, rejette en `419` si le jeton est absent ou invalide. Ajouter `csrfField()` dans tous les formulaires. Poser `session.cookie_samesite = 'Lax'`.

---

#### P0-10 — Contenu payant téléchargeable sans achat

**Constat :** les fichiers audio sont stockés dans `uploads/audio/` sous la racine web, et `.htaccess` ne protège pas ce répertoire (il déclare même `AddType audio/mpeg mp3` et un cache d'une semaine pour `audio/mpeg`). `api/track.php` renvoie le chemin `audio_file` **pour n'importe quel titre**, sans vérifier `is_free`, `price` ni l'existence d'un achat — avec `Access-Control-Allow-Origin: *`.

Le nom de fichier est généré par `uniqid()` (`includes/functions.php` ligne 232) : horodaté, donc énumérable.

**Impact : l'intégralité du catalogue payant est gratuite.** Le modèle économique est vide de sens tant que ce point n'est pas traité.

**Correction :**
1. Déplacer `uploads/` **hors de la racine web** (`../storage/tchadok/`).
2. Servir l'audio par un contrôleur `download.php` / `stream.php` qui vérifie : titre approuvé, et (gratuit **ou** achat `completed` de l'utilisateur **ou** abonnement actif), puis émet le fichier via `X-Sendfile` / `mod_xsendfile` avec un **jeton signé à durée courte**.
3. Séparer physiquement le **extrait de prévisualisation** (30 s, public) du **master** (protégé). La colonne `preview_file` existe déjà et n'est pas exploitée.
4. Ajouter `uploads/.htaccess` avec `php_flag engine off` et `Deny from all` en défense en profondeur, tant que le déplacement n'est pas fait.

---

#### P0-11 — Compteur d'écoutes ouvert et falsifiable

**Fichier :** `api/stream.php` (fonction `recordStream`)

Aucune authentification, aucun jeton CSRF (l'en-tête `X-CSRF-Token` est annoncé dans `Access-Control-Allow-Headers` mais **jamais vérifié**), aucune limitation de débit, aucune déduplication, `Access-Control-Allow-Origin: *`. Les champs `duration`, `country`, `source` et `city` proviennent du client.

```php
$durationPlayed = max(0, (int)($payload['duration'] ?? 0));
$country = $payload['country'] ?? null;
$city    = $payload['city'] ?? null;
```

`completed` est calculé à 60 % de la durée déclarée du titre — mais la durée du titre elle-même est **saisie manuellement par l'artiste** (`artist-add-song.php` ligne 33, `duration` depuis `$_POST`), jamais lue depuis le fichier audio. Un artiste déclarant une durée de 1 seconde voit chaque impression comptée comme écoute complète.

**Impact : le baromètre est manipulable par n'importe qui, y compris par les artistes eux-mêmes, avec un gain financier direct si la rémunération est indexée sur les écoutes.**

**Correction — cahier des charges minimal d'une écoute opposable :**

| Règle | Mise en œuvre |
|---|---|
| Écoute = lecture réelle ≥ 30 s | Seuil serveur, pas client |
| Durée du titre de confiance | Extraction serveur via `getID3`/`ffprobe` à l'upload ; colonne `duration` en lecture seule pour l'artiste |
| Session de lecture signée | Jeton HMAC émis par le serveur à l'ouverture du titre, à durée de vie courte, consommé une fois |
| Déduplication | Une écoute comptée par (utilisateur \| empreinte anonyme, titre, fenêtre de 60 min) |
| Géolocalisation de confiance | Résolution serveur de l'IP (base GeoIP), jamais le champ client |
| IP de confiance | Ne lire `X-Forwarded-For` que si la requête vient d'un proxy déclaré (§3.2) |
| Limitation de débit | Par IP et par compte, avec seuils distincts |
| Détection d'anomalies | Écarts de ratio écoutes/auditeurs uniques, rafales, concentration d'IP ; mise en quarantaine avant consolidation |
| Séparation brut / certifié | `streams` = brut ; `streams_certified` = après filtrage ; **seul le certifié alimente le classement public** |

---

### 3.2. Vulnérabilités élevées (P1)

#### P1-1 — Double colonne de mot de passe : le maillon faible l'emporte

**Fichiers :** `includes/auth.php` lignes 44-51, `includes/database.php` fonction `checkAdminCredentials`

La table `users` porte **deux** colonnes, `password` **et** `password_hash`, toutes deux `NOT NULL`. L'authentification accepte l'une **ou** l'autre :

```php
if (!empty($user['password_hash']) && verifyPassword($password, $user['password_hash'])) {
    $passwordValid = true;
} elseif (!empty($user['password']) && verifyPassword($password, $user['password'])) {
    $passwordValid = true;
}
```

Toute divergence entre les deux colonnes crée un second mot de passe valide permanent. Or les scripts de maintenance écrivent tantôt l'une (`execute-sql.php` : `UPDATE users SET password = ?`), tantôt l'autre (`update-passwords.php` : `UPDATE users SET password_hash = ?`) : **après passage de `update-passwords.php`, l'ancien mot de passe reste valide via la colonne `password`**, et inversement. `register.php` écrit la même valeur dans les deux, ce qui masque le problème sur les comptes récents.

**Correction :** migration en trois temps — (1) recopier `password_hash` vers `password` là où elles diffèrent après décision explicite, (2) faire basculer tout le code sur `password_hash` uniquement, (3) `ALTER TABLE users DROP COLUMN password`. Ajouter un `password_changed_at` et invalider les sessions à chaque changement.

---

#### P1-2 — « Se souvenir de moi » : déni de service et jeton partagé

**Fichier :** `includes/auth.php` lignes 170-199

```php
$stmt = $this->db->prepare(
    "SELECT u.* … WHERE u.remember_token IS NOT NULL AND u.is_active = 1"
);
$stmt->execute();
while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (verifyPassword($token, $user['remember_token'])) { … }
}
```

Le jeton n'est pas indexable : la fonction **charge tous les utilisateurs porteurs d'un jeton et exécute un `bcrypt` (coût 12) sur chacun**, à chaque requête d'un visiteur non connecté présentant un cookie. Avec 10 000 comptes, une seule requête consomme plusieurs minutes de CPU. **C'est un vecteur de déni de service trivial** (`curl -H "Cookie: remember_token=x"` en boucle).

Défauts associés : une seule colonne `remember_token` par utilisateur (impossible d'avoir plusieurs appareils, pas de révocation ciblée) ; cookie posé avec `secure = false` et **sans `SameSite`** (ligne 141) ; pas de rotation du jeton à l'usage, donc un jeton volé reste valide 30 jours.

**Correction :** schéma **sélecteur + vérificateur** dans une table `remember_tokens (id, user_id, selector UNIQUE, validator_hash, expires_at, device_label, last_used_at, revoked_at)`. Recherche par `selector` indexé, puis un seul `hash_equals` sur le vérificateur. Rotation à chaque usage, `Secure` + `HttpOnly` + `SameSite=Lax`, et écran « appareils connectés » côté utilisateur.

---

#### P1-3 — Fixation de session

**Constat :** `session_regenerate_id()` n'apparaît **nulle part** dans le projet (0 occurrence). L'identifiant de session est conservé à l'identique entre l'état anonyme et l'état authentifié, y compris pour un administrateur.

Par ailleurs `startSecureSession()` (`includes/functions.php` lignes 15-22) ne pose ni `session.use_strict_mode` (qui rejetterait un identifiant non émis par le serveur), ni `session.cookie_samesite`, et force `session.cookie_secure = 1` **inconditionnellement** — ce qui casse la session sur tout environnement HTTP non-localhost.

**Correction :** `session_regenerate_id(true)` immédiatement après validation du mot de passe et après tout changement de privilège ; `session.use_strict_mode = 1` ; `cookie_samesite = 'Lax'` ; `cookie_secure` conditionné à `isProduction()` ou à la détection HTTPS ; renommer le cookie (`TCHADOKSESSID`) pour ne pas annoncer PHP ; expiration inactive courte pour les sessions admin (15-30 min) distincte des sessions fan.

---

#### P1-4 — Aucune limitation de débit, aucun verrouillage de compte

**Constat :** 0 occurrence de `rate_limit`, `throttle` ou équivalent dans l'ensemble du projet. `login.php` et `admin/login.php` acceptent un nombre illimité de tentatives. `register.php` permet la création massive de comptes. `api/search.php` et `api/stream.php` sont sans plafond.

Le module `includes/advanced-auth.php` **contient** les fonctions attendues (`logLoginAttempt`, `isAccountLocked`, `getRecentFailedAttempts`, `detectSuspiciousLogin`) — mais elles ne sont **jamais appelées par le flux de connexion**, et leur couche de persistance est factice (P1-5).

**Correction :** table `login_attempts (identifier, ip, success, created_at)` indexée ; verrouillage progressif (5 échecs → 15 min, 10 → 1 h) par couple identifiant/IP ; limitation de débit générique par IP sur les endpoints publics (Redis ou table avec fenêtre glissante) ; captcha au-delà d'un seuil sur `register.php` et `contact.php`.

---

#### P1-5 — Le module de sécurité avancée est une simulation qui affiche de fausses données à l'utilisateur

**Fichiers :** `includes/advanced-auth.php` (19,2 Ko), `security-settings.php` (45 Ko), `assets/js/security-settings.js` (13,7 Ko)

La couche de persistance du module est explicitement factice :

```php
function storeRememberToken($userId, $selector, $hashedToken, $expires) {
    return true;                       // Simulation
}
function getRememberToken($selector) {
    return ['user_id' => 1, 'hashed_token' => password_hash('dummy_token', …), …];
}
function getRecentFailedAttempts($identifier, $minutes) {
    return [];                         // ⇒ isAccountLocked() renvoie toujours false
}
function getRecentSuccessfulLogins($userId, $days) {
    return [['ip_address' => '192.168.1.1', …, 'location' => "N'Djamena", …]];
}
```

Aucune des tables nécessaires (`login_attempts`, `user_2fa_settings`, `remember_tokens`, `verification_codes`, `backup_codes`) n'existe dans le schéma — vérifié sur `database/tchadok.sql` et les 14 scripts `sql/`.

**Conséquences :**
- `getRememberToken()` renvoie **`user_id => 1`**, c'est-à-dire le compte `super_admin`. Si ce chemin est branché un jour sans être corrigé, le résultat est une élévation de privilège directe.
- `isAccountLocked()` renvoie systématiquement `false`.
- Un utilisateur qui active la 2FA depuis `security-settings.php` **n'est pas protégé** : le secret TOTP n'est jamais persisté.
- L'écran « connexions récentes » présente des données **fabriquées** (`192.168.1.1`, `N'Djamena`) comme historique de sécurité réel.

Ce dernier point dépasse la question technique : afficher à un utilisateur un faux journal de sécurité et une fausse 2FA est un problème de **loyauté de l'information**, susceptible d'engager la responsabilité de l'éditeur.

**Correction immédiate :** retirer `security-settings.php` de la navigation et afficher un état « fonctionnalité en cours de déploiement » plutôt que des données inventées. Puis implémenter réellement : tables de persistance, 2FA TOTP vérifiée, codes de secours à usage unique, journal de connexions alimenté par le flux réel.

---

#### P1-6 — Injection SQL dans les onglets du dashboard admin

**Fichiers :** `admin/dashboard-tabs/users.php` (l. 9-12), `artists.php` (l. 9), `music.php` (l. 14-18), `payments.php` (l. 61), `playlists.php` (l. 17), `analytics.php` (l. 15-17, 65)

```php
$search = $_GET['search'] ?? '';
$whereClause = $search
    ? "WHERE first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%'"
    : '';
$users = $pdo->query("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
```

Concaténation directe de `$_GET['search']` et de `$_GET['artist']` dans des requêtes exécutées par `query()`. Ces fichiers représentent ~130 Ko et **ne sont inclus par aucun point d'entrée** (vérifié : seul `admin/dashboard-modals/modals.php` est inclus, depuis `admin-dashboard.php` l. 695). Ils sont donc actuellement inertes — mais présents dans le dépôt, prêts à être réactivés, et accessibles en direct par URL.

**Correction :** supprimer le répertoire `admin/dashboard-tabs/`. Réécrire les onglets nécessaires en requêtes préparées, avec `LIMIT`/`OFFSET` castés en entier et `ORDER BY` choisi dans une liste blanche.

---

#### P1-7 — Contenu non modéré publié au catalogue public

**Fichier :** `includes/database.php` lignes 101, 269, 304, 339, 675, 745, 927

```sql
WHERE a.status = 'approved' OR a.status = 'draft'
```

Huit requêtes du catalogue public incluent les contenus en `draft`. Or `upload.php` (l. 137) crée les albums directement en `draft`, et `admin-add-song.php` / `admin-add-album.php` proposent `draft` comme statut de sortie. Un artiste peut donc **publier au catalogue public sans aucune revue**, quel que soit le contenu déposé — y compris une œuvre dont il n'a pas les droits.

Symétriquement, il n'existe **aucun écran de modération** : les titres insérés en `pending` par `artist-add-song.php` n'ont aucun chemin vers `approved`. Recherche exhaustive : zéro `UPDATE … SET status = 'approved'` dans le projet. Le champ `status` est décoratif, et la table `reports` (signalements) n'est lue ni écrite nulle part.

**Correction :** retirer `draft` de toutes les requêtes publiques ; construire une file de modération (§6.4) ; interdire `draft` et `approved` comme valeurs soumissibles par un artiste (seule transition autorisée : `draft → pending`) ; journaliser chaque décision de modération avec son auteur et son motif.

---

#### P1-8 — Publication d'un titre par URL arbitraire, sans validation de fichier

**Fichiers :** `artist-add-song.php` lignes 62-64 et 82-84, `artist-add-album.php` lignes 56-58

```php
$audioUrl = trim($_POST['audio_file_url'] ?? '');
if ($audioUrl) { $audioPath = $audioUrl; }
```

Une chaîne libre issue du `POST` est stockée telle quelle comme chemin du média, **court-circuitant entièrement** le contrôle d'upload. Elle est ensuite rendue dans les pages publiques et renvoyée par `api/track.php`. Selon les points de rendu, cela ouvre l'injection de contenu (`javascript:`, `data:`), le pointage vers un hôte tiers hostile, et la publication de contenus hors plateforme présentés comme du catalogue Tchadok.

Le contrôle d'upload lui-même est insuffisant (`includes/functions.php` lignes 208-244) : **extension seule**, aucune vérification du type MIME réel (`finfo`), aucune inspection de conteneur audio, aucun ré-encodage des images.

**Correction :** supprimer les champs `*_file_url` des formulaires artiste (les réserver, si nécessaire, à un import administrateur avec liste blanche de domaines) ; valider le type réel par `finfo_file` **et** par lecture d'en-tête de conteneur ; ré-encoder systématiquement les pochettes par GD/Imagick ; stocker hors racine web avec un nom aléatoire (`random_bytes`) et non `uniqid()`.

---

#### P1-9 — Un artiste peut rattacher un titre à l'album d'un autre artiste

**Fichier :** `artist-add-song.php` lignes 32 et 91-97

`$_POST['album_id']` est casté en entier puis inséré **sans vérifier que l'album appartient à l'artiste connecté**. Référence directe à objet non contrôlée : un artiste peut insérer ses titres dans la discographie d'un confrère — avec effet direct sur l'attribution des écoutes, des ventes et des revenus.

**Correction :** `SELECT id FROM albums WHERE id = ? AND artist_id = ?` avant insertion, et rejet explicite sinon. Généraliser : **tout identifiant reçu d'un client doit être revalidé contre la propriété du compte**. Le même contrôle manque sur `create-playlist.php` et les actions de `api/playlists.php` à revoir.

---

#### P1-10 — Création libre de genres par les artistes

**Fichier :** `upload.php` lignes 81-85

```php
if ($customGenre && !$genreId) {
    $stmt = $db->prepare("INSERT INTO genres (name, name_french, is_active, created_at) VALUES (?, ?, 1, NOW())");
    $stmt->execute([$customGenre, $customGenre]);
}
```

C'est la **seule** écriture sur la table `genres` de tout le projet. Il n'existe **aucune interface d'administration des genres** — ni création, ni modification, ni fusion, ni désactivation (0 occurrence de `UPDATE genres` / `DELETE FROM genres`). La table n'est de plus **pas alimentée par le dump** : sur une installation neuve, elle est vide.

Résultat : la taxonomie nationale du baromètre est construite par saisie libre des artistes, immédiatement active, sans dédoublonnage (« Saï », « sai », « Saï moderne » deviennent trois genres). Toute statistique par genre est structurellement inexploitable.

**Correction :** retirer cette écriture ; réserver la taxonomie à l'administration (§6.3) ; permettre à l'artiste de **proposer** un genre (file de validation) sans l'activer ; livrer un référentiel initial de genres tchadiens en données de référence.

---

#### P1-11 — Inscription artiste sans aucune vérification

**Fichier :** `register.php` lignes 20-115

Le champ `user_type` du formulaire suffit à créer une ligne `artists` active (`is_active = 1`) immédiatement. Aucune vérification d'identité, aucun justificatif, aucune validation administrateur, et `email_verified = 0` sans qu'aucun e-mail de vérification ne soit envoyé — le drapeau n'est jamais contrôlé à la connexion.

Pour une plateforme qui **encaisse de l'argent pour le compte de tiers** et publie des œuvres protégées, c'est un risque juridique direct : usurpation d'identité d'artiste, dépôt d'œuvres dont le déposant n'a pas les droits, versements vers un bénéficiaire non identifié.

**Correction :** séparer « compte fan » (ouvert) de « compte artiste » (dossier soumis à validation : pièce d'identité, justificatif de titularité des droits, coordonnées mobile money vérifiées, contrat de distribution accepté). Ajouter la vérification d'e-mail obligatoire avant toute publication. Voir §6.5.

---

#### P1-12 — Fuite de données personnelles par API publique

**Fichier :** `api/user.php` (actions `list` et `get`)

`GET /api/user.php?action=list` renvoie, sans authentification, jusqu'à 100 utilisateurs avec `email`, `country`, `city`, statut premium et date de création ; `action=get` ajoute `phone` et `last_login`.

**Impact :** constitution d'une base de prospection, ciblage d'hameçonnage, exposition des données personnelles des mineurs éventuels. Au regard de la protection des données (loi tchadienne n° 007/PR/2015 relative à la protection des données à caractère personnel, et RGPD pour tout utilisateur de l'UE), il s'agit d'une violation de données à notifier.

**Correction :** authentification + rôle ; principe de minimisation (ne renvoyer que les champs nécessaires à l'écran appelant) ; pagination plafonnée ; journalisation des accès aux données personnelles.

---

#### P1-13 — Confiance aveugle aux en-têtes d'IP cliente

**Fichiers :** `includes/auth.php` lignes 155-165, `includes/functions.php` lignes 339-353

```php
if (!empty($_SERVER['HTTP_CLIENT_IP']))            { $ip = $_SERVER['HTTP_CLIENT_IP']; }
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  { $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; }
```

`HTTP_CLIENT_IP` et `X-Forwarded-For` sont fournis par le client et falsifiables à volonté. Ces valeurs alimentent `user_sessions.ip_address`, `streams.ip_address` et le journal API — donc la géographie du baromètre, la détection d'abus et la piste d'audit. Elles rendent également inopérante toute future limitation de débit par IP.

Les deux implémentations divergent par ailleurs (`functions.php` filtre les plages privées, `auth.php` non) : deux sources de vérité pour la même donnée.

**Correction :** une seule fonction `clientIp()`, qui ne lit `X-Forwarded-For` **que** si `REMOTE_ADDR` figure dans une liste de proxys de confiance configurée, et qui retient alors l'entrée la plus à droite non fiable. Par défaut : `REMOTE_ADDR`.

---

#### P1-14 — Divulgation de messages d'erreur techniques

**Constat :** plusieurs points d'entrée renvoient `$e->getMessage()` à l'utilisateur : `register.php` (« Erreur lors de l'inscription : … »), `artist-add-song.php`, `artist-add-album.php`, `premium-payment.php`, `admin/update-passwords.php`, et l'ensemble des API (`api/track.php`, `api/stream.php`, `api/search.php`, `api/transaction.php`).

Aggravé par `config/constants.php` ligne 179 :

```php
define('ENVIRONMENT', 'development');
define('DEBUG_MODE', ENVIRONMENT === 'development');
```

Cette constante est **écrite en dur** et écrase l'intention de `config/env.php` : quelle que soit la valeur de `APP_ENV` dans `.env`, `DEBUG_MODE` vaut `true` et `display_errors` est activé. Le `.htaccess` confirme (`php_flag display_errors On`). Une erreur PDO expose donc structure de tables, requêtes et chemins serveur.

**Correction :** faire dériver `ENVIRONMENT` de `env('APP_ENV')` ; en production, journaliser l'exception et n'afficher qu'un identifiant de corrélation ; retirer `display_errors` du `.htaccess` et gérer ce réglage par environnement.

---

### 3.3. Vulnérabilités moyennes (P2)

| Réf. | Constat | Fichier | Correction |
|---|---|---|---|
| P2-1 | `Access-Control-Allow-Origin: *` posé globalement sur **toutes** les réponses, HTML comprises, avec `GET, POST, PUT, DELETE` | `.htaccess` | Retirer du `.htaccess` ; CORS explicite par endpoint public, liste blanche d'origines, pas de credentials |
| P2-2 | Aucune politique de sécurité de contenu (CSP), pas de HSTS, `X-Frame-Options` commenté | `.htaccess` | CSP en `report-only` puis bloquante, `Strict-Transport-Security`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy` |
| P2-3 | `config/env.php` lignes 59-61 : **chaque clé du `.env` devient une constante PHP globale** — y compris `DB_PASSWORD`, `APP_KEY`, `ICECAST_ADMIN_PASSWORD` | `config/env.php` | Ne définir en constante qu'une liste blanche explicite ; les secrets restent accessibles par `env()` seulement |
| P2-4 | `sanitizeInput()` applique `htmlspecialchars` **à l'entrée**, avant insertion en base | `includes/functions.php` l. 41-46 | Échapper **à la sortie** (`e()` dans les vues) ; valider/normaliser à l'entrée. Corriger les données déjà doublement échappées |
| P2-5 | Prix négatif acceptable : `(float) $_POST['price']` sans borne | `artist-add-song.php` l. 39, `artist-add-album.php` l. 35 | Borner `[0, max]`, arrondir au FCFA, valider contre une grille tarifaire (§6.2) |
| P2-6 | `$_POST['type']` de l'album n'est pas validé contre l'énumération | `artist-add-album.php` l. 32 | Liste blanche `['single','maxi_single','ep','album']` |
| P2-7 | `$_SESSION['premium_status']` figé à la connexion ; `premium_expires_at` jamais contrôlé | `includes/auth.php` l. 103 | Résoudre le statut premium à chaque requête depuis les abonnements actifs, ou invalider la session à l'expiration |
| P2-8 | `permissions` (`'["all"]'`) stocké mais jamais lu : `isAdmin()` est binaire | `includes/functions.php` l. 89 | RBAC réel : rôles + permissions vérifiées par `can('finance.read')` etc. Cf. §6.6 |
| P2-9 | `$_SESSION` entière sérialisée en JSON dans `user_sessions.data` | `includes/auth.php` l. 131 | Ne stocker que les métadonnées utiles (IP, agent, dernière activité) |
| P2-10 | Échec de connexion BD silencieux : `$auth = null`, l'application continue | `includes/auth.php` l. 203-210 | Page d'erreur 503 explicite ; ne jamais poursuivre sans couche d'authentification |
| P2-11 | 12 `innerHTML =` dans le JS, dont `main.js` l. 683 sur des résultats de recherche | `assets/js/*.js` | `textContent` ou construction de nœuds ; échappement explicite si HTML nécessaire |
| P2-12 | `api/docs.php` (39,5 Ko) documente publiquement toute la surface d'API | `api/docs.php` | Supprimer ou placer derrière authentification admin |
| P2-13 | Énumération de comptes à l'inscription (« Cet email ou nom d'utilisateur est deja utilise ») | `register.php` l. 66 | Message neutre + confirmation par e-mail |
| P2-14 | `logs/php_errors.log` versionné dans l'arborescence web ; contient des chemins serveur | `logs/` | Journaux hors racine web ; rotation ; purge du dépôt |
| P2-15 | `sendEmail()` utilise `mail()` alors que la configuration SMTP existe et n'est pas branchée | `includes/functions.php` l. 302 | Transport SMTP authentifié (PHPMailer/Symfony Mailer), SPF/DKIM/DMARC |
| P2-16 | Aucune vérification d'e-mail effective (`verification_token` jamais utilisé) | `register.php` | Envoi + validation obligatoires avant publication ou achat |
| P2-17 | `sw.js` met en cache des réponses sans distinguer les pages authentifiées | `sw.js` | Exclure explicitement `/admin*`, `*-dashboard.php`, `/api/*` du cache |
| P2-18 | `includes/player.php` (9,1 Ko) orphelin mais présent | `includes/` | Supprimer |

### 3.4. Points faibles (P3)

- `generateSlug()` (`includes/functions.php` l. 198) supprime les caractères accentués au lieu de les translittérer : « Émission » → « mission ». Impact SEO et collisions d'URL.
- `logActivity()` (l. 323) utilise `LOG_PATH` en chemin **relatif** : la destination dépend du répertoire courant du script appelant.
- `getClientIP()` et `validateTchadianPhone()` / `generateSecureToken()` sont **déclarées deux fois** dans le projet (`functions.php` et `advanced-auth.php`) — cause de l'incident tracé dans `logs/php_errors.log` et du commit `eae497d` « Correction du conflit de déclaration de fonctions ».
- `mkdir($destination, 0755, true)` sans vérification du retour (`functions.php` l. 236).
- Aucune page d'erreur : `.htaccess` référence `404.php`, `403.php`, `500.php` — **les trois sont absents**. `show404()` inclut `pages/404.php`, également absent.
- `header('refresh:2;url=…')` au lieu d'une redirection HTTP après traitement `POST` (pas de motif POST/Redirect/GET : double soumission possible au rafraîchissement).

---

## 4. Architecture et qualité de code

### 4.1. Diagnostic général

Le projet suit un modèle « un fichier PHP par page » avec inclusion d'un en-tête et d'un pied commun. Ce modèle est légitime à petite échelle, mais il a été poussé au-delà de son point de rupture : la logique de requêtage, les règles métier, la mise en forme et le HTML cohabitent dans des fichiers de 20 à 50 Ko. `admin-dashboard.php` fait 50,6 Ko, `security-settings.php` 45 Ko, `decouvrir.php` 38,3 Ko.

Conséquences observables, pas théoriques :

- Le même KPI est calculé différemment selon l'écran. Le chiffre d'affaires vaut `SUM(transactions.amount WHERE status='completed')` dans `admin-dashboard.php` et `SUM(purchases.amount - commission WHERE payment_status='completed')` dans `artist-dashboard.php`. **Les deux chiffres ne se réconcilient pas.**
- Le prix du Premium est écrit en dur dans trois endroits avec **deux valeurs différentes** (§5.5).
- Aucun test n'est possible : il n'existe aucun point d'entrée testable isolément.

### 4.2. Deux couches d'accès aux données concurrentes

| Couche | Fichier | Utilisée par |
|---|---|---|
| `TchadokDatabase` (singleton PDO, lit `.env`) | `includes/database.php` | **toute** l'application |
| `Database` (instanciation directe, identifiants en dur) | `config/database.php` | **aucun fichier** — 0 `require` dans le projet |

`config/database.php` est du code mort qui expose des identifiants (§3.1 / P0-7) et qui a provoqué un incident réel :

```
[29-Jun-2025 16:49:08] PHP Fatal error: Cannot declare class Database,
because the name is already in use in …\includes\database.php on line 9
```

**Action : supprimer `config/database.php`.**

Conséquence indirecte : `pages/admin/dashboard.php` (32,9 Ko) appelle `$db->fetchOne(...)` et `$db->fetchAll(...)`, méthodes qui n'existent **que** sur la classe morte `Database`. Ce fichier est donc **cassé** (erreur fatale sur `$db` non défini). Idem pour `pages/artist/dashboard.php`.

### 4.3. Dashboards dupliqués et code mort

Le projet contient **deux générations complètes** de dashboards :

| Écran | Version active | Version morte |
|---|---|---|
| Admin | `admin-dashboard.php` (50,6 Ko) | `pages/admin/dashboard.php` (32,9 Ko) + `admin/dashboard-tabs/*` (8 fichiers, ~130 Ko) |
| Artiste | `artist-dashboard.php` (27,6 Ko) | `pages/artist/dashboard.php` (29,1 Ko) |
| Admin (redirection) | `admin/dashboard.php` (stub) | — |

Les versions mortes contiennent des liens vers des fichiers **inexistants** (`pages/admin/users.php`, `pages/admin/analytics.php`, `pages/artist/analytics.php`) et les injections SQL de P1-6. Elles ne sont référencées par aucun point d'entrée.

**Inventaire du code mort identifié (~340 Ko) :**

| Élément | Taille | Statut |
|---|---:|---|
| `admin/dashboard-tabs/` (8 fichiers) | ~130 Ko | Jamais inclus — contient les injections SQL P1-6 |
| `pages/admin/dashboard.php` | 32,9 Ko | Jamais atteint, cassé (`$db` indéfini) |
| `pages/artist/dashboard.php` | 29,1 Ko | Idem |
| `assets/css/main.css` | 34,4 Ko | Référencé uniquement par `includes/header.php`, lui-même orphelin |
| `includes/header.php` + `includes/footer.php` | 14,5 Ko | 0 utilisation (tout le site utilise `header-tailwind.php`) |
| 21 fichiers CSS orphelins | ~125 Ko | Jamais référencés (liste en annexe A) |
| `includes/player.php` | 9,1 Ko | 0 utilisation |
| `config/database.php` | 3,1 Ko | 0 utilisation, identifiants en dur |
| `install.php`, `validate-placeholders.php`, `api/docs.php` | ~83 Ko | Outils de développement exposés en racine web |
| `admin/execute-sql.php`, `admin/update-passwords.php` | ~18 Ko | Outils destructeurs (P0-4, P0-5) |

Soit environ **40 % de la base de code** sans fonction, dont la majorité des vulnérabilités les plus graves. La suppression de ce code est l'action au meilleur rapport bénéfice/risque du chantier.

### 4.4. Modules « désactivés » : un motif à clarifier

Sept fichiers ont été vidés et renvoient une erreur ou une page « module désactivé » :

`api/payment.php` (501), `api/payments/airtel-money.php` (501), `api/payments/moov-money.php` (501), `api/recommendations.php` (501), `api/server.php` (410), `api/data-generator.php` (410), `admin/reset-database.php`, `admin/create-test-accounts.php`, `database/install.php`, `database/generate-test-data.php`.

La démarche est saine (neutraliser des simulations trompeuses plutôt que les laisser croire à des fonctionnalités). Mais elle laisse le produit dans un état intermédiaire non documenté : le paiement est **annoncé** partout dans l'interface et **désactivé** dans le code. Il faut choisir : soit masquer les parcours concernés dans l'interface, soit les brancher.

### 4.5. Anti-patrons récurrents

**Requêtes N+1.** `artist-dashboard.php` lignes 130-150 exécute **6 requêtes** dans une boucle pour obtenir le revenu mensuel, au lieu d'un `GROUP BY` :

```php
for ($i = 5; $i >= 0; $i--) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount - commission),0) FROM purchases
                          WHERE artist_id = ? AND payment_status='completed'
                            AND DATE_FORMAT(created_at,'%Y-%m') = ?");
}
```

Même motif dans `admin/dashboard-tabs/analytics.php` : 6 mois × 3 métriques = **18 requêtes**, plus 24 requêtes pour l'activité horaire. À remplacer par une agrégation unique. Le `DATE_FORMAT(created_at, …)` sur la colonne indexée empêche par ailleurs l'usage de l'index : préférer `created_at >= ? AND created_at < ?`.

**`try` englobant l'intégralité de la page.** `admin-dashboard.php` lignes 88-175 enveloppe 14 requêtes dans un seul `try` dont le `catch` remet **tous** les compteurs à zéro. Une seule table manquante fait afficher un dashboard entièrement à zéro, sans aucun signal d'erreur pour l'exploitant. Le même motif existe dans `artist-dashboard.php`, où le `catch` substitue même des libellés de mois factices (`['Jan','Fev','Mar',…]`).

**`tableExists()` appelé à chaud.** Chaque appel interroge `information_schema`. `admin-dashboard.php` en déclenche 3 par affichage, `artist-dashboard.php` 3 également. Le schéma doit être une hypothèse garantie par les migrations, pas une question posée à chaque requête.

**Interpolation de variables dans le SQL même quand la valeur est sûre.** `artist-add-song.php` ligne 28 :
```php
$albums = $db->query("SELECT id, title FROM albums WHERE artist_id = {$artist['id']} ORDER BY title")->fetchAll();
```
La valeur provient de la base, donc l'exploitation est improbable — mais le motif normalise une pratique dangereuse et échappera tôt ou tard au contrôle.

### 4.6. Absence totale d'industrialisation

| Élément attendu | État |
|---|---|
| Gestionnaire de dépendances (`composer.json`) | Absent |
| Autoloading / espaces de noms | Absent — 100 % de `require_once` relatifs |
| Migrations de schéma versionnées | Absent — 14 scripts SQL ad hoc, ordre d'application non documenté |
| Tests (unitaires, intégration, bout en bout) | Absent |
| Intégration continue | Absent |
| Analyse statique (PHPStan, Psalm) | Absent |
| Style de code (PHP-CS-Fixer, PSR-12) | Absent |
| Journalisation structurée | Partielle et incohérente (`error_log`, `logActivity`, `api_server.log`) |
| Supervision / alertes | Absent |
| Sauvegarde | Annoncée dans `.env` (`BACKUP_ENABLED=true`), aucune implémentation |

Le fichier `.gitignore` ignore `composer.lock` et `package-lock.json` — exactement les fichiers qu'il faut versionner pour garantir des installations reproductibles.

**État du dépôt :** 178 fichiers modifiés non commités au moment de l'audit, dont `.env`. Un travail de fond sur la sécurité ne peut pas démarrer sur un arbre de travail dans cet état.

---

## 5. Base de données et modèle de données

### 5.1. Trois schémas concurrents

| Source | Contenu | Divergences |
|---|---|---|
| `database/tchadok.sql` (37,7 Ko) | Schéma de référence : 29 tables, 2 vues, 34 clés étrangères | Fait autorité |
| `install.php` (inline) | 18 tables, base `tchadok_db` | `users` sans colonne `password`, avec `user_type`; table `payments` inexistante ailleurs |
| `sql/*.sql` (14 fichiers) | Correctifs successifs `ALTER TABLE users` | `fix-password-column.sql` et `update-password-structure.sql` se contredisent sur `password` vs `password_hash` |

Aucun ordre d'application, aucun registre de migrations appliquées. Il est **impossible de déterminer avec certitude le schéma d'une installation existante**. C'est le prérequis à traiter avant toute autre correction de données.

### 5.2. Points solides du schéma

À signaler, car ce socle mérite d'être conservé :

- **34 clés étrangères** avec `ON DELETE CASCADE` cohérents.
- **Index pertinents** sur les tables de volume : `streams(track_id, created_at)`, `streams(artist_id, created_at)`, `streams(user_id, created_at)`, `purchases(created_at)`, `charts(chart_date, chart_type, item_type)`.
- **Contraintes d'unicité** métier correctes : `favorites(user_id, item_type, item_id)`, `follows(follower_id, followed_id, followed_type)`, `playlist_tracks(playlist_id, track_id)`, `transactions(reference)`.
- `DECIMAL` pour tous les montants (jamais `FLOAT`) — correct.
- `albums.type ENUM('album','ep','single','maxi_single')` : **le format de sortie demandé est déjà modélisé**.

### 5.3. Compteurs agrégés jamais maintenus

Recherche exhaustive sur l'ensemble du projet : **zéro** écriture sur les colonnes suivantes.

| Colonne | Lue par | Écrite par |
|---|---|---|
| `tracks.total_streams` | admin-dashboard, artist-dashboard, decouvrir, index, genres, artists, albums, api/track | **jamais** |
| `tracks.total_sales` | artist-dashboard, admin-dashboard | **jamais** |
| `tracks.total_downloads` | artist-dashboard | **jamais** |
| `albums.total_streams` | artist-dashboard, albums | **jamais** |
| `albums.total_sales`, `total_tracks`, `total_duration` | albums, artist-dashboard | **jamais** |
| `artists.total_streams` | admin-dashboard (classement), artists, decouvrir | **jamais** |
| `artists.total_sales`, `artists.total_earnings` | dashboards | **jamais** |
| `charts` (table entière) | — | **jamais** |
| `site_stats` (table entière) | — | **jamais** |
| `reports` (table entière) | — | **jamais** |

Les seuls `UPDATE` sur ces tables concernent `artists.stage_name` et `albums.cover_image` (`upload.php` l. 88 et 146).

**Conséquence directe : tous les KPI de tous les dashboards affichent 0**, ou la valeur figée importée par le dump. Les « classements » du dashboard admin (`ORDER BY total_streams DESC`) trient une colonne constante. Les vues `top_tracks` et `top_artists` reposent sur les mêmes colonnes.

C'est le défaut fonctionnel n° 1 du projet : **l'ensemble de l'appareil statistique est une coquille vide.**

**Correction — architecture de mesure recommandée :**

```
streams (brut, append-only)
   └─► filtrage anti-fraude (seuil 30 s, déduplication, IP de confiance, quarantaine)
         └─► streams_certified  (fait certifié, immuable)
               ├─► daily_rollups   (jour × titre × genre × région)  ◄── tâche planifiée nocturne
               │     └─► charts     (classements J / S / M / A)     ◄── tâche planifiée
               └─► compteurs dénormalisés (tracks/albums/artists)   ◄── recalcul incrémental
```

Règles :
- Les compteurs dénormalisés sont un **cache**, jamais la source de vérité. Ils doivent être reconstructibles intégralement par une commande.
- Les agrégats journaliers (`daily_rollups`) portent les dimensions du baromètre : **date, titre, album, artiste, genre, catégorie, région/ville, source, type d'auditeur**.
- Les tableaux de bord lisent les rollups, jamais la table `streams` brute.

### 5.4. Taxonomie des genres dédoublée

Deux représentations coexistent :

| Représentation | Où | Utilisée par |
|---|---|---|
| `genres` (table, avec `genre_id` en clé étrangère sur `tracks` et `albums`) | schéma | pages publiques `genres.php`, `getGenresWithStats()` |
| `artists.genres` (colonne `TEXT`, saisie libre, multi-valeurs non normalisée) | schéma | `admin/dashboard-tabs/analytics.php` (`GROUP BY a.genres`), `getColorForGenre()`, dashboard artiste |

La statistique « par genre » du dashboard admin regroupe donc sur une **chaîne libre** (`GROUP BY a.genres` produit un groupe distinct pour « Saï, Afrobeat » et pour « Afrobeat, Saï »), tandis que les pages publiques utilisent la table normalisée — **laquelle est vide sur une installation neuve** (aucun `INSERT INTO genres` dans le dump) et **alimentée en écriture libre par les artistes** (P1-10).

Il n'est structurellement pas possible de produire un baromètre par genre dans cet état.

**Correction :**
1. Faire de `genres` la **seule** source de vérité, avec un référentiel initial validé (voir §6.3 pour une proposition de nomenclature).
2. Créer `artist_genres (artist_id, genre_id, is_primary)` pour remplacer `artists.genres`.
3. Migrer la colonne texte par correspondance manuelle assistée, puis la supprimer.
4. Ajouter `genres.parent_id` pour gérer la hiérarchie **catégorie → genre → sous-genre**, demandée pour le baromètre « par genre, par catégorie ».
5. Rendre `tracks.genre_id` obligatoire à la soumission.

### 5.5. Incohérences de données et de règles

| Constat | Détail |
|---|---|
| **Prix Premium contradictoire** | `config/constants.php` l. 125-126 : 2 000 / 20 000 FCFA. `premium.php` l. 129/147 et `premium-payment.php` l. 29/35 : **2 500 / 25 000 FCFA**. Les constantes ne sont jamais lues par les pages. |
| **Aucune grille tarifaire en base** | Tous les prix (Premium, commission) sont en dur dans le code. Aucun écran d'administration. Un changement de tarif exige un déploiement. |
| **Commission déclarée à trois endroits** | `DEFAULT_COMMISSION_RATE = 15.0` (constantes), `ARTIST_COMMISSION_RATE=15` (`.env`), `artists.commission_rate DEFAULT 15.00` (schéma). Aucune n'est lue au calcul : `purchases.commission` serait à calculer, mais rien n'écrit dans `purchases`. |
| **`tracks.status` sans index** | Toutes les requêtes du catalogue public filtrent sur `status`. Index manquant. |
| **`albums.total_tracks` et `total_duration`** | Jamais recalculés à l'ajout d'un titre. |
| **Pas de suppression logique** | Aucune colonne `deleted_at`. Les `DELETE` en cascade détruisent l'historique de ventes et d'écoutes rattaché. Incompatible avec une obligation de conservation comptable. |
| **Pas de table de versements** | `transactions.type` inclut `withdrawal`, mais aucune table de demande de retrait, de seuil, de justificatif ni de statut d'exécution. Aucun code ne produit de `withdrawal`. |
| **Pas de table de panier / commande** | Un achat multi-articles est impossible à modéliser. `purchases` est une ligne par article, sans regroupement de commande, donc sans facture. |
| **Pas de gestion des droits d'auteur partagés** | Un titre a un seul `artist_id`. Featurings, producteurs, auteurs-compositeurs, labels : non modélisés. Aucun partage de royalties possible. |
| **Devise unique implicite** | `currency VARCHAR(3) DEFAULT 'XAF'` partout, mais aucun taux de change ni arrondi défini. La diaspora paiera en EUR/USD. |

---

## 6. Conformité fonctionnelle à la vision produit

Cette section confronte l'ambition énoncée — *référence de la vente de musique d'artistes tchadiens, baromètre national des écoutes par genre et catégorie* — à ce qui existe réellement dans le code.

### 6.1. Tableau de conformité

| Exigence | État | Détail |
|---|---|---|
| L'artiste peut ajouter des musiques | **Partiel** | Deux parcours divergents (`upload.php`, `artist-add-song.php`) ; durée saisie à la main ; publication par URL arbitraire (P1-8) ; pas de CSRF |
| L'artiste choisit de vendre en single / maxi single / album | **Non** | `albums.type` existe en base, mais le champ n'est ni validé ni exploité ; **un titre seul n'a aucun format de sortie**, aucune règle de prix par format, aucun bundle |
| L'artiste voit l'évolution de ses ventes | **Non** | `purchases` jamais alimentée ⇒ courbe vide |
| L'artiste voit l'évolution de ses écoutes | **Non** | `total_streams` jamais incrémenté ⇒ valeur 0 ; `streams` non agrégée |
| L'artiste voit les montants encaissés par semaine / mois | **Non** | Un seul graphique, mensuel, sur 6 mois, alimenté par une table vide. Aucune granularité hebdomadaire, aucune vue trimestre/année, aucun cumul |
| L'artiste peut retirer ses gains | **Non** | Aucune table, aucun écran, aucun flux |
| L'admin voit le classement des vues | **Non** | `ORDER BY total_streams DESC` sur colonne constante ; table `charts` vide |
| L'admin voit le classement des ventes | **Non** | Aucun écran |
| L'admin voit les statistiques des artistes par genre | **Non** | Écran mort (`dashboard-tabs/analytics.php`), et regroupement sur chaîne libre (§5.4) |
| L'admin peut ajouter des genres | **Non** | Aucune interface. Seuls les **artistes** créent des genres, sans contrôle (P1-10) |
| L'admin peut faire d'autres ajustements (tarifs, commissions, mise en avant) | **Non** | Tout est en dur dans le code |
| Modération des contenus déposés | **Non** | Aucun écran d'approbation ; `draft` publié publiquement (P1-7) |
| Paiement mobile money fonctionnel | **Non** | `includes/payment.php` contient le code cURL Airtel/Moov mais n'est appelé par **aucun** point d'entrée ; les endpoints sont désactivés (501) ; aucune clé API réelle |
| Baromètre national par genre / catégorie / région | **Non** | Inexistant, et métrique falsifiable (P0-11) |
| Dashboards professionnels et cohérents entre rôles | **Partiel** | Voir §7 |

**Synthèse : sur les 15 exigences, 2 sont partiellement couvertes, 13 ne le sont pas.** La plateforme est aujourd'hui un site vitrine avec radio et blog fonctionnels, pas une place de marché musicale.

---

### 6.2. Spécification proposée — modèle de vente par format

C'est la brique à construire en premier après la sécurisation. Proposition concrète.

#### 6.2.1. Introduire la notion de « sortie » (release)

Le modèle actuel sépare `tracks` et `albums`, ce qui empêche de vendre un single comme un produit. Ajouter une entité pivot :

```sql
CREATE TABLE releases (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  artist_id       INT NOT NULL,
  title           VARCHAR(200) NOT NULL,
  slug            VARCHAR(220) NOT NULL UNIQUE,
  format          ENUM('single','maxi_single','ep','album','compilation') NOT NULL,
  genre_id        INT NOT NULL,
  cover_image     VARCHAR(255),
  description     TEXT,
  language        VARCHAR(50),
  release_date    DATE NOT NULL,
  is_preorder     TINYINT(1) DEFAULT 0,
  price_bundle    DECIMAL(8,2),          -- prix de la sortie complète
  allow_track_buy TINYINT(1) DEFAULT 1,  -- achat au titre autorisé
  status          ENUM('draft','pending','approved','rejected','offline') DEFAULT 'draft',
  rejected_reason TEXT,
  reviewed_by     INT NULL,
  reviewed_at     DATETIME NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_releases_artist FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
  CONSTRAINT fk_releases_genre  FOREIGN KEY (genre_id)  REFERENCES genres(id),
  KEY idx_releases_status_date (status, release_date),
  KEY idx_releases_artist (artist_id),
  KEY idx_releases_genre_format (genre_id, format)
);
```

`tracks.release_id` remplace `tracks.album_id`. `albums` devient une vue de compatibilité (`WHERE format IN ('ep','album','compilation')`) le temps de la migration.

#### 6.2.2. Règles de format à faire respecter par le serveur

| Format | Titres | Durée totale indicative | Prix bundle | Achat au titre |
|---|---:|---|---|---|
| **Single** | 1 à 2 | — | Optionnel (= prix du titre) | Oui |
| **Maxi single** | 3 à 5 | < 30 min | Obligatoire, avec remise minimale vs somme des titres | Oui |
| **EP** | 4 à 7 | < 30 min | Obligatoire | Oui |
| **Album** | 8 et plus | — | Obligatoire | Configurable par l'artiste |
| **Compilation** | 8 et plus, multi-artistes | — | Obligatoire | Oui |

Ces règles doivent être **validées côté serveur** à la soumission, pas seulement suggérées dans l'interface. Un artiste ne doit pas pouvoir déclarer « album » une sortie d'un titre.

#### 6.2.3. Grille tarifaire administrée

Sortir tous les prix du code :

```sql
CREATE TABLE pricing_rules (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  scope         ENUM('track','release','subscription') NOT NULL,
  format        VARCHAR(20) NULL,          -- single, maxi_single, album…
  currency      CHAR(3) DEFAULT 'XAF',
  min_price     DECIMAL(8,2) NOT NULL,
  max_price     DECIMAL(8,2) NOT NULL,
  suggested     DECIMAL(8,2) NOT NULL,
  commission_rate DECIMAL(4,2) NOT NULL,   -- part plateforme
  active_from   DATE NOT NULL,
  active_to     DATE NULL
);
```

Valeurs de départ à débattre (ordres de grandeur pour le marché tchadien) :

| Produit | Plancher | Suggéré | Plafond |
|---|---:|---:|---:|
| Titre à l'unité | 200 FCFA | 300 FCFA | 1 000 FCFA |
| Maxi single | 750 FCFA | 1 000 FCFA | 2 500 FCFA |
| EP | 1 000 FCFA | 1 500 FCFA | 3 500 FCFA |
| Album | 1 500 FCFA | 2 500 FCFA | 6 000 FCFA |
| Premium mensuel | — | 2 500 FCFA | — |
| Premium annuel | — | 25 000 FCFA | — |

Le plancher est essentiel : il évite la guerre des prix qui détruirait la valeur perçue du catalogue tchadien, et il garantit que la commission couvre les frais mobile money (2,5 % annoncés dans `config/payment.php`, souvent davantage en pratique).

#### 6.2.4. Panier, commande et facture

```sql
CREATE TABLE orders (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  reference        VARCHAR(40) NOT NULL UNIQUE,   -- TCHK-2026-000123
  user_id          INT NOT NULL,
  subtotal         DECIMAL(10,2) NOT NULL,
  platform_fee     DECIMAL(10,2) NOT NULL,
  gateway_fee      DECIMAL(10,2) NOT NULL,
  total            DECIMAL(10,2) NOT NULL,
  currency         CHAR(3) DEFAULT 'XAF',
  status           ENUM('cart','awaiting_payment','paid','failed','cancelled','refunded') DEFAULT 'cart',
  payment_method   VARCHAR(30),
  gateway_ref      VARCHAR(100) NULL UNIQUE,      -- idempotence opérateur
  paid_at          DATETIME NULL,
  invoice_number   VARCHAR(30) NULL UNIQUE,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_id       INT NOT NULL,
  item_type      ENUM('track','release') NOT NULL,
  item_id        INT NOT NULL,
  artist_id      INT NOT NULL,
  unit_price     DECIMAL(8,2) NOT NULL,
  commission_rate DECIMAL(4,2) NOT NULL,   -- figé au moment de la vente
  commission     DECIMAL(8,2) NOT NULL,
  artist_net     DECIMAL(8,2) NOT NULL,
  KEY idx_items_artist (artist_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

Points non négociables :
- **Le prix et le taux de commission sont figés dans `order_items` au moment de la vente.** Un changement de tarif ne doit jamais réécrire l'historique.
- **`gateway_ref` en clé unique** : c'est la garantie d'idempotence face aux callbacks dupliqués des opérateurs mobile money, qui sont fréquents.
- Le passage en `paid` est déclenché **uniquement** par le callback opérateur vérifié (signature), jamais par le navigateur du client.
- La numérotation de facture est séquentielle et sans trou (obligation comptable).

#### 6.2.5. Droits d'accès et téléchargement

```sql
CREATE TABLE entitlements (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  item_type     ENUM('track','release') NOT NULL,
  item_id       INT NOT NULL,
  order_item_id INT NULL,
  source        ENUM('purchase','subscription','gift','promo') NOT NULL,
  downloads_used INT DEFAULT 0,
  max_downloads  INT DEFAULT 5,
  granted_at    DATETIME NOT NULL,
  expires_at    DATETIME NULL,
  UNIQUE KEY uq_entitlement (user_id, item_type, item_id, source)
);
```

Un seul contrôleur `media.php` sert l'audio : il vérifie le droit, journalise, décrémente le quota, et délègue au serveur web par `X-Sendfile`. C'est ce composant qui ferme la faille P0-10.

#### 6.2.6. Versements aux artistes

```sql
CREATE TABLE payouts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  artist_id      INT NOT NULL,
  period_start   DATE NOT NULL,
  period_end     DATE NOT NULL,
  gross          DECIMAL(10,2) NOT NULL,
  commission     DECIMAL(10,2) NOT NULL,
  adjustments    DECIMAL(10,2) DEFAULT 0,
  net            DECIMAL(10,2) NOT NULL,
  method         ENUM('airtel_money','moov_money','bank_transfer') NOT NULL,
  destination    VARCHAR(60) NOT NULL,       -- numéro ou IBAN, chiffré au repos
  status         ENUM('draft','approved','processing','paid','failed','on_hold') DEFAULT 'draft',
  approved_by    INT NULL,
  statement_url  VARCHAR(255) NULL,          -- relevé PDF
  paid_at        DATETIME NULL,
  UNIQUE KEY uq_period (artist_id, period_start, period_end)
);
```

Règles : seuil minimal de versement (p. ex. 10 000 FCFA), période de rétention avant éligibilité (p. ex. 30 jours, pour couvrir les remboursements), double validation humaine avant exécution, relevé détaillé téléchargeable par l'artiste. **Aucun versement ne doit pouvoir être déclenché par une seule personne.**

---

### 6.3. Spécification proposée — référentiel des genres et catégories

Le baromètre demandé s'articule « par genre, par catégorie ». Il faut donc une hiérarchie, absente aujourd'hui.

```sql
ALTER TABLE genres
  ADD COLUMN parent_id  INT NULL AFTER id,
  ADD COLUMN slug       VARCHAR(60) NOT NULL AFTER name,
  ADD COLUMN sort_order INT DEFAULT 0,
  ADD COLUMN status     ENUM('active','merged','archived') DEFAULT 'active',
  ADD COLUMN merged_into INT NULL,
  ADD UNIQUE KEY uq_genre_slug (slug),
  ADD CONSTRAINT fk_genre_parent FOREIGN KEY (parent_id) REFERENCES genres(id);
```

**Proposition de référentiel initial** — à valider par un comité éditorial, idéalement avec des professionnels du secteur et un ethnomusicologue, car c'est un acte patrimonial autant que technique :

| Catégorie | Genres |
|---|---|
| **Patrimoine & traditionnel** | Saï, Kanembou, Sara, Toubou, Ouaddaïen, Hadjarai, Griot / chant de louange, Musique de cour |
| **Musiques urbaines** | Rap tchadien, Hip-hop, Trap, Afrobeats, Afropop, Coupé-décalé, Ndombolo |
| **Variété & world** | Variété tchadienne, Soukous, Reggae, Zouk, World fusion |
| **Spirituel** | Gospel, Chant chrétien, Madh / chant soufi, Nasheed |
| **Instrumental & jazz** | Jazz sahélien, Instrumental, Kora / luth, Percussions |
| **Jeunesse & éducatif** | Comptines, Contes musicaux, Chansons pédagogiques |

Fonctions d'administration à livrer avec : création, renommage (sans casser les URL — d'où le `slug` stable), **fusion** de deux genres avec réaffectation des titres (`merged_into`), archivage, réordonnancement, couleur et icône, description éditoriale, et validation des propositions venues des artistes.

Un genre **ne doit jamais être supprimé** : il est archivé ou fusionné, sinon l'historique statistique devient incohérent.

---

### 6.4. Spécification proposée — modération et qualité du catalogue

C'est la condition de crédibilité : une plateforme « de référence » ne peut pas publier sans revue.

**File de modération** (`releases.status`, `tracks.status`) avec les transitions autorisées :

```
draft ──(artiste soumet)──► pending ──(modérateur)──┬──► approved ──(admin)──► offline
                               ▲                    └──► rejected ──(artiste corrige)──► pending
                               └────────────────────────────────────┘
```

Aucune autre transition. L'artiste ne peut pas écrire `approved`. Le catalogue public ne lit **que** `approved`.

**Contrôles à automatiser à l'upload**, avant même la revue humaine :

| Contrôle | Outil | Blocant |
|---|---|---|
| Type de fichier réel | `finfo` + lecture d'en-tête de conteneur | Oui |
| Durée, débit, canaux, fréquence | `getID3` / `ffprobe` | Oui (durée en lecture seule ensuite) |
| Qualité minimale | ≥ 128 kbit/s, ≥ 44,1 kHz | Oui |
| Niveau sonore | Normalisation LUFS (−14 LUFS cible) | Non — signalé |
| Silence / fichier corrompu | Analyse de forme d'onde | Oui |
| Empreinte acoustique | Détection de doublon dans le catalogue (Chromaprint) | Signalé au modérateur |
| Métadonnées obligatoires | Titre, genre, date, langue, crédits | Oui |
| Pochette | ≥ 1400×1400, ré-encodée, sans texte promotionnel | Oui |

**Grille de revue humaine** à formaliser (qualité audio, exactitude des métadonnées, droits déclarés, contenu explicite correctement signalé, pochette conforme), avec motif de rejet obligatoire et retour à l'artiste.

**Signalements :** la table `reports` existe et n'est pas utilisée. Ajouter le parcours complet : signalement par un utilisateur (contenu inapproprié, atteinte au droit d'auteur, spam, faux profil), file de traitement, décision motivée, procédure de contre-notification. Pour les revendications de droits d'auteur, prévoir un retrait provisoire immédiat et un délai de réponse contradictoire.

---

### 6.5. Spécification proposée — parcours d'onboarding artiste

Le parcours actuel (case à cocher au formulaire d'inscription) ne tient pas pour une plateforme qui encaisse et reverse de l'argent.

| Étape | Contenu | Bloquant pour |
|---|---|---|
| 1. Compte | E-mail vérifié, téléphone vérifié par SMS | Tout |
| 2. Identité | Pièce d'identité ou passeport, selfie de vérification | Publication |
| 3. Profil artiste | Nom de scène, biographie, genre principal, photo, réseaux | Publication |
| 4. Droits | Déclaration de titularité, attestation d'absence de cession exclusive concurrente, contrat de distribution accepté et horodaté | Publication |
| 5. Encaissement | Numéro mobile money **au nom de l'artiste vérifié**, ou coordonnées bancaires | Versement |
| 6. Fiscal | Régime, numéro d'identification si applicable | Versement au-delà d'un seuil |
| 7. Validation | Revue humaine du dossier, décision motivée | Publication |

Trois niveaux de compte artiste utiles : **Découverte** (publication limitée, pas de vente), **Vérifié** (vente activée, badge), **Partenaire** (mise en avant éditoriale, commission négociée). Cela donne une progression lisible et un levier de qualité.

---

## 7. Dashboards — audit et refonte

### 7.1. État des lieux

Cinq dashboards existent, dont deux morts. Comparaison de la coquille (chrome) utilisée :

| Écran | En-tête admin persistant | Nav secondaire ancres | En-tête collant | Graphiques | Masque la nav publique |
|---|:--:|:--:|:--:|:--:|:--:|
| `admin-dashboard.php` | Oui | Oui | Oui | **Non** | Oui |
| `admin-blog.php` | Oui | Oui | Oui | Non | Oui |
| `artist-dashboard.php` | **Non** | Oui | Oui | Oui (1) | Oui |
| `user-dashboard.php` | **Non** | Oui | Oui | Non | Oui |
| `wallet.php` | Non | **Non** | **Non** | Non | Oui |
| `settings.php` | Non | Non | Non | Non | Oui |
| `history.php` | Non | Non | Non | Non | **Non** |

**Cinq incohérences structurelles :**

1. **Les rôles ne sont pas traités à égalité.** L'administration dispose d'une coquille applicative persistante (`includes/admin-shell-header.php`, avec 8 modules) ; l'artiste et le fan n'en ont aucune. Un artiste qui quitte son dashboard pour `wallet.php` ou `edit-profile.php` **perd toute navigation contextuelle** et se retrouve sur une page isolée.
2. **La navigation secondaire n'est pas une navigation.** `includes/dashboard-secondary-nav.php` génère des ancres vers des sections de la même page (`#artist-overview`, `#artist-metrics`…). Ce n'est pas une architecture d'information : c'est une table des matières. Elle ne permet aucune profondeur, aucune URL partageable vers une sous-vue, aucun état.
3. **`history.php` n'a pas la même coquille que les autres** : il conserve la navigation publique, produisant une rupture visuelle nette au sein d'un même parcours.
4. **Le dashboard admin ne contient aucun graphique** (0 balise `<canvas>`), alors qu'il affiche une section intitulée « KPIs ». Tout est en cartes de nombres. Seul `artist-dashboard.php` charge Chart.js, pour un unique graphique.
5. **Une carte « Maintenance » du dashboard admin pointe vers `admin/execute-sql.php`** — une console SQL destructrice (P0-5) est proposée comme un module de travail ordinaire, au même niveau visuel que « Blog » ou « Playlists ».

### 7.2. Qualité de ce qui existe

Il faut le dire : la **facture visuelle** des dashboards est bonne. Grille cohérente, `rounded-3xl` + `border-white/10` + `bg-surface/75` appliqués avec régularité, hiérarchie typographique lisible (`text-xs uppercase tracking-[0.2em]` pour les libellés, `text-3xl font-semibold` pour les valeurs), système d'ombres à trois niveaux (`elev-1/2/3`), en-tête collant avec transition. Le travail de direction artistique est réel et mérite d'être conservé.

Ce qui manque n'est pas le style : c'est la **substance** (les chiffres sont à zéro, §5.3) et l'**architecture d'information** (pas de navigation, pas de profondeur, pas de filtres).

### 7.3. Défauts de conception analytique

Au-delà de la coquille, les dashboards ne respectent pas les principes d'un tableau de bord exploitable :

| Défaut | Constat | Effet |
|---|---|---|
| **Aucune comparaison temporelle** | Toutes les cartes affichent une valeur brute (« 1 240 écoutes »), jamais une évolution | Impossible de savoir si la situation s'améliore |
| **Aucun sélecteur de période** | Périodes figées dans le code (30 jours, 6 mois) | L'exploitant ne peut pas répondre à « et la semaine dernière ? » |
| **Aucun filtre** | Ni par genre, ni par région, ni par format, ni par artiste | Le dashboard admin ne permet aucune analyse |
| **Aucun export** | Ni CSV, ni PDF | Aucun reporting possible vers un partenaire ou un financeur |
| **Sommes et comptages mélangés** | « 1 240 » peut être des écoutes, des FCFA ou des comptes, sans unité explicite sur plusieurs cartes | Erreurs de lecture |
| **Zéro ambigu** | Une valeur à 0 peut signifier « aucune donnée », « aucune activité » ou « erreur de requête » (le `catch` met tout à 0, §4.5) | Perte de confiance dans l'outil |
| **Pas d'état vide travaillé** | Une plateforme jeune affiche des cartes à 0 et des graphiques plats | Impression d'outil cassé |
| **Pas de granularité hebdomadaire** | Demande explicite non couverte | — |

### 7.4. Refonte proposée — architecture d'information commune

Une **coquille unique** partagée par les trois rôles, avec navigation latérale persistante et fil d'Ariane, chaque sous-vue ayant sa propre URL :

```
/dashboard                       (redirige selon le rôle)

FAN                       ARTISTE                        ADMIN
├─ Aperçu                 ├─ Aperçu                      ├─ Aperçu
├─ Ma bibliothèque        ├─ Statistiques                 ├─ Baromètre national
│  ├─ Achats              │  ├─ Écoutes                   │  ├─ Classements
│  ├─ Favoris             │  ├─ Ventes                    │  ├─ Par genre
│  └─ Playlists           │  ├─ Audience & géographie     │  ├─ Par région
├─ Historique             │  └─ Sources de trafic         │  └─ Par format
├─ Abonnement             ├─ Mon catalogue                ├─ Catalogue
├─ Paiements & factures   │  ├─ Sorties                   │  ├─ File de modération
└─ Paramètres             │  ├─ Titres                    │  ├─ Titres & sorties
   ├─ Profil              │  └─ Nouvelle sortie           │  └─ Signalements
   ├─ Sécurité            ├─ Revenus                      ├─ Artistes
   └─ Notifications       │  ├─ Encaissements             │  ├─ Dossiers à valider
                          │  ├─ Versements                │  └─ Comptes
                          │  └─ Relevés                   ├─ Utilisateurs
                          ├─ Promotion                    ├─ Finance
                          │  ├─ Mise en avant             │  ├─ Transactions
                          │  └─ Codes promo               │  ├─ Versements à valider
                          └─ Profil artiste               │  └─ Rapports
                                                          ├─ Éditorial (blog, playlists, radio, podcasts)
                                                          └─ Configuration
                                                             ├─ Genres & catégories
                                                             ├─ Tarifs & commissions
                                                             ├─ Rôles & permissions
                                                             └─ Journal d'audit
```

Principes :
- **Une coquille, trois menus.** Le composant est unique ; seul le contenu du menu dépend du rôle. Cela garantit la cohérence et divise par trois le coût de maintenance.
- **Une URL par vue** (`/dashboard/artist/revenus/versements`), donc partageable, marquable, et traçable.
- Fil d'Ariane systématique, titre de page explicite, barre d'actions contextuelle à droite.
- Bandeau d'état global en haut (ex. : « 12 titres en attente de modération », « 3 versements à valider ») — c'est ce qui transforme un tableau de bord en outil de travail.

### 7.5. Refonte proposée — dashboard artiste

**Barre de contrôle en haut de toutes les vues statistiques :** sélecteur de période (`7 j`, `30 j`, `Cette semaine`, `Ce mois`, `Ce trimestre`, `Cette année`, `Personnalisé`), comparaison (`vs période précédente`, `vs même période N-1`), granularité (`Jour` / `Semaine` / `Mois`), filtre par sortie, par format et par genre. **L'état de cette barre se reflète dans l'URL.**

**Aperçu — 6 indicateurs, chacun avec évolution et micro-courbe :**

| Indicateur | Définition | Comparaison |
|---|---|---|
| Écoutes certifiées | Écoutes ≥ 30 s, dédupliquées | vs période précédente, en % |
| Auditeurs uniques | Comptes + empreintes anonymes distincts | vs période précédente |
| Ventes | Nombre d'articles vendus | vs période précédente |
| Chiffre d'affaires brut | Somme encaissée avant commission | vs période précédente |
| Revenu net | Après commission et frais | Cumul disponible au versement |
| Taux de conversion | Ventes / auditeurs uniques | Positionnement vs moyenne de son genre |

**Graphiques :**

1. **Évolution des écoutes** — courbe, granularité au choix, avec une seconde série en pointillé pour la période de comparaison.
2. **Montants encaissés par semaine / par mois** — histogramme empilé par format (single / maxi / EP / album) + courbe de cumul sur un second axe. C'est la réponse directe à la demande « montants encaissés par semaines, mois ». Le choix de la granularité pilote l'axe.
3. **Répartition des revenus par format** — barres horizontales (pas de camembert : illisible au-delà de trois parts et sur mobile).
4. **Ventes par titre** — tableau trié, colonnes : titre, format, écoutes, ventes, CA net, conversion, tendance 7 j.
5. **Audience géographique** — barres horizontales par ville/région tchadienne + ligne « diaspora ». Une carte choroplèthe est séduisante mais peu lisible sur les 23 provinces ; à réserver à une vue dédiée.
6. **Sources d'écoute** — recherche, playlist, radio, profil artiste, partage externe. C'est l'indicateur qui permet à l'artiste d'agir.

**Vue « Revenus » :** solde disponible / en attente de rétention / versé ; bouton « Demander un versement » (actif au-delà du seuil) ; tableau des versements avec statut et relevé téléchargeable ; détail ligne à ligne des ventes de la période.

**États vides travaillés :** pour un artiste sans écoute, afficher non pas un graphique plat mais un parcours : « Publiez votre première sortie », « Complétez votre profil (70 %) », « Partagez votre lien artiste ». Le dashboard doit être utile dès le premier jour.

### 7.6. Refonte proposée — dashboard admin

**Aperçu :** bandeau d'actions requises (modération, dossiers artistes, versements à valider, signalements), puis 8 indicateurs avec évolution : utilisateurs actifs, nouveaux comptes, artistes vérifiés, titres publiés, écoutes certifiées, ventes, CA plateforme (commissions), taux de conversion global.

**Vue « Baromètre national »** — c'est le produit différenciant, à traiter comme tel :

| Bloc | Contenu |
|---|---|
| Top 50 titres | Rang, évolution de rang (▲▼ =), titre, artiste, genre, écoutes certifiées, semaines de présence, pic de classement |
| Top 20 artistes | Même logique, avec auditeurs uniques |
| Top 20 sorties | Par format, séparément (un single ne concourt pas contre un album) |
| Classement des ventes | Distinct du classement des écoutes — ce sont deux réalités différentes et les afficher ensemble induit en erreur |
| Vue par genre | Part d'audience, croissance, nombre d'artistes actifs, revenu moyen par titre |
| Vue par catégorie | Agrégation des genres selon la hiérarchie du §6.3 |
| Vue par région | Écoutes et ventes par province, avec indice de pénétration rapporté à la population |
| Vue par format | Part des écoutes et des ventes par single / maxi / EP / album — donnée stratégique inédite au Tchad |
| Nouveaux entrants | Titres entrés au classement cette semaine |
| Export | CSV et PDF, avec mention de la méthodologie et de la date d'arrêté |

**Figer les classements.** Un classement doit être **arrêté** à une date (hebdomadaire, le lundi par exemple) et **immuable** ensuite. C'est ce qui le rend citable par la presse et les institutions. La table `charts` existe déjà pour cela. Publier un classement qui bouge en continu interdit toute référence.

**Publier la méthodologie.** Une page publique expliquant ce qu'est une écoute comptée, la fenêtre d'observation, le traitement anti-fraude et la date d'arrêté. C'est la condition pour être « la référence » plutôt qu'« un chiffre parmi d'autres ».

**Vue « Configuration » :** gestion des genres et catégories (§6.3), grille tarifaire et commissions (§6.2.3), mise en avant éditoriale, rôles et permissions (§8.3), journal d'audit consultable et filtrable.

### 7.7. Refonte proposée — dashboard fan

Le dashboard fan actuel affiche des statistiques de consommation (titres écoutés, achats, dépenses). C'est correct mais peu engageant. À compléter par : reprise de lecture, recommandations, nouveautés des artistes suivis, bibliothèque téléchargeable hors ligne, factures, et gestion d'abonnement claire (date de renouvellement, historique, résiliation en un clic — c'est une exigence de loyauté contractuelle).

---

## 8. Gouvernance, habilitations et traçabilité

### 8.1. Constat

Le contrôle d'accès du projet tient en trois fonctions booléennes (`includes/functions.php` l. 82-98) :

```php
function isArtist() { return isLoggedIn() && $_SESSION['user_type'] === USER_TYPE_ARTIST; }
function isAdmin()  { return isLoggedIn() && $_SESSION['user_type'] === USER_TYPE_ADMIN; }
function isFan()    { return isLoggedIn() && $_SESSION['user_type'] === USER_TYPE_FAN; }
```

La colonne `admins.permissions` contient `'["all"]'` et **n'est jamais lue**. `admins.role` distingue `super_admin` et `admin` mais n'est utilisé nulle part pour discriminer un droit. Résultat : **tout administrateur peut tout faire**, y compris valider des transactions financières, réinitialiser des mots de passe en masse et exécuter du SQL arbitraire.

Il n'existe par ailleurs **aucun journal d'audit** : aucune table, aucune écriture. Impossible de savoir qui a approuvé un titre, validé un versement, modifié un tarif ou supprimé un compte. Pour une plateforme qui manipule de l'argent pour le compte de tiers, c'est rédhibitoire — tant pour la conformité que pour la résolution de litiges avec les artistes.

### 8.2. Journal d'audit à mettre en place

```sql
CREATE TABLE audit_log (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_id     INT NULL,                    -- NULL = système / tâche planifiée
  actor_role   VARCHAR(30) NOT NULL,
  action       VARCHAR(60) NOT NULL,        -- release.approve, payout.execute, pricing.update…
  target_type  VARCHAR(40) NULL,
  target_id    VARCHAR(40) NULL,
  before_state JSON NULL,
  after_state  JSON NULL,
  reason       TEXT NULL,                   -- obligatoire pour rejet, blocage, ajustement
  ip_address   VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor (actor_id, created_at),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_action (action, created_at)
);
```

Actions à journaliser sans exception : connexion et échec de connexion administrateur, création/modification/suppression de compte, changement de rôle, approbation ou rejet de contenu, modification de tarif ou de commission, validation et exécution de versement, remboursement, accès à des données personnelles en masse, modification de la taxonomie, arrêté de classement.

Le journal doit être en **écriture seule** depuis l'application (aucun `UPDATE`, aucun `DELETE`), avec une politique de conservation explicite.

### 8.3. Rôles et permissions à mettre en place

Remplacer le booléen par des permissions nommées, vérifiées par un `can('finance.payout.execute')`.

| Rôle | Permissions | Justification |
|---|---|---|
| **Super-administrateur** | Toutes, y compris gestion des rôles | 1 à 2 personnes nommées, 2FA obligatoire |
| **Administrateur plateforme** | Configuration, taxonomie, tarifs, mise en avant. **Pas** de finance ni de suppression de compte | Séparation des pouvoirs |
| **Responsable finance** | Transactions, versements, remboursements, rapports. **Pas** de modification de contenu | Contrôle interne |
| **Modérateur catalogue** | File de modération, signalements. **Pas** de finance, pas de configuration | Volume de travail délégable |
| **Éditorial** | Blog, playlists, radio, podcasts, mises en avant. Lecture seule sur les statistiques | Équipe contenu |
| **Support** | Lecture des comptes, réinitialisation de mot de passe à la demande de l'utilisateur, tickets. **Pas** de suppression | Service client |
| **Analyste** | Lecture seule sur tous les tableaux de bord et exports | Partenaires, financeurs, presse |

Deux règles à inscrire dans le code :
1. **Séparation des pouvoirs sur l'argent.** La création d'un versement et son exécution doivent relever de deux comptes distincts. Le contrôle doit être technique, pas seulement procédural.
2. **2FA obligatoire** pour tout rôle disposant d'une permission d'écriture en administration (une fois la 2FA réellement implémentée, cf. P1-5).

---

## 9. Design, système de styles et cohérence visuelle

### 9.1. Ce qui fonctionne

La direction artistique est le point fort du projet :

- **Palette cohérente et identitaire** : bleu `#2F6DE0` (accent), ambre `#FFC107` (accent secondaire), fonds sombres `#0B0F17` / `#141A26` / `#1B2433`. Le rappel du bleu et du jaune du drapeau tchadien dans le logo et les halos est juste — présent sans être folklorique.
- **Typographie maîtrisée** : Sora pour les titres, Manrope pour le texte. Contraste de graisses lisible, `tracking` élargi sur les libellés en capitales.
- **Vocabulaire de composants régulier** : cartes `rounded-3xl` + bordure `white/10` + fond `surface/75`, pastilles `rounded-full`, échelle d'ombres `elev-1/2/3`, halos radiaux en arrière-plan de section. Appliqué avec une réelle constance sur les pages publiques.
- Le module blog (`assets/css/blog.css`, `admin-blog.php`, TinyMCE) est le plus abouti fonctionnellement du projet.

Ce socle doit être **formalisé**, pas refait.

### 9.2. Quatre systèmes de styles superposés

| Système | Poids | Statut |
|---|---:|---|
| **Tailwind Play CDN** (`cdn.tailwindcss.com`), config inline dans `includes/header-tailwind.php` | ~400 Ko de JS + compilation navigateur | **Actif** sur les 47 pages |
| **`assets/css/main.css`** (Bootstrap-like : `.alert`, `.btn-close`, `.page-link`, `data-bs-dismiss`) | 34,4 Ko | Chargé uniquement par `includes/header.php`, **orphelin** |
| **CSS par page** (`*-tailwind.css` actifs + 21 fichiers orphelins) | ~180 Ko dont ~125 Ko mort | Mixte |
| **Bootstrap 5 CDN** | — | Chargé par `api/docs.php` seulement |

Effet concret : `includes/functions.php` (`displayFlashMessages()`, `generatePagination()`) génère du **balisage Bootstrap** (`alert alert-dismissible fade show`, `btn-close`, `data-bs-dismiss`, `pagination page-item page-link`) alors que **Bootstrap n'est pas chargé**. Les messages flash et la pagination s'affichent donc sans style sur tout le site. On retrouve ce balisage jusque dans `artist-add-song.php` (`<div class="alert alert-success">`).

**Correction :** une seule chaîne — Tailwind **compilé** (§11.1) — et réécriture de `displayFlashMessages()` et `generatePagination()` en composants maison.

### 9.3. Le thème clair est structurellement fragile

Les jetons de couleur Tailwind sont définis avec les **valeurs du thème sombre** (`bg: '#0B0F17'`, `text: '#E6EAF2'`). Le thème clair est ensuite obtenu en **surchargeant les classes utilitaires par leur nom**, dans `assets/css/tailwind-base.css` :

```css
.theme-light .bg-surface\/60 { background-color: rgba(255,255,255,.96); }
.theme-light .bg-surface\/70 { … }
.theme-light .bg-surface\/90 { … }
.theme-light .bg-white\/5    { … }
.theme-light .bg-white\/10   { … }
.theme-light .border-white\/10 { … }
.theme-light .from-surface\/90 { … }
```

**Chaque nouvelle variante d'opacité employée dans un gabarit exige une surcharge écrite à la main.** Le fichier en contient déjà une trentaine et ne couvre pas tout : `bg-surface/75` — utilisé partout dans les dashboards — **n'a pas de surcharge**. Le thème clair des dashboards est donc déjà cassé aujourd'hui.

C'est une dette qui s'aggrave à chaque écran ajouté.

**Correction :** basculer sur des variables CSS comme valeurs des jetons Tailwind.

```css
:root {
  --c-bg: 11 15 23;          /* triplet RGB, pour permettre l'alpha Tailwind */
  --c-surface: 20 26 38;
  --c-text: 230 234 242;
  --c-muted: 164 174 194;
  --c-border: 34 43 59;
  --c-accent: 47 109 224;
}
:root.theme-light {
  --c-bg: 247 248 250;
  --c-surface: 255 255 255;
  --c-text: 17 24 39;
  --c-muted: 90 100 120;
  --c-border: 226 232 240;
  --c-accent: 29 78 216;     /* assombri pour le contraste sur fond clair */
}
```

```js
colors: {
  bg:      'rgb(var(--c-bg) / <alpha-value>)',
  surface: 'rgb(var(--c-surface) / <alpha-value>)',
  text:    'rgb(var(--c-text) / <alpha-value>)',
  muted:   'rgb(var(--c-muted) / <alpha-value>)',
  accent:  'rgb(var(--c-accent) / <alpha-value>)',
}
```

`bg-surface/75` fonctionne alors dans les deux thèmes **sans aucune surcharge**, et le nombre de règles `.theme-light` tombe de trente à zéro.

### 9.4. Incohérences de palette

Trois palettes cohabitent dans le code :

| Source | Valeurs | Usage |
|---|---|---|
| `config/constants.php` l. 145-152 (`THEME_COLORS`) | `#0066CC`, `#FFD700`, `#CC3333`, `#228B22`, `#2C3E50` | **Jamais lue par le front** |
| Config Tailwind inline | `#2F6DE0`, `#FFC107`, `#0B0F17`… | Système actif |
| Valeurs littérales dans les gabarits | `#0066CC`, `#FFD700`, `rgba(16,185,129,.18)`, `rgba(47,109,224,.18)` | Logo, halos de `genres.php`, dégradés de dashboards |

Le logo utilise `#0066CC` et `#FFD700` (ancienne palette) tandis que l'interface utilise `#2F6DE0` et `#FFC107` : **la marque et le produit n'ont pas la même couleur**. À trancher, puis à propager.

Par ailleurs les couleurs sémantiques ne sont pas jetonnées : `emerald-*`, `rose-*`, `amber-*`, `sky-*`, `cyan-*` sont employés directement. On trouve ainsi le succès en `emerald-500` à un endroit et en `#228B22` à un autre.

**Correction :** définir des jetons sémantiques (`success`, `warning`, `danger`, `info`, `neutral`) et interdire l'usage direct des couleurs Tailwind par défaut dans les gabarits.

### 9.5. Absence de bibliothèque de composants

Chaque page réimplémente les mêmes objets d'interface en classes utilitaires copiées. La carte d'indicateur, par exemple, apparaît avec des variantes de padding et d'arrondi dans `admin-dashboard.php`, `artist-dashboard.php`, `user-dashboard.php` et `wallet.php`.

**Correction :** extraire des partiels PHP réutilisables, au minimum :

`stat-card`, `chart-card`, `data-table` (avec tri, pagination, état vide, état de chargement), `badge` (statut, format, genre), `empty-state`, `alert`, `modal`, `form-field` (libellé + aide + erreur + champ, en un seul composant — voir §10), `pagination`, `breadcrumb`, `page-header`, `tabs`, `dropdown`, `avatar`, `media-tile`, `price`, `trend-indicator`.

Ces composants portent les jetons ; les gabarits ne portent plus de couleurs.

### 9.6. Points de détail relevés

- `includes/header-tailwind.php` utilise `<details>/<summary>` pour le menu utilisateur : robuste sans JS, mais sans fermeture au clic extérieur ni à la touche `Échap`, et sans `aria-expanded` piloté.
- Les icônes décoratives (`fas fa-music`, `fa-wave-square`…) ne portent pas `aria-hidden="true"` de façon systématique et sont annoncées par les lecteurs d'écran.
- `animate-pulse` est appliqué à quatre icônes décoratives sur `login.php` sans garde `prefers-reduced-motion`.
- Les métadonnées Open Graph référencent `assets/images/og-image.jpg` et `twitter-image.jpg` — **fichiers absents** (`assets/images/` ne contient aucun `.jpg`). Tout partage sur Facebook, WhatsApp ou X affiche une image cassée, sur toutes les pages du site. Correctif rapide et à fort effet pour la diffusion.
- `README.md` référence `assets/images/logo.png`, absent (seul `logo.svg` existe).
- L'icône de l'onglet est un SVG en `data:` URI inline dans l'en-tête, alors que `assets/images/favicon.ico` et `favicon.svg` existent et ne sont pas utilisés. À unifier.
- **Le lecteur audio est cassé à l'échelle du site.** `includes/footer-tailwind.php` (l. 68) charge `assets/js/player.js` sur **toutes** les pages, mais le balisage du lecteur vit dans `includes/player.php`, qui n'est inclus nulle part, et sa feuille de style `assets/css/player.css` n'est référencée que par `includes/header.php`, lui-même orphelin. Il y a donc 15,3 Ko de JavaScript exécuté sans conteneur ni style sur chaque page.

---

## 10. Accessibilité

Référentiel visé : **WCAG 2.2 niveau AA**. C'est un choix à assumer explicitement : une plateforme culturelle destinée à devenir une référence nationale, susceptible de recevoir des financements publics ou institutionnels, sera évaluée sur ce critère.

### 10.1. Défauts bloquants

| Constat | Mesure | Critère WCAG |
|---|---|---|
| **166 champs de formulaire sans association programmatique** à un libellé (ni `for`/`id`, ni `aria-label`, ni `aria-labelledby`) | Comptage sur l'ensemble des `.php` | 1.3.1, 3.3.2, 4.1.2 |
| **4 images sur 11 sans attribut `alt`** | Comptage | 1.1.1 |
| **Aucun état de focus visible personnalisé** dans les CSS actifs ; l'anneau natif est souvent masqué par les fonds translucides | 0 `:focus-visible` dans les CSS en production (5 occurrences uniquement dans `main.css`, orphelin) | 2.4.7, 2.4.11 |
| **Aucune gestion de `prefers-reduced-motion`** dans les CSS actifs | `animate-pulse`, transitions d'en-tête collant, halos animés | 2.3.3 |
| **Aucun lien d'évitement** (« Aller au contenu ») | Absent de `header-tailwind.php` | 2.4.1 |
| **Menu utilisateur `<details>/<summary>`** sans fermeture au clavier (`Échap`), sans piégeage de focus, `aria-expanded` non piloté | `includes/header-tailwind.php` | 2.1.1, 4.1.2 |
| **Icônes décoratives non masquées** aux technologies d'assistance | `aria-hidden="true"` non systématique sur les `<i class="fas …">` | 1.1.1 |
| **Contraste non déterministe** : le texte est posé sur des fonds translucides (`bg-surface/60`, `bg-white/5`) superposés à des dégradés et halos radiaux | Le rapport de contraste réel varie selon la position dans la page | 1.4.3 |
| **Absence de structure de titres fiable** : plusieurs pages passent de `h1` à `h3` ou utilisent `<p>` avec classes de titre | `admin-dashboard.php`, `artist-dashboard.php` | 1.3.1, 2.4.6 |
| **Pas de région live** pour les messages dynamiques (toasts construits par JS) | `main.js`, `upload.js`, `premium.js` | 4.1.3 |
| **Tableaux de données sans `<caption>` ni `scope`** | Dashboards | 1.3.1 |

### 10.2. Contrastes calculés

| Combinaison | Rapport | AA texte normal (4,5:1) | Verdict |
|---|---:|---|---|
| `text-muted` `#A4AEC2` sur `surface` `#141A26` | ≈ 7,9:1 | ✔ | Bon |
| `accent` `#2F6DE0` sur blanc (thème clair) | ≈ 4,7:1 | ✔ *(limite)* | Passe de justesse ; échoue en AAA. À assombrir pour le thème clair |
| Blanc sur `accent` `#2F6DE0` (boutons) | ≈ 4,7:1 | ✔ *(limite)* | Idem — un bouton principal ne devrait pas être à la limite |
| `text-xs` en `text-muted` avec `tracking-[0.2em]` en capitales | — | — | Contraste conforme mais **lisibilité faible** à 12 px, en particulier sur écran de téléphone en extérieur |

Recommandation : viser **7:1** sur les éléments d'action principaux plutôt que le minimum de 4,5:1. Le surcoût est nul et la marge protège contre les régressions.

### 10.3. Plan de mise en conformité

1. Créer un composant `form-field` unique (libellé associé, texte d'aide relié par `aria-describedby`, message d'erreur relié, état `aria-invalid`) et l'imposer partout. Cela résout à lui seul la majorité des 166 défauts.
2. Ajouter un anneau de focus global à fort contraste : `*:focus-visible { outline: 3px solid var(--c-focus); outline-offset: 2px; }`.
3. Ajouter le bloc `@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }`.
4. Ajouter le lien d'évitement en première position du `<body>`.
5. Passer les fonds translucides porteurs de texte à une opacité garantissant le contraste, ou poser une couche opaque sous le texte.
6. Remplacer `<details>` par un composant de menu accessible (ou compléter le comportement clavier).
7. Ajouter `role="status" aria-live="polite"` sur le conteneur de toasts.
8. Intégrer un contrôle automatisé (`axe-core`, `pa11y`) dans l'intégration continue une fois celle-ci en place, et faire une passe manuelle au lecteur d'écran (NVDA + VoiceOver) sur les cinq parcours critiques : inscription, connexion, achat, publication d'un titre, consultation du baromètre.

---

## 11. Performance

Le contexte d'usage est déterminant : au Tchad, l'accès se fait très majoritairement en **3G/4G sur mobile**, avec un **coût de données réel pour l'utilisateur**. Chaque kilo-octet superflu est un coût facturé au public visé. Les choix actuels sont, de ce point de vue, les plus défavorables possibles.

### 11.1. Tailwind Play CDN en production — défaut le plus coûteux

`includes/header-tailwind.php` charge `https://cdn.tailwindcss.com`, sur les 47 pages du site.

Ce script est **explicitement documenté par Tailwind comme réservé au prototypage**. Il :
- télécharge ~400 Ko de JavaScript non compressible en cache partagé utile ;
- **compile le CSS dans le navigateur, à chaque chargement de page**, en scannant le DOM ;
- bloque le rendu, produisant un flash de contenu non stylé (FOUC) visible sur connexion lente ;
- exige `unsafe-eval` dans toute politique de sécurité de contenu, ce qui **empêche la mise en place d'une CSP correcte** (§3.3 / P2-2) ;
- rend la configuration de thème (couleurs, polices, ombres) dupliquée dans un `<script>` inline sur chaque page.

**Correction :** compiler Tailwind localement (`tailwindcss -i src/app.css -o assets/css/app.min.css --minify`), avec purge sur les gabarits `.php`. Le résultat attendu pour un projet de cette taille est de l'ordre de **15 à 30 Ko** de CSS, servi avec empreinte de version dans le nom de fichier et cache long. **Gain estimé : ~400 Ko et plusieurs centaines de millisecondes par page**, plus le déblocage de la CSP.

### 11.2. Quatre origines tierces bloquantes

| Origine | Contenu | Poids indicatif |
|---|---|---|
| `cdn.tailwindcss.com` | Compilateur Tailwind | ~400 Ko |
| `cdnjs.cloudflare.com` | Font Awesome 6.4 (CSS + fontes) | ~75 Ko CSS + ~150 Ko de fontes |
| `fonts.googleapis.com` / `fonts.gstatic.com` | Manrope (4 graisses) + Sora (3 graisses) | ~120 Ko |
| `cdn.jsdelivr.net` | Chart.js 3.9.1, TinyMCE 6 + langue FR | ~200 Ko + ~500 Ko (TinyMCE) |

Chaque origine impose résolution DNS, poignée de main TLS et négociation — pénalité lourde sur réseau mobile à forte latence. Aucun `Subresource Integrity` n'est posé : une compromission de CDN exécuterait du code arbitraire sur le site.

**Corrections :**
- Auto-héberger les fontes en **WOFF2**, limitées aux graisses réellement utilisées (2 par famille suffisent), avec `font-display: swap` et `preload` sur la police de titre.
- Remplacer Font Awesome par un **jeu d'icônes SVG en sprite** limité aux icônes employées. Le projet en utilise quelques dizaines ; charger 225 Ko pour cela est disproportionné.
- Auto-héberger Chart.js, et ne le charger **que** sur les pages qui affichent un graphique.
- Charger TinyMCE **uniquement** sur `admin-blog.php`.
- Poser `integrity` + `crossorigin` sur tout ce qui resterait externe.

**Gain cumulé estimé : 1 Mo à 1,3 Mo par visite initiale.**

### 11.3. Défauts serveur

| Constat | Détail | Correction |
|---|---|---|
| Requêtes N+1 | 6 requêtes pour le revenu mensuel artiste ; 18 + 24 dans `analytics.php` (§4.5) | Agrégation unique `GROUP BY` |
| `DATE_FORMAT()` sur colonne indexée | `WHERE DATE_FORMAT(created_at,'%Y-%m') = ?` empêche l'usage de l'index | `created_at >= ? AND created_at < ?` |
| `SELECT *` systématique | `getCurrentUser()`, `getUserById()`, `api/user.php?action=list` | Sélection explicite des colonnes |
| `tableExists()` à chaud | 3 requêtes `information_schema` par affichage de dashboard | Supprimer : le schéma est une garantie des migrations |
| `getCurrentUser()` rejoue la requête à chaque appel | Aucun cache de requête | Mémoïsation par requête HTTP |
| Aucun cache applicatif | `CACHE_ENABLED = true` et `CACHE_LIFETIME` définis, **aucune implémentation** | Cache des classements et agrégats (fichier ou Redis), invalidation à l'arrêté |
| Agrégats calculés à la volée | Les dashboards devront balayer `streams` quand le volume montera | Rollups précalculés (§5.3) |
| Aucune pagination sur plusieurs listes | `api/user.php?action=list` plafonne à 100 en dur ; `api/artist.php` à 100 | Pagination par curseur |
| Images non optimisées | Aucun `srcset`, aucun WebP/AVIF, aucun `loading="lazy"` généralisé | Pipeline de variantes à l'upload |
| Audio sans pré-écoute allégée | `preview_file` prévu mais inexploité ; le master complet est servi | Générer un extrait 30 s à faible débit à l'upload |

### 11.4. Budget de performance proposé

À inscrire comme contrainte de projet, mesurée sur profil **Moto G Power / 3G rapide** (et non sur poste de développement) :

| Métrique | Cible |
|---|---|
| Poids total page d'accueil (première visite) | ≤ 400 Ko |
| CSS total | ≤ 40 Ko |
| JavaScript total (hors page éditeur) | ≤ 120 Ko |
| Largest Contentful Paint | ≤ 2,5 s |
| Interaction to Next Paint | ≤ 200 ms |
| Cumulative Layout Shift | ≤ 0,1 |
| Requêtes SQL par page | ≤ 12 |
| Origines tierces | ≤ 1 |

---

## 12. SEO, référencement et application mobile

### 12.1. Ce qui est en place

Le travail de `includes/header-tailwind.php` est correct sur les bases : `<title>` construit, `meta description`, URL canonique calculée, Open Graph et Twitter Card complets, `lang="fr"`, `viewport` correct, `theme-color`.

### 12.2. Manques

| Constat | Impact | Correction |
|---|---|---|
| **Images de partage absentes** (`og-image.jpg`, `twitter-image.jpg`) | Tout partage social affiche une carte cassée — sur un marché où WhatsApp et Facebook sont les principaux canaux de découverte, c'est un handicap majeur | Générer les images ; produire une carte dynamique par titre/artiste (pochette + nom) |
| **Aucune donnée structurée** (JSON-LD) | Pas d'affichage enrichi ; les moteurs ne comprennent ni les œuvres ni les artistes | `MusicRecording`, `MusicAlbum`, `MusicGroup`, `Offer`, `BreadcrumbList`, `Organization`, `Article` pour le blog |
| **Aucun `sitemap.xml`, aucun `robots.txt`** | Indexation laissée au hasard | Sitemap généré (index + titres + artistes + genres + blog), soumis à la Search Console |
| **URL non parlantes** : `albums.php?id=12` au lieu de `/album/nom-artiste/titre-album` | Perte de pertinence et de partageabilité | Réécriture avec `slug` (à ajouter sur `tracks`, `artists`, `releases`) |
| **Aucune page dédiée par titre** | Impossible de référencer une œuvre individuellement — pourtant le premier point d'entrée naturel depuis une recherche | Créer `/titre/{slug}` avec lecteur d'extrait, bouton d'achat, crédits, paroles |
| **Aucun balisage multilingue** alors que `SUPPORTED_LANGUAGES` déclare fr/ar/en | Pas d'internationalisation réelle | `hreflang` + traduction effective ; l'arabe implique un support **RTL** complet, aujourd'hui inexistant |
| **Aucune page « méthodologie » du baromètre** | Un classement non documenté n'est pas citable | Page publique dédiée (§7.6) |
| **Contenus `draft` indexables** (P1-7) | Du contenu non validé se retrouve dans l'index | Corriger les requêtes publiques |
| **Aucune stratégie de pages de destination par genre/province** | C'est pourtant le levier SEO naturel d'un baromètre national | Pages `/genre/{slug}` et `/classement/{periode}` avec contenu éditorialisé |

### 12.3. Progressive Web App

`sw.js` existe (8,3 Ko), et les icônes PWA sont présentes (`icon-72` à `icon-512`, en SVG). Mais :

- **Aucun `manifest.json`** dans le projet. Sans manifeste, pas d'installation sur l'écran d'accueil — or c'est exactement le mode de distribution pertinent au Tchad, où installer une application depuis un magasin consomme des données et de l'espace.
- Les icônes PWA sont en **SVG** ; plusieurs navigateurs mobiles exigent du **PNG** pour l'écran d'accueil.
- Le service worker met en cache sans exclure explicitement les pages authentifiées ni `/api/*` (P2-17) : risque de servir à un utilisateur le contenu mis en cache d'un autre sur appareil partagé — situation courante.
- Aucune stratégie hors ligne pour l'audio acheté, alors que « mode hors-ligne » est annoncé dans le `README`.

**Correction :** produire `manifest.json` (nom, nom court, icônes PNG 192/512 + maskable, `start_url`, `display: standalone`, `theme_color`, `background_color`, `lang: fr`), durcir le service worker (cache réseau-d'abord pour le HTML, cache-d'abord pour les seuls actifs versionnés, exclusion stricte des routes authentifiées), et implémenter la mise en cache de l'audio sous droit valide.

---

## 13. Conformité juridique et gestion des risques

Cette section n'est pas un avis juridique. Elle signale les points qui exigent une validation par un conseil, et ceux qui relèvent de la simple mise en œuvre technique.

### 13.1. Droit d'auteur — le risque principal

| Risque | État actuel | Mesure |
|---|---|---|
| Dépôt d'œuvres par une personne qui n'en détient pas les droits | **Aucune barrière** : inscription artiste libre (P1-11), aucune modération (P1-7) | Onboarding vérifié (§6.5) + file de modération (§6.4) |
| Absence de contrat de distribution horodaté | Aucun | Acceptation versionnée et horodatée, archivée, opposable |
| Absence de procédure de retrait sur notification | `reports` inutilisée | Procédure de notification / contre-notification, retrait provisoire, journal des décisions |
| Œuvres à ayants droit multiples | Un seul `artist_id` par titre | Modéliser auteurs, compositeurs, interprètes, producteurs, et le partage de royalties |
| Relations avec la société de gestion collective (BUTDRA au Tchad) | Aucune trace | Clarifier le régime de déclaration et de reversement — **à trancher avant la première vente** |
| Détection de doublons / contenus déjà déposés | Aucune | Empreinte acoustique (§6.4) |

**C'est le sujet sur lequel un manquement peut arrêter le projet**, plus encore que la sécurité technique : une plateforme qui vend des œuvres sans chaîne de droits documentée s'expose à des actions individuelles et à une perte de crédibilité irréversible auprès des artistes.

### 13.2. Données personnelles

Cadre applicable : **loi tchadienne n° 007/PR/2015** relative à la protection des données à caractère personnel, et **RGPD** pour tout utilisateur situé dans l'Union européenne (la diaspora est une cible explicite).

| Obligation | État | Mesure |
|---|---|---|
| Base légale et information | `confidentialite.php` existe (20,2 Ko) — contenu à faire relire | Revue juridique |
| Sécurité des données | **Violation caractérisée** : `api/user.php?action=list` expose e-mails et téléphones sans authentification (P1-12) | Correction P0/P1 |
| Minimisation | `SELECT *` généralisé, `$_SESSION` complète sérialisée en base | Sélection explicite |
| Droit d'accès et de portabilité | Aucun export | Export des données utilisateur (JSON + CSV) |
| Droit à l'effacement | Suppression en cascade destructive, ou aucune | Suppression logique + anonymisation, avec conservation des écritures comptables |
| Durées de conservation | Aucune politique | Définir par catégorie (compte, écoutes, transactions, journaux) |
| Consentement cookies | `main.js` gère un bandeau `cookieConsent` | Vérifier qu'aucun traceur n'est posé avant consentement |
| Registre des traitements | Absent | À constituer |
| Procédure de notification de violation | Absente | À écrire — elle serait déjà nécessaire pour P1-12 |
| Chiffrement au repos des données sensibles | Aucun (les coordonnées mobile money seront en clair) | Chiffrement applicatif des coordonnées de paiement |
| Mineurs | `date_of_birth` collectée, jamais contrôlée | Définir l'âge minimum et le régime de consentement parental |

### 13.3. Paiement et obligations financières

| Point | État | Mesure |
|---|---|---|
| Agrément / statut pour l'encaissement pour compte de tiers | Non documenté | **Question préalable** : vérifier le cadre BEAC/COBAC applicable et le statut requis pour reverser à des artistes |
| Conventions opérateurs (Airtel Money, Moov Money) | Clés d'API factices (`YOUR_AIRTEL_CLIENT_ID`) | Contractualiser ; obtenir les environnements de test puis de production |
| Vérification des callbacks | Aucune (endpoints désactivés) | Signature/HMAC vérifiée, IP source contrôlée, idempotence sur la référence opérateur |
| Facturation | Aucune facture émise | Numérotation séquentielle sans trou, mentions légales, TVA le cas échéant, archivage |
| Traçabilité comptable | `DELETE` possible sur les transactions (P0-3) | Immuabilité, écritures d'annulation uniquement |
| Réconciliation | Aucune | Rapprochement quotidien automatique entre relevés opérateurs et `orders` |
| Remboursements et litiges | `refundPayment()` existe dans `includes/payment.php`, jamais appelé | Procédure documentée et outillée, avec délai de réponse |
| Lutte anti-blanchiment | Aucun contrôle | Seuils de vigilance, vérification d'identité renforcée au-delà d'un montant |

### 13.4. Obligations contractuelles envers les artistes

Les documents `conditions.php` (17,7 Ko) et `confidentialite.php` (20,2 Ko) existent mais décrivent un service qui n'est pas celui livré. Points à traiter dans un contrat de distribution dédié :

- Taux de commission, sa modification et son préavis.
- Définition contractuelle de l'**écoute comptabilisée** — c'est elle qui fonde la rémunération ; elle doit figurer au contrat, pas seulement dans le code.
- Fréquence, seuil et délai des versements ; traitement des frais de transfert.
- Droit de l'artiste à consulter le détail de ses ventes et à contester un décompte.
- Conditions de retrait d'une œuvre, à l'initiative de l'artiste comme de la plateforme.
- Exclusivité ou non ; durée ; sort des ventes en cas de résiliation.
- Réversibilité : ce que l'artiste récupère s'il quitte la plateforme.

### 13.5. Loyauté de l'information affichée

Trois points relèvent moins de la technique que de la sincérité de ce qui est présenté à l'utilisateur, et doivent être corrigés sans attendre :

1. **`security-settings.php` affiche un faux journal de connexions et une 2FA non fonctionnelle** (P1-5). Un utilisateur peut croire son compte protégé alors qu'il ne l'est pas.
2. **Les parcours de paiement sont présentés comme actifs** alors que les endpoints renvoient 501. Un utilisateur peut croire avoir souscrit.
3. **Le `README.md` décrit une soixantaine de fonctionnalités** dont la grande majorité n'existe pas (recommandations intelligentes, lyrics synchronisés, mode hors-ligne, royalties, certification, concours, forums, parrainage, backup automatique). S'il sert de support de présentation à des partenaires ou des financeurs, l'écart est un risque de réputation sérieux. À remplacer par un état des lieux honnête et une feuille de route datée.

---

## 14. Fonctionnalités supplémentaires suggérées

Les propositions sont classées par rapport valeur/effort. Celles marquées **★** sont celles qui, à mon sens, construisent la position de référence — pas seulement une fonctionnalité de plus.

### 14.1. Autour du baromètre — la différenciation

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 1 ★ | **Baromètre public hebdomadaire** — page publique, classements arrêtés le lundi, archives consultables par semaine, permaliens par édition. C'est l'actif qui rend Tchadok citable par la presse, la radio et les institutions. | Très forte | Moyen |
| 2 ★ | **Kit presse automatique** — pour chaque édition : visuels prêts à publier (Top 10 en image), communiqué généré, export CSV. Rend la reprise médiatique gratuite et systématique. | Forte | Faible |
| 3 ★ | **Certifications Tchadok** — paliers officiels (Or, Platine…) sur les écoutes certifiées et les ventes, avec badge, attestation PDF et annonce. Crée une reconnaissance symbolique qui n'existe pas au Tchad, à coût quasi nul. | Très forte | Faible |
| 4 | **Baromètre par province** — indice de pénétration rapporté à la population, révélant les scènes régionales au-delà de N'Djamena. | Forte | Moyen |
| 5 | **Baromètre diaspora** — classement distinct pour les écoutes hors Tchad. Donnée inédite, à forte valeur pour les artistes en tournée. | Forte | Faible |
| 6 | **Rétrospective annuelle** — « Tchadok 2026 » : bilan national + bilan personnalisé par auditeur, partageable. Moteur d'acquisition saisonnier éprouvé. | Forte | Moyen |
| 7 | **API publique du baromètre** — lecture seule, avec clés, quotas et attribution obligatoire. Fait de Tchadok la source citée plutôt que copiée. | Forte | Faible |
| 8 | **Indice de découverte** — part des écoutes allant à des artistes de moins de 12 mois. Indicateur de santé de la scène, utile aux politiques culturelles. | Moyenne | Faible |

### 14.2. Monétisation

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 9 ★ | **Paiement par crédits prépayés** — l'utilisateur recharge un portefeuille par mobile money (la colonne `users.wallet_balance` existe déjà), puis achète sans frais de transaction par achat. **Contourne le principal frein du marché** : le coût et la friction d'une transaction mobile money de 300 FCFA. | Très forte | Moyen |
| 10 ★ | **Précommande et sortie programmée** — `releases.is_preorder` déjà prévu. Permet aux artistes de capitaliser sur l'annonce, et à la plateforme de lisser les sorties. | Forte | Faible |
| 11 | **Pourboire / soutien direct à l'artiste** — sans contrepartie de fichier. Culturellement très adapté : le public tchadien soutient volontiers un artiste nommément. Marge de commission plus faible, volume potentiellement supérieur aux ventes. | Forte | Faible |
| 12 | **Codes promo et cartes cadeaux** — leviers d'acquisition et de cadeau entre proches (usage social fort). | Moyenne | Faible |
| 13 | **Offre famille / partagée** et **offre étudiante** | Moyenne | Faible |
| 14 | **Offres par opérateur** — Premium inclus dans un forfait data Airtel/Moov, ou écoute en zéro-rating. Négociation commerciale, pas technique, mais transformerait l'acquisition. | Très forte | Faible technique / fort commercial |
| 15 | **Licence B2B** — abonnements pour bars, restaurants, radios, transporteurs, avec tarif par établissement et attestation de diffusion. Revenu récurrent, marché réel et aujourd'hui non servi. | Forte | Moyen |
| 16 | **Vente d'albums physiques et de merchandising** — le CD et la clé USB restent des supports vivants au Tchad. Gestion de stock et retrait en point relais. | Moyenne | Fort |
| 17 | **Billetterie de concerts** — extension naturelle, et source de données croisées écoutes/déplacements. | Forte | Fort |

### 14.3. Outils artistes

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 18 ★ | **Lien intelligent d'artiste** (`tchadok.td/@nom`) — page publique unique à partager sur WhatsApp, avec extraits, achat et suivi. Point d'entrée unique et mesurable. | Très forte | Faible |
| 19 | **Statistiques en temps quasi réel** à la sortie d'un titre (premières 48 h) | Forte | Moyen |
| 20 | **Comparaison anonymisée par genre** — « votre taux de conversion vs médiane de votre genre ». Aide à l'action sans exposer les concurrents. | Forte | Faible |
| 21 | **Partage de royalties entre collaborateurs** — répartition en pourcentage entre auteurs, compositeurs, interprètes, producteurs, versements automatiques. Prérequis du §5.5. | Forte | Fort |
| 22 | **Soumission aux playlists éditoriales** — formulaire, file de traitement, retour motivé. Transparence rare et très appréciée des artistes. | Forte | Faible |
| 23 | **Relevés de revenus PDF** téléchargeables par période | Forte | Faible |
| 24 | **Notifications d'étapes** (« 1 000 écoutes », « première vente », « entrée au Top 50 ») — rétention artiste à faible coût | Moyenne | Faible |
| 25 | **Espace Label / Manager** — un compte gérant plusieurs artistes, avec vue consolidée | Moyenne | Moyen |

### 14.4. Expérience auditeur

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 26 ★ | **Mode économie de données** — débit réduit au choix, taille affichée avant téléchargement, téléchargement différé en Wi-Fi. Répond au premier frein réel du marché. | Très forte | Moyen |
| 27 ★ | **Partage WhatsApp natif** avec carte visuelle (pochette, titre, artiste, extrait de 30 s). WhatsApp est le canal de découverte dominant ; le négliger serait une erreur stratégique. | Très forte | Faible |
| 28 | **Paroles synchronisées**, en français, arabe tchadien et langues nationales | Forte | Moyen |
| 29 | **Radio algorithmique par genre et par province** — extension du moteur radio déjà fonctionnel | Forte | Moyen |
| 30 | **Playlists collaboratives** | Moyenne | Faible |
| 31 | **Recommandations** par filtrage collaboratif puis co-écoute | Forte | Moyen |
| 32 | **Interface arabe complète avec support RTL** — `SUPPORTED_LANGUAGES` l'annonce déjà ; c'est une question d'accès pour une partie significative de la population. | Forte | Moyen |
| 33 | **Fonctionnement hors ligne réel** pour le contenu sous droit | Forte | Moyen |
| 34 | **Recherche phonétique tolérante** — les noms tchadiens ont de multiples orthographes ; une recherche exacte échoue constamment. | Forte | Faible |

### 14.5. Éditorial et communauté

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 35 | **Archive patrimoniale** — numérisation et mise en ligne du répertoire historique tchadien, avec notices. Mission d'intérêt général, argument décisif auprès des institutions et bailleurs. | Très forte | Fort |
| 36 | **Fiches artistes documentées** — biographie, discographie, influences, articles liés | Forte | Moyen |
| 37 | **Dossiers par genre** — histoire du Saï, des musiques kanembou… Contenu SEO durable et à valeur culturelle. | Forte | Moyen |
| 38 | **Agenda des concerts et événements** | Moyenne | Moyen |
| 39 | **Tremplin / concours de découverte** avec vote du public encadré (anti-fraude obligatoire) | Forte | Moyen |
| 40 | **Podcasts et interviews** — module déjà présent, à éditorialiser | Moyenne | Faible |

### 14.6. Exploitation et confiance

| # | Proposition | Valeur | Effort |
|---|---|---|---|
| 41 ★ | **Tableau de bord anti-fraude** — détection des écoutes anormales, mise en quarantaine, revue humaine. **Sans cela, le baromètre n'a aucune valeur.** | Critique | Moyen |
| 42 ★ | **Page publique de méthodologie** du baromètre | Critique | Faible |
| 43 | **Journal d'audit consultable** (§8.2) | Forte | Faible |
| 44 | **Sauvegarde automatisée avec restauration testée** — annoncée dans `.env`, inexistante | Critique | Faible |
| 45 | **Page d'état du service** (statut radio, API, paiements) | Moyenne | Faible |
| 46 | **Support intégré** — tickets, base de connaissances (`aide.php` existe et peut servir de socle) | Moyenne | Moyen |
| 47 | **Rapport annuel public** — santé de la scène musicale tchadienne. Positionne Tchadok en institution de référence plutôt qu'en simple plateforme. | Très forte | Faible |

---

## 15. Plan d'action priorisé

### P0 — Blocant, à traiter immédiatement (4 à 5 jours)

Objectif : rendre la plateforme non triviale à compromettre. **Aucune mise en ligne avant l'achèvement de cette phase.**

| # | Action | Cible | Charge |
|---|---|---|---|
| 1 | **Retirer du dépôt** `install.php`, `admin/execute-sql.php`, `admin/update-passwords.php`, `validate-placeholders.php`, `api/docs.php`, `config/database.php` | 6 fichiers | 1 h |
| 2 | **Retirer le code mort** : `admin/dashboard-tabs/`, `pages/admin/`, `pages/artist/`, `includes/header.php`, `includes/footer.php`, `includes/player.php`, `assets/css/main.css` et les 21 CSS orphelins (annexe A) | ~340 Ko | 2 h |
| 3 | **Authentifier et autoriser** `api/user.php`, `api/artist.php`, `api/transaction.php` — ou les neutraliser (501) le temps de leur réécriture | 3 fichiers | 4 h |
| 4 | **Faire tourner tous les secrets** : mot de passe MySQL, `JWT_SECRET`, `APP_KEY`, mot de passe admin Icecast, identifiants SMTP | — | 2 h |
| 5 | **Désindexer `.env`** de Git puis committer ; remplacer `.env.production` par un `.env.example` sans valeurs réelles ; purger l'historique avec `git filter-repo` | — | 2 h |
| 6 | **Retirer les comptes du dump** `database/tchadok.sql` ; changer immédiatement le mot de passe du `super_admin` en base | 1 fichier | 1 h |
| 7 | **Garde CSRF centralisée** sur tous les points d'entrée `POST` | 22 fichiers | 6 h |
| 8 | **Durcir la session** : `session_regenerate_id(true)` à la connexion, `use_strict_mode`, `SameSite=Lax`, `cookie_secure` conditionnel | `functions.php`, `auth.php` | 2 h |
| 9 | **Corriger `checkRememberMe()`** (sélecteur + vérificateur indexé) ou désactiver la fonctionnalité en attendant | `auth.php` | 4 h |
| 10 | **Retirer `draft` des requêtes publiques** (8 occurrences) | `includes/database.php` | 1 h |
| 11 | **Retirer les champs `*_file_url`** des formulaires artiste | 2 fichiers | 1 h |
| 12 | **Protéger `uploads/`** : `.htaccess` local (`Deny from all`, `php_flag engine off`) en attendant le déplacement hors racine | 1 fichier | 30 min |
| 13 | **Retirer `Access-Control-Allow-Origin: *`** du `.htaccess`, désactiver `display_errors`, faire dériver `ENVIRONMENT` de `APP_ENV` | `.htaccess`, `constants.php` | 1 h |
| 14 | **Retirer `security-settings.php` de la navigation** (données fabriquées présentées comme réelles) | 2 fichiers | 30 min |
| 15 | **Limiter le débit** sur `login.php`, `register.php`, `api/stream.php`, `api/search.php` | 4 fichiers | 4 h |
| 16 | **Créer les pages d'erreur** 403 / 404 / 500, référencées mais absentes | 3 fichiers | 1 h |
| 17 | **Committer l'arbre de travail** (178 fichiers en attente) sur une branche dédiée avant toute intervention | — | 1 h |

**Charge totale estimée : 4 à 5 jours-personne.**

### P1 — Socle indispensable (3 à 4 semaines)

| # | Chantier | Référence | Charge |
|---|---|---|---|
| 18 | **Unifier le schéma** : registre de migrations, source de vérité unique, suppression de la colonne `password` | §5.1, P1-1 | 4 j |
| 19 | **Chaîne de mesure des écoutes** : seuil 30 s, jeton de session signé, déduplication, durée extraite du fichier, GeoIP serveur, IP de confiance | P0-11, P1-13 | 5 j |
| 20 | **Rollups et compteurs** : tâches planifiées d'agrégation, reconstruction intégrale possible, alimentation de `charts` | §5.3 | 5 j |
| 21 | **Référentiel des genres** : hiérarchie, référentiel initial, écrans d'administration, migration de `artists.genres`, retrait de la création libre | §6.3, P1-10 | 4 j |
| 22 | **File de modération** : transitions d'état, écran admin, contrôles automatiques à l'upload, signalements | §6.4, P1-7 | 6 j |
| 23 | **Onboarding artiste vérifié** | §6.5, P1-11 | 5 j |
| 24 | **Rôles, permissions et journal d'audit** | §8.2, §8.3 | 5 j |
| 25 | **Unifier le parcours de publication** : un seul écran, validation serveur des formats, contrôle de propriété de l'album | P1-8, P1-9 | 4 j |
| 26 | **2FA réelle** pour les rôles d'administration | P1-5 | 3 j |
| 27 | **Tailwind compilé**, auto-hébergement des fontes et icônes, CSP activable | §11.1, §11.2 | 3 j |
| 28 | **Vérification d'e-mail effective**, transport SMTP authentifié | P2-15, P2-16 | 2 j |

### P2 — Le produit (6 à 10 semaines)

| # | Chantier | Référence | Charge |
|---|---|---|---|
| 29 | **Panier, commande, facture** | §6.2.4 | 8 j |
| 30 | **Intégration mobile money réelle** : Airtel + Moov, callbacks signés, idempotence, réconciliation quotidienne | §13.3 | 10 j |
| 31 | **Droits d'accès et livraison protégée de l'audio** | §6.2.5, P0-10 | 5 j |
| 32 | **Portefeuille prépayé** | prop. 9 | 5 j |
| 33 | **Versements aux artistes** avec séparation des pouvoirs | §6.2.6 | 7 j |
| 34 | **Refonte de la coquille des dashboards** : navigation unique, URL par vue, bibliothèque de composants | §7.4, §9.5 | 10 j |
| 35 | **Dashboard artiste complet** : périodes, comparaisons, granularité hebdomadaire, ventes par format, géographie, export | §7.5 | 8 j |
| 36 | **Dashboard admin et baromètre interne** : classements figés, vues par genre / catégorie / région / format, export | §7.6 | 10 j |
| 37 | **Baromètre public hebdomadaire**, page méthodologie, kit presse | prop. 1, 2, 42 | 8 j |
| 38 | **Tableau de bord anti-fraude** | prop. 41 | 6 j |
| 39 | **Mise en conformité accessibilité AA** | §10.3 | 6 j |
| 40 | **Sauvegarde automatisée avec restauration testée** | prop. 44 | 2 j |

### P3 — Consolidation et croissance

Grille tarifaire administrée · droits d'accès liés à l'abonnement · lien intelligent d'artiste · certifications Tchadok · mode économie de données · partage WhatsApp enrichi · précommandes · pourboires · interface arabe et support RTL · recherche phonétique · manifeste PWA et fonctionnement hors ligne · données structurées et sitemap · API publique du baromètre · partage de royalties · espace label · licence B2B · archive patrimoniale · rétrospective annuelle · tests automatisés et intégration continue.

### 15.1. Séquencement recommandé

```
Semaine 1          P0 — sécurisation                  |
Semaines 2 à 5     P1 — socle                         |  aucune communication publique
Semaines 6 à 11    P2 — vente + dashboards            |  avant la fin de P2
Semaines 12 à 15   P2 — baromètre + anti-fraude       |
Semaine 16         Lancement du baromètre public
Au-delà            P3 — croissance
```

### 15.2. Trois décisions à arbitrer avant le démarrage

1. **Modèle commercial.** Vente à l'unité, abonnement, ou les deux. Recommandation : **vente à l'unité + portefeuille prépayé en priorité**, abonnement en second temps. La vente à l'unité correspond à l'usage local, ne suppose pas d'atteindre une taille critique de catalogue, et rémunère l'artiste immédiatement — ce qui est le meilleur argument pour en recruter.
2. **Statut pour l'encaissement pour compte de tiers.** À clarifier juridiquement **avant** d'écrire la première ligne du module de paiement : la réponse peut changer l'architecture (encaissement par la plateforme, ou redirection vers l'artiste).
3. **Définition officielle de l'écoute comptabilisée.** À arrêter, publier et inscrire au contrat artiste **avant** tout lancement du baromètre. Une définition modifiée après coup invaliderait tout l'historique.

### 15.3. Ce que je recommande de ne pas faire

- **Ne pas ajouter de fonctionnalités avant la fin de P1.** Le projet souffre d'un excès de surface pour un manque de fondations : 104 fichiers PHP dont environ 40 % morts, et aucune des promesses centrales tenue.
- **Ne pas communiquer publiquement sur le baromètre avant l'anti-fraude.** Un classement discrédité une fois ne se rattrape pas.
- **Ne pas repartir de zéro.** Le schéma de données, la direction artistique, le moteur radio et le module blog sont des acquis réels. La refonte doit être ciblée, pas totale.
- **Ne pas conserver les deux générations de dashboards « au cas où ».** Git conserve l'historique ; le code mort ne sert qu'à masquer les vulnérabilités.

---

## 16. Annexes

### Annexe A — Feuilles de style orphelines

Fichiers présents dans `assets/css/` et référencés par aucun gabarit actif (~125 Ko) :

`admin-dashboard-legacy.css` (9,6 Ko) · `admin-dashboard-widgets.css` (10,1 Ko) · `admin-dashboard.css` (6 Ko) · `admin-dashboard-pages.css` (1,1 Ko) · `admin-execute-sql.css` (0,7 Ko) · `admin-login.css` (1,9 Ko) · `admin-reset-database.css` (1 Ko) · `admin-update-passwords.css` (1,2 Ko) · `albums.css` (2,6 Ko) · `artist-dashboard.css` (1,6 Ko) · `artist-dashboard-pages.css` (1,1 Ko) · `artists.css` (3,7 Ko) · `blog.css` (15,5 Ko) · `contact.css` (10,8 Ko) · `decouvrir.css` (13,2 Ko) · `emissions.css` (12,4 Ko) · `genres.css` (4,3 Ko) · `home.css` (5,4 Ko) · `radio-live.css` (10,3 Ko) · `search.css` (3,8 Ko) · `user-dashboard.css` (8,6 Ko)

S'y ajoutent `main.css` (34,4 Ko) et `player.css` (2,8 Ko), référencés uniquement depuis `includes/header.php`, lui-même orphelin.

**Attention avant suppression :** ces fichiers portent des classes de page (`page-hero`, `page-hero-note`, `site-nav-*`) qui pourraient être employées par les gabarits. Vérifier par recherche de chaque sélecteur avant retrait — les variantes `*-tailwind.css`, elles, sont actives et à conserver.

### Annexe B — Inventaire du code mort et des fichiers à retirer

| Fichier / répertoire | Taille | Raison | Action |
|---|---:|---|---|
| `install.php` | 29,8 Ko | Installeur public, identifiants `root`, crée un admin à mot de passe publié (P0-6) | **Retirer** |
| `admin/execute-sql.php` | 13,4 Ko | Console SQL web, identifiants en dur, réinitialisation de masse (P0-5) | **Retirer** |
| `admin/update-passwords.php` | 5,3 Ko | Réinitialise tous les mots de passe à `12345678` (P0-4) | **Retirer** |
| `api/docs.php` | 39,5 Ko | Documentation publique de la surface d'API (P2-12) | **Retirer** ou protéger |
| `validate-placeholders.php` | 13,8 Ko | Page de test exposée en racine web | **Retirer** |
| `config/database.php` | 3,1 Ko | Classe morte, identifiants en dur, collision de nom déjà survenue | **Retirer** |
| `admin/dashboard-tabs/` (8 fichiers) | ~130 Ko | Jamais inclus, contient six injections SQL (P1-6) | **Retirer** |
| `pages/admin/dashboard.php` | 32,9 Ko | Jamais atteint, cassé (`$db` non défini), liens vers fichiers absents | **Retirer** |
| `pages/artist/dashboard.php` | 29,1 Ko | Idem | **Retirer** |
| `includes/header.php` | 9,9 Ko | 0 utilisation | **Retirer** |
| `includes/footer.php` | 4,6 Ko | 0 utilisation | **Retirer** |
| `includes/player.php` | 9,1 Ko | 0 utilisation, alors que `player.js` est chargé partout (§9.6) | **Réintégrer ou retirer** |
| `assets/css/main.css` + 21 CSS | ~160 Ko | Orphelins (annexe A) | **Retirer après vérification** |
| `admin/create-test-accounts.php`, `admin/reset-database.php` | ~2,5 Ko | Déjà neutralisés, ne servent plus | **Retirer** |
| `database/install.php`, `database/generate-test-data.php` | ~0,6 Ko | Déjà neutralisés | **Retirer** |
| `sample-data.sql` | 0,2 Ko | Vide de sens | **Retirer** |
| `logs/*.log` | ~10,7 Ko | Journaux dans l'arborescence web | **Déplacer hors racine** |
| `INSCRIPTION-FONCTIONNELLE.md`, `PLACEHOLDERS_GUIDE.md`, `migration.md` | ~20 Ko | Notes de développement en racine | **Déplacer dans `docs/`** |

### Annexe C — Récapitulatif des vulnérabilités

| Réf. | Sévérité | Intitulé | Emplacement |
|---|---|---|---|
| P0-1 | Critique | Création anonyme d'un compte administrateur | `api/user.php` |
| P0-2 | Critique | Suppression anonyme de comptes, unitaire et en masse | `api/user.php` |
| P0-3 | Critique | Validation anonyme de transactions financières | `api/transaction.php` |
| P0-4 | Critique | Réinitialisation de masse des mots de passe | `admin/update-passwords.php` |
| P0-5 | Critique | Console SQL web et création d'admin | `admin/execute-sql.php` |
| P0-6 | Critique | Installeur public à identifiants publiés | `install.php` |
| P0-7 | Critique | Secrets versionnés et codés en dur | `.env`, `.env.production`, `config/*`, `admin/*` |
| P0-8 | Critique | Super-administrateur livré dans le dump | `database/tchadok.sql` |
| P0-9 | Critique | Absence de protection CSRF (18 formulaires) | Transversal |
| P0-10 | Critique | Contenu payant téléchargeable sans achat | `uploads/`, `api/track.php`, `.htaccess` |
| P0-11 | Critique | Compteur d'écoutes ouvert et falsifiable | `api/stream.php` |
| P1-1 | Élevé | Double colonne de mot de passe | `includes/auth.php`, schéma |
| P1-2 | Élevé | « Se souvenir de moi » : déni de service par bcrypt en boucle | `includes/auth.php` |
| P1-3 | Élevé | Fixation de session | `includes/functions.php`, `auth.php` |
| P1-4 | Élevé | Aucune limitation de débit ni verrouillage | Transversal |
| P1-5 | Élevé | Module de sécurité simulé, données fabriquées affichées | `includes/advanced-auth.php`, `security-settings.php` |
| P1-6 | Élevé | Injections SQL (6 occurrences) | `admin/dashboard-tabs/*` |
| P1-7 | Élevé | Contenu non modéré publié au catalogue public | `includes/database.php` (8 requêtes) |
| P1-8 | Élevé | Publication par URL arbitraire, validation de fichier insuffisante | `artist-add-song.php`, `artist-add-album.php` |
| P1-9 | Élevé | Rattachement d'un titre à l'album d'un autre artiste | `artist-add-song.php` |
| P1-10 | Élevé | Création libre de genres par les artistes | `upload.php` |
| P1-11 | Élevé | Inscription artiste sans aucune vérification | `register.php` |
| P1-12 | Élevé | Fuite de données personnelles par API publique | `api/user.php` |
| P1-13 | Élevé | Confiance aveugle aux en-têtes d'IP cliente | `includes/auth.php`, `functions.php` |
| P1-14 | Élevé | Divulgation de messages d'erreur techniques | Transversal, `constants.php` |
| P2-1 à P2-18 | Moyen | Voir §3.3 | — |
| P3 | Faible | Voir §3.4 | — |

### Annexe D — Méthode et reproductibilité

**Outils employés :** lecture intégrale des fichiers de configuration, d'authentification et d'accès aux données ; recherche par motif sur l'ensemble du dépôt (`Select-String`) ; analyse du graphe d'inclusion pour identifier le code mort ; extraction et analyse du schéma SQL ; inventaire des références d'actifs pour détecter les orphelins.

**Recherches clés, reproductibles telles quelles :**

| Constat | Commande |
|---|---|
| Compteurs jamais écrits | `Select-String -Path *.php,api\*.php,includes\*.php -Pattern "UPDATE tracks SET total_streams\|total_streams \+"` |
| CSRF manquant | Croiser `Pattern "REQUEST_METHOD.*POST"` et `Pattern "csrf"` |
| Code mort | Croiser la liste des fichiers et les `include`/`require` effectifs |
| CSS orphelin | Extraire `assets/css/*.css` des gabarits, comparer au contenu du répertoire |
| Absence de modération | `Select-String -Pattern "SET status = 'approved'"` → 0 résultat |
| Absence de vente | `Select-String -Pattern "INSERT INTO purchases"` → 0 résultat |
| Fixation de session | `Select-String -Pattern "session_regenerate_id"` → 0 résultat |
| Limitation de débit | `Select-String -Pattern "rate_limit\|throttle"` → 0 résultat |

**Limites, rappelées :** aucun test dynamique n'a été mené (serveur non démarré, base non interrogée). Les vulnérabilités décrites comme exploitables l'ont été déduites par lecture du code et **doivent être confirmées sur un environnement isolé** avant tout signalement externe ou communication. Les estimations de charge supposent un développeur familier de la base de code, et n'intègrent ni recette, ni rédaction juridique, ni négociation avec les opérateurs.

---

## 17. Conclusion

Tchadok n'est pas un projet raté — c'est un projet **inachevé qui se présente comme achevé**. L'écart entre ce que le `README` annonce, ce que l'interface laisse croire et ce que le code exécute est le fil conducteur de tous les problèmes relevés : un module de sécurité qui simule, un tunnel de paiement qui n'encaisse pas, des statistiques qui affichent zéro, un baromètre annoncé et inexistant, des dashboards soignés posés sur des tables vides.

Le socle, lui, est réel : le modèle de données est bien conçu, la direction artistique est aboutie, le moteur radio fonctionne, le module blog est solide. Ce n'est pas une base à jeter.

La séquence est simple et non négociable dans son ordre :

1. **Fermer les portes** (P0, une semaine). Sans cela, rien d'autre n'a de sens.
2. **Rendre la mesure honnête** (P1). Un baromètre falsifiable ne vaut rien — et c'est précisément ce qui doit faire la valeur de Tchadok.
3. **Rendre la vente possible** (P2). C'est la promesse faite aux artistes ; elle n'est aujourd'hui tenue par aucune ligne de code.
4. **Construire la référence** (P2-P3). Classements arrêtés, méthodologie publiée, certifications, archive patrimoniale.

La singularité de ce projet n'est pas d'être un service de streaming de plus. C'est d'être **la source de la mesure** de la musique tchadienne — le chiffre que la presse cite, que le ministère reprend, que l'artiste montre à son producteur. Cet actif se construit par la rigueur de la méthode, pas par le nombre de fonctionnalités. C'est là qu'il faut mettre l'effort.

---

*Audit réalisé le 21 septembre 2026 sur la branche `main` (HEAD `8bed209`). Analyse statique du code et du schéma ; aucun test d'intrusion dynamique n'a été conduit.*
