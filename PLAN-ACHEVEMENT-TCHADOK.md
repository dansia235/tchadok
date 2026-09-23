# Plan d'achèvement Tchadok — liste de tâches vers la mise en production

**Document de travail** — compagnon de `AUDIT-PLATEFORME-TCHADOK.md`
**Date :** 21 septembre 2026
**Objet :** liste exhaustive et ordonnée des tâches pour corriger les vulnérabilités, purger le code mort, rendre le tunnel de paiement fonctionnel, réparer les statistiques, et livrer un environnement local complet (simulateurs Airtel Money, Moov Money, VISA, KONOOM) avant bascule en production.

---

## Mode d'emploi

### Identifiants de tâche

| Préfixe | Domaine |
|---|---|
| `PREP` | Préparation du chantier |
| `CFG` | Configuration multi-environnement (`.env.local` / `.env.production`, `.htaccess.local` / `.htaccess.production`) |
| `SEC` | Sécurité |
| `CLEAN` | Suppression du code mort |
| `DATA` | Schéma, migrations, intégrité |
| `PAY` | Passerelles de paiement et simulateurs locaux |
| `SHOP` | Tunnel d'achat, droits d'accès, livraison des fichiers |
| `SUB` | Abonnements Premium |
| `PAYOUT` | Versements aux artistes |
| `STAT` | Mesure des écoutes, anti-fraude, agrégats |
| `CHART` | Classements et baromètre |
| `TAXO` | Genres et catégories |
| `MOD` | Modération et onboarding artiste |
| `DASH` | Dashboards |
| `UX` | Design system, accessibilité, performance |
| `SEO` | Référencement, partage social, PWA |
| `QA` | Tests, outillage, intégration continue |
| `DEPLOY` | Mise en production |

### Conventions

- **Charge** exprimée en jours-personne (`j`) ou heures (`h`), pour un développeur familier de la base de code. N'inclut ni recette métier, ni rédaction juridique, ni négociation partenaires.
- **Bloque** : tâches qui ne peuvent pas démarrer tant que celle-ci n'est pas terminée.
- Chaque tâche porte des **critères d'acceptation** vérifiables. Une tâche sans critère rempli n'est pas terminée.

### Définition de « terminé »

Une tâche est terminée quand **tous** les points suivants sont vrais :

1. Le code est sur une branche dédiée et fusionné après relecture par une autre personne.
2. Les critères d'acceptation sont vérifiés manuellement, et notés comme tels.
3. Aucune régression sur les parcours critiques : inscription, connexion, publication d'un titre, achat, consultation du baromètre.
4. La documentation touchée est à jour (`README-ENVIRONNEMENT.md`, `database/README.md`, `docs/`).
5. Rien n'a été ajouté à `.gitignore` pour masquer un problème.

### Règle de séquencement

Les lots 0 à 3 sont **séquentiels et bloquants**. À partir du lot 4, plusieurs lots peuvent être menés en parallèle si l'équipe le permet — les dépendances sont indiquées tâche par tâche.

### Récapitulatif des charges

| Lot | Intitulé | Charge | Parallélisable |
|---|---|---:|---|
| 0 | Préparation du chantier | 1,5 j | Non |
| 1 | Configuration multi-environnement | 3 j | Non |
| 2 | Sécurité — critique et élevée (P0 + P1) | 17 j | Non |
| 3 | Suppression du code mort | 2 j | Non |
| 4 | Schéma et migrations | 8 j | Partiellement |
| 5 | Passerelles de paiement et simulateurs locaux | 14 j | Oui |
| 6 | Tunnel d'achat et livraison protégée | 13 j | Après 4 et 5 |
| 7 | Abonnements Premium | 5 j | Après 5 |
| 8 | Versements aux artistes | 8 j | Après 6 |
| 9 | Mesure des écoutes et anti-fraude | 11 j | Après 4 |
| 10 | Agrégats, classements, baromètre | 15 j | Après 9 |
| 11 | Genres et catégories | 5 j | Après 4 |
| 12 | Modération et onboarding artiste | 11 j | Après 11 |
| 13 | Dashboards | 24 j | Après 10 |
| 14 | Design, accessibilité, performance | 15 j | Oui |
| 15 | SEO, partage social, PWA | 6 j | Oui |
| 16 | Qualité, tests, intégration continue | 8 j | Oui |
| 17 | Mise en production | 4 j | Non |
| | **Total** | **≈ 170 j** | |

À une personne à plein temps : environ 8 mois. À trois personnes correctement réparties sur les lots parallélisables : environ 3 à 3,5 mois.

---

## LOT 0 — Préparation du chantier

> Objectif : partir d'une base saine et réversible. Aucune correction ne doit commencer sur l'arbre de travail actuel, qui compte 178 fichiers modifiés non commités.

### PREP-01 — Stabiliser le dépôt

**Charge :** 3 h · **Bloque :** tout

- Créer la branche `chantier/remise-a-plat` depuis `main`.
- Revoir les 178 fichiers modifiés : committer ce qui est intentionnel, restaurer le reste. Ne rien committer dont l'intention n'est pas comprise.
- Poser un tag `avant-chantier` sur `main` pour disposer d'un point de retour.
- Vérifier que `main` est protégée (pas de push direct) si la forge le permet.

**Critères d'acceptation :**
- `git status` retourne un arbre propre.
- Le tag `avant-chantier` existe et pointe sur l'état d'origine.

---

### PREP-02 — Sauvegarde complète et restauration testée

**Charge :** 3 h · **Bloque :** `SEC-*`, `DATA-*`

- Export complet de la base de production (structure + données) avec `mysqldump`, horodaté.
- Archive complète du répertoire `uploads/`.
- **Restaurer la sauvegarde sur une base vierge** et vérifier qu'elle est exploitable. Une sauvegarde non testée n'est pas une sauvegarde.
- Stocker hors du serveur web et hors du dépôt.

**Critères d'acceptation :**
- La base restaurée se connecte et affiche l'accueil du site.
- La procédure de restauration est écrite dans `docs/exploitation/sauvegarde.md`.

---

### PREP-03 — Environnement local de référence

**Charge :** 4 h · **Bloque :** l'ensemble du chantier

- Documenter la version exacte de PHP, MySQL/MariaDB et Apache utilisée par XAMPP sur le poste, et **figer la même version en production**.
- Vérifier les extensions PHP nécessaires : `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (ou `imagick`), `curl`, `json`, `zip`.
- Installer `ffmpeg`/`ffprobe` (nécessaire pour `STAT-02` et `MOD-04`) et noter le chemin.
- Créer une base locale dédiée `tchadok_local`, distincte de toute base de production.

**Critères d'acceptation :**
- `docs/exploitation/pre-requis.md` liste versions et extensions, avec la commande de vérification.
- Toutes les extensions requises sont présentes (`php -m`).

---

### PREP-04 — Créer l'arborescence cible

**Charge :** 2 h · **Bloque :** `CFG-*`

Créer les répertoires qui accueilleront le travail des lots suivants :

```
tchadok/
├── config/
├── includes/
├── database/
│   ├── migrations/        ← NOUVEAU : migrations numérotées
│   └── seeds/             ← NOUVEAU : données de référence et démo
├── docs/
│   ├── exploitation/      ← NOUVEAU
│   ├── paiement/          ← NOUVEAU
│   └── methodologie/      ← NOUVEAU
├── mock-gateways/         ← NOUVEAU : simulateurs de paiement (jamais déployé)
├── scripts/
├── storage/               ← NOUVEAU : hors racine web à terme
│   ├── uploads/
│   ├── logs/
│   └── cache/
└── tests/                 ← NOUVEAU
```

**Critères d'acceptation :**
- Les répertoires existent avec un `.gitkeep`.
- `storage/` et `mock-gateways/` sont référencés dans `.gitignore` pour ce qui doit l'être (contenu de `storage/`, pas le code de `mock-gateways/`).

---

## LOT 1 — Configuration multi-environnement

> Objectif : deux environnements clairement séparés, avec une bascule sûre et sans surprise. C'est le socle de tout le reste : tant que la configuration est ambiguë, chaque correction de sécurité peut être annulée par un fichier oublié.

### Principe retenu

| Fichier | Versionné | Rôle | En production |
|---|:--:|---|---|
| `.env.local` | **Non** | Configuration XAMPP : base locale `tchadok_local`, simulateurs de paiement, debug actif | **Supprimé** |
| `.env.local.example` | Oui | Modèle sans secret, à copier au premier clonage | Présent, sans effet |
| `.env.production` | **Non** | Configuration réelle, déposée manuellement sur le serveur | Présent |
| `.env.production.example` | Oui | Modèle sans secret | Présent, sans effet |
| `.htaccess.local` | Oui | Règles Apache de développement | **Supprimé** |
| `.htaccess.production` | Oui | Règles Apache durcies | Source de `.htaccess` |
| `.htaccess` | **Non** | Fichier **généré** par la bascule — seul fichier lu par Apache | Copie de `.htaccess.production` |

### Point technique à connaître avant de commencer

**Apache ne lit que le fichier nommé `.htaccess`.** Il ignore totalement `.htaccess.local` et `.htaccess.production`. Supprimer `.htaccess.local` en production ne suffit donc pas : si aucun `.htaccess` n'est présent, **toutes les règles de sécurité disparaissent silencieusement** (réécriture d'URL, blocage des fichiers sensibles, en-têtes de sécurité).

Deux tâches en découlent : un script de bascule (`CFG-04`) qui génère `.htaccess` à partir de la bonne source, et un garde-fou applicatif (`CFG-05`) qui refuse de servir le site si la configuration active ne correspond pas à l'environnement déclaré. Le second point est important : il transforme une erreur de déploiement silencieuse en erreur bruyante.

---

### CFG-01 — Réécrire le chargeur d'environnement

**Charge :** 4 h · **Fichier :** `config/env.php` · **Bloque :** `CFG-02`, `CFG-03`, `SEC-07`

Le chargeur actuel cherche `.env`, puis se rabat sur `.env.production`. Ce comportement est dangereux : un poste de développement sans `.env` charge silencieusement la configuration de production.

**À faire :**

1. Nouvel ordre de résolution, strict :
   - `.env.local` s'il existe → environnement local ;
   - sinon `.env.production` s'il existe → environnement de production ;
   - sinon **erreur fatale explicite**, jamais de valeur par défaut.
2. **Supprimer toutes les valeurs par défaut de secrets** dans le code. Aujourd'hui `env('DB_PASSWORD', 'dansia')` fournit un mot de passe de repli : une variable absente passe inaperçue. Toute variable sensible manquante doit lever une exception au démarrage.
3. **Cesser de définir chaque clé du `.env` comme constante PHP globale** (lignes 59-61 actuelles). Seule une liste blanche explicite (`APP_ENV`, `APP_DEBUG`, `SITE_URL`, `APP_TIMEZONE`) devient constante. `DB_PASSWORD`, `APP_KEY`, `ICECAST_ADMIN_PASSWORD` restent accessibles uniquement par `env()`.
4. Ajouter `EnvLoader::requireKeys([...])` qui vérifie au démarrage la présence des variables obligatoires et liste **toutes** celles qui manquent en une fois.
5. Ajouter `EnvLoader::environment()` retournant `'local'` ou `'production'`, déduit du **fichier effectivement chargé** et non d'une variable que l'on peut oublier de changer.
6. Gérer correctement les valeurs multi-lignes, les `#` en fin de ligne et les guillemets, que le parseur actuel traite mal.

**Critères d'acceptation :**
- Sans aucun fichier d'environnement, l'application affiche une erreur explicite nommant les fichiers attendus — pas une page blanche, pas une connexion à une base inconnue.
- Avec `.env.local` présent, `EnvLoader::environment()` retourne `'local'` même si `APP_ENV` dit autre chose, et un avertissement est journalisé en cas d'incohérence.
- `defined('DB_PASSWORD')` retourne `false`.
- Une variable obligatoire manquante produit un message nommant toutes les variables manquantes.

---

### CFG-02 — Produire `.env.local` et son modèle

**Charge :** 3 h · **Dépend de :** `CFG-01`

Créer `.env.local.example` (versionné, sans secret) et `.env.local` (non versionné, sur le poste).

Contenu attendu pour le local :

```ini
# ---- Environnement ----
APP_ENV=local
APP_DEBUG=true
APP_NAME="Tchadok (local)"
APP_URL=http://localhost/tchadok
SITE_URL=http://localhost/tchadok
APP_TIMEZONE=Africa/Ndjamena

# ---- Base de données locale XAMPP ----
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tchadok_local
DB_USERNAME=tchadok_local
DB_PASSWORD=<mot de passe local dédié, pas 'root', pas vide>
DB_CHARSET=utf8mb4

# ---- Clés (valeurs locales distinctes de la production) ----
APP_KEY=base64:<généré localement>
SESSION_SECRET=<généré localement>
JWT_SECRET=<généré localement>

# ---- Sessions ----
SESSION_LIFETIME=7200
SESSION_SECURE=false          # HTTP en local
SESSION_SAMESITE=Lax

# ---- Stockage ----
STORAGE_PATH=./storage
UPLOAD_MAX_SIZE=52428800

# ---- Mail : capture locale, aucun envoi réel ----
MAIL_DRIVER=log               # écrit dans storage/logs/mail.log
MAIL_FROM_ADDRESS=noreply@tchadok.local
MAIL_FROM_NAME="Tchadok (local)"

# ---- Paiement : simulateurs locaux (voir LOT 6) ----
PAYMENT_DRIVER=mock
AIRTEL_BASE_URL=http://127.0.0.1:9101
AIRTEL_CLIENT_ID=mock-client
AIRTEL_CLIENT_SECRET=mock-secret
AIRTEL_WEBHOOK_SECRET=<généré localement>

MOOV_BASE_URL=http://127.0.0.1:9102
MOOV_MERCHANT_ID=mock-merchant
MOOV_API_KEY=mock-key
MOOV_WEBHOOK_SECRET=<généré localement>

VISA_BASE_URL=http://127.0.0.1:9103
VISA_MERCHANT_ID=mock-merchant
VISA_API_KEY=mock-key
VISA_WEBHOOK_SECRET=<généré localement>

KONOOM_BASE_URL=http://127.0.0.1:9104
KONOOM_MERCHANT_ID=mock-merchant
KONOOM_API_KEY=mock-key
KONOOM_WEBHOOK_SECRET=<généré localement>

PAYMENT_CALLBACK_BASE=http://localhost/tchadok

# ---- Radio ----
RADIO_ENGINE_ENABLED=true
RADIO_STREAM_PUBLIC_URL=http://localhost:8000/tchadok.mp3
ICECAST_STATUS_URL=http://127.0.0.1:8000/status-json.xsl
ICECAST_ADMIN_USER=admin
ICECAST_ADMIN_PASSWORD=<local>
RADIO_ENGINE_USE_STATUS_LISTENURL=false

# ---- Garde-fous ----
ALLOW_DEV_TOOLS=true
```

**Points d'attention :**
- Ne **pas** réutiliser `dansia/dansia`. Créer un utilisateur MySQL local dédié avec les droits limités à `tchadok_local`.
- Les clés locales doivent être **différentes** de celles de production, pour qu'une fuite de poste de développement n'ouvre rien.

**Critères d'acceptation :**
- `.env.local` figure dans `.gitignore` ; `.env.local.example` est versionné et ne contient aucune valeur réelle.
- Un nouveau développeur peut démarrer en copiant le modèle et en renseignant 4 valeurs.

---

### CFG-03 — Produire `.env.production` et son modèle

**Charge :** 3 h · **Dépend de :** `CFG-01` · **Lié à :** `SEC-07`

`.env.production` actuel contient `DB_USERNAME=dansia` / `DB_PASSWORD=dansia` et est **versionné**. Il doit devenir un fichier de serveur, jamais commité.

**À faire :**

1. Créer `.env.production.example` : toutes les clés, **aucune valeur réelle**, avec un commentaire par clé expliquant comment l'obtenir.
2. Retirer `.env.production` du suivi Git (désindexation, puis commit), et retirer la ligne `!.env.production` de `.gitignore` qui l'exclut explicitement de l'ignore.
3. Différences structurantes avec le local :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tchadok.td
SITE_URL=https://tchadok.td

SESSION_SECURE=true
SESSION_SAMESITE=Lax
SESSION_LIFETIME=1800          # sessions plus courtes
ADMIN_SESSION_LIFETIME=900     # administration encore plus courtes

MAIL_DRIVER=smtp               # transport réel authentifié

PAYMENT_DRIVER=live            # bascule des simulateurs vers les API réelles
AIRTEL_BASE_URL=https://openapi.airtel.africa
MOOV_BASE_URL=<fourni par Moov>
VISA_BASE_URL=<fourni par l'acquéreur>
KONOOM_BASE_URL=<fourni par KONOOM>
PAYMENT_CALLBACK_BASE=https://tchadok.td

ALLOW_DEV_TOOLS=false
FORCE_HTTPS=true
HSTS_ENABLED=true
```

4. Écrire `docs/exploitation/secrets.md` : liste des secrets, où les obtenir, qui y a accès, et la **procédure de rotation**.

**Critères d'acceptation :**
- Aucune valeur de secret réelle n'est présente dans le dépôt (vérifié par recherche sur `password`, `secret`, `key`).
- `.env.production` et `.env.local` sont tous deux ignorés par Git.
- `git log` confirme la désindexation.

---

### CFG-04 — `.htaccess.local`, `.htaccess.production` et script de bascule

**Charge :** 1 j · **Dépend de :** `CFG-01`

**1. Créer `.htaccess.local`** à partir du `.htaccess` actuel, en conservant le confort de développement mais en corrigeant ce qui fausse les tests :

- Garder : `RewriteBase /tchadok/`, réécriture sans extension, `display_errors On`, limites d'upload élevées.
- **Retirer** `Header set Access-Control-Allow-Origin "*"` — cette ligne globale rend tout test CSRF inopérant et donne une fausse impression de sécurité. Le CORS doit être géré par endpoint, même en local.
- Ajouter le blocage explicite de `mock-gateways/`, `storage/`, `tests/`, `database/`, `scripts/`, `docs/`.
- Ajouter les mêmes en-têtes de sécurité qu'en production, **sauf** HSTS (impossible en HTTP local) — pour que ce qui marche en local marche en production.

**2. Compléter `.htaccess.production`** (le fichier existe déjà, 8,7 Ko, à reprendre) :

- `RewriteBase /`
- Redirection HTTPS forcée et `Strict-Transport-Security` (après validation du certificat).
- `display_errors Off`, `log_errors On`, chemin de log hors racine web.
- `Options -Indexes`, `ServerSignature Off`.
- Blocage : `.env*`, `*.sql`, `*.md`, `*.log`, `composer.*`, `package*.json`, `mock-gateways/`, `storage/`, `tests/`, `database/`, `scripts/`, `docs/`, `.git*`.
- En-têtes : `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, et une CSP (d'abord en `Content-Security-Policy-Report-Only`, bloquante seulement après `UX-03` qui supprime Tailwind CDN — voir la note ci-dessous).
- **Aucun** `Access-Control-Allow-Origin` global.
- Pages d'erreur `/403.php`, `/404.php`, `/500.php` (créées par `SEC-16`).

> **Note CSP :** tant que `cdn.tailwindcss.com` est chargé, une CSP correcte est impossible (le compilateur Tailwind exige `unsafe-eval`). La CSP bloquante est donc conditionnée à `UX-03`. En attendant, la poser en `Report-Only` permet de collecter les violations sans rien casser.

**3. Créer `scripts/env-switch.php`** :

```
php scripts/env-switch.php local
php scripts/env-switch.php production
php scripts/env-switch.php --status
```

Comportement :
- copie `.htaccess.<cible>` vers `.htaccess` ;
- vérifie la présence du `.env.<cible>` correspondant et refuse la bascule s'il manque ;
- en cible `production`, **refuse** de basculer si `.env.local` ou `.htaccess.local` sont encore présents, et les liste ;
- écrit un marqueur `storage/.environment` contenant la cible active et l'horodatage ;
- `--status` affiche l'environnement actif, la source du `.htaccess` en place et les incohérences détectées.

**4. Ajouter `.htaccess` à `.gitignore`** (fichier généré), et documenter dans `README-ENVIRONNEMENT.md` que la première commande après un clonage est `php scripts/env-switch.php local`.

**Critères d'acceptation :**
- `php scripts/env-switch.php local` puis rechargement du site : tout fonctionne en local.
- `php scripts/env-switch.php production` sur un poste où `.env.local` existe encore : la commande **échoue** avec un message nommant les fichiers à retirer.
- `.htaccess` n'est plus suivi par Git.
- `curl -I` sur une page confirme les en-têtes de sécurité attendus dans chaque environnement.

---

### CFG-05 — Garde-fou applicatif de cohérence d'environnement

**Charge :** 4 h · **Dépend de :** `CFG-01`, `CFG-04`

Une erreur de déploiement ne doit jamais être silencieuse. Ajouter `includes/environment-guard.php`, inclus très tôt dans le démarrage de l'application.

**Vérifications, à chaque requête (coût négligeable, mise en cache possible) :**

| Situation détectée | Réaction |
|---|---|
| `.env.local` présent **et** `APP_ENV=production` | Arrêt immédiat, page 503, journalisation critique |
| `.htaccess` absent | Arrêt immédiat : les règles de sécurité ne sont pas actives |
| `.htaccess` ne correspond pas à l'environnement (contrôle par empreinte) | Avertissement critique dans les logs, bandeau admin |
| `APP_DEBUG=true` en environnement `production` | Arrêt immédiat |
| `ALLOW_DEV_TOOLS=true` en `production` | Arrêt immédiat |
| `PAYMENT_DRIVER=mock` en `production` | Arrêt immédiat — le risque d'encaisser dans le vide est majeur |
| Répertoire `mock-gateways/` présent en `production` | Avertissement critique, bandeau admin |
| Secret en valeur de modèle (`CHANGE-ME`, `YOUR_`, `votre-`, `mock-`) en `production` | Arrêt immédiat, avec le nom de la variable fautive |
| `storage/` accessible depuis le web | Avertissement critique |

En local, ces contrôles affichent un bandeau discret plutôt que de bloquer, pour ne pas gêner le développement — sauf le contrôle `PAYMENT_DRIVER`, qui reste informatif dans les deux sens.

**Critères d'acceptation :**
- Déposer un `.env.local` sur un serveur configuré en production provoque une 503 explicite, pas un fonctionnement dégradé.
- Un secret laissé à sa valeur de modèle empêche le démarrage en production, en nommant la variable.
- Les contrôles n'ajoutent pas plus de 2 ms à une requête.

---

### CFG-06 — Réécrire `README-ENVIRONNEMENT.md`

**Charge :** 4 h · **Dépend de :** `CFG-01` à `CFG-05`

Le document actuel décrit l'ancien mécanisme (`.env` unique, copie de `.env.production` en `.env`) et référence des scripts de comptes de test supprimés. À reprendre intégralement :

- Installation locale pas à pas : pré-requis, base `tchadok_local`, utilisateur MySQL dédié, copie de `.env.local.example`, bascule `env-switch local`, import du schéma, lancement des simulateurs de paiement (LOT 6), premier compte administrateur.
- Déploiement production : checklist ordonnée, bascule `env-switch production`, dépôt manuel de `.env.production`, suppression de `.env.local`, `.htaccess.local`, `mock-gateways/`, `tests/`.
- Tableau des variables : nom, obligatoire ou non, valeur locale type, où obtenir la valeur de production.
- Dépannage : symptômes réels et causes.

**Critères d'acceptation :**
- Une personne n'ayant jamais vu le projet installe l'environnement local en suivant le document, sans poser de question.
- Le document ne mentionne plus aucun fichier supprimé par le `LOT 3`.

---

## LOT 2 — Sécurité (P0 et P1)

> Objectif : fermer les onze portes ouvertes identifiées dans l'audit, puis traiter les quatorze failles de niveau élevé.
>
> **`SEC-01` à `SEC-08` sont les blocants P0 : aucune mise en ligne, aucune démonstration publique, aucune communication avant leur achèvement.** Les trois premières se font le premier jour. `SEC-09` à `SEC-20` couvrent le niveau P1 et peuvent démarrer en parallèle du LOT 3 une fois les P0 traités.

### SEC-01 — Neutraliser immédiatement les API d'administration ouvertes

**Charge :** 4 h · **Priorité :** immédiate · **Fichiers :** `api/user.php`, `api/artist.php`, `api/transaction.php`

Ces trois endpoints n'ont **aucun contrôle d'accès**. `api/user.php?action=create&user_type=admin` crée un administrateur avec le mot de passe `12345678`.

**Action en deux temps.**

*Temps 1 — dans l'heure :* remplacer le corps des trois fichiers par une réponse `503`, exactement comme cela a été fait pour `api/payment.php`. La fonctionnalité perdue est celle d'un dashboard admin qui ne l'utilise pas réellement ; le risque supprimé est la prise de contrôle totale.

*Temps 2 — réécriture propre :*
- Garde d'authentification en tête de fichier : session valide + rôle requis.
- Contrôle de méthode HTTP : lecture en `GET`, écriture en `POST`, jamais de suppression par `GET`.
- Jeton CSRF obligatoire sur toute écriture (dépend de `SEC-09`).
- `user_type` **retiré** des champs acceptés à la création ; la promotion au rôle administrateur devient une action distincte, réservée au `super_admin`, journalisée, avec confirmation par mot de passe.
- Suppression de compte : passage en suppression logique (`deleted_at`), jamais de `DELETE` SQL (dépend de `DATA-06`).
- Action groupée : plafonnée à 50 identifiants, confirmation explicite, journalisation.
- Aucun mot de passe par défaut : à la création, un jeton d'invitation à usage unique est envoyé par e-mail.
- Réponse : uniquement les champs nécessaires à l'écran appelant (pas de `SELECT` global).

**Critères d'acceptation :**
- Un appel anonyme à chacune des 14 actions des trois fichiers retourne `401`.
- Un utilisateur authentifié non-administrateur obtient `403`.
- Aucun chemin ne permet de créer ou promouvoir un administrateur sans être `super_admin`.
- Chaque action d'écriture produit une entrée dans le journal d'audit (dépend de `SEC-19`).

---

### SEC-02 — Retirer les outils d'administration destructeurs

**Charge :** 2 h · **Priorité :** immédiate

Fichiers à retirer du dépôt et du serveur :

| Fichier | Ce qu'il fait |
|---|---|
| `admin/update-passwords.php` | Remet **tous** les mots de passe à `12345678`. Garde-fou inopérant (`$_SESSION['admin_id']` jamais défini par le flux réel), aucun jeton CSRF |
| `admin/execute-sql.php` | Console SQL web, identifiants en dur, branche `update_all_passwords`, création d'un admin `12345678` |
| `install.php` | Installeur public, se connecte en `root` sans mot de passe, crée `admin@tchadok.td / password123` et **affiche les identifiants** |
| `validate-placeholders.php` | Page de test exposée en racine web |
| `api/docs.php` | Documentation publique de toute la surface d'API |
| `admin/create-test-accounts.php`, `admin/reset-database.php` | Déjà neutralisés, sans usage |
| `database/install.php`, `database/generate-test-data.php` | Déjà neutralisés |

**Également :** retirer la carte « Maintenance » du dashboard admin (`admin-dashboard.php`, entrée pointant vers `admin/execute-sql.php`) et l'entrée correspondante de `includes/admin-shell-header.php`.

**Ne pas remplacer par un équivalent.** Le besoin légitime de réinitialisation est couvert par `admin/reset-password.php`, qui est correctement écrit (CSRF, jeton haché, expiration, message neutre). Les migrations de schéma passent par `DATA-01`, hors serveur web.

**Critères d'acceptation :**
- Les URL correspondantes retournent 404.
- Aucun lien du site ne pointe vers ces fichiers.
- `admin/reset-password.php` fonctionne toujours (test bout en bout du parcours « mot de passe oublié »).

---

### SEC-03 — Faire tourner tous les secrets compromis

**Charge :** 3 h · **Priorité :** immédiate · **Dépend de :** `CFG-03`

Tous les secrets ci-dessous sont présents dans le dépôt Git ou dans le code. Ils doivent être **considérés comme publics** et remplacés, indépendamment de la correction du code.

| Secret | Emplacement actuel | Action |
|---|---|---|
| Mot de passe MySQL | `.env`, `.env.production`, `config/database.php`, `admin/execute-sql.php`, `admin/update-passwords.php` | Nouveau mot de passe fort ; nouvel utilisateur MySQL avec droits limités à la base applicative (pas de `GRANT ALL`, pas de `SUPER`) |
| `JWT_SECRET` | `config/constants.php` l. 108 | Régénérer (`openssl rand -base64 48`), déplacer dans le fichier d'environnement |
| `APP_KEY`, `SESSION_SECRET` | `.env` (valeurs de modèle) | Régénérer, différentes par environnement |
| `ICECAST_ADMIN_PASSWORD` | `.env`, `.env.production` (`CHANGE-ME`) | Régénérer, reconfigurer Icecast |
| Identifiants SMTP | `config/constants.php` l. 82 | Régénérer côté fournisseur, déplacer dans le fichier d'environnement |
| Mot de passe du `super_admin` | `database/tchadok.sql` (hash versionné, mot de passe documenté comme public) | Changer en base immédiatement |

**Également :** vérifier si la base de production est joignable depuis Internet. Si oui, la restreindre à `localhost` ou au réseau applicatif — un mot de passe public sur un port ouvert est une compromission déjà effective.

**Critères d'acceptation :**
- Aucun des anciens secrets ne fonctionne plus.
- Aucun secret n'est lu depuis le code PHP : tout passe par `env()`.
- `docs/exploitation/secrets.md` recense la rotation effectuée, datée.

---

### SEC-04 — Aucun secret dans Git

**Charge :** 3 h · **Dépend de :** `SEC-03`, `CFG-02`, `CFG-03` · **Statut :** fait le 22/09/2026, **réécriture d'historique écartée**

> **Tâche révisée le 22/09/2026.** La formulation initiale prévoyait de purger l'historique avec `git filter-repo` puis de forcer la republication. Un inventaire complet de l'historique a montré qu'il ne contient **aucun vrai secret** : uniquement des valeurs de modèle, les identifiants locaux `dansia` (locaux par décision du 22/09/2026), un `JWT_SECRET` jamais utilisé, et les mots de passe par défaut d'outils supprimés. Aucune clé privée, aucun jeton cloud, aucune URL avec identifiants. La réécriture — qui change tous les identifiants de commit et casse le clone de chaque collaborateur — a donc été écartée. Détail et justification : `docs/exploitation/secrets.md`, section 3.4.

**Réalisé :**
1. `.env` et `.env.production` désindexés (en `SEC-03`) ; `.gitignore` corrigé, la ligne `!.env.production` qui les ré-autorisait retirée.
2. `.htaccess`, fichier désormais généré, retiré du suivi (en `CFG-04`).
3. **Hook de pré-commit** versionné, `scripts/git-hooks/pre-commit` : refuse tout commit ajoutant un fichier d'environnement réel, `.htaccess`, ou un motif de vrai secret. N'affiche jamais le contenu en cause. Activation : `git config core.hooksPath scripts/git-hooks`.

**Reste à faire :**
- Rejouer le même contrôle côté serveur dans l'intégration continue (`QA-04`), où il ne peut pas être contourné par `--no-verify`.
- Changer en production tout compte utilisant encore `password123` ou `12345678`. C'est le seul risque résiduel, et la réécriture d'historique ne l'aurait pas traité.

**Critères d'acceptation :**
- Aucun fichier d'environnement réel n'est suivi par Git. ✔
- Un commit ajoutant `.env.local` est refusé. ✔ (testé)
- Un commit introduisant une clé AWS ou un `define()` de secret est refusé, sans que la valeur apparaisse dans la sortie. ✔ (testé)

---

### SEC-05 — Retirer les comptes du dump de référence

**Charge :** 3 h · **Fichier :** `database/tchadok.sql` · **Dépend de :** `PREP-02`

Le dump livre un `super_admin` (`admin@tchadok.td`) et un compte démo partageant **le même hash bcrypt**, avec un mot de passe documenté comme public dans `sql/README-COMPTES-TEST.md`.

**À faire :**
1. Retirer les instructions d'insertion dans `users` et `admins` du dump de référence.
2. Créer `database/seeds/demo.sql` contenant les comptes de démonstration — **jamais importé en production**, et vérifié par `CFG-05`.
3. Créer `scripts/create-admin.php`, exécutable **uniquement en ligne de commande** (`PHP_SAPI === 'cli'`, refus sinon) : demande e-mail et mot de passe en saisie masquée, impose une politique de robustesse, refuse les mots de passe des listes courantes, crée le compte et l'entrée `admins`, journalise.
4. Changer immédiatement le mot de passe du `super_admin` existant en base.
5. Retirer `sql/README-COMPTES-TEST.md` et les scripts de comptes de test et d'administrateur du répertoire `sql/`.

**Critères d'acceptation :**
- Une installation neuve depuis le dump n'a **aucun** compte.
- `scripts/create-admin.php` appelé par HTTP retourne une erreur et ne fait rien.
- Un mot de passe faible est refusé avec un message clair.

---

### SEC-06 — Protéger les fichiers déposés et couper l'accès libre au contenu payant

**Charge :** 1 j · **Dépend de :** `CFG-04` · **Complété par :** `SHOP-05` · **Statut :** fait le 22/09/2026 (points 1 à 6), point 7 reporté

> **Bilan du 22/09/2026.** La faille était plus nette encore que décrite : le contrôle d'accès était **entièrement fait dans le navigateur**. `assets/js/player.js` et `assets/js/main.js` décidaient eux-mêmes, à partir de `is_free` et d'un drapeau Premium, si un titre était lisible — alors que `api/track.php` leur renvoyait le chemin du fichier pour tous les titres. La décision est désormais prise côté serveur (`includes/media-access.php`), le client ne reçoit plus que des URL signées, liées à la session, émises uniquement si l'accès est accordé, et servies par `media.php` qui revérifie tout.
>
> **Réalisé :** interdiction d'accès direct (`uploads/audio/.htaccess`, `storage/.htaccess`) ; exécution de scripts coupée dans les dépôts (`uploads/.htaccess`) ; audio et documents déposés sous `storage/uploads/` ; épisodes de podcast placés explicitement dans un emplacement public dédié (`uploads/podcasts/`), car gratuits par nature ; `media.php` avec requêtes partielles, libération du verrou de session avant envoi, refus des URL externes et de la traversée de répertoire ; noms de fichiers en `random_bytes`. Statut Premium lu en base à chaque requête, expiration comprise.
>
> **Avancé depuis `SEC-10` :** `cookie_secure` suivait une valeur forcée à 1 — en HTTP, le cookie de session n'était jamais renvoyé par Firefox, Safari ou curl, rendant la connexion impossible en local. Il suit désormais `SESSION_SECURE`.
>
> **Vérifié :** `tests/securite/sec06-acces-media.ps1`, 39 contrôles, tous au vert.
>
> **Reporté :** point 7 (génération automatique de l'extrait de 30 s) — nécessite `ffmpeg`, absent du poste, et relève de `MOD-04`. En attendant, un titre payant sans extrait déposé manuellement n'est pas écoutable avant achat. `mod_xsendfile` étant absent de XAMPP, l'envoi se fait par PHP ; le chemin `X-Sendfile` est prêt (`MEDIA_SENDFILE=xsendfile`). Les quotas de téléchargement relèvent de `SHOP-04/05`.

Les fichiers audio sont dans `uploads/audio/`, sous la racine web, servis directement par Apache. `api/track.php` renvoie le chemin de n'importe quel titre sans vérifier ni le prix ni un achat, avec `Access-Control-Allow-Origin: *`. **Tout le catalogue payant est actuellement gratuit.**

**Mesures immédiates (le jour même) :**
1. Créer `uploads/.htaccess` : `Require all denied` (Apache 2.4) **et** `php_flag engine off` en défense en profondeur.
2. Retirer `audio_file` et `preview_file` des réponses de `api/track.php` ; ne renvoyer qu'un identifiant de média à résoudre par un contrôleur dédié.
3. Retirer `Access-Control-Allow-Origin: *` de `api/track.php`.

**Mesures structurelles (dans ce lot) :**
4. Déplacer le répertoire d'upload vers `storage/uploads/`, **hors racine web**, et adapter tous les chemins (constantes `UPLOADS_PATH`, `AUDIO_PATH`, `IMAGES_PATH`).
5. Créer `media.php`, unique point de service des fichiers : il résout l'identifiant, vérifie le droit d'accès (gratuit, acheté, ou couvert par un abonnement actif — logique complète en `SHOP-05`), journalise, et délègue l'envoi au serveur web via `X-Sendfile` / `mod_xsendfile`.
6. Renommer les fichiers déposés avec `random_bytes(16)` plutôt que `uniqid()`, qui est horodaté donc énumérable.
7. Mettre en place l'extrait de prévisualisation : la colonne `preview_file` existe et n'est pas exploitée. Générer automatiquement 30 s à faible débit à la publication (`MOD-04`), seul fichier accessible sans droit.

**Critères d'acceptation :**
- L'accès direct à un fichier audio par URL retourne 403 ou 404.
- Sans achat, `media.php?id=<titre payant>` retourne 402 ou 403 ; l'extrait reste accessible.
- Un fichier `.php` déposé dans le répertoire d'upload n'est pas exécuté.

---

### SEC-07 — Supprimer la seconde couche d'accès à la base

**Charge :** 2 h · **Statut :** fait le 22/09/2026

> **Bilan.** `config/database.php` a été supprimé dès `SEC-03`, puisqu'il portait des identifiants en dur. Restaient les deux seuls consommateurs de son API : `pages/admin/dashboard.php` et `pages/artist/dashboard.php`, prévus au `LOT 3` (`CLEAN-02`). Vérification faite, ils étaient **atteignables** et, pour un administrateur connecté, produisaient une **erreur fatale exposant le chemin serveur** (`$db->fetchOne()` sur une variable indéfinie). Ils ont donc été retirés dès maintenant ; aucune page du site n'y renvoyait. `CLEAN-02` n'a plus à les traiter.
>
> **Relevé en passant :** `config/payment.php` (l. 368) construit un lien de notification vers `pages/user/purchases.php`, **qui n'existe pas**. À corriger avec la page « Ma bibliothèque » (`SHOP-04`).

`config/database.php` déclare une classe `Database` avec les identifiants en dur, n'est requis par **aucun fichier**, et a déjà provoqué une erreur fatale tracée dans `logs/php_errors.log` (« Cannot declare class Database, because the name is already in use »).

**À faire :** retirer le fichier. Vérifier au préalable qu'aucun code ne référence `new Database` ni `$db->fetchOne` / `$db->fetchAll` hors des fichiers eux-mêmes retirés au `LOT 3` (`pages/admin/dashboard.php`, `pages/artist/dashboard.php`).

**Critères d'acceptation :**
- Recherche sur `new Database(` : aucun résultat.
- Le site fonctionne sur les cinq parcours critiques.

---

### SEC-08 — Retirer le contenu non modéré du catalogue public

**Charge :** 2 h · **Fichier :** `includes/database.php` · **Statut :** fait le 22/09/2026

> **Bilan.** Le périmètre réel était plus large que les occurrences `draft` annoncées (7, et non 8). Un recensement de toutes les requêtes publiques sur `tracks` et `albums` a montré que **la recherche publique n'avait aucun filtre** : brouillons, titres en attente et titres **rejetés** sortaient dans les résultats, ainsi que les artistes désactivés. Les compteurs publics (« X titres » par artiste, statistiques de la page d'accueil, artistes par genre, écoutes récentes) comptaient eux aussi le contenu non publié.
>
> **Réalisé :**
> - les 7 filtres `approved OR draft` réduits à `approved` ;
> - `searchContent` : titres et albums publiés uniquement, artistes actifs uniquement ;
> - `getPlatformStats`, `tracks_count` (3 fonctions), `getTopArtistsByGenre`, `getGenresWithStats`, `getRecentStreams`, `getAlbumTypes` : contenu publié uniquement ;
> - **artistes désactivés exclus** de toutes les listes publiques — un artiste suspendu, pour fraude par exemple, ne reste plus en vitrine ;
> - `api/stream.php` n'enregistre plus d'écoute sur un titre non publié : un propriétaire ou un administrateur lisant un brouillon ne gonfle plus les compteurs publics via le trigger.
>
> **Point 3 du plan, non appliqué — formulation erronée.** `admin-add-song.php` et `admin-add-album.php` sont des pages **administrateur** : les administrateurs sont les modérateurs, leur retirer `approved` les empêcherait de publier. Les pages artistes (`artist-add-song.php`, `artist-add-album.php`, `upload.php`) forcent déjà `pending` pour les titres.
>
> **Incident corrigé en cours de tâche :** une première correction par expression régulière avait produit `WHEREa.status = 'approved'ORDER BY` sur trois requêtes — valide pour `php -l`, invalide pour MySQL, et masqué par les fonctions qui interceptent les erreurs SQL et renvoient un tableau vide. D'où `tests/securite/sec08-catalogue-public.php`, qui **exécute** chaque fonction publique contre la base, avec contrôles positifs et surveillance du journal d'erreurs. **36 contrôles, tous au vert.**
>
> **À faire avant déploiement en production** — compter le contenu qui va disparaître du catalogue public :
> ```sql
> SELECT 'titres' AS type, status, COUNT(*) FROM tracks WHERE status <> 'approved' GROUP BY status
> UNION ALL
> SELECT 'albums', status, COUNT(*) FROM albums WHERE status <> 'approved' GROUP BY status
> UNION ALL
> SELECT 'titres publies d''artistes inactifs', 'approved', COUNT(*)
>   FROM tracks t JOIN artists ar ON ar.id = t.artist_id
>  WHERE t.status = 'approved' AND ar.is_active = 0;
> ```
> Arbitrer avec l'équipe éditoriale **avant** la mise en ligne. Ne pas basculer ce contenu en masse vers `approved` sans revue : c'est précisément le problème corrigé.
>
> **Relevé pour `MOD-01` :** `upload.php` crée automatiquement des albums en `draft`, sans aucun chemin vers `pending` ni `approved`. Un titre approuvé peut donc appartenir à un album qui ne sera jamais visible.

Huit requêtes publiques incluent les contenus en `draft` : lignes 101, 269, 304, 339, 675, 745, 927.

**À faire :**
1. Remplacer partout `WHERE status = 'approved' OR status = 'draft'` par `WHERE status = 'approved'`.
2. Vérifier que `upload.php` ne crée plus d'album publié sans revue : la création reste en `draft` mais **invisible publiquement**, ce qui est le comportement attendu.
3. Retirer `draft` et `approved` des valeurs de statut soumissibles par un artiste dans `admin-add-song.php` et `admin-add-album.php`.

**Attention :** cette correction va faire disparaître du catalogue public tous les contenus actuellement en `draft`. Compter le volume concerné avant application et décider avec l'équipe éditoriale s'il faut les basculer en `approved` (après revue) ou les laisser masqués. Ne pas basculer en masse sans revue — c'est précisément le problème que l'on corrige.

**Critères d'acceptation :**
- Un titre en `draft` ou `pending` n'apparaît sur aucune page publique, ni dans les résultats de recherche, ni dans l'API.
- Le nombre de contenus rendus invisibles est documenté et arbitré.

---

### SEC-09 — Protection CSRF centralisée

**Charge :** 1 j · **Statut :** fait le 22/09/2026

> **Bilan.** `includes/csrf-guard.php`, chargée en fin de `includes/functions.php`, vérifie toute requête `POST`, `PUT`, `PATCH` ou `DELETE` **avant** l'exécution du point d'entrée. Active par défaut : un formulaire ajouté sans jeton échoue au lieu de passer. Exemption uniquement par `define('TCHADOK_CSRF_EXEMPT', '<raison>')`, raison obligatoire, journalisée — réservée aux callbacks de paiement (`PAY-04`). Jeton accepté par champ `csrf_token`, en-tête `X-CSRF-Token` ou corps JSON ; renouvelé à la connexion. Jeton ajouté aux 23 formulaires. Côté JavaScript, un correctif de `fetch()` ajoute l'en-tête à toute requête modifiante **vers le site uniquement** : la protection est aussi active par défaut pour le code à venir.
>
> **Code 403 et non 419.** Le plan prévoyait 419 (convention de Laravel). Apache ne connaît pas ce code et le transforme en **500** : le refus devenait indistinguable d'une panne. 403 est le code standard d'un refus CSRF. Les réponses JSON portent `reason: "csrf"` pour que le front distingue ce cas d'un refus d'autorisation. **`419.php` prévu en `SEC-16` n'a donc plus lieu d'être.**
>
> **Traité en plus :**
> - envoi au-delà de `post_max_size` : PHP vide alors `$_POST`, jeton compris ; la garde répond **413 « fichier trop volumineux »** plutôt que « session expirée » ;
> - `verifyCSRFToken()` plantait en PHP 8 (`TypeError`) quand le jeton était absent ;
> - **déconnexion** : passée en `POST` (un lien `GET` permettait à n'importe quel site de déconnecter un utilisateur par une image), et elle **ne déconnectait pas les comptes « se souvenir de moi »** — la session était détruite mais pas le cookie `remember_token`, et l'utilisateur était reconnecté dès la requête suivante. Vérifié sur l'ancienne version, corrigé. Redirection limitée au site lui-même.
>
> **Vérifié :** `tests/securite/sec09-csrf.ps1` (64 contrôles) et `tests/securite/sec09-fetch-patch.js` (17 contrôles), tous au vert ; suites `SEC-06` et `SEC-08` adaptées pour envoyer le jeton comme un navigateur, toujours au vert.
>
> **Relevé pour `SEC-14` / `SEC-20` :** `security-settings.php` utilise par défaut le secret TOTP d'exemple public `JBSWY3DPEHPK3PXP`, identique pour tous, et fait générer le QR code par un **service tiers** (`api.qrserver.com`) en lui transmettant le secret dans l'URL.

22 fichiers traitent des requêtes `POST` ; **4** seulement vérifient le jeton CSRF. Les fonctions nécessaires existent déjà dans `includes/functions.php`.

**À faire :**
1. Créer `includes/csrf-guard.php` : sur toute requête `POST`, `PUT`, `PATCH` ou `DELETE`, vérifie le jeton et rejette en `419` avec une page explicite si absent ou invalide. Comparaison par `hash_equals`.
2. Inclure ce garde dans le démarrage commun de l'application, en **exclusion explicite** plutôt qu'en adhésion explicite : un nouveau formulaire est protégé par défaut. La seule exception légitime est la réception des callbacks de paiement, protégée autrement (signature HMAC, `PAY-04`).
3. Ajouter `csrfField()` dans les 18 formulaires non protégés : `login.php`, `register.php`, `upload.php`, `artist-add-song.php`, `artist-add-album.php`, `admin-add-song.php`, `admin-add-album.php`, `admin-manage-radio.php`, `admin-playlists.php`, `admin-podcasts.php`, `edit-profile.php`, `settings.php`, `security-settings.php`, `create-playlist.php`, `premium-payment.php`, `contact.php`.
4. Pour les appels JavaScript : exposer le jeton via une balise `<meta name="csrf-token">` et l'envoyer en en-tête `X-CSRF-Token`, vérifié par le même garde.
5. Lier le jeton à la session et le régénérer à chaque changement de privilège.

**Critères d'acceptation :**
- Une requête `POST` sans jeton sur chacun des 22 points d'entrée retourne `419`.
- Les 22 formulaires fonctionnent normalement depuis le site.
- Un formulaire ajouté sans jeton **échoue** — la protection est active par défaut.

---

### SEC-10 — Durcir la session

**Charge :** 4 h · **Fichiers :** `includes/functions.php`, `includes/auth.php` · **Statut :** fait le 22/09/2026

> **Bilan.** Points 1 à 6 et 8 réalisés comme prévu : identifiant régénéré à la connexion (session vidée d'abord, jeton CSRF renouvelé) et à chaque changement de mot de passe ; `use_strict_mode`, cookies seuls, pas d'identifiant dans les URL ; cookie `TCHADOKSESSID` `HttpOnly`, `SameSite=Lax`, `Secure` selon `SESSION_SECURE`, sans date d'expiration ; inactivité limitée par `SESSION_LIFETIME` (30 min par défaut) et `ADMIN_SESSION_LIFETIME` (15 min par défaut ; 2 h dans `.env.local`), avec un message sur la page de connexion expliquant pourquoi la session a été fermée ; `user_sessions.data` n'est plus rempli.
>
> **Écart au plan (point 7) : un registre des sessions plutôt que `password_changed_at`.** `user_sessions` devient le registre de référence : chaque session authentifiée y a une ligne, contrôlée à chaque requête (une lecture par clé primaire ; `last_activity` réécrit au plus une fois par minute). Changer son mot de passe supprime les lignes des autres sessions, qui sont fermées à leur requête suivante. Ce choix évite une migration de schéma (la colonne `password_changed_at` prévue en `DATA-02` n'est plus nécessaire), permet la révocation **d'une** session, dont l'écran « Appareils connectés » de `SEC-11` a besoin, et fait qu'une déconnexion ferme réellement la session côté serveur. Si l'inscription au registre échoue à la connexion (table absente), la session fonctionne sans possibilité de révocation plutôt que de rendre la connexion impossible ; l'incident est journalisé.
>
> **Traité en plus :**
> - **La page de réinitialisation du mot de passe administrateur s'effaçait elle-même.** `admin/reset-password.php` exécutait `@unlink(__FILE__)` après la première réinitialisation réussie : la page disparaissait, et le lien « mot de passe oublié » de la console menait ensuite à une 404 pour tous les administrateurs. Supprimé ; la réinitialisation ferme désormais toutes les sessions du compte.
> - **Changer son mot de passe laissait « se souvenir de moi » actif** sur les autres appareils, qui étaient reconnectés aussitôt. Le jeton est maintenant invalidé en même temps que les sessions.
> - **Plus de connexion automatique pour les administrateurs** : elle contournait le délai d'inactivité de 15 min. La case est ignorée pour eux, et `checkRememberMe()` refuse les comptes administrateurs.
> - Le cookie `remember_token` avait `Secure` forcé à `false` : en production, il aurait circulé en clair sur toute requête HTTP. Il suit désormais `SESSION_SECURE`, avec `SameSite=Lax`.
> - **Refus d'un non-administrateur sur la console** : `Auth::logout()` détruisait la session, si bien que le formulaire réaffiché portait un jeton CSRF qui n'était plus enregistré, et l'essai suivant était refusé (403). La déconnexion vide et régénère la session au lieu de la détruire.
> - `login.php` : messages d'erreur et de succès désormais échappés.
>
> **Vérifié :** `tests/securite/sec10-session.ps1` (35 contrôles, dont un vrai délai d'inactivité de 60 s obtenu en abaissant temporairement `ADMIN_SESSION_LIFETIME`, et un scénario de fixation de session) ; suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17) toujours au vert ; 19 pages publiques en 200, aucune erreur PHP. La section « se souvenir de moi » de `sec09-csrf.ps1` utilise maintenant le compte mélomane, puisqu'un administrateur n'y a plus droit.
>
> **Reste pour `SEC-11` :** le déni de service de `checkRememberMe()` (un bcrypt par compte porteur d'un jeton, à chaque requête) n'est pas traité ici.

`session_regenerate_id()` n'apparaît **nulle part** dans le projet : l'identifiant de session est conservé entre l'état anonyme et l'état administrateur (fixation de session). `startSecureSession()` force par ailleurs `cookie_secure = 1` inconditionnellement, ce qui casse la session sur tout environnement HTTP non-localhost.

**À faire :**
1. `session_regenerate_id(true)` immédiatement après validation du mot de passe, et après tout changement de privilège.
2. `session.use_strict_mode = 1` : rejette un identifiant non émis par le serveur.
3. `session.cookie_samesite = 'Lax'`.
4. `session.cookie_secure` piloté par `SESSION_SECURE` du fichier d'environnement (`false` en local, `true` en production).
5. Renommer le cookie de session (`TCHADOKSESSID`) pour ne pas annoncer la technologie.
6. Expiration d'inactivité distincte : `SESSION_LIFETIME` pour les comptes ordinaires, `ADMIN_SESSION_LIFETIME` (15 min) pour l'administration.
7. Invalider toutes les sessions d'un utilisateur au changement de mot de passe (ajout d'une colonne `password_changed_at`, comparée à la date d'ouverture de session).
8. Ne plus sérialiser `$_SESSION` entière dans `user_sessions.data` : ne conserver que IP, agent et dernière activité.

**Critères d'acceptation :**
- L'identifiant de session change à la connexion (vérifié dans l'inspecteur du navigateur).
- Un identifiant forgé n'ouvre pas de session.
- Le cookie porte `HttpOnly`, `SameSite=Lax`, et `Secure` en production uniquement.
- Un changement de mot de passe déconnecte les autres appareils.

---

### SEC-11 — Corriger « Se souvenir de moi »

**Charge :** 1 j · **Fichier :** `includes/auth.php` (lignes 170-199) · **Statut :** fait le 23/09/2026

> **Bilan.** Les sept points sont traités. Le cookie porte désormais un couple **sélecteur + vérificateur** : le sélecteur est une clé publique indexée, le vérificateur un secret de 32 octets. Identifier le porteur d'un cookie demande **une lecture par clé unique et une comparaison**, contre un `bcrypt` par compte auparavant. Mesuré avec 61 jetons en base : **0,12 s**, là où l'ancienne version aurait dépassé 10 s — et ce coût était offert à n'importe quel visiteur anonyme, en boucle. Le vérificateur tourne à chaque usage, le cookie suit `REMEMBER_LIFETIME` (30 jours), avec `Secure` selon `SESSION_SECURE`, `HttpOnly` et `SameSite=Lax`. La colonne `users.remember_token` est supprimée ; la migration est dans `database/migrations/2026-09-22-sec11-remember-tokens.sql`, et `database/tchadok.sql` est à jour.
>
> **Empreinte SHA-256 et non bcrypt.** Le vérificateur est une valeur aléatoire de 32 octets, pas un mot de passe choisi par un humain : il n'y a pas d'attaque par dictionnaire à ralentir. Le ralentir volontairement recréerait le déni de service que cette tâche corrige.
>
> **Tolérance de rotation.** Un navigateur envoie souvent plusieurs requêtes en parallèle avec le même cookie, et une seule réponse fixe le nouveau. Le vérificateur précédent reste donc accepté **120 secondes** après une rotation. Au-delà, un vérificateur périmé signifie que le cookie a été copié : **tous les jetons du compte sont révoqués** et l'incident est journalisé.
>
> **Trouvé pendant les tests : fermer une session ne suffisait pas.** L'appareil écarté depuis l'écran « Appareils connectés » revenait à la requête suivante grâce à son cookie. Chaque jeton retient maintenant la session qu'il a ouverte (`remember_tokens.session_id`) : fermer une session retire aussi sa connexion automatique.
>
> **Traité en plus :**
> - **Déconnexion par appareil** : `logout` invalidait le jeton unique du compte, donc déconnectait tous les appareils. Seul celui qui se déconnecte est concerné.
> - **Fonctions de simulation retirées** de `includes/advanced-auth.php` (`createRememberToken`, `verifyRememberToken`, `deleteRememberTokens`, `storeRememberToken`, `getRememberToken`). `getRememberToken()` renvoyait en dur l'utilisateur 1, le super-administrateur : brancher ce chemin donnait le compte le plus privilégié à qui présentait un cookie. Elles n'étaient appelées nulle part. Cela couvre par avance le point 3 de `SEC-14`.
> - **Écran « Appareils connectés »** (`settings.php#devices`) : sessions ouvertes et appareils mémorisés, date de dernier usage, révocation à l'unité ou globale. **Aucun identifiant de session n'apparaît dans la page** — les formulaires portent une empreinte SHA-256, vérifiée côté serveur avec le propriétaire. Un jeton appartenant à un autre compte ne peut pas être révoqué.
>
> **Vérifié :** `tests/securite/sec11-souvenir.ps1` (50 contrôles) ; suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17), `SEC-10` (35) au vert ; 18 pages publiques en 200, aucune erreur PHP. Le contrôle `SEC-10` qui lisait `users.remember_token` a été porté sur la nouvelle table.
>
> **Ce que cela ne couvre pas :** un cookie copié reste utilisable tant que la personne légitime ne revient pas — c'est inhérent au mécanisme, la rotation et la détection de réutilisation en limitent la durée. La limitation de débit sur la connexion relève de `SEC-12`.

`checkRememberMe()` charge **tous** les utilisateurs porteurs d'un jeton et exécute un `bcrypt` (coût 12) sur chacun, à chaque requête d'un visiteur non connecté présentant un cookie. Avec 10 000 comptes, une seule requête consomme plusieurs minutes de CPU : déni de service trivial.

**À faire :**
1. Créer la table `remember_tokens` : `id`, `user_id`, `selector` (unique, indexé), `validator_hash`, `expires_at`, `device_label`, `ip_address`, `last_used_at`, `revoked_at`.
2. Cookie au format `selector:validator`. Recherche par `selector` indexé, puis **une seule** comparaison `hash_equals` sur le hash du validateur.
3. Rotation du validateur à chaque usage : un jeton volé devient inutilisable dès la prochaine connexion légitime.
4. Cookie `Secure` (en production), `HttpOnly`, `SameSite=Lax`, durée alignée sur la politique retenue.
5. Retirer la colonne `users.remember_token`.
6. Écran « Appareils connectés » dans les paramètres : liste, date de dernier usage, révocation unitaire et globale.
7. **Si le lot ne peut pas être fait immédiatement :** désactiver purement et simplement la case « se souvenir de moi ». Le confort perdu ne justifie pas un vecteur de déni de service.

**Critères d'acceptation :**
- Une requête avec un cookie invalide déclenche **une** requête SQL indexée et **zéro** appel bcrypt.
- Le validateur change après chaque usage.
- La révocation depuis l'écran « Appareils » invalide le cookie immédiatement.

---

### SEC-12 — Limitation de débit et verrouillage de compte

**Charge :** 1 j · **Statut :** fait le 23/09/2026

> **Bilan.** Deux mécanismes distincts, dans `includes/rate-limit.php` :
> - **`VerrouConnexion`** — verrouillage progressif des tentatives de connexion : **5 échecs → 15 min, 10 échecs → 1 h**, sur le couple identifiant + adresse. Vérifié **avant** la comparaison du mot de passe : un `bcrypt` par tentative est précisément le coût qu'une attaque cherche à imposer. Le bon mot de passe ne lève pas le verrou, et une tentative refusée n'est pas enregistrée — insister n'allonge donc pas la punition. Une connexion réussie remet le compteur à zéro. Actif sur `login.php` **et** `admin/login.php`, où des identifiants valides mais non administrateurs comptent comme un échec, sans quoi la console servirait de banc d'essai.
> - **`LimiteDebit`** — fenêtre glissante par action et par adresse : inscription (10/h), contact (5/10 min), réinitialisation de mot de passe (5/15 min), recherche (60/min), enregistrement d'écoute (60/min). Refus en **429** avec `Retry-After`, en JSON pour les API et en page pour le reste, via le rendu partagé `includes/reponse-refus.php` (extrait de la garde CSRF, qui l'utilise désormais aussi).
>
> **Le verrou porte sur le couple, jamais sur le seul identifiant.** Verrouiller un compte sur son seul nom permettrait à n'importe qui de bloquer n'importe quel titulaire à distance, en se trompant de mot de passe à sa place. Une garde plus large, **20 échecs par adresse, tous identifiants confondus**, arrête celui qui essaie beaucoup de comptes depuis un même poste.
>
> **Les connexions réussies ne sont pas comptées.** Limiter le nombre de connexions par adresse punirait les adresses partagées — un cybercafé, un opérateur mobile qui place ses abonnés derrière une même adresse. Au Tchad, c'est le cas courant, pas l'exception. Seuls les échecs comptent.
>
> **Question de vérification** (point 4) : une addition générée et vérifiée sur le serveur, affichée sur `register.php` et `contact.php` **au-delà de deux envois depuis la même adresse**. Pas de service tiers : la réponse ne sort pas du site, la page reste utilisable sans JavaScript, et un visiteur ordinaire ne voit jamais la question. Une réponse ne sert qu'une fois.
>
> **Purge** (point 5) : `login_attempts` conservée 90 jours (c'est aussi une piste d'audit), compteurs de débit 1 jour. Déclenchée une requête sur cinquante, et disponible en appel direct pour une tâche planifiée.
>
> **Interrupteur :** `RATE_LIMIT_ENABLED` (défaut `true`). Si la migration n'est pas appliquée, la limitation se désactive d'elle-même et le signale dans le journal, plutôt que de mettre le site en panne.
>
> **Traité en plus :** suppression de `logLoginAttempt()`, `isAccountLocked()`, `storeLoginAttempt()` et `getRecentFailedAttempts()` dans `includes/advanced-auth.php`. Aucune n'était appelée, et `isAccountLocked()` répondait toujours « compte ouvert » : les laisser à côté d'un vrai verrou invitait à brancher la mauvaise. Cela couvre une partie du point 3 de `SEC-14`.
>
> **Vérifié :** `tests/securite/sec12-limitation.ps1` (38 contrôles) — dont les trois critères d'acceptation : le 6e échec verrouille avec un message neutre, 62 appels à `api/search.php` déclenchent un 429, et les échecs d'un tiers depuis une autre adresse n'empêchent pas le titulaire de se connecter. Suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17), `SEC-10` (35), `SEC-11` (50) au vert ; 19 pages publiques en 200, aucune erreur PHP.
>
> **Migration :** `database/migrations/2026-09-23-sec12-limitation-debit.sql` (tables `login_attempts` et `rate_limit_hits`).

Aucune limitation de débit n'existe dans le projet (0 occurrence de `rate_limit` ou `throttle`). Les fonctions `isAccountLocked()` et `logLoginAttempt()` existent dans `includes/advanced-auth.php` mais ne sont jamais appelées, et leur persistance est factice (`SEC-14`).

**À faire :**
1. Créer la table `login_attempts` : `identifier`, `ip_address`, `success`, `user_agent`, `created_at`, indexée sur `(identifier, created_at)` et `(ip_address, created_at)`.
2. Verrouillage progressif par couple identifiant/IP : 5 échecs → 15 min, 10 échecs → 1 h, avec message neutre ne révélant pas l'existence du compte.
3. Limitation de débit générique par IP sur : `login.php`, `admin/login.php`, `register.php`, `contact.php`, `api/search.php`, `api/stream.php`, `admin/reset-password.php`. Fenêtre glissante, stockage en table ou en cache.
4. Captcha au-delà d'un seuil sur `register.php` et `contact.php`.
5. Purge automatique des enregistrements de plus de 90 jours.

**Critères d'acceptation :**
- 6 tentatives de connexion échouées verrouillent l'accès pour ce couple, avec message neutre.
- 100 appels en 10 secondes à `api/stream.php` déclenchent une réponse `429`.
- Le verrouillage ne permet pas de bloquer le compte d'un tiers à distance (le verrou porte sur le couple, pas sur le seul identifiant).

---

### SEC-13 — Supprimer les valeurs client de confiance sur l'IP

**Charge :** 3 h · **Fichiers :** `includes/auth.php` (l. 155-165), `includes/functions.php` (l. 339-353) · **Statut :** fait le 23/09/2026

> **Bilan.** Une seule fonction, `clientIp()` dans `includes/functions.php` ; la méthode privée de `includes/auth.php` et l'ancienne `getClientIP()` ont disparu. Par défaut, **seule `REMOTE_ADDR` fait foi** : c'est la seule valeur établie par la connexion elle-même. `X-Forwarded-For` n'est lu que si la requête arrive d'un proxy déclaré dans `TRUSTED_PROXIES` ; la chaîne est alors parcourue **de droite à gauche**, en sautant les proxys connus, jusqu'à la première adresse qui ne l'est pas. Tout ce qui se trouve à gauche a pu être écrit par le client. `HTTP_CLIENT_IP` n'est plus lu du tout : cet en-tête n'a aucun émetteur légitime dans une chaîne de proxys.
>
> **`TRUSTED_PROXIES` accepte des plages CIDR**, en IPv4 comme en IPv6 : un CDN ne s'énumère pas adresse par adresse. La comparaison est binaire (`inet_pton` puis comparaison de préfixe), donc les deux familles suivent le même chemin.
>
> **Tous les consommateurs passent par elle :** registre des sessions (`includes/auth.php`), écoutes (`api/stream.php`), jetons de connexion automatique (`includes/remember-me.php`), limitation de débit et verrouillage (`includes/rate-limit.php`), vues du blog, journaux de `media.php` et de la garde CSRF. Le point important est le dernier : tant que la limitation lisait un en-tête modifiable, **il suffisait d'en changer à chaque requête pour repartir d'un compteur neuf**. C'est vérifié par un test dédié.
>
> **Vérifié :** `tests/securite/sec13-adresse.php` (31 contrôles) — en-têtes falsifiés ignorés, chaîne de proxys, entrées illisibles, plages CIDR et IPv6, câblage réel avec et sans `TRUSTED_PROXIES`, verrouillage non contournable, absence de seconde implémentation. Suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17), `SEC-10` (35), `SEC-11` (50), `SEC-12` (38) au vert ; 18 pages publiques en 200.
>
> **Pour la mise en production :** si le site passe derrière un reverse proxy ou un CDN, renseigner `TRUSTED_PROXIES` dans `.env.production`, **sinon toutes les visites seront enregistrées sous l'adresse du proxy** — la géographie du baromètre deviendrait un point unique, et la limitation de débit s'appliquerait à tout le monde d'un coup. Laisser vide si le serveur web est joint directement.

Deux implémentations divergentes lisent `HTTP_CLIENT_IP` et `X-Forwarded-For`, en-têtes fournis par le client et falsifiables. Ces valeurs alimentent `user_sessions.ip_address`, `streams.ip_address` et le journal API — donc la géographie du baromètre, la détection d'abus et la piste d'audit.

**À faire :**
1. Une seule fonction `clientIp()` dans `includes/functions.php` ; retirer la version de `auth.php`.
2. Par défaut : `REMOTE_ADDR`, rien d'autre.
3. `X-Forwarded-For` lu **uniquement** si `REMOTE_ADDR` figure dans `TRUSTED_PROXIES` (liste configurée dans le fichier d'environnement), et en retenant alors l'entrée la plus à droite non fiable.
4. Ne jamais lire `HTTP_CLIENT_IP`.

**Critères d'acceptation :**
- Une requête portant `X-Forwarded-For: 8.8.8.8` depuis une IP non déclarée comme proxy est journalisée avec l'IP réelle.
- `TRUSTED_PROXIES` vide en local ne casse rien.

---

### SEC-14 — Retirer le module de sécurité simulé

**Charge :** 3 h · **Fichiers :** `security-settings.php`, `includes/advanced-auth.php`, `assets/js/security-settings.js` · **Statut :** fait le 23/09/2026

> **Bilan.** `includes/advanced-auth.php` et `assets/js/security-settings.js` sont **supprimés**. `security-settings.php` est réécrite : elle ne montre plus que ce que la plateforme sait réellement.
>
> **Écart au plan (points 1 et 2) : la page est conservée et rendue honnête, au lieu d'être retirée de la navigation.** La raison est qu'entre-temps, les données existent vraiment : `login_attempts` (`SEC-12`) fournit un historique de connexions authentique, le registre des sessions (`SEC-10`) et les jetons d'appareils (`SEC-11`) fournissent les compteurs. Remplacer la page par « fonctionnalité en cours de déploiement » aurait masqué des informations de sécurité réelles et utiles. Le critère qui compte — ne présenter aucune donnée fabriquée — est tenu, et vérifié par un test qui cherche nommément chaque invention de l'ancienne page.
>
> **Ce que la page affiche maintenant :** l'état du compte (email vérifié ou non, dernière connexion, nombre de sessions ouvertes, nombre d'appareils mémorisés), l'historique réel des tentatives de connexion avec appareil, adresse et date, le formulaire de changement de mot de passe, et un renvoi vers `settings.php#devices` pour la révocation. La 2FA est annoncée **indisponible**, avec une phrase qui dit pourquoi : l'écran précédent n'enregistrait rien.
>
> **Ce qui a disparu :** le faux journal d'activité (« Connexion réussie · N'Djamena · Il y a 2h »), la liste d'appareils écrite dans le HTML (« Safari sur iPhone »), le score de sécurité calculé sur une variable de session, le secret TOTP d'exemple public `JBSWY3DPEHPK3PXP` identique pour tous, et l'appel à `api.qrserver.com` **qui transmettait ce secret à un service tiers**.
>
> **Traité en plus :**
> - `forceMotDePasse()` (ex-`checkPasswordStrength`) déplacée dans `includes/functions.php` : c'est le seul calcul du module qui ne dépendait d'aucune persistance. Un mot de passe trop faible est refusé au changement.
> - Les **connexions automatiques par cookie** sont désormais tracées dans `login_attempts` (`VerrouConnexion::tracer()`), sans toucher au compteur d'échecs : un historique incomplet serait trompeur, mais un cookie volé ne doit pas lever un verrou en cours.
> - Les primitives TOTP (RFC 6238), correctes mais inutilisées, disparaissent avec le fichier. `SEC-20` peut les récupérer dans l'historique : `git show cbd0d66:includes/advanced-auth.php`.
>
> **Sur les fonctions déclarées deux fois** (point 4) : la seule paire qui provoquait réellement une erreur fatale — `functions.php` et `advanced-auth.php`, chargés ensemble — est résolue par la suppression. Un test vérifie qu'aucune fonction n'est déclarée deux fois **parmi les fichiers chargés à chaque requête**. Les autres doublons relevés (`handleGet`, `respond`, `bulkAction`…) sont des aides locales dans des points d'entrée qui ne se chargent jamais ensemble, ou se trouvent dans `admin/dashboard-tabs/`, dossier mort supprimé en `CLEAN-01`.
>
> **Vérifié :** `tests/securite/sec14-securite-simulee.php` (36 contrôles) ; suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17), `SEC-10` (35), `SEC-11` (50), `SEC-12` (38), `SEC-13` (31) au vert ; 18 pages publiques en 200.

La couche de persistance est factice : `getRememberToken()` retourne en dur `user_id => 1` (le `super_admin`), `getRecentFailedAttempts()` retourne toujours un tableau vide (donc `isAccountLocked()` renvoie systématiquement `false`), et `getRecentSuccessfulLogins()` retourne des données inventées (`192.168.1.1`, `N'Djamena`) affichées à l'utilisateur comme son historique de sécurité réel. Aucune des tables nécessaires n'existe.

**Ce point dépasse la technique :** présenter à un utilisateur un faux journal de connexions et une 2FA qui ne persiste rien est un problème de loyauté de l'information.

**À faire immédiatement :**
1. Retirer `security-settings.php` de la navigation (`includes/admin-shell-header.php`) et de tout lien.
2. Remplacer la page par un état « fonctionnalité en cours de déploiement » — ne **jamais** laisser les données fabriquées affichées.
3. Retirer les fonctions de simulation de `includes/advanced-auth.php` (`storeRememberToken`, `getRememberToken`, `storeLoginAttempt`, `getRecentFailedAttempts`, `getRecentSuccessfulLogins`, la redéclaration conditionnelle de `getUserById`).
4. Résoudre les redéclarations de `generateSecureToken()`, `validateTchadianPhone()` et `getClientIP()` entre `functions.php` et `advanced-auth.php`, cause de l'erreur fatale déjà tracée dans les logs.

L'implémentation réelle de la 2FA est traitée en `SEC-20`.

**Critères d'acceptation :**
- Aucune donnée fabriquée n'est présentée comme réelle à un utilisateur.
- Aucune fonction n'est déclarée deux fois dans le projet.
- `includes/advanced-auth.php` ne contient plus que du code opérationnel, ou n'existe plus.

---

### SEC-15 — Corriger la configuration d'environnement et la divulgation d'erreurs

**Charge :** 4 h · **Fichiers :** `config/constants.php`, les deux `.htaccess`, points d'entrée · **Statut :** fait le 23/09/2026

> **Bilan.** Les points 1, 2 et 5 étaient déjà traités au `LOT 0` (`ENVIRONMENT` et `DEBUG_MODE` dérivés de `EnvLoader`, `display_errors` piloté par l'environnement, journal vers `storage/logs/php-errors.log`, bloc `mod_php7` retiré). Cette tâche livre les points 3 et 4.
>
> **Gestionnaire global** (`includes/erreurs.php`, installé au démarrage) : toute exception non interceptée et toute erreur fatale reçoivent une **référence de huit caractères** — sans `0`, `O`, `1` ni `I`, pour être dictée au téléphone sans ambiguïté. Le visiteur voit cette référence et rien d'autre ; le journal reçoit le détail complet, trace, méthode, chemin et adresse comprises. En local, le détail reste affiché à l'écran : c'est la machine du développeur.
>
> **Les messages d'exception ne partent plus à l'écran** (point 4) : 10 pages (`register.php`, `upload.php`, `premium-payment.php`, les quatre pages d'ajout de contenu, `admin-playlists.php`, `admin-podcasts.php`, `admin-manage-radio.php`) et 6 fonctions de `includes/blog-manager.php` passent par `GestionErreurs::messagePublic()`.
>
> **Les API distinguent deux cas.** Une exception portant un code **4xx** est écrite pour le client (« La requête de recherche doit contenir au moins 2 caractères ») : son message est conservé, il est utile et ne révèle rien. Tout le reste devient un **500 avec référence**. Sans cette distinction, masquer les erreurs aurait rendu les API inutilisables.
>
> **Trouvé et retiré : `admin/test-db-connection.php`.** Ce script de diagnostic répondait **sans aucune authentification** et renvoyait en JSON l'hôte MySQL, le nom de la base et l'utilisateur. Il n'était référencé nulle part. Supprimé.
>
> **Relevé pour le `LOT 5` (paiement) :** `includes/payment.php` **ne se charge pas du tout** — erreur de syntaxe ligne 250, `new \PDO::PARAM_STR(...)`, qui n'est pas du PHP valide. Le fichier n'étant inclus par aucun point d'entrée, l'erreur n'a jamais été visible. Le code cURL Airtel et Moov présenté comme base de `PAY-03` n'a donc **jamais été exécuté** : il est à reprendre, pas à adapter. Le fichier est laissé en l'état pour la refonte.
>
> **Vérifié :** `tests/securite/sec15-erreurs.php` (46 contrôles), dont les trois critères d'acceptation : une erreur SQL en production n'affiche ni la requête ni le chemin serveur, la référence affichée retrouve la trace complète dans le journal, et le mode production s'obtient sans toucher au code. Les essais de bout en bout déposent une page qui échoue volontairement, puis la retirent. Suites `SEC-06` (39), `SEC-08` (36), `SEC-09` (64 + 17), `SEC-10` (35), `SEC-11` (50), `SEC-12` (38), `SEC-13` (31), `SEC-14` (36) au vert ; 18 pages publiques en 200.

`config/constants.php` ligne 179 écrit `define('ENVIRONMENT', 'development')` **en dur** : quelle que soit la valeur de `APP_ENV`, `DEBUG_MODE` vaut `true` et `display_errors` est activé. Le `.htaccess` confirme (`php_flag display_errors On`). Plusieurs points d'entrée renvoient `$e->getMessage()` à l'utilisateur.

**À faire :**
1. Faire dériver `ENVIRONMENT` et `DEBUG_MODE` de `EnvLoader::environment()`.
2. Retirer `display_errors` de `.htaccess.production` ; activer `log_errors` vers `storage/logs/php-errors.log`.
3. Gestionnaire d'exceptions global : en local, trace complète ; en production, journalisation avec identifiant de corrélation et page 500 générique affichant seulement cet identifiant.
4. Remplacer tous les `$e->getMessage()` renvoyés à l'utilisateur (`register.php`, `artist-add-song.php`, `artist-add-album.php`, `premium-payment.php`, et l'ensemble des API).
5. Retirer le bloc `<IfModule mod_php7.c>` des deux `.htaccess` : le projet cible PHP 8, ce bloc n'est jamais évalué et entretient la confusion.

**Critères d'acceptation :**
- Une erreur SQL en production affiche un identifiant de corrélation, jamais la requête ni le chemin serveur.
- Cet identifiant permet de retrouver la trace complète dans les logs.
- `APP_ENV=production` suffit à désactiver l'affichage des erreurs.

---

### SEC-16 — Créer les pages d'erreur

**Charge :** 3 h · **Statut :** fait le 23/09/2026

> **Bilan.** `403.php`, `404.php`, `429.php` et `500.php` créés, habillés aux couleurs du site, avec le bon code HTTP réellement envoyé. `show404()` pointait sur `pages/404.php`, fichier absent : elle produisait une erreur d'inclusion au lieu d'une page 404. Corrigée, avec un chemin absolu — le chemin relatif dépendait du répertoire de la page appelante. `ErrorDocument 429` ajouté aux deux `.htaccess`.
>
> **Aucune page 419** (point 1) : le refus CSRF répond 403 depuis `SEC-09`, Apache ne connaissant pas le code 419. Une page pour un code jamais émis n'aurait servi à rien.
>
> **Ces pages ne chargent rien de l'application.** C'est le point de conception : une page 500 qui démarrerait la configuration, la session et la base de données échouerait précisément dans le cas où elle sert — Apache n'aurait alors que sa page par défaut à montrer, qui annonce sa version. L'identité visuelle est donc reproduite en CSS interne (`includes/page-erreur.php`), et le chemin du site est déduit de l'emplacement du fichier, ce qui fonctionne en local (`/tchadok/`) comme à la racine d'un domaine. Un test coupe la base de données et vérifie que les pages restent servies.
>
> **Effet visible :** les chemins bloqués par le `.htaccess` (`includes/`, `config/`, `storage/`, `database/`) affichaient la page d'Apache ; ils affichent maintenant la page 403 du site.
>
> **Vérifié :** `tests/securite/sec16-pages-erreur.php` (60 contrôles) — codes HTTP réels, habillage, `noindex`, absence de signature serveur, de version PHP et de chemin, indépendance vis-à-vis de l'application, base de données coupée, déclarations `ErrorDocument` des deux `.htaccess`. Les dix autres suites au vert ; 18 pages publiques en 200.

Les deux `.htaccess` référencent `404.php`, `403.php`, `500.php` : **les trois sont absents**. `show404()` inclut `pages/404.php`, également absent — la fonction produit donc une erreur au lieu d'une page 404.

**À faire :**
1. Créer `403.php`, `404.php`, `500.php`, `419.php` (jeton CSRF invalide) et `429.php` (trop de requêtes), avec la coquille visuelle du site.
2. Code HTTP correct envoyé, pas seulement affiché.
3. Aucune information technique en production.
4. Corriger `show404()` pour pointer sur le bon fichier.
5. Déclarer les cinq pages dans les deux `.htaccess`.

**Critères d'acceptation :**
- Une URL inexistante retourne un vrai `404` (vérifié par `curl -I`) avec une page habillée.
- Une erreur applicative en production affiche la page 500, pas une trace PHP.

---

### SEC-17 — Supprimer la publication par URL arbitraire et durcir les dépôts

**Charge :** 1 j · **Fichiers :** `artist-add-song.php`, `artist-add-album.php`, `upload.php`, `includes/functions.php` · **Statut :** fait le 23/09/2026

> **Bilan.** `uploadFile()` ne regardait que l'extension du nom envoyé par le client : un fichier PHP renommé en `.mp3` passait et atterrissait dans un répertoire servi par le serveur web. Elle contrôle désormais, dans cet ordre : le code d'erreur du dépôt, la provenance (`is_uploaded_file`), la **taille réelle** du fichier reçu (celle annoncée vient du client), l'extension dans la liste blanche de l'appelant, le **type réel** lu par `finfo`, et la **signature du conteneur** (ID3 ou synchronisation MPEG, `RIFF/WAVE`, `fLaC`, `ftyp`, `FF D8 FF`, `\x89PNG`, `RIFF/WEBP`). Tout ce qui commence par `<?php`, `<script` ou `<html` est refusé quoi qu'il arrive.
>
> **Les images sont ré-encodées** par GD : ce qui sort est une image et rien d'autre, les métadonnées — où l'on glisse volontiers du code — ne survivent pas. Un test dépose un JPEG valide suivi d'une charge PHP et vérifie qu'elle a disparu du fichier enregistré. Les images au-delà de 3 000 pixels sont réduites. Sans GD, le fichier est accepté après contrôle par `getimagesize()`, et l'exploitant est prévenu dans le journal.
>
> **`mkdir()` est vérifié** (point 4), l'écriture du répertoire aussi, et l'échec produit un message compréhensible au lieu d'une erreur PHP dans la page.
>
> **Publication par URL supprimée** (point 1) — **et au-delà du périmètre annoncé.** Le plan visait les deux formulaires artiste ; les champs existaient en réalité dans **neuf** points d'entrée (`artist-add-song`, `artist-add-album`, `admin-add-song`, `admin-add-album`, `admin-podcasts`, `admin-manage-radio`, `admin-playlists`, `create-playlist`, `admin-blog`, plus le traitement de `includes/blog-manager.php`). N'en retirer que deux aurait laissé la faille ouverte par la console : un chemin de média pointant sur une adresse externe contourne `media.php`, donc tout le contrôle d'accès de `SEC-06`, et casserait de toute façon la politique de contenu de `SEC-18`.
>
> **Prix bornés** (point 5) : `validerPrix()` refuse le négatif — `(float) $_POST['price']` acceptait `-500`, ce qui aurait **crédité l'acheteur à chaque vente** —, le montant absurde (plafond 500 000 FCFA) et les valeurs hors grille (multiples de 50 FCFA, comme les formulaires l'annoncent). Appliqué aux quatre formulaires de publication.
>
> **Vérifié :** `tests/securite/sec17-depots.php` (38 contrôles) — PHP déguisé en `.mp3`, `.jpg` et `.png`, texte déguisé en audio, MP3 déguisé en WAV, JPEG déguisé en PNG, extensions hors liste, fichier vide, dépassement de taille, répertoire impossible, charge utile dans une image, noms de stockage aléatoires et non répétés, bornes de prix, absence des champs URL dans les neuf fichiers, et un essai de bout en bout qui force `audio_file_url` depuis la console : aucun titre n'est créé. Les onze autres suites au vert ; 18 pages publiques en 200.

`$_POST['audio_file_url']` et `$_POST['cover_image_url']` sont stockés tels quels comme chemin du média, court-circuitant tout contrôle de fichier. Le contrôle de dépôt lui-même ne vérifie que **l'extension**.

**À faire :**
1. Retirer les champs `audio_file_url`, `preview_file_url` et `cover_image_url` des formulaires artiste et de leur traitement.
2. Réécrire `uploadFile()` : vérification du type MIME réel par `finfo_file`, contrôle de l'en-tête de conteneur audio, liste blanche de types stricte, taille, et nom de fichier généré par `random_bytes`.
3. Ré-encoder systématiquement les images déposées (GD ou Imagick) : cela neutralise les charges utiles embarquées dans les métadonnées.
4. Vérifier le retour de `mkdir()` et échouer proprement.
5. Borner les prix : refuser les valeurs négatives et hors grille (`(float) $_POST['price']` accepte aujourd'hui `-500`).
6. Valider `$_POST['type']` de l'album contre la liste `single`, `maxi_single`, `ep`, `album`.
7. Vérifier que `$_POST['album_id']` appartient bien à l'artiste connecté — aujourd'hui un artiste peut rattacher un titre à l'album d'un confrère, avec effet direct sur l'attribution des écoutes et des revenus.

**Critères d'acceptation :**
- Un `.php` renommé en `.mp3` est refusé.
- Un prix négatif est refusé avec un message clair.
- Un `album_id` appartenant à un autre artiste est refusé.
- Aucun champ de formulaire ne permet de définir un chemin de média.

---

### SEC-18 — Nettoyer les en-têtes CORS et ajouter les en-têtes de sécurité

**Charge :** 4 h · **Dépend de :** `CFG-04` · **Statut :** fait le 23/09/2026

> **Bilan.** Les points 1, 4 et 6 étaient déjà traités au `LOT 1` (ouverture globale retirée des deux `.htaccess`, en-têtes de sécurité posés, `X-XSS-Protection` jamais réintroduit). Cette tâche livre les points 2, 3 et 5.
>
> **Plus aucune API n'ouvre à `*`.** Six d'entre elles reposaient l'en-tête de leur côté, y compris celles qui agissent au nom de la personne connectée : `api/stream.php`, `api/playlists.php`, `api/follows.php`, `api/notifications.php` n'ont plus aucun en-tête CORS, et leur requête de prévol répond 405 — il n'y a plus rien à négocier.
>
> **Ouverture explicite pour la lecture publique** (`includes/cors.php`) : `api/search.php` et `api/radio/metadata.php` déclarent une ouverture, accordée **uniquement** aux origines de `CORS_ALLOWED_ORIGINS`. Liste vide par défaut, y compris en production : même origine seulement. L'en-tête `Vary: Origin` accompagne la réponse, sans quoi un cache partagé servirait la réponse d'une origine à une autre. Jamais de `Access-Control-Allow-Credentials` : répondre une origine précise **avec** les cookies reviendrait à exposer les sessions.
>
> **L'application Android n'a pas besoin de cette liste** : le CORS est une règle appliquée par les navigateurs, pas par un client natif. La liste reste donc vide même quand l'application existera.
>
> **Politique de contenu et collecte** (point 5) : la CSP `Report-Only` est désormais posée **aussi en local** — c'est pendant le développement qu'on veut voir les violations, pas le jour de la bascule en mode bloquant. Les deux environnements pointent sur `api/csp-report.php`, qui écrit dans `storage/logs/csp-report.log`. Cet endpoint est exempté de CSRF (le navigateur n'envoie ni session ni jeton, exemption déclarée et journalisée), limité en débit, borné à 16 Ko de corps, et neutralise les retours à la ligne pour qu'un rapport ne puisse pas fabriquer une fausse entrée de journal. Il accepte les deux formats, `csp-report` et Reporting API, et ne répond rien (204).
>
> **Vérifié :** `tests/securite/sec18-entetes.php` (41 contrôles) — en-têtes de sécurité, absence d'ouverture par défaut sur huit points d'entrée avec une origine tierce, ouverture accordée puis retirée en modifiant la liste blanche, prévol accepté puis refusé, collecte des rapports, tentative d'injection de ligne dans le journal, et configuration des deux `.htaccess`. Les douze autres suites au vert ; 18 pages publiques en 200.
>
> **Reste avant la mise en production :** passer la CSP en mode bloquant une fois les violations corrigées (`UX-03`). Les scripts en ligne et `unsafe-eval` autorisés aujourd'hui viennent du CDN Tailwind, à remplacer par une feuille compilée.

`.htaccess` pose `Access-Control-Allow-Origin: *` **globalement**, sur toutes les réponses, pages HTML comprises, avec `GET, POST, PUT, DELETE`. Plusieurs API le reposent individuellement.

**À faire :**
1. Retirer l'en-tête global des deux `.htaccess`.
2. CORS explicite, endpoint par endpoint, uniquement sur ce qui doit être public en lecture (`api/search.php`, `api/radio/metadata.php`), avec liste blanche d'origines et sans credentials.
3. Retirer `Access-Control-Allow-Origin: *` de `api/track.php`, `api/stream.php`, `api/playlists.php`, `api/follows.php`, `api/notifications.php`.
4. Ajouter les en-têtes de sécurité dans les deux `.htaccess` (voir `CFG-04`).
5. Poser la CSP en `Report-Only` avec un endpoint de collecte ; la rendre bloquante après `UX-03`.
6. Retirer `X-XSS-Protection` (obsolète, et contre-productif sur les navigateurs récents).

**Critères d'acceptation :**
- Aucune réponse HTML ne porte `Access-Control-Allow-Origin`.
- Une requête cross-origin vers une API non publique est rejetée par le navigateur.
- Les en-têtes attendus sont présents dans les deux environnements.

---

### SEC-19 — Rôles, permissions et journal d'audit

**Charge :** 5 j · **Dépend de :** `DATA-01` · **Statut :** fait le 23/09/2026

> **Bilan.** Les sept rôles du §8.3 de l'audit existent, avec un catalogue de **24 permissions nommées** réparties en cinq domaines. `Autorisations::peut('finance.versement.executer')` remplace le booléen ; `Autorisations::exiger()` refuse en **403 côté serveur**, à l'entrée de l'écran — masquer une entrée de menu ne protège rien, l'adresse se tape.
>
> **La table `admins` n'ouvre plus aucun droit.** C'est `user_roles` qui fait foi. La migration convertit les lignes existantes (`super_admin` → `super_admin`, `moderator` → `moderateur_catalogue`, le reste → `admin_plateforme`), de sorte que personne ne perd l'accès au moment de la bascule ; `scripts/create-admin.php` attribue désormais un rôle et **refuse de créer un compte** si les migrations n'ont pas été appliquées, plutôt que de fabriquer un administrateur sans droits.
>
> **Le super-administrateur reçoit aussi les permissions ajoutées plus tard.** Sans cette règle, créer une permission la rendrait inaccessible à tout le monde — y compris au seul rôle censé pouvoir tout faire — jusqu'à ce que quelqu'un pense à l'attribuer. Les lignes sont malgré tout semées en base, pour que la lecture directe de `role_permissions` reste parlante.
>
> **Séparation des pouvoirs sur l'argent** (point 4) : `verifierSeparationVersement()` refuse qu'un même compte prépare et exécute le même versement. Le responsable finance détient bien les deux permissions — c'est son métier — et reste soumis à la règle. Elle vit dans la couche d'autorisation, pas dans un écran, pour que le `LOT 8` et une future API la trouvent aussi.
>
> **Journal d'audit** : table `audit_log` (auteur, rôles au moment de l'action, action, cible, états avant/après, motif, adresse, navigateur). L'application n'y fait **que des `INSERT`** — un test vérifie qu'aucun code ne contient d'`UPDATE` ni de `DELETE` sur cette table. Les clés sensibles (mot de passe, jeton, secret) sont **masquées** avant écriture : un journal ne doit pas devenir l'endroit où traîne un hash. Écran de consultation `admin/journal.php`, filtrable, réservé à `journal.lire`.
>
> **La connexion d'administration est tracée depuis `Auth::login()`**, pas depuis la page : la console, la page publique et une future API empruntent toutes ce point. Sont également journalisés les échecs sur un compte d'administration, les refus d'autorisation (avec la permission refusée), les attributions et retraits de rôle, les ajouts au catalogue depuis la console, et les réinitialisations de mot de passe.
>
> **Écart au plan :** pas d'écran d'attribution des rôles — `scripts/roles.php` (`liste`, `voir`, `attribuer`, `retirer`) le fait en ligne de commande, ce qui suffit pour amorcer le serveur et laisse une trace au journal, là où une requête SQL manuelle n'en laisserait aucune. L'écran viendra avec la gestion des comptes. Les écrans finance n'existant pas encore, le critère « un modérateur ne peut pas les atteindre » est vérifié sur les écrans existants (édition du catalogue, éditorial, journal) et sur la couche d'autorisation.
>
> **Vérifié :** `tests/securite/sec19-roles-audit.php` (59 contrôles) — modèle complet, cumul de rôles, permission inconnue accordée au seul super-administrateur, refus 403 réellement prononcés sur quatre écrans, règle des deux comptes, journal alimenté par six types d'actions avec auteur et rôles, masquage des valeurs sensibles, écran de consultation et son filtre. Les quatorze autres suites au vert.

`isAdmin()` est binaire. La colonne `admins.permissions` contient `'["all"]'` et n'est **jamais lue** : tout administrateur peut tout faire, y compris valider des transactions. Aucun journal d'audit n'existe — impossible de savoir qui a approuvé un titre, validé un versement ou supprimé un compte.

**À faire :**
1. Tables `roles`, `permissions`, `role_permissions`, `user_roles`.
2. Fonction `can('finance.payout.execute')` remplaçant `isAdmin()` dans tous les contrôles.
3. Sept rôles : `super_admin`, `admin_plateforme`, `responsable_finance`, `moderateur_catalogue`, `editorial`, `support`, `analyste`. Périmètres détaillés au §8.3 de l'audit.
4. **Séparation des pouvoirs sur l'argent** : la création d'un versement et son exécution relèvent de deux comptes distincts, contrôle technique et non seulement procédural.
5. Table `audit_log` : `actor_id`, `actor_role`, `action`, `target_type`, `target_id`, `before_state`, `after_state`, `reason`, `ip_address`, `user_agent`, `created_at`. En **écriture seule** depuis l'application.
6. Journaliser sans exception : connexion et échec administrateur, création/modification/suppression de compte, changement de rôle, approbation ou rejet de contenu, modification de tarif ou commission, validation et exécution de versement, remboursement, accès en masse à des données personnelles, modification de la taxonomie, arrêté de classement.
7. Écran de consultation du journal, filtrable, réservé au `super_admin`.

**Critères d'acceptation :**
- Un `moderateur_catalogue` ne peut pas atteindre les écrans finance (403 côté serveur, pas seulement masquage de menu).
- Un versement créé et exécuté par le même compte est refusé.
- Chaque action sensible produit une entrée traçable avec son auteur.

---

### SEC-20 — Authentification à deux facteurs réelle

**Charge :** 3 j · **Dépend de :** `SEC-14`, `SEC-19` · **Statut :** fait le 23/09/2026

> **Bilan.** TOTP conforme à la RFC 6238, vérifié contre les **quatre vecteurs officiels** de la norme. Tables `user_2fa_settings` et `user_backup_codes`, activation après vérification d'un premier code, dix codes de secours affichés une seule fois, vérification à la connexion, protection contre le rejeu, et obligation pour les rôles disposant d'un droit d'écriture en administration.
>
> **Pas de QR code, et c'est délibéré.** L'écran retiré en `SEC-14` faisait fabriquer le QR par `api.qrserver.com` — en **transmettant le secret à un tiers**, ce qui annule l'intérêt du second facteur. Aucun encodeur QR n'est disponible localement (le projet n'a pas de gestionnaire de dépendances). La clé est donc affichée par groupes de quatre, avec l'URI `otpauth://` : toutes les applications acceptent la saisie manuelle. Le QR reviendra avec un encodeur servi par le site lui-même.
>
> **Le secret est chiffré** (AES-256-GCM, clé dérivée d'`APP_KEY` par HKDF) : une base volée ne doit pas livrer les seconds facteurs, sinon le vol de la base suffirait à se faire passer pour n'importe quel administrateur. Les codes de secours sont stockés en SHA-256 — ce sont des valeurs aléatoires, pas des mots de passe choisis : il n'y a rien à ralentir, et vérifier dix bcrypt à chaque essai coûterait cher pour rien.
>
> **Rejeu** (point 3) : un code vaut trente secondes, et rien n'empêche de le rejouer dans cet intervalle. Le dernier pas de temps accepté est mémorisé, et tout pas inférieur ou égal est refusé — un code capté ne sert qu'une fois. Vérifié par un test qui rejoue un code valide sur une seconde session.
>
> **Obligation** (point 5) : `ADMIN_2FA_REQUIRED`, `true` en production. Un compte doté d'une permission d'écriture en administration est **renvoyé vers l'activation** plutôt que simplement bloqué — bloquer sans proposer la sortie serait un cul-de-sac — et ne peut plus la retirer lui-même. `false` en local, pour ne pas imposer un téléphone à chaque essai.
>
> **Récupération** (point 6) : `scripts/deux-facteurs.php reinitialiser <compte> --raison="…"`, en ligne de commande sur le serveur, **motif obligatoire**, opération inscrite au journal d'audit. Quelqu'un qui perd téléphone et codes de secours reste récupérable, mais personne ne retire un second facteur sans laisser de trace ni dire pourquoi.
>
> **Écart au plan :** la récupération passe par la ligne de commande et non par un écran de `super_admin`. Sur un VPS administré à la main, c'est le chemin le plus sûr : il suppose un accès au serveur, ce qui est une garantie plus forte qu'une session d'administration, et il reste tracé.
>
> **Vérifié :** `tests/securite/sec20-deux-facteurs.php` (47 contrôles) — vecteurs RFC, activation refusée sur code faux puis acceptée, secret et codes jamais stockés en clair, connexion en deux temps, rejeu refusé, code de secours consommé une seule fois, obligation appliquée sur les écrans d'administration puis levée après activation, impossibilité de la retirer pour un rôle concerné, récupération en ligne de commande avec motif et journalisation. Les quinze autres suites au vert — **671 contrôles au total**. Deux tests plus anciens ont été mis à jour : `SEC-14` (la 2FA n'est plus « indisponible ») et `DATA-01` (une cinquième migration).

Les algorithmes TOTP de `includes/advanced-auth.php` sont corrects ; seule la persistance manque.

**À faire :**
1. Tables `user_2fa_settings` (`user_id`, `method`, `secret` chiffré, `enabled_at`, `last_used_at`) et `user_backup_codes` (`user_id`, `code_hash`, `used_at`).
2. Parcours d'activation : QR code, vérification d'un premier code avant activation, génération de 10 codes de secours affichés **une seule fois**.
3. Vérification au moment de la connexion, avec fenêtre de tolérance et **protection contre le rejeu** (un code déjà utilisé est refusé).
4. Codes de secours à usage unique, consommés et marqués.
5. **2FA obligatoire** pour tout rôle disposant d'une permission d'écriture en administration.
6. Procédure de récupération documentée pour un administrateur ayant perdu son second facteur (intervention d'un autre `super_admin`, journalisée).

**Critères d'acceptation :**
- Un administrateur sans 2FA ne peut pas atteindre les écrans d'administration.
- Un code TOTP déjà utilisé est refusé.
- Un code de secours ne fonctionne qu'une fois.

---

## LOT 3 — Suppression du code mort

> Objectif : retirer environ 340 Ko de code sans usage, qui concentre une part importante des vulnérabilités et rend toute relecture pénible. Ce lot vient **après** le LOT 2 pour ne pas retarder les corrections urgentes, mais avant tout développement, pour ne pas travailler sur une base encombrée.

### CLEAN-01 — Vérifier avant de retirer

**Charge :** 4 h · **Bloque :** `CLEAN-02` à `CLEAN-05` · **Statut :** fait le 23/09/2026

> **Bilan.** `docs/nettoyage.md` : chaque fichier retiré, sa taille, la preuve de non-usage, la décision. Aucun fichier n'est resté au statut incertain.
>
> **Une première analyse par nom de fichier a produit de faux positifs** : `admin/dashboard-tabs/artists.php` passait pour utilisé parce que des liens pointent vers la page publique `artists.php`. Même piège pour `settings.php`, `users.php` et `playlists.php`. L'analyse a été refaite sur les **chemins**.
>
> **Preuve décisive pour les feuilles de style :** les douze pages publiques ont été demandées au serveur et les balises `<link>` réellement servies relevées. Seules `tailwind-base.css` et les neuf `*-tailwind.css` sont chargées. Une feuille qu'aucune page ne charge ne peut pas influencer le rendu.

Pour chaque fichier candidat, confirmer l'absence d'usage par trois contrôles : recherche du nom de fichier dans les `include`/`require`, recherche dans les liens (`href`, `action`, `src`), recherche des sélecteurs CSS dans les gabarits pour les feuilles de style.

**Produire `docs/nettoyage.md`** : un tableau fichier / raison / preuve de non-usage / décision. Ce document sert de trace en cas de régression.

**Critères d'acceptation :**
- Chaque retrait est justifié par une preuve écrite.
- Les fichiers au statut incertain sont listés séparément, pour arbitrage explicite.

---

### CLEAN-02 — Retirer les dashboards de la génération précédente

**Charge :** 3 h · **Dépend de :** `CLEAN-01` · **Statut :** fait le 23/09/2026

> **Bilan.** Les huit fichiers de `admin/dashboard-tabs/` sont retirés (133 Ko), et avec eux les **six injections SQL** qu'ils contenaient : supprimer la vulnérabilité plutôt que la corriger. `pages/` n'existait déjà plus dans le dépôt. `admin/dashboard.php` est **conservé** : c'est une redirection de 0,3 Ko vers la console, et une adresse peut avoir été mémorisée.
>
> **Trouvé au passage :** `manifest.json` déclarait deux raccourcis vers des pages inexistantes (`/pages/user/playlists.php` et `/discover.php`). Corrigés et vérifiés.

| Élément | Taille | Raison |
|---|---:|---|
| `admin/dashboard-tabs/` (8 fichiers) | ~130 Ko | Jamais inclus ; contient six injections SQL via `$_GET['search']` et `$_GET['artist']` |
| `pages/admin/dashboard.php` | 32,9 Ko | Jamais atteint ; cassé (`$db->fetchOne` sur une classe retirée en `SEC-07`) ; liens vers des fichiers inexistants |
| `pages/artist/dashboard.php` | 29,1 Ko | Idem |
| `admin/dashboard.php` | 0,3 Ko | Simple redirection, à conserver ou remplacer par une règle de réécriture |

Le répertoire `pages/` devient vide et peut disparaître.

**Critères d'acceptation :**
- Les URL correspondantes retournent 404.
- Aucun lien du site ne pointe vers `pages/`.
- Le dashboard admin en production fonctionne toujours.

---

### CLEAN-03 — Retirer l'ancienne coquille et ses styles

**Charge :** 4 h · **Dépend de :** `CLEAN-01` · **Statut :** fait le 23/09/2026

> **Bilan.** `includes/header.php`, `includes/footer.php`, `assets/css/main.css`, `assets/js/main.js`, `assets/js/admin-dashboard.js` et **21 feuilles orphelines** retirés. `sw.js` mettait `main.css` et `main.js` en cache : corrigé, sans quoi l'installation du service worker aurait échoué sur un 404.
>
> **Le lecteur audio : la situation n'était pas celle que décrivait le plan.** Vérification faite, `assets/js/player.js` ne dépend plus de `includes/player.php` — il **construit lui-même son interface**, en classes Tailwind du système actif, et expose `window.playTrack`, appelé par `home.js`, `decouvrir.js` et `radio-live.js`. Il a été recâblé sur les URL signées en `SEC-06`. C'est `includes/player.php` qui est l'ancienne version, en classes **Bootstrap** absentes de la coquille actuelle : le réintégrer aurait produit un lecteur cassé. **Décision : garder `player.js`, retirer `includes/player.php` et `player.css`.** Le lecteur est fonctionnel et stylé — l'état « entre les deux » que le plan voulait éviter n'existait pas.
>
> **Aucune régression :** les douze pages publiques ont été capturées avant et après, jetons et horodatages neutralisés. Le HTML servi est **identique**.

| Élément | Taille | Raison |
|---|---:|---|
| `includes/header.php` | 9,9 Ko | 0 utilisation (les 47 pages utilisent `header-tailwind.php`) |
| `includes/footer.php` | 4,6 Ko | 0 utilisation |
| `assets/css/main.css` | 34,4 Ko | Référencé uniquement par `includes/header.php` |
| `assets/css/player.css` | 2,8 Ko | Idem |
| 21 feuilles orphelines | ~125 Ko | Liste en annexe A de l'audit |

**Attention particulière :** ces feuilles portent des classes utilisées par les gabarits actifs (`page-hero`, `page-hero-note`, `site-nav-*`, `site-footer-*`). Vérifier **chaque sélecteur** avant retrait, et récupérer dans `tailwind-base.css` ce qui est encore nécessaire. Les variantes `*-tailwind.css` sont actives et à conserver.

**Cas particulier — le lecteur audio.** `includes/footer-tailwind.php` charge `assets/js/player.js` sur **toutes** les pages, mais le balisage du lecteur vit dans `includes/player.php`, inclus nulle part, et `player.css` n'est référencé que par le `header.php` orphelin. Il y a donc 15,3 Ko de JavaScript exécuté sans conteneur ni style sur chaque page. **Décision à prendre :** réintégrer le lecteur (ajouter le balisage et la feuille de style dans la coquille active) ou retirer le chargement du script. Ne pas laisser l'état actuel.

**Critères d'acceptation :**
- Aucune régression visuelle sur les 10 pages les plus visitées, vérifiée par comparaison avant/après.
- Le lecteur audio est soit fonctionnel et stylé, soit absent — pas entre les deux.

---

### CLEAN-04 — Retirer les scripts SQL obsolètes

**Charge :** 3 h · **Dépend de :** `CLEAN-01`, `DATA-01` · **Statut :** fait le 23/09/2026

> **Bilan.** Répertoire `sql/` (9 fichiers) et `sample-data.sql` retirés. L'état réel du schéma est archivé dans `docs/schema-reel-2026-09-23.sql` et porté par `database/migrations/` depuis `DATA-01` — dont la première migration est précisément une photographie de la base. Le test `DATA-01` vérifie déjà qu'une installation neuve produit le schéma de référence.

Les 14 fichiers de `sql/` sont des correctifs successifs sans ordre d'application documenté, dont deux se contredisent sur `password` / `password_hash`.

**À faire :**
1. Reconstituer l'état réel du schéma en production (`SHOW CREATE TABLE` sur chaque table) et l'archiver dans `docs/schema-reel-<date>.sql`.
2. Reporter le contenu encore pertinent dans les migrations numérotées (`DATA-01`).
3. Retirer l'intégralité du répertoire `sql/` ainsi que `sample-data.sql`.

**Critères d'acceptation :**
- Le répertoire `sql/` n'existe plus.
- Une installation neuve depuis `database/migrations/` produit exactement le schéma de référence.

---

### CLEAN-05 — Ranger la racine du projet

**Charge :** 2 h · **Statut :** fait le 23/09/2026

> **Bilan.** `INSCRIPTION-FONCTIONNELLE.md`, `PLACEHOLDERS_GUIDE.md`, `migration.md` et `README-ENVIRONNEMENT.md` déplacés dans `docs/`. Journaux de `logs/` retirés de la racine web (ils vivent dans `storage/logs/`, déjà bloqué). Trois maquettes HTML statiques oubliées à la racine — `admin-dashboard.html`, `header-moderne2.html`, `header-moderne3.html`, 66 Ko — retirées : servies telles quelles par Apache, elles n'étaient liées nulle part.
>
> **`README.md` réécrit.** Il annonçait une soixantaine de fonctionnalités dont la plupart n'existent pas — lyrics synchronisés, recommandations, cadeaux musicaux, royalties. Il décrit maintenant **ce qui fonctionne**, ce qui ne fonctionne pas, l'installation, et renvoie au plan pour la suite.
>
> **Écart assumé :** `AUDIT-PLATEFORME-TCHADOK.md` et `PLAN-ACHEVEMENT-TCHADOK.md` restent à la racine. Ils sont modifiés à chaque tâche et cités par des dizaines de références ; les déplacer en plein chantier ferait du bruit pour rien. À faire à la clôture.
>
> **Total du lot : 439 Ko retirés**, 674 contrôles automatisés toujours au vert, 15 pages publiques en 200.

| Élément | Action |
|---|---|
| `INSCRIPTION-FONCTIONNELLE.md`, `PLACEHOLDERS_GUIDE.md`, `migration.md` | Déplacer dans `docs/` ou retirer si obsolètes |
| Fichiers de journal du répertoire `logs/` | Déplacer vers `storage/logs/`, hors racine web ; ajouter au `.gitignore` |
| `AUDIT-PLATEFORME-TCHADOK.md`, `PLAN-ACHEVEMENT-TCHADOK.md` | Déplacer dans `docs/` une fois le chantier engagé |
| `README.md` | Réécrire (`QA-05`) : il décrit une soixantaine de fonctionnalités dont la plupart n'existent pas |

**Critères d'acceptation :**
- La racine ne contient que des points d'entrée web, les fichiers de configuration et `README.md`.
- Aucun fichier de journal n'est accessible par URL.

---

## LOT 4 — Schéma et migrations

> Objectif : une seule source de vérité pour le schéma, et les tables nécessaires aux lots suivants. Aujourd'hui trois schémas concurrents coexistent (`database/tchadok.sql`, l'inline de `install.php`, les 14 correctifs de `sql/`) sans ordre d'application documenté : il est impossible de savoir avec certitude ce que contient une installation donnée.

### DATA-01 — Mettre en place un système de migrations

**Charge :** 2 j · **Bloque :** `DATA-02` à `DATA-08`, `SEC-19`, tous les lots suivants · **Statut :** fait le 23/09/2026

> **Pourquoi cette tâche est passée avant `SEC-19`.** Le plan se contredisait : l'annexe C place le LOT 2 (jusqu'à `SEC-20`) en semaine 2 et le LOT 4 en semaines 3-4, alors que `SEC-19` déclare dépendre de `DATA-01`. `SEC-19` ajoute cinq tables ; les écrire à la main aurait multiplié les scripts appliqués un par un, exactement ce que cette tâche supprime. Elle sert aussi directement la mise en production sur VPS : `php scripts/migrate.php up` remplace une série de commandes `mysql` passées à la main.
>
> **Livré.** `scripts/migrate.php` (`status`, `up`, `down --steps=N`, `verify`), en ligne de commande uniquement — une requête web reçoit un 404. Registre `schema_migrations` (`version`, `nom`, `applique_le`, `duree_ms`, `checksum`), créé par le script lui-même. Trois migrations : la **photographie du schéma** — point de départ commun, en créations conditionnelles, qui rend une base neuve complète sans toucher une base existante — puis les deux changements de `SEC-11` et `SEC-12`, qui rattrapent les installations antérieures et ne font rien ailleurs.
>
> **Idempotence réelle, pas déclarative.** MySQL ne connaît pas `DROP COLUMN IF EXISTS` : la suppression de `users.remember_token` passe par un test sur `information_schema` et une instruction préparée. Un test vide le registre et rejoue les trois migrations sur une base déjà à jour : rien n'échoue, rien ne change.
>
> **Le découpage SQL gère les déclencheurs.** Trois déclencheurs contiennent des points-virgules dans leur corps ; un découpage naïf sur `;` les casserait. Le script suit les changements de `DELIMITER`, les chaînes et les commentaires.
>
> **`database/tchadok.sql` n'est plus la source** (point 5) : il est **généré** par `scripts/export-schema.php`, qui interroge la base et écrit des créations conditionnelles, sans `AUTO_INCREMENT` courant et **sans `DEFINER`** — cet attribut fige un compte MySQL (`root@localhost`) qui n'existera pas sur le serveur.
>
> **Écart au plan :** pas de fichier `0001_creer_registre_migrations.sql` — le registre est créé par le script, sinon la première migration ne pourrait pas être enregistrée avant d'exister. La photographie du schéma n'a pas de section `DOWN` : annuler la création d'un schéma entier par commande n'a pas de sens, à ce niveau on restaure une sauvegarde. `down` le refuse en le disant.
>
> **Vérifié :** `tests/schema/data01-migrations.php` (36 contrôles) — base vide devenue complète par une seule commande, rejeu sans effet, registre et empreintes, annulation puis réapplication, refus d'annuler la photographie, détection d'une migration modifiée après application, refus par le web, et propriétés de l'export. Tout dans une base jetable, créée et supprimée par le test. Les treize suites de sécurité restent au vert.

**À faire :**
1. Créer `database/migrations/` avec des fichiers numérotés et horodatés : `2026_09_22_0001_creer_registre_migrations.sql`, etc. Chaque migration est **idempotente** et porte une section `-- UP` et une section `-- DOWN`.
2. Table `schema_migrations` (`version`, `nom`, `applique_le`, `duree_ms`, `checksum`).
3. `scripts/migrate.php`, **exclusivement en ligne de commande** (refus si `PHP_SAPI !== 'cli'`) :
   - `migrate.php status` — liste les migrations appliquées et en attente ;
   - `migrate.php up` — applique les migrations en attente, une transaction par migration, arrêt à la première erreur ;
   - `migrate.php down --steps=1` — annule la dernière migration ;
   - `migrate.php verify` — compare les checksums et signale toute migration modifiée après application.
4. Première migration : la **photographie du schéma réel** relevé en `CLEAN-04`, pour que les installations existantes et neuves convergent.
5. `database/tchadok.sql` devient un simple export de référence, régénéré après chaque migration — plus jamais la source.

**Critères d'acceptation :**
- Une base vide devient un schéma complet par la seule commande `migrate.php up`.
- Appliquer deux fois de suite ne produit aucune erreur.
- `migrate.php` appelé par HTTP ne fait rien et signale une erreur.
- `migrate.php verify` détecte une migration modifiée après coup.

---

### DATA-02 — Supprimer la double colonne de mot de passe

**Charge :** 1 j · **Dépend de :** `DATA-01`, `PREP-02` · **Statut :** fait le 23/09/2026

> **Bilan.** La colonne `password` est retirée ; `password_hash` fait seule foi. L'authentification acceptait l'une **ou** l'autre : toute divergence donnait au compte un **second mot de passe valide, permanent et invisible** — précisément ce que produisait l'ancien `admin/update-passwords.php`.
>
> **Les trois temps du plan sont respectés.** `scripts/analyser-mots-de-passe.php` produit le rapport **avant** toute écriture : comptes dont les deux colonnes diffèrent, colonnes vides, valeurs qui ne sont pas des hash bcrypt, et la liste nominative des comptes porteurs de deux mots de passe utilisables. La migration ne converge que là où `password_hash` est vide ou illisible — pour ne verrouiller personne — puis supprime la colonne.
>
> **Sur les comptes en conflit, `password_hash` l'emporte** : l'ancien mot de passe cesse de fonctionner, ce qui est l'objectif. Comme une migration ne peut pas prévenir les gens, le script offre `--reinitialiser-conflits` : mot de passe rendu inutilisable, sessions fermées, opération journalisée. La marche à suivre est écrite dans le script lui-même.
>
> **Code basculé** : `includes/auth.php`, `checkAdminCredentials()`, `register.php`, `settings.php`, `security-settings.php`, `2fa.php`, `admin/reset-password.php`, `scripts/create-admin.php`, plus le jeu de démonstration et trois jeux d'essai. `checkAdminCredentials()` **contrôle enfin `is_active`** : un compte désactivé ouvrait encore l'administration.
>
> **Un défaut trouvé par les tests, et corrigé.** La migration n'était pas rejouable : relancée sur une base déjà migrée, elle échouait sur « Unknown column 'password' ». Le test `DATA-01` qui vide le registre et rejoue tout l'a signalé. La convergence est désormais conditionnée à l'existence de la colonne.
>
> **Vérifié :** `tests/schema/data02-mot-de-passe.php` (18 contrôles). Le critère central est vérifié de la seule façon qui compte — **en essayant réellement de se connecter** avec l'ancien mot de passe d'un compte d'essai après changement : le refus est constaté sur le site en fonctionnement, pas déduit du code. Les seize autres suites au vert : **692 contrôles**.

La table `users` porte `password` **et** `password_hash`, toutes deux `NOT NULL`, et l'authentification accepte l'une **ou** l'autre. Toute divergence crée un second mot de passe valide permanent : après passage de l'ancien `admin/update-passwords.php`, l'ancien mot de passe reste valide via la colonne `password`.

**Migration en trois temps :**
1. **Analyse** — compter les comptes où les deux colonnes diffèrent, où l'une est vide, où l'une n'est pas un hash bcrypt valide. Produire un rapport avant toute écriture.
2. **Convergence** — pour chaque cas, décider explicitement quelle valeur fait foi. Si les deux colonnes portent des hash valides et distincts, **forcer une réinitialisation de mot de passe** pour ce compte plutôt que d'en choisir un arbitrairement.
3. **Bascule** — faire porter tout le code sur `password_hash` uniquement (`includes/auth.php` l. 44-51, `includes/database.php` fonction `checkAdminCredentials`, `register.php`, `api/user.php`), puis retirer la colonne `password`.

**Également :** vérifier que `checkAdminCredentials()` contrôle bien `is_active`, ce qu'elle ne fait pas aujourd'hui.

**Critères d'acceptation :**
- La colonne `password` n'existe plus.
- Aucun compte ne dispose de deux mots de passe valides.
- Les comptes en conflit ont reçu un e-mail de réinitialisation.

---

### DATA-03 — Introduire l'entité « sortie » (release) et les formats de vente

**Charge :** 2 j · **Dépend de :** `DATA-01` · **Bloque :** `SHOP-*`, `MOD-*` · **Statut :** fait le 23/09/2026

> **Bilan.** La table `releases` existe et porte ce que l'artiste met en vente : `format` (single, maxi single, EP, album, compilation), `slug` unique, `price_bundle`, `allow_track_buy`, `is_preorder`, le circuit de modération (`status`, `rejected_reason`, `reviewed_by`, `reviewed_at`) et les compteurs. `tracks.release_id`, `tracks.slug` et `artists.slug` complètent le rattachement et préparent les URL parlantes de `SEO-02`.
>
> **Les albums existants sont repris identifiants inclus**, avec leurs compteurs d'écoutes et de ventes, puis `albums` devient une **vue de compatibilité** exposant les anciens noms de colonnes (`format AS type`, `COALESCE(price_bundle, 0) AS price`). Les quatorze lectures existantes — `getAlbums()`, `countAlbums()`, `albums.php`, `api/track.php`, `api/playlists.php`, `history.php`, les deux tableaux de bord — continuent de fonctionner sans être touchées. `tracks.album_id` est conservée le temps que le code migre ; la clé étrangère vers l'ancienne table est retirée, sans quoi la table ne pouvait pas devenir une vue.
>
> **Les règles de format vivent dans `includes/sorties.php`, pas dans les formulaires.** Un contrôle qui ne vit que dans l'interface se contourne en envoyant la requête directement — ce que fera l'application Android. `Sorties::verifierComposition()` ne touche pas la base : c'est le cœur testable, réutilisable par la future API. Les formulaires artiste et console appellent `validerEnregistrement()` avant d'écrire, et `changerStatut()` avant toute publication.
>
> **Le moment du contrôle compte.** À la création, une sortie n'a aucun titre : exiger huit titres pour un album empêcherait de le créer. Le nombre de titres est donc vérifié à la **publication** (passage en « en attente » ou « approuvé »), le prix à l'enregistrement, où il est déjà connu. Une sortie naît en **brouillon** ; un refus, lui, n'exige aucune composition — on doit pouvoir rejeter un brouillon vide.
>
> **La remise du bundle est une règle, pas une suggestion** : 10 % minimum pour un maxi single ou un EP, 15 % pour un album ou une compilation. Sans remise, personne n'a de raison d'acheter la sortie plutôt que les titres un par un. Le message de refus chiffre le plafond au lieu d'énoncer un principe.
>
> **Code basculé** : `artist-add-album.php`, `admin-add-album.php` et `upload.php` écrivent dans `releases` ; les trois `INSERT INTO tracks` (`upload.php`, `artist-add-song.php`, `admin-add-song.php`) renseignent `release_id` et le slug. `scripts/generer-slugs.php` remplit les slugs manquants sur une base existante.
>
> **Deux défauts trouvés par les tests, et corrigés.** Le jeu d'essai `SEC-08` écrivait dans `albums`, devenue une vue non inscriptible — il écrit désormais dans `releases`. Le test `DATA-01` comptait deux vues sur une base fraîchement migrée ; il en attend trois et vérifie nommément la vue de compatibilité.
>
> **Vérifié :** `tests/schema/data03-sorties.php` (109 contrôles). Les trois critères du plan sont vérifiés **deux fois** : sans base, sur le cœur de règles, puis sur la base réelle par le chemin qu'empruntent les formulaires — un album d'un titre reste en brouillon, un maxi single sans prix est refusé, la sortie migrée ressort par `getAlbums()` et s'affiche sur `albums.php`. Les seize autres suites au vert : **785 contrôles** au total.

Le modèle actuel sépare `tracks` et `albums`, ce qui empêche de vendre un single comme un produit. L'énumération `albums.type` contient déjà `single`, `maxi_single`, `ep`, `album` mais n'est ni validée ni exploitée.

**À faire :**
1. Créer la table `releases` (structure détaillée au §6.2.1 de l'audit) : `artist_id`, `title`, `slug` unique, `format`, `genre_id`, `cover_image`, `release_date`, `is_preorder`, `price_bundle`, `allow_track_buy`, `status`, `rejected_reason`, `reviewed_by`, `reviewed_at`.
2. Ajouter `tracks.release_id` ; migrer les `album_id` existants ; conserver `albums` en vue de compatibilité (`format IN ('ep','album','compilation')`) le temps de la transition.
3. Ajouter `tracks.slug` et `artists.slug` (nécessaires aux URL parlantes de `SEO-02` et au lien artiste).
4. Implémenter les **règles de format côté serveur**, pas seulement dans l'interface :

| Format | Titres | Prix bundle | Achat au titre |
|---|---:|---|---|
| Single | 1 à 2 | Optionnel | Oui |
| Maxi single | 3 à 5 | Obligatoire, remise minimale vs somme des titres | Oui |
| EP | 4 à 7 | Obligatoire | Oui |
| Album | 8 et plus | Obligatoire | Configurable par l'artiste |
| Compilation | 8 et plus, multi-artistes | Obligatoire | Oui |

Un artiste ne doit pas pouvoir déclarer « album » une sortie d'un seul titre.

**Critères d'acceptation :**
- Une sortie d'un titre déclarée « album » est refusée avec un message explicite.
- Un maxi single sans prix bundle est refusé.
- Les albums existants sont migrés sans perte, et les pages publiques fonctionnent.

---

### DATA-04 — Sortir les prix et commissions du code

**Charge :** 1 j · **Dépend de :** `DATA-01` · **Statut :** fait le 23/09/2026

> **Bilan.** La table `pricing_rules` porte la grille : portée (`track`, `release`, `subscription`), format, devise, plancher, plafond, prix suggéré, taux de commission, période de validité et auteur de la dernière modification. `includes/tarifs.php` la lit **une fois par requête** — la relire à chaque appel coûterait une requête SQL par titre affiché.
>
> **Le tarif Premium est tranché : 2 000 / 20 000 FCFA**, arbitré avec le porteur du projet. Les pages publiques affichaient 2 500 / 25 000 pendant que les constantes, jamais lues, disaient 2 000 / 20 000 ; c'est donc une baisse de 20 % par rapport à l'affichage précédent, décidée en connaissance de cause. L'économie annuelle n'est plus un montant écrit à la main mais un calcul : annoncer « 5 000 FCFA » devenait faux dès que la grille changeait.
>
> **Grille de départ, planchers réduits de moitié** par rapport à la proposition de l'audit, pour laisser les artistes débutants entrer plus bas en prix : titre 100/300/1 000, single 150/500/1 500, maxi single 400/1 000/2 500, EP 500/1 500/3 500, album 750/2 500/6 000, compilation 750/3 000/8 000. La compilation est commissionnée à 20 %, les autres produits à 15 %.
>
> **Trois copies supprimées.** `PREMIUM_MONTHLY`, `PREMIUM_ANNUAL` et `DEFAULT_COMMISSION_RATE` ont disparu de `config/constants.php` ; `'commission_rate' => 15` a quitté `config/payment.php`, où `calculateCommission()` délègue désormais à `Tarifs::commission()` avec la portée et le format en argument — une compilation ne se commissionne pas comme un titre. Les bornes de forme de `SEC-17` (négatif, montant absurde, pas de 50 FCFA) demeurent : elles interdisent l'absurde quel que soit le produit, là où la grille porte les bornes **commerciales**.
>
> **`upload.php` annonçait des bornes que rien ne vérifiait** : « Prix entre 500 et 10 000 FCFA » dans la page, et un simple `(float) $_POST['price']` côté serveur, qui acceptait n'importe quoi. Les cinq formulaires (dépôt, titre artiste, titre console, album artiste, album console) valident maintenant contre la grille et **affichent les bornes réelles**, lues en base.
>
> **L'écran `admin/tarifs.php`** est le seul endroit où un prix change. Réservé à la permission `tarif.modifier`, il refuse un plafond sous le plancher, un suggéré hors bornes, une commission au-delà de 50 %, et inscrit l'avant/après au journal d'audit — ce qu'une requête SQL passée à la main ne ferait pas. Il est désormais **atteignable** : avec le journal d'audit de `SEC-19`, il n'était joignable qu'en tapant son adresse ; les deux entrées apparaissent dans la console selon la permission de celui qui regarde.
>
> **Un format inconnu ne s'échappe plus.** Une portée sans règle propre — format ajouté plus tard, valeur inattendue dans une requête forgée — hérite de l'**enveloppe** de sa portée : plancher le plus bas, plafond le plus haut. Sans ce repli, le contrôle de prix était simplement sauté, ce qu'un envoi forgé cherche précisément. Défaut trouvé par les tests.
>
> **Vérifié :** `tests/schema/data04-tarifs.php` (100 contrôles). Les deux critères qui comptent sont vérifiés **sur le site en fonctionnement** : le tarif est changé en base puis la page publique relue, qui affiche aussitôt le nouveau montant et la nouvelle économie ; et un prix sous le plancher est soumis par le **vrai formulaire artiste**, session ouverte, jeton CSRF compris — le refus est constaté à l'écran, pas déduit du code. Les dix-sept autres suites au vert : **885 contrôles** au total.
>
> **Reste à faire, hors périmètre.** `includes/payment.php` demeure inutilisable : erreur de syntaxe ligne 250 (`new \PDO::PARAM_STR(...)`), et le fichier contient **deux fois** son propre contenu. Aucune page ne le charge. `LOT 5` le refait entièrement.

Les prix Premium sont écrits en dur à trois endroits avec **deux valeurs contradictoires** : `config/constants.php` dit 2 000 / 20 000 FCFA, `premium.php` et `premium-payment.php` disent 2 500 / 25 000. Les constantes ne sont jamais lues par les pages. La commission est déclarée à trois endroits, et n'est lue nulle part au calcul.

**À faire :**
1. Table `pricing_rules` (structure au §6.2.3 de l'audit) : `scope`, `format`, `currency`, `min_price`, `max_price`, `suggested`, `commission_rate`, `active_from`, `active_to`.
2. Charger la grille depuis la base, avec mise en cache.
3. Retirer les constantes de prix de `config/constants.php` et les valeurs littérales de `premium.php` et `premium-payment.php`.
4. **Trancher les prix Premium** : 2 000/20 000 ou 2 500/25 000. Le plus important est qu'une seule valeur existe.
5. Valeurs de départ proposées (à valider) : titre 200/300/1 000 FCFA (plancher/suggéré/plafond), maxi single 750/1 000/2 500, EP 1 000/1 500/3 500, album 1 500/2 500/6 000. Le plancher protège la valeur perçue du catalogue et garantit que la commission couvre les frais mobile money.
6. Écran d'administration de la grille (`DASH-09`), avec journalisation des modifications.

**Critères d'acceptation :**
- Aucun prix n'est écrit en dur dans le code.
- Un changement de tarif en base est visible sans déploiement.
- Un prix hors grille soumis par un artiste est refusé.

---

### DATA-05 — Créer les tables commerciales

**Charge :** 1,5 j · **Dépend de :** `DATA-01`, `DATA-03`, `DATA-04` · **Bloque :** `LOT 6`, `LOT 7`, `LOT 8` · **Statut :** fait le 23/09/2026

> **Bilan.** Les six tables existent — `orders`, `order_items`, `entitlements`, `payouts`, `payment_intents`, `payment_events` — plus `invoice_counters`, qui porte la numérotation de facture. `includes/commandes.php` tient les quelques règles que le schéma ne peut pas exprimer.
>
> **Les cinq points non négociables sont tenus, et vérifiés en les attaquant.**
> - `gateway_ref` **UNIQUE** : la seconde commande portant la même référence est refusée par la base (`Duplicate entry`), pas par un test applicatif qui perdrait la course entre deux callbacks simultanés. Le code, lui, renvoie un message lisible avant d'y arriver.
> - `unit_price` et `commission_rate` **figés à la vente** : le test change la commission de 15 % à 40 % en base, puis relit la ligne de commande — elle porte toujours 15 % et la part nette promise à l'artiste.
> - `payment_events` **immuable** : deux déclencheurs refusent `UPDATE` et `DELETE` avec un message explicite, quels que soient les droits du compte — y compris `root` en local. Une preuve modifiable n'est pas une preuve.
> - **Double validation des versements** : la contrainte `double_validation` refuse, au niveau de la base, un versement dont l'approbateur est celui qui l'a préparé. La colonne `created_by` a été ajoutée pour cela.
> - **Facture sans trou** : `AUTO_INCREMENT` saute des valeurs dès qu'une transaction échoue. Le numéro vient donc d'un compteur verrouillé (`SELECT … FOR UPDATE`) et n'est attribué **qu'à l'encaissement** — une commande abandonnée ne consomme pas de numéro. Le test encaisse cinq commandes et vérifie que la suite est continue.
>
> **L'idempotence est traitée comme la règle, pas comme un cas limite.** Les opérateurs mobile money rejouent leurs callbacks : `marquerPayee()` appelée deux fois avec la même référence encaisse une fois, ne renvoie pas d'erreur — l'opérateur attend un accusé de réception — et n'ouvre pas un second droit. Le quota de téléchargement se décrémente en **une seule requête SQL conditionnelle**, pour que deux téléchargements simultanés ne passent pas tous les deux.
>
> **Le contrôle d'accès aux médias lit enfin les droits.** `MediaAccess::aAchete()` interrogeait `purchases` sans regarder la péremption : un droit expiré ouvrait encore le fichier. Il lit maintenant `entitlements`, avec expiration et révocation.
>
> **Écart assumé par rapport au plan : `purchases` est supprimée, pas transformée en vue.** Le plan prévoyait une vue de compatibilité. Mais la migration `0001`, photographie du schéma, crée un **déclencheur sur `purchases`** — et un déclencheur ne se pose pas sur une vue. Rejouer les migrations depuis un registre vide, le scénario de reprise que `DATA-01` garantit, échouait alors avec « purchases is not of type BASE TABLE ». Défaut trouvé par le test `DATA-01`. Plutôt que de corriger une migration déjà appliquée ailleurs — ce qui invaliderait son empreinte et obligerait chaque serveur à la ré-enregistrer à la main — la table est supprimée : `0001` la recrée vide et inoffensive, `0009` la retire de nouveau. Les **quatre écrans** qui la lisaient (dashboard artiste, dashboard membre, portefeuille, et le contrôle d'accès) lisent désormais `orders`, `order_items` et `entitlements`. Ils y gagnent : `purchases` n'étant écrite par personne, ces chiffres affichaient **toujours zéro**.
>
> **Le compteur de ventes compte enfin au bon moment.** `update_purchase_stats` incrémentait à l'`INSERT` : une commande créée, même jamais payée, comptait comme une vente. Le nouveau déclencheur `compter_vente_payee` suit le **passage à `paid`**, une seule fois, et crédite l'artiste de sa part nette.
>
> **Une fragilité de test corrigée au passage.** `SEC-10` échouait quand il suivait `SEC-09` : la limitation de débit, partagée par toute la plateforme, était encore chargée par les centaines de requêtes du test précédent, et les connexions repartaient en 429. Il remet le compteur à zéro avant de commencer.
>
> **Vérifié :** `tests/schema/data05-commerce.php` (109 contrôles). Chaque interdit est vérifié **en essayant l'écriture** et en constatant le refus du serveur : seconde référence opérateur, modification d'un événement, suppression d'un événement, versement auto-approuvé, période versée deux fois, suppression d'un acheteur ayant commandé. Les dix-huit autres suites au vert : **993 contrôles** au total.

Structures détaillées aux §6.2.4 à §6.2.6 de l'audit.

| Table | Rôle | Points non négociables |
|---|---|---|
| `orders` | Commande | `reference` unique, `gateway_ref` **unique** (idempotence opérateur), `invoice_number` séquentiel sans trou |
| `order_items` | Lignes de commande | `unit_price` **et** `commission_rate` **figés au moment de la vente** — un changement de tarif ne doit jamais réécrire l'historique |
| `entitlements` | Droits d'accès | Source (`purchase`, `subscription`, `gift`, `promo`), quota de téléchargement, expiration |
| `payouts` | Versements artistes | Période unique par artiste, `approved_by` distinct du créateur |
| `payment_intents` | Tentatives de paiement | Une commande peut donner lieu à plusieurs tentatives ; chacune est tracée |
| `payment_events` | Journal brut des échanges passerelle | Requête, réponse, callback, signature, horodatage — **immuable**, sert de preuve en cas de litige |

**Également :** la table `purchases` existante, lue par quatre écrans mais jamais écrite, est conservée en vue de compatibilité pointant sur `order_items`, puis retirée quand les dashboards seront refaits (`LOT 13`).

**Critères d'acceptation :**
- Les migrations créent les six tables avec leurs contraintes.
- Une seconde commande portant le même `gateway_ref` est rejetée par la base, pas seulement par le code.
- `payment_events` n'accepte ni `UPDATE` ni `DELETE` depuis l'application.

---

### DATA-06 — Suppression logique et conservation

**Charge :** 1 j · **Dépend de :** `DATA-01` · **Statut :** fait le 23/09/2026

> **Bilan.** `deleted_at` existe sur `users`, `artists`, `tracks`, `releases` et `playlists` — plus `anonymized_at` sur `users`. La vue `albums` expose la colonne, et `streams.user_id` devient facultative : une écoute doit pouvoir survivre à son auteur, sinon le baromètre par genre et les revenus des artistes s'effondrent dès qu'un auditeur s'en va.
>
> **Trois gestes, désormais distincts.** *Retirer* fait disparaître un contenu des pages publiques sans le sortir de la base, et se répare (`retablir`). *Anonymiser* remplace ce qui désigne une personne et conserve les écritures comptables — définitif. *Purger* n'existe pas encore : les durées de conservation sont une politique déclarée, pas une tâche automatique, et le document le dit.
>
> **Les trente-six lectures publiques filtrent.** C'est le point qu'il est facile de croire sur parole et facile de rater : une seule requête oubliée laisse le contenu retiré à l'écran. Chaque `status = 'approved'` et chaque `is_active = 1` de `includes/database.php` est désormais accompagné du filtre de retrait, et le test appelle **chaque** fonction de lecture publique avant de relire les pages elles-mêmes.
>
> **Le droit à l'effacement ne s'oppose pas à la conservation comptable.** `Effacement::anonymiser()` remplace nom, courriel, téléphone, ville, pays, date de naissance et photo ; rend le mot de passe inutilisable ; ferme sessions, jetons « se souvenir de moi », second facteur et notifications. Restent, sans lien avec une personne : commandes, lignes, factures, droits d'accès, événements de paiement, versements, et les écoutes **détachées de leur auteur**. Le journal d'audit conserve l'identifiant numérique et le motif, jamais le nom — il faut pouvoir prouver que la demande a été honorée.
>
> **La base refusait déjà de supprimer un acheteur** depuis `DATA-05` (`orders.user_id` en `ON DELETE RESTRICT`) : le test le constate en essayant le `DELETE`. Anonymiser est donc la seule voie, ce qui est le bon comportement.
>
> **Livrés :** `includes/effacement.php`, `scripts/effacement.php` (retirer, rétablir, état, anonymiser — hors du web, tracé au journal, et qui **annonce ce qui sera conservé avant d'agir**), et `docs/exploitation/conservation.md` : durée par catégorie, ce qui part, ce qui reste, et ce qui n'est pas encore fait.
>
> **Un test défectueux corrigé au passage.** `SEC-12` vérifiait qu'une réponse à la question de vérification ne se rejoue pas, en renvoyant l'ancienne somme. Or la page de confirmation arme déjà une nouvelle question, et les sommes vont de 4 à 18 : l'ancienne réponse tombait juste par hasard environ une fois sur dix, faisant échouer le contrôle au hasard. Le test tire désormais jusqu'à obtenir une question différente — le refus devient certain, et le contrôle garde son sens. Trois exécutions de suite pour le confirmer.
>
> **Vérifié :** `tests/schema/data06-effacement.php` (86 contrôles). Les dix-neuf autres suites au vert : **1 083 contrôles** au total.

Aucune table ne porte de `deleted_at`. Les suppressions en cascade détruisent l'historique de ventes et d'écoutes rattaché — incompatible avec une obligation de conservation comptable et avec la résolution de litiges artistes.

**À faire :**
1. Ajouter `deleted_at` sur `users`, `artists`, `tracks`, `releases`, `albums`, `playlists`.
2. Remplacer les `DELETE` applicatifs par une mise à jour de `deleted_at`, et filtrer systématiquement en lecture.
3. Anonymisation plutôt que suppression pour un utilisateur exerçant son droit à l'effacement : remplacer les données personnelles, **conserver les écritures comptables** (obligation légale) et les écoutes sous forme agrégée non nominative.
4. Écrire `docs/exploitation/conservation.md` : durée de conservation par catégorie de données (compte, écoutes, transactions, journaux d'audit).

**Critères d'acceptation :**
- Supprimer un compte ayant acheté ne fait pas disparaître la ligne de commande.
- Un compte anonymisé ne contient plus ni e-mail, ni téléphone, ni nom.
- Les contenus supprimés n'apparaissent plus sur les pages publiques.

---

### DATA-07 — Index manquants et intégrité

**Charge :** 4 h · **Dépend de :** `DATA-01`

Le schéma est globalement bien indexé (34 clés étrangères, index pertinents sur `streams` et `purchases`), mais il manque l'essentiel pour le catalogue public.

**À faire :**
1. Index sur `tracks(status, created_at)` et `releases(status, release_date)` : **toutes** les requêtes du catalogue public filtrent sur `status`, aujourd'hui sans index.
2. Index sur `tracks(genre_id, status)` et `albums(genre_id, status)` pour les vues par genre du baromètre.
3. Vérifier que `DATE_FORMAT(created_at, ...)` n'est plus utilisé dans les clauses `WHERE` : cette écriture empêche l'usage de l'index (remplacer par un intervalle `created_at >= ? AND created_at < ?`).
4. Contrôler que le jeu de caractères est homogène (`utf8mb4_unicode_ci` partout ; le schéma actuel mélange `utf8mb4_general_ci` et d'autres).

**Critères d'acceptation :**
- `EXPLAIN` sur les cinq requêtes du catalogue public ne montre plus de parcours complet de table.
- Aucune requête de production n'applique de fonction sur une colonne indexée dans un `WHERE`.

---

### DATA-08 — Données de référence

**Charge :** 4 h · **Dépend de :** `DATA-01`, `TAXO-01`

La table `genres` **n'est pas alimentée par le dump** : sur une installation neuve elle est vide. C'est la cause première de l'impossibilité de produire des statistiques par genre.

**À faire :**
1. `database/seeds/referentiel.sql` : catégories et genres (proposition au §6.3 de l'audit), grille tarifaire initiale, rôles et permissions, provinces du Tchad pour le baromètre régional.
2. `database/seeds/demo.sql` : jeu de démonstration (artistes fictifs clairement identifiés comme tels, titres, écoutes) — **jamais importé en production**, contrôlé par `CFG-05`.
3. `scripts/seed.php`, en ligne de commande uniquement, avec un garde refusant l'exécution du jeu de démonstration si l'environnement est `production`.

**Critères d'acceptation :**
- Une installation neuve dispose d'un référentiel de genres exploitable.
- Le jeu de démonstration ne peut pas être importé en production.

---

## LOT 5 — Passerelles de paiement et simulateurs locaux

> Objectif : rendre le paiement réellement fonctionnel, et disposer en local d'un environnement complet — Airtel Money, Moov Money, VISA, KONOOM — qui exerce **exactement le même chemin de code** que la production. Un simulateur qui contourne les contrôles de sécurité donne une fausse confiance : les mocks doivent signer leurs callbacks, respecter l'idempotence et savoir échouer.

### État de départ

`includes/payment.php` contient du code cURL pour Airtel et Moov, mais n'est appelé par **aucun point d'entrée**. Les endpoints `api/payment.php` et `api/payments/*` retournent 501. `config/payment.php` ne contient que des valeurs de modèle (`YOUR_AIRTEL_CLIENT_ID`). Le tunnel Premium insère une ligne `status = 'pending'` et s'arrête là, sans callback : **personne ne peut devenir Premium**. Aucun achat de titre n'est possible.

### Note sur KONOOM

Je n'ai pas de connaissance fiable de la spécification d'API de KONOOM. L'adaptateur et le simulateur correspondants sont donc conçus contre un **contrat REST générique documenté** (`docs/paiement/konoom-contrat.md`), à réaligner dès l'obtention de la documentation officielle du partenaire. C'est précisément l'intérêt de l'architecture par adaptateurs de `PAY-01` : ce réalignement n'affectera que le fichier de l'adaptateur, pas le reste de l'application. La même précaution vaut pour Moov Money et pour l'acquéreur VISA, dont les spécifications exactes dépendent du contrat signé.

---

### PAY-01 — Abstraction des passerelles

**Charge :** 2 j · **Dépend de :** `DATA-05` · **Bloque :** `PAY-02` à `PAY-11`

**À faire :**
1. Créer l'interface `PaymentGateway` dans `includes/payment/` :

```php
interface PaymentGateway {
    public function code(): string;              // airtel_money, moov_money, visa, konoom
    public function supports(string $currency): bool;
    public function initiate(PaymentIntent $intent): GatewayResult;
    public function status(string $gatewayRef): GatewayResult;
    public function verifyCallback(array $headers, string $rawBody): CallbackResult;
    public function refund(string $gatewayRef, int $amount, string $reason): GatewayResult;
}
```

2. Quatre adaptateurs : `AirtelMoneyGateway`, `MoovMoneyGateway`, `VisaGateway`, `KonoomGateway`.
3. `PaymentGatewayFactory` : résout l'adaptateur et l'URL de base depuis le fichier d'environnement. **Le basculement local → production est un changement de configuration, jamais de code** (`PAYMENT_DRIVER=mock` ou `live`, et `<GATEWAY>_BASE_URL`).
4. Objets de valeur : `PaymentIntent`, `GatewayResult`, `CallbackResult` — pas de tableaux associatifs qui se déforment de couche en couche.
5. Reprendre le code cURL existant de `includes/payment.php` comme base des adaptateurs Airtel et Moov, en corrigeant : délai d'attente explicite, vérification du certificat TLS, journalisation dans `payment_events`, gestion des codes d'erreur.

**Critères d'acceptation :**
- Changer `PAYMENT_DRIVER` de `mock` à `live` ne modifie aucun fichier PHP.
- Ajouter une cinquième passerelle demande un seul fichier d'adaptateur et une entrée de configuration.
- Chaque échange avec une passerelle produit une entrée `payment_events`.

---

### PAY-02 — Machine à états du paiement et idempotence

**Charge :** 2 j · **Dépend de :** `PAY-01`, `DATA-05`

C'est le cœur de la fiabilité financière. Les opérateurs mobile money envoient fréquemment des callbacks en double, dans le désordre, ou après un long délai.

**États et transitions autorisées :**

```
created ──► awaiting_payment ──┬──► paid ──► refunded
                               ├──► failed
                               ├──► cancelled
                               └──► expired    (délai dépassé, aucun callback)
```

**Règles non négociables :**
1. **Seul un callback opérateur vérifié** (signature valide) fait passer une commande en `paid`. Jamais le navigateur du client, jamais un appel d'administration, jamais `api/transaction.php` (endpoint retiré en `SEC-01`).
2. **Idempotence par `gateway_ref`**, garantie par une contrainte d'unicité en base — pas seulement par un test applicatif. Un second callback identique est accepté en HTTP (200, pour que l'opérateur cesse de réessayer) mais ne produit **aucun** effet supplémentaire.
3. **Transitions irréversibles** : une commande `paid` ne repasse jamais en `pending`. Un remboursement crée une écriture d'annulation, il ne réécrit pas l'historique.
4. **Verrou** sur la ligne de commande pendant le traitement d'un callback (`SELECT ... FOR UPDATE`), pour éviter que deux callbacks simultanés ne créent deux droits d'accès.
5. **Expiration automatique** : une tentative sans callback au bout de 15 minutes passe en `expired`, avec relance du statut auprès de la passerelle avant conclusion (le callback peut s'être perdu).
6. **Contrôle de montant** : le montant du callback doit correspondre exactement au montant de la commande. Tout écart met la commande en revue manuelle plutôt que de l'accepter.

**Critères d'acceptation :**
- Rejouer trois fois le même callback ne crée qu'un droit d'accès et qu'une facture.
- Deux callbacks simultanés sur la même commande n'en valident qu'une.
- Un callback portant un montant différent ne valide pas la commande et déclenche une alerte.
- Une tentative abandonnée bascule en `expired` après vérification auprès de la passerelle.

---

### PAY-03 — Socle des simulateurs locaux

**Charge :** 2 j · **Dépend de :** `PAY-01`

**Principe :** quatre serveurs HTTP indépendants, un par opérateur, plus une console de pilotage. Chacun imite les URL, l'authentification et le format de réponse de l'opérateur correspondant. Ils fonctionnent **sans base de données** (stockage JSON dans `mock-gateways/storage/`) pour être démarrables en une commande et réinitialisables en supprimant un répertoire.

**Arborescence :**

```
mock-gateways/
├── lib/
│   ├── MockStore.php          stockage JSON, verrou de fichier
│   ├── MockRouter.php          routage minimal
│   ├── Signer.php              signature HMAC des callbacks
│   ├── Scenario.php            résolution du scénario depuis le numéro / la carte
│   └── CallbackDispatcher.php  envoi différé des callbacks vers l'application
├── airtel/index.php            port 9101
├── moov/index.php              port 9102
├── visa/index.php              port 9103
├── konoom/index.php            port 9104
├── console/index.php           port 9100 — interface de pilotage
├── storage/                    transactions simulées (ignoré par Git)
└── README.md
```

**Lanceur :** `scripts/mock-gateways.bat` (Windows/XAMPP) et `scripts/mock-gateways.sh`, démarrant les cinq serveurs via le serveur intégré de PHP :

```
php -S 127.0.0.1:9101 -t mock-gateways/airtel
php -S 127.0.0.1:9102 -t mock-gateways/moov
php -S 127.0.0.1:9103 -t mock-gateways/visa
php -S 127.0.0.1:9104 -t mock-gateways/konoom
php -S 127.0.0.1:9100 -t mock-gateways/console
```

**Exigences communes aux quatre simulateurs :**

| Exigence | Détail |
|---|---|
| Authentification imitée | Chaque simulateur exige le mode d'authentification de l'opérateur réel et **rejette** les identifiants invalides |
| Callbacks **signés** | HMAC-SHA256 sur le corps brut, en-tête dédié, secret partagé lu dans le fichier d'environnement |
| Callbacks **différés** | Envoyés 2 à 15 secondes après l'initiation, comme en réalité — cela révèle les hypothèses de synchronisme cachées dans le code |
| Callbacks **rejouables** | Depuis la console, pour tester l'idempotence |
| Réponses lentes et erreurs | Latence configurable, `500`, `502`, timeout — pour exercer la gestion d'erreur |
| Persistance | Les transactions survivent au redémarrage du simulateur |

**Sécurité :** les simulateurs écoutent sur `127.0.0.1` uniquement, jamais sur `0.0.0.0`. Le répertoire `mock-gateways/` est bloqué par les deux `.htaccess` (`CFG-04`) et sa présence en production déclenche une alerte (`CFG-05`).

**Critères d'acceptation :**
- Une seule commande démarre les cinq serveurs.
- Un appel sans identifiants valides est rejeté par chaque simulateur.
- Un callback envoyé avec un secret erroné est **refusé par l'application**.
- Supprimer `mock-gateways/storage/` réinitialise l'état.

---

### PAY-04 — Réception des callbacks côté application

**Charge :** 1,5 j · **Dépend de :** `PAY-02`, `PAY-03`

**À faire :**
1. Créer `api/payments/callback.php` avec une route par passerelle : `/api/payments/callback/airtel`, `/moov`, `/visa`, `/konoom`.
2. Pour chaque callback, dans cet ordre :
   - lire le **corps brut** avant toute désérialisation (la signature porte sur les octets exacts) ;
   - vérifier la signature via `verifyCallback()` de l'adaptateur, en comparaison à temps constant ;
   - vérifier que l'IP source figure dans la liste déclarée de l'opérateur (en production ; en local, `127.0.0.1`) ;
   - enregistrer l'événement brut dans `payment_events` **avant** tout traitement — même si la suite échoue, la preuve existe ;
   - appliquer la transition d'état sous verrou ;
   - répondre `200` rapidement, y compris pour un doublon.
3. **Exclure ces routes du garde CSRF** (`SEC-09`) — c'est la seule exception légitime, et elle est compensée par la vérification de signature.
4. Journaliser et alerter sur : signature invalide, IP inconnue, commande introuvable, écart de montant.
5. En cas d'erreur applicative, répondre `500` pour que l'opérateur réessaie — ne jamais répondre `200` sur un traitement échoué.

**Critères d'acceptation :**
- Un callback à signature invalide est rejeté (`401`) et journalisé comme incident.
- Un callback valide pour une commande inconnue est journalisé, sans effet.
- Un doublon retourne `200` sans second effet.
- `payment_events` contient la trace de tous les callbacks, y compris rejetés.

---

### PAY-05 — Simulateur Airtel Money

**Charge :** 1,5 j · **Dépend de :** `PAY-03` · **Port :** 9101

Airtel Africa expose une API OAuth2 : le code existant dans `includes/payment.php` (`getAirtelAccessToken()`, `processAirtelMoney()`) donne la forme générale à imiter.

**Points de terminaison simulés :**

| Méthode | Chemin | Comportement |
|---|---|---|
| `POST` | `/auth/oauth2/token` | `client_credentials` → jeton porteur, expiration 3600 s. Identifiants erronés → `401` |
| `POST` | `/merchant/v1/payments/` | Initiation USSD push. Exige le jeton porteur, `X-Country: TD`, `X-Currency: XAF`. Retourne un identifiant de transaction, statut `TIP` (en attente) |
| `GET` | `/standard/v1/payments/{id}` | Consultation de statut |
| `POST` | `/standard/v1/payments/refund` | Remboursement |

**Comportement à imiter fidèlement :** l'USSD push n'est pas instantané. Le simulateur retourne « en attente », puis envoie le callback après le délai configuré — exactement comme un abonné qui saisit son code sur son téléphone.

**Scénarios pilotés par le numéro de téléphone** (documentés dans `docs/paiement/jeux-de-test.md`) :

| Numéro | Résultat |
|---|---|
| `66000001` | Succès immédiat (2 s) |
| `66000002` | Succès après 15 s (abonné lent) |
| `66000003` | Échec — solde insuffisant |
| `66000004` | Échec — code PIN erroné |
| `66000005` | Aucun callback (test de l'expiration `PAY-02`) |
| `66000006` | Annulé par l'abonné |
| `66000007` | Succès, **callback envoyé trois fois** (test d'idempotence) |
| `66000008` | Succès, callback à **signature invalide** (doit être rejeté) |
| `66000009` | Succès, mais **montant différent** dans le callback (doit déclencher une revue) |
| `66000010` | Erreur `500` à l'initiation |
| Tout autre | Succès après 5 s |

**Critères d'acceptation :**
- Le parcours complet fonctionne en local : sélection du moyen de paiement, initiation, attente, callback, commande `paid`, droit d'accès créé.
- Chacun des dix scénarios produit le comportement attendu côté application.
- Un jeton porteur expiré provoque un renouvellement automatique par l'adaptateur.

---

### PAY-06 — Simulateur Moov Money

**Charge :** 1,5 j · **Dépend de :** `PAY-03` · **Port :** 9102

Moov utilise une signature de requête plutôt qu'OAuth (le code existant contient `generateMoovSignature()`). La spécification exacte dépend du contrat : le simulateur suit le contrat documenté dans `docs/paiement/moov-contrat.md`, à réaligner à la signature du partenaire.

**Points de terminaison simulés :** `POST /payment/request` (initiation), `GET /payment/status/{id}`, `POST /payment/refund`.

**Spécificités à imiter :**
- Signature HMAC de la requête, vérifiée par le simulateur — un mauvais secret retourne `401`.
- Horodatage dans la requête, avec **rejet au-delà de 5 minutes d'écart** (protection contre le rejeu). Cela oblige à traiter correctement la synchronisation d'horloge, source classique d'incidents en production.
- Format de réponse et codes d'erreur distincts d'Airtel, pour vérifier que l'abstraction `PAY-01` tient réellement.

**Mêmes dix scénarios** que `PAY-05`, sur la plage `65000001` à `65000010`.

**Critères d'acceptation :**
- Une requête à signature invalide est rejetée par le simulateur.
- Une requête horodatée d'il y a 10 minutes est rejetée.
- Le parcours complet fonctionne, avec le même code applicatif qu'Airtel.

---

### PAY-07 — Simulateur VISA / carte bancaire

**Charge :** 2 j · **Dépend de :** `PAY-03` · **Port :** 9103

Le paiement par carte diffère structurellement du mobile money : saisie des données de carte, authentification forte (3-D Secure), redirection.

**Exigence absolue : aucune donnée de carte ne doit transiter par l'application ni être stockée.** Le simulateur imite une page hébergée par l'acquéreur : l'application redirige vers le simulateur, qui collecte la carte et retourne un **jeton**. C'est le seul modèle acceptable, et il doit être adopté dès le local pour que la production n'ait pas à changer d'architecture.

**Points de terminaison simulés :**

| Méthode | Chemin | Comportement |
|---|---|---|
| `POST` | `/sessions` | Crée une session de paiement, retourne l'URL de la page hébergée |
| `GET` | `/hosted/{session}` | Page de saisie simulée (formulaire carte) |
| `POST` | `/hosted/{session}/submit` | Traite la carte, déclenche ou non le défi 3-D Secure |
| `GET` | `/hosted/{session}/3ds` | Page de défi simulée (code `123456`) |
| `GET` | `/payments/{id}` | Consultation de statut |
| `POST` | `/payments/{id}/refund` | Remboursement, total ou partiel |

**Cartes de test** (reprenant les numéros de test standards de l'industrie, non rattachés à un compte réel) :

| Numéro | Résultat |
|---|---|
| `4111 1111 1111 1111` | Succès, sans défi 3-D Secure |
| `4000 0000 0000 3220` | Succès, **avec** défi 3-D Secure (code `123456`) |
| `4000 0000 0000 0002` | Refusée par l'émetteur |
| `4000 0000 0000 9995` | Refusée — provision insuffisante |
| `4000 0000 0000 0069` | Refusée — carte expirée |
| `4000 0000 0000 0127` | Refusée — cryptogramme incorrect |
| `4000 0000 0000 0119` | Erreur de traitement de l'acquéreur |
| `4000 0000 0000 0259` | Succès, puis **contestation** déclenchée 30 s plus tard |

Le dernier cas est important : les contestations existent en carte et pas en mobile money. Il faut que l'application sache retirer un droit d'accès accordé.

**Également :** gestion de la conversion de devise (un porteur de carte étranger paiera en EUR ou USD) — taux et arrondi à définir, aujourd'hui absents du projet.

**Critères d'acceptation :**
- Aucun numéro de carte n'apparaît dans les journaux, la base ou `payment_events`.
- Le parcours avec défi 3-D Secure aboutit.
- Une contestation retire le droit d'accès et crée une écriture d'annulation.

---

### PAY-08 — Simulateur KONOOM

**Charge :** 1,5 j · **Dépend de :** `PAY-03` · **Port :** 9104

**Préalable :** obtenir la documentation officielle de KONOOM. En son absence, le simulateur est construit contre un contrat REST générique que je documente dans `docs/paiement/konoom-contrat.md`, avec la liste explicite des points à confirmer auprès du partenaire :

- mode d'authentification (clé d'API, OAuth2, signature de requête) ;
- format d'initiation et de réponse ;
- mécanisme de notification (callback poussé, ou consultation périodique) ;
- méthode de signature des callbacks ;
- codes d'erreur et leur signification ;
- disponibilité et modalités de remboursement ;
- existence d'un environnement de test fourni par le partenaire ;
- devises et plafonds de transaction.

**Contrat provisoire simulé :** `POST /v1/charges` (initiation), `GET /v1/charges/{id}` (statut), `POST /v1/charges/{id}/refund`, authentification par clé d'API en en-tête, callback signé HMAC-SHA256.

**Mêmes dix scénarios**, sur une plage d'identifiants dédiée.

**Critères d'acceptation :**
- Le parcours complet fonctionne en local avec le contrat provisoire.
- `docs/paiement/konoom-contrat.md` liste précisément ce qui doit être confirmé auprès du partenaire.
- Le réalignement sur la spécification réelle ne touchera que `KonoomGateway` et le simulateur.

---

### PAY-09 — Console de pilotage des simulateurs

**Charge :** 1,5 j · **Dépend de :** `PAY-03` · **Port :** 9100

Une interface web locale pour observer et provoquer, sans laquelle le développement du tunnel d'achat devient pénible.

**Fonctions :**
- Liste des transactions simulées des quatre passerelles : identifiant, montant, statut, horodatage, scénario appliqué.
- Détail d'une transaction : requête reçue, réponse envoyée, callbacks émis avec leur signature, latence.
- Actions manuelles sur une transaction en attente : **valider**, **refuser**, **expirer**, **rejouer le callback**, **envoyer un callback mal signé**, **envoyer un callback au montant altéré**.
- Réglages globaux : latence des callbacks, taux d'échec aléatoire, indisponibilité simulée d'une passerelle.
- Bouton de réinitialisation complète.
- Flux des callbacks envoyés, avec le code de réponse de l'application — c'est là qu'on voit immédiatement si l'idempotence fonctionne.

**Critères d'acceptation :**
- Un développeur peut reproduire les dix scénarios sans toucher à un fichier de configuration.
- Rejouer un callback depuis la console montre `200` et l'absence de second effet côté application.
- La console est inaccessible depuis une autre machine.

---

### PAY-10 — Réconciliation et rapports financiers

**Charge :** 1,5 j · **Dépend de :** `PAY-02`, `PAY-04`

Sans réconciliation, un écart entre ce que l'opérateur a encaissé et ce que la plateforme a enregistré passe inaperçu jusqu'au litige.

**À faire :**
1. Tâche planifiée quotidienne rapprochant les commandes `paid` de la veille avec le relevé de l'opérateur (import de fichier, ou consultation API selon ce que chaque partenaire fournit).
2. Table `reconciliation_runs` et `reconciliation_discrepancies` : montant encaissé sans commande, commande sans encaissement, écart de montant, doublon.
3. Écran d'administration listant les écarts, avec résolution manuelle tracée.
4. Alerte si le taux d'écart dépasse un seuil.
5. Les simulateurs produisent un relevé quotidien au même format, pour que la réconciliation soit testable en local.

**Critères d'acceptation :**
- La réconciliation s'exécute en local sur les données des simulateurs.
- Un écart injecté volontairement est détecté et listé.
- Aucun écart ne peut être clos sans motif saisi.

---

### PAY-11 — Bascule vers les passerelles réelles

**Charge :** 1 j (hors délais partenaires) · **Dépend de :** `PAY-05` à `PAY-08`

Cette tâche dépend d'éléments externes dont les délais ne sont pas maîtrisés par l'équipe technique. À engager **au plus tôt**, en parallèle du développement.

**À obtenir auprès de chaque partenaire :**

| Partenaire | À obtenir | Responsable |
|---|---|---|
| Airtel Money Tchad | Contrat marchand, identifiants de test puis de production, liste des IP de callback, documentation | Direction |
| Moov Money Tchad | Idem | Direction |
| Acquéreur VISA | Contrat d'acquisition, page hébergée, identifiants, certification éventuelle | Direction |
| KONOOM | Documentation d'API, contrat, identifiants | Direction |

**Question préalable à trancher avant le premier encaissement :** le statut requis pour **encaisser pour le compte de tiers** et reverser à des artistes. Selon la réponse, l'architecture peut changer (encaissement par la plateforme, ou redirection du paiement vers l'artiste). Cette question conditionne `LOT 8` et doit être posée maintenant, pas au moment de la mise en ligne.

**Séquence de bascule :**
1. Environnement de test du partenaire : renseigner `<GATEWAY>_BASE_URL` sur leur bac à sable, `PAYMENT_DRIVER=live`, secrets de test.
2. Rejouer les dix scénarios contre leur bac à sable — certains ne seront pas reproductibles, les noter.
3. Vérifier la liste des IP de callback et l'ouvrir côté pare-feu.
4. Transaction réelle de bout en bout, de faible montant, avec remboursement, avant ouverture au public.
5. Passage en production : secrets de production, `PAYMENT_DRIVER=live`, suppression de `mock-gateways/`, contrôle par `CFG-05`.

**Critères d'acceptation :**
- Une transaction réelle de bout en bout aboutit sur chaque passerelle contractée.
- `CFG-05` empêche tout démarrage en production avec `PAYMENT_DRIVER=mock`.
- `docs/paiement/exploitation.md` décrit la conduite à tenir en cas de panne d'une passerelle.

---

## LOT 6 — Tunnel d'achat et livraison protégée

> Objectif : rendre la vente possible. Aujourd'hui la table `purchases` est lue par quatre écrans et **jamais écrite** : il n'existe ni panier, ni bouton « Acheter », ni contrôle de droit au téléchargement, ni facture. C'est la promesse centrale faite aux artistes, et elle n'est tenue par aucune ligne de code.

### SHOP-01 — Panier

**Charge :** 2 j · **Dépend de :** `DATA-05`

**À faire :**
1. Panier persistant en base (table `orders` au statut `cart`), rattaché à l'utilisateur connecté ; pour un visiteur, panier de session fusionné à la connexion.
2. Ajout d'un titre ou d'une sortie complète ; un titre déjà couvert par une sortie présente dans le panier est signalé pour éviter le double achat.
3. Contrôles serveur à chaque modification : le contenu existe, il est `approved`, il n'est pas gratuit, il n'est pas **déjà possédé** par l'utilisateur.
4. Les prix sont **relus depuis la base** à chaque affichage et au passage en commande — jamais repris du client.
5. Récapitulatif : sous-total, frais de transaction, total, en FCFA avec la mise en forme locale.

**Critères d'acceptation :**
- Un prix modifié côté client n'a aucun effet sur le montant facturé.
- Acheter deux fois le même titre est impossible.
- Le panier survit à une déconnexion/reconnexion.

---

### SHOP-02 — Passage en commande et choix du moyen de paiement

**Charge :** 2 j · **Dépend de :** `SHOP-01`, `PAY-02`

**À faire :**
1. Écran de paiement : récapitulatif figé, choix entre Airtel Money, Moov Money, carte, KONOOM et portefeuille (`SHOP-06`).
2. Validation du numéro de téléphone pour le mobile money, avec le format tchadien.
3. Création de la commande (`awaiting_payment`) et du `payment_intent`, **figeant `unit_price` et `commission_rate`** dans `order_items`.
4. Écran d'attente avec consultation périodique du statut, instructions claires (« composez votre code sur votre téléphone »), et délai visible.
5. Issues traitées explicitement : succès, échec avec motif lisible (solde insuffisant, code erroné, annulation), expiration avec possibilité de reprise.
6. Nouvelle tentative possible sur une commande échouée, sans recréer le panier.

**Critères d'acceptation :**
- Les dix scénarios de `PAY-05` produisent un message compréhensible pour l'utilisateur, jamais un code d'erreur brut.
- Une commande expirée peut être relancée.
- Fermer l'onglet pendant l'attente n'empêche pas la validation à l'arrivée du callback.

---

### SHOP-03 — Facturation

**Charge :** 1,5 j · **Dépend de :** `SHOP-02`

**À faire :**
1. Numérotation **séquentielle sans trou** (obligation comptable), générée sous verrou à la validation du paiement, au format `TCHK-2026-000123`.
2. Facture PDF : mentions légales, identité de l'éditeur, détail des lignes, TVA le cas échéant, moyen de paiement, référence opérateur.
3. Envoi par e-mail à la validation, et mise à disposition permanente dans l'espace client.
4. Archivage des factures selon la durée de conservation retenue (`DATA-06`).

**Critères d'acceptation :**
- Deux commandes validées simultanément obtiennent deux numéros consécutifs distincts.
- Une facture reste téléchargeable après suppression du compte, pour les besoins comptables.

---

### SHOP-04 — Droits d'accès

**Charge :** 1,5 j · **Dépend de :** `DATA-05`, `SHOP-02`

**À faire :**
1. À la validation du paiement, création des `entitlements` correspondants — un par titre, y compris pour l'achat d'une sortie complète (ce qui simplifie tout le reste).
2. Sources distinctes : `purchase` (permanent), `subscription` (tant que l'abonnement est actif), `gift`, `promo`.
3. Quota de téléchargement par droit (5 par défaut), décrémenté à chaque téléchargement effectif, avec possibilité de relèvement par le support.
4. Révocation en cas de remboursement ou de contestation (`PAY-07`).
5. Écran « Ma bibliothèque » listant les droits, avec lecture et téléchargement.

**Critères d'acceptation :**
- Acheter une sortie donne accès à tous ses titres.
- Un remboursement retire l'accès.
- Le quota épuisé bloque le téléchargement mais **pas la lecture en ligne**.

---

### SHOP-05 — Livraison protégée des fichiers

**Charge :** 2 j · **Dépend de :** `SHOP-04`, `SEC-06`

C'est la tâche qui ferme définitivement la faille P0-10 : aujourd'hui tout le catalogue payant est librement téléchargeable par URL directe.

**À faire :**
1. `media.php` devient le **seul** point de service des fichiers audio et des pochettes en pleine résolution.
2. Décision d'accès : extrait → public ; titre gratuit → public ; titre payant → droit d'accès valide requis.
3. Jeton signé à durée courte (5 min) pour l'URL de lecture, lié à la session : une URL partagée ne fonctionne pas chez un tiers.
4. Prise en charge des requêtes partielles (`Range`) pour la lecture en flux et la reprise de téléchargement.
5. Envoi délégué au serveur web (`X-Sendfile` / `mod_xsendfile`) pour ne pas mobiliser PHP sur le transfert.
6. Journalisation de chaque service de fichier : qui, quoi, quand, quelle IP, quel volume.
7. Limitation de débit par compte sur les téléchargements, pour contenir l'extraction massive de catalogue.

**Critères d'acceptation :**
- L'accès direct à un fichier du répertoire de stockage retourne 403 ou 404.
- Une URL de lecture copiée dans un autre navigateur ne fonctionne pas.
- La lecture en flux avec avance rapide fonctionne (requêtes `Range`).
- Le téléchargement d'un titre non acheté est refusé.

---

### SHOP-06 — Portefeuille prépayé

**Charge :** 2 j · **Dépend de :** `PAY-02`, `SHOP-02`

Recommandation forte pour le marché visé : le coût et la friction d'une transaction mobile money de 300 FCFA sont le principal frein à l'achat à l'unité. Le rechargement groupé les élimine. La colonne `users.wallet_balance` existe déjà.

**À faire :**
1. Table `wallet_transactions` : `user_id`, `type` (`topup`, `purchase`, `refund`, `adjustment`), `amount`, `balance_after`, `order_id`, `reference`, `created_at`. **Le solde est toujours recalculable** depuis ce journal — la colonne `wallet_balance` n'est qu'un cache.
2. Rechargement via les passerelles de `LOT 5`, avec paliers suggérés (1 000, 2 500, 5 000, 10 000 FCFA).
3. Paiement par portefeuille : débit atomique sous verrou, refus si solde insuffisant.
4. Historique consultable, avec solde après chaque opération.
5. Politique explicite sur le remboursement du solde non consommé, à inscrire aux conditions générales.

**Critères d'acceptation :**
- Deux achats simultanés sur un solde insuffisant n'en passent qu'un.
- Le solde recalculé depuis le journal correspond toujours à `wallet_balance`.
- Un rechargement échoué ne crédite rien.

---

### SHOP-07 — Remboursements et litiges

**Charge :** 2 j · **Dépend de :** `SHOP-04`, `PAY-01`

`refundPayment()` existe dans `includes/payment.php` et n'est appelé nulle part.

**À faire :**
1. Écran d'administration de remboursement, réservé au rôle `responsable_finance`, avec motif obligatoire et journalisation.
2. Appel de la passerelle quand elle le permet ; sinon marquage « à rembourser manuellement » avec suivi.
3. Écriture d'annulation dans `order_items` — jamais de modification de la ligne d'origine.
4. Révocation du droit d'accès, et **reprise de la commission** sur le solde artiste si le versement n'a pas encore eu lieu.
5. Parcours de réclamation côté utilisateur, avec délai de réponse affiché.
6. Traitement des contestations de carte (`PAY-07`).

**Critères d'acceptation :**
- Un remboursement retire l'accès et ajuste le solde artiste.
- L'historique d'origine reste intact et consultable.
- Aucun remboursement n'est possible sans motif saisi.

---

### SHOP-08 — Téléchargement hors ligne, lisible seulement dans la plateforme

**Charge :** 4 j · **Dépend de :** `SHOP-04`, `SHOP-05`, `SEO-04` (service worker)

**Exigence produit (23/09/2026) :** « télécharger » met le contenu à disposition **hors connexion dans l'application**, comme Netflix. Cela ne produit **jamais** un fichier réutilisable ailleurs. Une application Android suivra et devra se comporter de la même façon : l'API est donc conçue pour servir les deux clients dès le départ.

Sans cette règle, un bouton « télécharger » annulerait tout le travail de `SEC-06` et `SHOP-05` : le premier acheteur redistribuerait le catalogue.

**À faire :**
1. Table `offline_downloads` : `user_id`, `track_id` ou `release_id`, `device_id`, `key_id`, `downloaded_at`, `expires_at`, `last_seen_at`, `revoked_at`, `bytes`. Un compte est limité à **3 appareils** et à un nombre de titres configurable.
2. Points d'entrée `api/offline/*` : demande de mise hors ligne → contrôle du droit d'accès (`SHOP-04`), enregistrement, puis remise d'un jeton de contenu à durée limitée et d'une clé **dérivée pour cet appareil**.
3. Le média part chiffré (AES-GCM par morceaux) depuis `media.php`. Côté navigateur, la clé est une `CryptoKey` **non exportable** (WebCrypto) conservée en IndexedDB : le code de la page peut déchiffrer pour lire, pas récupérer la clé.
4. Web : service worker + Cache/IndexedDB, lecture par `MediaSource` à partir du flux déchiffré. **Aucun lien direct, aucun `Content-Disposition: attachment`** sur un média, à aucun moment.
5. Expiration à 30 jours, renouvelée silencieusement à la première connexion suivante. Révocation immédiate côté serveur (remboursement, litige, fin d'abonnement) : l'appareil efface le contenu à son prochain contact, et au plus tard à l'expiration.
6. Écran « Téléchargements » : liste, espace occupé, suppression, et appareils enregistrés — le même écran que « Appareils connectés » (`SEC-11`), pour n'avoir qu'un endroit où révoquer.
7. Parité Android : mêmes points d'API, stockage privé de l'application, clé dans le **Keystore Android**, lecture par ExoPlayer sur source chiffrée.

**Ce que cela protège, et ce que cela ne protège pas.** Un contenu mis hors ligne dans un navigateur ne peut pas être rendu inviolable : la clé vit dans le navigateur de la personne, et un utilisateur déterminé, outils de développement ouverts, finira par extraire le flux déchiffré. Le dispositif élève fortement le coût — clé non exportable, liée à l'appareil, expirante et révocable — et rend la redistribution de masse impraticable ; il ne remplace pas un DRM industriel (Widevine). Sur Android, le Keystore et le bac à sable de l'application donnent une garantie sensiblement meilleure. **Annoncer cette limite aux artistes est préférable à leur laisser croire à une protection absolue.**

**Critères d'acceptation :**
- Un titre mis hors ligne se lit sans réseau, dans l'application.
- Les fichiers du cache local, copiés ailleurs, sont illisibles.
- Aucune réponse du serveur ne porte `Content-Disposition: attachment` pour un média.
- Révoquer un droit rend le contenu illisible au prochain contact de l'appareil.
- Un quatrième appareil est refusé tant qu'un des trois n'a pas été retiré.
- Les mêmes points d'API servent l'application Android sans modification du serveur.

---

## LOT 7 — Abonnements Premium

> Objectif : un tunnel Premium qui aboutit réellement. Aujourd'hui il insère une ligne `status = 'pending'` et s'arrête : aucun callback, aucun encaissement, **personne ne peut devenir Premium**. Le statut affiché à l'utilisateur est par ailleurs figé en session à la connexion, et `premium_expires_at` n'est jamais contrôlé.

### SUB-01 — Plans d'abonnement administrés

**Charge :** 1 j · **Dépend de :** `DATA-04`

- Table `subscription_plans` : libellé, durée, prix, avantages, actif ou non.
- Retirer les tableaux `$plans` écrits en dur dans `premium.php` (l. 129, 147) et `premium-payment.php` (l. 29, 35).
- Trancher la contradiction 2 000/20 000 contre 2 500/25 000 (`DATA-04`).
- Écran d'administration des plans, avec journalisation des changements de prix.

**Critères d'acceptation :** un changement de prix est visible sans déploiement, et n'affecte pas les abonnements en cours.

---

### SUB-02 — Souscription et renouvellement

**Charge :** 2 j · **Dépend de :** `SUB-01`, `PAY-02`

- Souscription via le tunnel de paiement commun de `LOT 5` — pas de chemin séparé.
- Activation **uniquement** sur callback validé.
- Dates de début et de fin calculées à l'activation, jamais à l'intention de paiement.
- Renouvellement : rappel par e-mail à J-7 et J-1, reconduction manuelle (le prélèvement automatique en mobile money est rarement disponible — à confirmer avec les opérateurs).
- Résiliation en un clic, avec accès maintenu jusqu'à la fin de la période payée. C'est une exigence de loyauté contractuelle.

**Critères d'acceptation :**
- Un paiement échoué n'active pas l'abonnement.
- La résiliation ne coupe pas l'accès immédiatement.
- Aucun double abonnement actif simultané.

---

### SUB-03 — Contrôle du statut et expiration

**Charge :** 1 j · **Dépend de :** `SUB-02`

`$_SESSION['premium_status']` est figé à la connexion (`includes/auth.php` l. 103) et `premium_expires_at` n'est contrôlé nulle part : un abonnement expiré reste actif jusqu'à la déconnexion.

- Résoudre le statut premium **à chaque requête** depuis les abonnements actifs, avec mise en cache courte.
- Tâche planifiée quotidienne passant les abonnements échus en `expired` et retirant les droits d'accès de source `subscription`.
- Affichage clair dans l'espace client : date de fin, statut, historique.

**Critères d'acceptation :**
- Un abonnement expiré perd ses avantages sans attendre une reconnexion.
- Les droits d'accès issus d'un achat ne sont **pas** affectés par l'expiration de l'abonnement.

---

### SUB-04 — Avantages effectifs

**Charge :** 1 j · **Dépend de :** `SUB-03`, `SHOP-05`

Définir et implémenter ce que Premium apporte réellement — aujourd'hui rien n'est différencié dans le code.

Proposition à arbitrer : écoute sans publicité, téléchargements hors ligne, qualité supérieure, accès anticipé aux sorties, absence de plafond de playlists (`FREE_PLAYLIST_LIMIT` est déclaré mais jamais appliqué).

**Critères d'acceptation :** chaque avantage annoncé sur `premium.php` est vérifié par le code ; aucun avantage annoncé n'est fictif.

---

## LOT 8 — Versements aux artistes

> Objectif : que l'argent arrive aux artistes. Aucune table, aucun écran, aucun flux n'existe aujourd'hui — `transactions.type` mentionne `withdrawal`, mais aucun code n'en produit.

### PAYOUT-01 — Calcul du solde artiste

**Charge :** 2 j · **Dépend de :** `SHOP-02`, `DATA-05`

- Vue consolidée par artiste : chiffre d'affaires brut, commission, net, déjà versé, en attente de rétention, disponible.
- **Période de rétention** avant éligibilité (30 jours proposés), pour couvrir remboursements et contestations.
- Ajustements manuels possibles (correction, avance, retenue), avec motif obligatoire et journalisation.
- Le calcul s'appuie sur `order_items.artist_net`, figé à la vente — jamais recalculé depuis la grille tarifaire courante.

**Critères d'acceptation :**
- Le solde disponible exclut les ventes de moins de 30 jours.
- Un remboursement ajuste le solde avant versement.
- La somme des soldes artistes plus la commission plateforme égale le chiffre d'affaires encaissé.

---

### PAYOUT-02 — Demande et validation

**Charge :** 2 j · **Dépend de :** `PAYOUT-01`, `SEC-19`

- Demande par l'artiste au-delà d'un seuil (10 000 FCFA proposés), vers un numéro mobile money **vérifié et à son nom** (collecté en `MOD-07`).
- File de validation côté administration.
- **Séparation des pouvoirs, contrôlée techniquement** : le compte qui approuve ne peut pas être celui qui exécute (`SEC-19`).
- Journalisation complète de chaque étape.

**Critères d'acceptation :**
- Un versement créé et exécuté par le même compte est refusé par le système.
- Une demande sous le seuil est refusée avec le montant manquant indiqué.

---

### PAYOUT-03 — Exécution et relevés

**Charge :** 2 j · **Dépend de :** `PAYOUT-02`, `PAY-01`

- Exécution via la passerelle (versement sortant) ou export bancaire selon ce que les contrats permettent — à confirmer en `PAY-11`.
- Statuts : `draft`, `approved`, `processing`, `paid`, `failed`, `on_hold`. Un échec revient en file avec le motif.
- **Relevé PDF détaillé** par période : ventes ligne à ligne, commission appliquée, ajustements, net versé. C'est le document que l'artiste montrera à son producteur.
- Notification à chaque changement de statut.

**Critères d'acceptation :**
- Un versement en échec est repris sans double paiement.
- Le relevé se réconcilie exactement avec les lignes de commande de la période.

---

### PAYOUT-04 — Simulation des versements en local

**Charge :** 1 j · **Dépend de :** `PAY-03`, `PAYOUT-03`

Les simulateurs de `LOT 5` doivent exposer un point de terminaison de versement sortant (`disbursement`), avec les mêmes scénarios : succès, échec pour numéro invalide, échec pour compte non enregistré, délai long, double notification.

**Critères d'acceptation :** le cycle complet vente → rétention → demande → validation → versement → relevé est exécutable de bout en bout en local.

---

### PAYOUT-05 — Contrat de distribution

**Charge :** 1 j technique (hors rédaction juridique) · **Dépend de :** `MOD-07`

- Acceptation **versionnée et horodatée** du contrat, archivée, opposable.
- Nouvelle acceptation exigée à chaque version, avec préavis sur les changements de commission.
- Le contrat doit inclure la **définition contractuelle de l'écoute comptabilisée** (`STAT-01`) : c'est elle qui fonde la rémunération, elle ne peut pas vivre uniquement dans le code.

**Critères d'acceptation :** aucun artiste ne peut publier sans acceptation enregistrée ; la version acceptée est consultable.

---

## LOT 9 — Mesure des écoutes et anti-fraude

> Objectif : une métrique honnête et défendable. Aujourd'hui `POST /api/stream.php` enregistre une écoute **sans authentification, sans CSRF, sans limite de débit**, avec `Access-Control-Allow-Origin: *`, et accepte `duration`, `country` et `city` **fournis par le client**. Une boucle de trois lignes fabrique un million d'écoutes. C'est le risque le plus lourd pour la crédibilité du projet.

### STAT-01 — Arrêter et publier la définition de l'écoute

**Charge :** 1 j (décision + rédaction) · **Bloque :** `STAT-02` à `STAT-06`, `CHART-*`

Décision de gouvernance, pas technique, à prendre **avant** d'écrire le code. Une définition modifiée après coup invaliderait tout l'historique.

**À arrêter :**

| Question | Proposition |
|---|---|
| Durée minimale | 30 secondes de lecture effective |
| Titres de moins de 30 s | Lecture complète |
| Déduplication | Une écoute par (auditeur, titre) et par fenêtre de 60 minutes |
| Auditeur non connecté | Empreinte anonyme (IP tronquée + agent), jamais d'identifiant persistant sans consentement |
| Écoutes radio | Comptées séparément, non mêlées aux écoutes à la demande |
| Écoutes de l'artiste sur ses propres titres | Exclues du classement |
| Fenêtre d'arrêté | Semaine du lundi 00:00 au dimanche 23:59, heure de N'Djamena |
| Délai de consolidation | 48 h après la fin de période avant publication |

**Livrables :** `docs/methodologie/ecoute-comptabilisee.md`, page publique correspondante (`CHART-05`), et clause au contrat artiste (`PAYOUT-05`).

**Critères d'acceptation :** la définition est écrite, validée par la direction, publiée et contractualisée.

---

### STAT-02 — Sécuriser l'enregistrement des écoutes

**Charge :** 2,5 j · **Dépend de :** `STAT-01`, `SEC-12`, `SEC-13`

**À faire :**
1. **Jeton de session de lecture** : à l'ouverture d'un titre, le serveur émet un jeton HMAC à durée courte, lié à l'utilisateur ou à la session, au titre et à un horodatage. L'enregistrement d'écoute n'est accepté qu'avec un jeton valide, **consommé une seule fois**.
2. Seuil de 30 s appliqué **côté serveur**, à partir de l'horodatage d'émission du jeton — pas à partir d'une durée déclarée par le client.
3. **Durée du titre extraite du fichier** par `ffprobe` ou `getID3` au dépôt, et rendue en lecture seule pour l'artiste. Aujourd'hui elle est saisie à la main (`artist-add-song.php` l. 33) : un artiste déclarant 1 seconde voit chaque impression comptée comme écoute complète.
4. **Géolocalisation résolue côté serveur** depuis l'IP (base GeoIP locale), jamais depuis le champ client.
5. Retirer `Access-Control-Allow-Origin: *`, appliquer la limitation de débit (`SEC-12`), exiger le jeton CSRF ou le jeton de lecture.
6. Déduplication selon la règle de `STAT-01`.

**Critères d'acceptation :**
- Un appel direct à l'endpoint sans jeton de lecture est rejeté.
- Un jeton rejoué est refusé.
- Une écoute déclarée à 5 s n'est pas comptée.
- La durée d'un titre ne peut pas être modifiée par l'artiste après dépôt.

---

### STAT-03 — Filtrage anti-fraude et certification

**Charge :** 3 j · **Dépend de :** `STAT-02`

Architecture en deux niveaux : `streams` reste le journal **brut** ; `streams_certified` porte le fait **certifié**, immuable, et c'est le seul qui alimente classements et rémunération.

**Règles de filtrage :**

| Signal | Traitement |
|---|---|
| Rafale anormale depuis une IP | Quarantaine, revue |
| Ratio écoutes / auditeurs uniques aberrant sur un titre | Quarantaine, alerte |
| Concentration sur une plage d'IP ou un agent unique | Quarantaine |
| Écoutes d'un artiste sur ses propres titres | Exclues |
| Comptes créés en rafale puis écoutant le même titre | Quarantaine, revue du compte |
| Écoutes hors plage horaire plausible en volume massif | Alerte |

Les écoutes en quarantaine ne sont **ni supprimées ni comptées** : elles attendent une décision humaine, tracée.

**Critères d'acceptation :**
- Un script générant 10 000 écoutes depuis une IP est détecté et mis en quarantaine.
- La levée de quarantaine est journalisée avec son auteur et son motif.
- Le volume certifié est toujours inférieur ou égal au volume brut, et l'écart est mesurable.

---

### STAT-04 — Tableau de bord anti-fraude

**Charge :** 2 j · **Dépend de :** `STAT-03`, `SEC-19`

- Liste des anomalies détectées, par gravité, avec le détail des signaux.
- Actions : valider, rejeter, mettre l'artiste ou le compte sous surveillance.
- Indicateurs de santé : part d'écoutes en quarantaine, évolution, titres les plus concernés.
- Alerte automatique au-delà d'un seuil.

**Critères d'acceptation :** un modérateur peut instruire une anomalie et tracer sa décision sans accès direct à la base.

---

### STAT-05 — Agrégats journaliers

**Charge :** 2 j · **Dépend de :** `STAT-03`

Table `daily_rollups`, portant les dimensions du baromètre : **date, titre, sortie, artiste, genre, catégorie, région, source, type d'auditeur**, avec écoutes certifiées, auditeurs uniques, durée totale, ventes, chiffre d'affaires.

- Tâche planifiée nocturne, idempotente : relancer un jour déjà traité produit exactement le même résultat.
- Reconstruction complète possible par commande, sur n'importe quelle plage de dates.
- Les dashboards lisent les rollups, **jamais** la table `streams` brute.

**Critères d'acceptation :**
- Relancer l'agrégation d'un jour ne duplique rien.
- Une reconstruction sur 90 jours aboutit et redonne les mêmes chiffres.
- Une requête de dashboard sur 12 mois s'exécute en moins de 200 ms.

---

### STAT-06 — Retirer les triggers et reconstruire les compteurs

**Charge :** 2 j · **Dépend de :** `STAT-05`, `DATA-01`

> **Tâche révisée le 21/09/2026.** Sa formulation initiale reposait sur un constat erroné de l'audit (« aucune écriture sur les compteurs »). La vérification faite lors de l'import local a montré que `database/tchadok.sql` installe **trois triggers** qui maintiennent une partie de ces colonnes. Le travail à faire change de nature : il ne s'agit pas d'ajouter une mise à jour manquante, mais de **remplacer une mise à jour non filtrée** par une chaîne contrôlable.

**État réel :**

| Colonne | Alimentée par |
|---|---|
| `tracks.total_streams`, `artists.total_streams`, `albums.total_streams` | trigger `update_stream_stats`, `AFTER INSERT ON streams` |
| `tracks.total_sales`, `albums.total_sales`, `artists.total_sales` | trigger `update_purchase_stats`, `AFTER INSERT ON purchases` |
| `albums.total_tracks` | trigger `update_album_tracks_count`, `AFTER INSERT ON tracks` |
| `tracks.total_downloads`, `albums.total_duration`, `artists.total_earnings` | **rien** — toujours à 0 |

**Défauts à corriger :**
1. Les triggers incrémentent depuis la **donnée brute**, sans seuil de durée, sans déduplication, sans filtre anti-fraude. Le compteur public est donc directement piloté par `api/stream.php`, endpoint ouvert (P0-11).
2. Ils ne se déclenchent qu'à l'`INSERT` : un achat `pending` incrémente les ventes, un paiement échoué ou remboursé ne les décrémente jamais, et retirer un titre d'un album laisse `total_tracks` faux.
3. `artists.total_sales` accumule un **montant en FCFA** tandis que `tracks.total_sales` et `albums.total_sales` comptent des **unités**. Toute agrégation croisant ces colonnes est fausse.
4. La logique vit dans la base : invisible depuis le code, hors des tests, hors de la revue, et **non reconstructible** après une correction de données.

**À faire :**
1. **Retirer les trois triggers** par migration (`DATA-01`), avec la section `-- DOWN` correspondante.
2. Reprendre leur logique, explicitement, dans le recalcul depuis les rollups (`STAT-05`) : incrémental par tâche planifiée.
3. Alimenter les trois colonnes orphelines (`total_downloads`, `total_duration`, `total_earnings`).
4. Corriger la sémantique de `artists.total_sales` : soit la renommer en `total_revenue`, soit la faire compter des unités et déplacer le montant dans `total_earnings`. Migration de données à prévoir.
5. Recalculer `albums.total_tracks` et `total_duration` à l'ajout **et au retrait** d'un titre.
6. Commande de **reconstruction intégrale** (`scripts/rebuild-counters.php`), exécutable à tout moment sur n'importe quelle plage.
7. Contrôle de cohérence périodique comparant compteurs et rollups, avec alerte en cas de dérive.

**Critères d'acceptation :**
- `SELECT COUNT(*) FROM information_schema.TRIGGERS` sur la base du projet retourne 0.
- Une écoute non certifiée (moins de 30 s, ou en quarantaine) **n'incrémente aucun compteur public**.
- Purger 1 000 écoutes frauduleuses et relancer le recalcul fait baisser les compteurs en conséquence — comportement impossible avec les triggers actuels.
- Un remboursement décrémente les ventes.
- La reconstruction intégrale donne exactement les mêmes valeurs que l'incrémental.

---

## LOT 10 — Agrégats, classements et baromètre

> Objectif : construire l'actif différenciant du projet. Les tables `charts`, `site_stats` et `reports` existent dans le schéma et ne sont **jamais alimentées** ; aucune page de classement n'existe. Le « baromètre national » annoncé n'a aujourd'hui aucune réalité.

### CHART-01 — Arrêtés de classement

**Charge :** 3 j · **Dépend de :** `STAT-05`

- Tâche planifiée hebdomadaire (lundi, après le délai de consolidation de 48 h) alimentant la table `charts`.
- Un classement arrêté est **immuable**. C'est ce qui le rend citable par la presse et les institutions ; un classement qui bouge en continu n'est référençable par personne.
- Classements séparés : titres, sorties (par format — un single ne concourt pas contre un album), artistes, **et ventes distinctes des écoutes** (ce sont deux réalités différentes, les confondre induit en erreur).
- Données portées : rang, rang précédent, évolution, semaines de présence, meilleur rang atteint, statut « nouvelle entrée ».
- Périodicités : hebdomadaire, mensuelle, annuelle.

**Critères d'acceptation :**
- Un classement arrêté ne change plus, même si des écoutes en quarantaine sont levées ensuite (elles comptent pour la période suivante).
- Les archives sont consultables par édition, avec permalien.

---

### CHART-02 — Vues analytiques du baromètre

**Charge :** 3 j · **Dépend de :** `CHART-01`, `TAXO-02`

- Par **genre** : part d'audience, croissance, nombre d'artistes actifs, revenu moyen par titre.
- Par **catégorie** : agrégation selon la hiérarchie de `TAXO-01`.
- Par **région** : écoutes et ventes par province, avec indice de pénétration rapporté à la population.
- Par **format** : part des écoutes et des ventes en single / maxi / EP / album — donnée stratégique aujourd'hui inexistante au Tchad.
- **Diaspora** : classement distinct pour les écoutes hors Tchad.
- **Indice de découverte** : part des écoutes allant à des artistes de moins de 12 mois.

**Critères d'acceptation :** chaque vue se recoupe avec les totaux nationaux ; aucun écart inexpliqué.

---

### CHART-03 — Baromètre public

**Charge :** 4 j · **Dépend de :** `CHART-01`, `CHART-02`

- Page publique `/barometre`, avec l'édition en cours et les archives.
- Top 50 titres, Top 20 artistes, Top 20 sorties par format, classement des ventes.
- Navigation par genre, catégorie, région, période.
- Design lisible sur mobile en priorité, en tenant compte du coût des données (`UX-04`).
- Données structurées JSON-LD pour le référencement (`SEO-01`).

**Critères d'acceptation :**
- Un classement est partageable par URL stable et s'affiche correctement en aperçu WhatsApp et Facebook.
- La page se charge en moins de 2,5 s sur profil 3G rapide.

---

### CHART-04 — Exports et kit presse

**Charge :** 2 j · **Dépend de :** `CHART-03`

- Export CSV et PDF de chaque édition, avec mention de la méthodologie et de la date d'arrêté.
- **Kit presse automatique** : visuel du Top 10 prêt à publier, communiqué généré, chiffres clés. C'est ce qui rend la reprise médiatique gratuite et systématique — investissement faible, effet durable sur la notoriété.
- API publique en lecture seule, avec clés, quotas et attribution obligatoire : mieux vaut être la source citée que la source copiée.

**Critères d'acceptation :** une édition génère son kit sans intervention manuelle.

---

### CHART-05 — Page de méthodologie

**Charge :** 1 j · **Dépend de :** `STAT-01`

Page publique expliquant : ce qu'est une écoute comptée, la fenêtre d'observation, le traitement anti-fraude, la date d'arrêté, le périmètre couvert et les limites connues.

**C'est la condition d'être « la référence » plutôt qu'« un chiffre parmi d'autres ».** Un classement dont la méthode n'est pas publique n'est pas opposable, et ne sera repris ni par la presse sérieuse ni par une institution.

**Critères d'acceptation :** la page est publiée, datée, versionnée, et liée depuis chaque classement.

---

### CHART-06 — Certifications Tchadok

**Charge :** 2 j · **Dépend de :** `CHART-01`

Paliers officiels (Or, Platine, Diamant) sur les écoutes certifiées et sur les ventes, avec seuils publiés, badge sur la fiche du titre et de l'artiste, attestation PDF et annonce automatique.

Coût faible, valeur symbolique forte : cette reconnaissance n'existe pas aujourd'hui au Tchad, et elle crée un motif de fierté et de communication pour les artistes, donc un moteur d'acquisition.

**Critères d'acceptation :** un titre franchissant un seuil obtient sa certification automatiquement, avec notification à l'artiste.

---

## LOT 11 — Genres et catégories

> Objectif : une taxonomie gouvernée. Aujourd'hui la seule écriture sur la table `genres` de tout le projet se trouve dans `upload.php` (l. 82) : **ce sont les artistes qui créent les genres**, en saisie libre, immédiatement actifs, sans dédoublonnage. Il n'existe aucune interface d'administration (0 occurrence de `UPDATE genres` ou `DELETE FROM genres`), et la table n'est pas alimentée par le dump.

### TAXO-01 — Hiérarchie et référentiel initial

**Charge :** 1,5 j · **Dépend de :** `DATA-01`

**À faire :**
1. Ajouter à `genres` : `parent_id` (hiérarchie catégorie → genre → sous-genre), `slug` unique et stable, `sort_order`, `status` (`active`, `merged`, `archived`), `merged_into`.
2. Charger le référentiel initial (proposition au §6.3 de l'audit, à valider par un comité éditorial — idéalement avec des professionnels du secteur et un ethnomusicologue, car c'est un acte patrimonial autant que technique) :

| Catégorie | Genres |
|---|---|
| Patrimoine & traditionnel | Saï, Kanembou, Sara, Toubou, Ouaddaïen, Hadjarai, Griot / chant de louange, Musique de cour |
| Musiques urbaines | Rap tchadien, Hip-hop, Trap, Afrobeats, Afropop, Coupé-décalé, Ndombolo |
| Variété & world | Variété tchadienne, Soukous, Reggae, Zouk, World fusion |
| Spirituel | Gospel, Chant chrétien, Madh / chant soufi, Nasheed |
| Instrumental & jazz | Jazz sahélien, Instrumental, Kora / luth, Percussions |
| Jeunesse & éducatif | Comptines, Contes musicaux, Chansons pédagogiques |

3. **Règle structurante : un genre n'est jamais supprimé.** Il est archivé ou fusionné, sinon l'historique statistique devient incohérent.

**Critères d'acceptation :** le référentiel est chargé, hiérarchisé, et chaque genre porte un `slug` stable.

---

### TAXO-02 — Unifier la taxonomie

**Charge :** 2 j · **Dépend de :** `TAXO-01`

Deux représentations concurrentes coexistent : la table `genres` (avec `genre_id` en clé étrangère sur `tracks` et `albums`, utilisée par les pages publiques) et la colonne `artists.genres`, texte libre multi-valeurs, utilisée par l'écran d'analyse de l'administration (`GROUP BY a.genres`, qui produit un groupe distinct pour « Saï, Afrobeat » et pour « Afrobeat, Saï »). **Aucune statistique par genre n'est exploitable dans cet état.**

**À faire :**
1. Créer `artist_genres` (`artist_id`, `genre_id`, `is_primary`).
2. Migrer `artists.genres` par correspondance assistée : produire la liste des valeurs distinctes existantes, faire trancher chaque correspondance par l'équipe éditoriale, puis appliquer. Ne pas automatiser cette étape — les erreurs de rattachement fausseraient le baromètre.
3. Retirer la colonne `artists.genres`.
4. Rendre `tracks.genre_id` et `releases.genre_id` **obligatoires** à la soumission.
5. Adapter toutes les requêtes utilisant `artists.genres`.

**Critères d'acceptation :**
- La colonne `artists.genres` n'existe plus.
- Aucun artiste actif n'est sans genre principal.
- Les statistiques par genre se recoupent entre l'administration et les pages publiques.

---

### TAXO-03 — Administration de la taxonomie

**Charge :** 1,5 j · **Dépend de :** `TAXO-02`, `SEC-19`

C'est la demande explicite « l'admin doit pouvoir ajouter des genres et faire d'autres ajustements ».

**Fonctions :** création, renommage (sans casser les URL, grâce au `slug` stable), **fusion** de deux genres avec réaffectation des contenus, archivage, réordonnancement, couleur et icône, description éditoriale, rattachement à une catégorie.

**Également :** retirer la création libre de `upload.php` (l. 81-85), et la remplacer par une **proposition** de genre par l'artiste, placée en file de validation sans activation immédiate.

**Critères d'acceptation :**
- La fusion de deux genres réaffecte tous les contenus et conserve les anciens `slug` en redirection.
- Un artiste ne peut plus créer de genre actif.
- Chaque modification de la taxonomie est journalisée.

---

## LOT 12 — Modération et onboarding artiste

> Objectif : la condition de crédibilité. Une plateforme « de référence » ne peut pas publier sans revue, ni payer des inconnus pour des œuvres dont personne n'a vérifié les droits. Aujourd'hui il n'existe **aucun écran de modération** (zéro `UPDATE ... SET status = 'approved'` dans tout le projet), et l'inscription artiste se fait par une simple case à cocher.

### MOD-01 — Machine à états du contenu

**Charge :** 1,5 j · **Dépend de :** `DATA-03`

Transitions autorisées, et aucune autre :

```
draft ──(artiste soumet)──► pending ──(modérateur)──┬──► approved ──(admin)──► offline
                               ▲                    └──► rejected ──(artiste corrige)──► pending
                               └────────────────────────────────────┘
```

- L'artiste ne peut **jamais** écrire `approved`.
- Le catalogue public ne lit **que** `approved` (`SEC-08`).
- Chaque transition est journalisée avec son auteur, sa date et son motif.
- Motif de rejet **obligatoire**, transmis à l'artiste.

**Critères d'acceptation :** une requête tentant une transition non autorisée est refusée côté serveur.

---

### MOD-02 — File de modération

**Charge :** 2,5 j · **Dépend de :** `MOD-01`, `SEC-19`

- Écran listant les contenus en attente, avec ancienneté, artiste, format, genre.
- Fiche de revue : lecture de l'extrait et du master, métadonnées, pochette, droits déclarés, historique de l'artiste.
- **Grille de revue formalisée** : qualité audio, exactitude des métadonnées, droits déclarés, contenu explicite correctement signalé, pochette conforme.
- Actions : approuver, rejeter avec motif, demander une correction.
- Indicateurs : volume en attente, délai moyen de traitement, taux de rejet par motif.
- Affectation à un modérateur, pour éviter les doubles revues.

**Critères d'acceptation :**
- Un titre soumis apparaît en file dans la minute.
- Un rejet notifie l'artiste avec le motif.
- Le délai de traitement est mesurable.

---

### MOD-03 — Signalements

**Charge :** 2 j · **Dépend de :** `MOD-02`

La table `reports` existe dans le schéma et n'est **ni lue ni écrite** nulle part.

- Signalement par un utilisateur : contenu inapproprié, atteinte au droit d'auteur, spam, faux profil.
- File de traitement avec priorisation ; les revendications de droits d'auteur passent en tête.
- **Pour le droit d'auteur : retrait provisoire immédiat**, notification à l'artiste, délai de réponse contradictoire, décision motivée.
- Procédure de contre-notification documentée.
- Journal des décisions, consultable.

**Critères d'acceptation :**
- Une revendication de droits retire le contenu du catalogue public en moins d'une minute.
- Chaque décision est motivée et traçable.

---

### MOD-04 — Contrôles automatiques au dépôt

**Charge :** 2,5 j · **Dépend de :** `SEC-17`, `PREP-03`

À exécuter avant même la revue humaine, pour que les modérateurs ne traitent que des dossiers valides.

| Contrôle | Outil | Bloquant |
|---|---|---|
| Type de fichier réel | `finfo` + lecture d'en-tête de conteneur | Oui |
| Durée, débit, canaux, fréquence | `ffprobe` / `getID3` | Oui — la durée devient la source de vérité (`STAT-02`) |
| Qualité minimale | ≥ 128 kbit/s, ≥ 44,1 kHz | Oui |
| Fichier silencieux ou corrompu | Analyse de forme d'onde | Oui |
| Niveau sonore | Normalisation LUFS (cible −14) | Non — signalé |
| Doublon dans le catalogue | Empreinte acoustique (Chromaprint) | Signalé au modérateur |
| Métadonnées obligatoires | Titre, genre, date, langue, crédits | Oui |
| Pochette | ≥ 1400×1400, ré-encodée, sans texte promotionnel | Oui |

**Également :** génération automatique de l'extrait de 30 s (`SEC-06`) et des variantes d'image (`UX-05`).

**Critères d'acceptation :**
- Un fichier de 64 kbit/s est refusé avec un message explicite.
- Un doublon exact est signalé au modérateur avec le titre concerné.
- La durée extraite correspond à la durée réelle du fichier.

---

### MOD-05 — Unifier le parcours de publication

**Charge :** 2 j · **Dépend de :** `DATA-03`, `MOD-04`

Trois parcours divergents coexistent : `upload.php` (38,1 Ko), `artist-add-song.php`, `artist-add-album.php`. Ils ne valident pas les mêmes choses et ne créent pas les mêmes données — `upload.php` crée même des albums en `draft` publiés publiquement.

**À faire :**
1. Un seul parcours, en étapes : choix du format de sortie → dépôt des fichiers → métadonnées → prix → récapitulatif → soumission.
2. Application des règles de format de `DATA-03` côté serveur.
3. Prix validé contre la grille tarifaire (`DATA-04`), avec le prix suggéré affiché.
4. Brouillon sauvegardé automatiquement, reprise possible.
5. Retirer les deux parcours redondants.

**Critères d'acceptation :**
- Un seul écran permet de publier.
- Toutes les règles de format et de prix sont appliquées côté serveur.
- Un brouillon interrompu est repris sans perte.

---

### MOD-06 — Onboarding artiste vérifié

**Charge :** 3 j · **Dépend de :** `SEC-19`, `MOD-02`

Aujourd'hui le champ `user_type` du formulaire d'inscription suffit à créer un artiste actif, sans aucune vérification, avec droit de publier et de vendre. Pour une plateforme qui encaisse pour le compte de tiers et publie des œuvres protégées, c'est un risque juridique direct.

**Parcours par étapes :**

| Étape | Contenu | Bloquant pour |
|---|---|---|
| 1. Compte | E-mail vérifié, téléphone vérifié par SMS | Tout |
| 2. Identité | Pièce d'identité, selfie de vérification | Publication |
| 3. Profil artiste | Nom de scène, biographie, genre principal, photo, réseaux | Publication |
| 4. Droits | Déclaration de titularité, attestation d'absence de cession exclusive concurrente, contrat accepté et horodaté (`PAYOUT-05`) | Publication |
| 5. Encaissement | Numéro mobile money **au nom de l'artiste vérifié**, ou coordonnées bancaires | Versement |
| 6. Fiscal | Régime, identifiant si applicable | Versement au-delà d'un seuil |
| 7. Validation | Revue humaine du dossier, décision motivée | Publication |

**Trois niveaux de compte :** *Découverte* (publication limitée, pas de vente), *Vérifié* (vente activée, badge), *Partenaire* (mise en avant éditoriale, commission négociée). Cela donne une progression lisible et un levier de qualité.

**Sécurité des pièces :** stockage hors racine web, chiffrées au repos, accès restreint et journalisé, durée de conservation définie.

**Critères d'acceptation :**
- Un compte artiste non validé ne peut pas publier.
- Un artiste sans coordonnées vérifiées ne peut pas demander de versement.
- Les pièces d'identité ne sont accessibles qu'aux rôles habilités, et chaque consultation est journalisée.

---

### MOD-07 — Vérification d'e-mail effective

**Charge :** 1 j · **Dépend de :** `QA-02`

`verification_token` existe dans le schéma, `email_verified` est mis à 0 à l'inscription, **aucun e-mail n'est envoyé** et le drapeau n'est jamais contrôlé. La colonne est décorative.

- Envoi d'un lien de vérification à durée limitée à l'inscription.
- Renvoi possible, avec limitation de débit.
- Vérification exigée avant achat, publication ou commentaire.
- Transport SMTP authentifié (`QA-02`) — `sendEmail()` utilise aujourd'hui `mail()` alors que la configuration SMTP existe et n'est pas branchée.

**Critères d'acceptation :** un compte non vérifié ne peut ni acheter ni publier ; le lien expire et peut être renvoyé.

---

## LOT 13 — Dashboards

> Objectif : des tableaux de bord réellement exploitables. La facture visuelle actuelle est bonne — grille régulière, hiérarchie typographique lisible, système d'ombres cohérent — et doit être conservée. Ce qui manque est la **substance** (tous les chiffres sont à zéro) et l'**architecture d'information** (aucune navigation, aucune profondeur, aucun filtre, aucun export, et **zéro graphique** dans le dashboard admin).

### DASH-01 — Coquille unique et navigation

**Charge :** 4 j · **Dépend de :** `SEC-19`, `UX-02`

Aujourd'hui l'administration dispose d'une coquille persistante (`includes/admin-shell-header.php`), l'artiste et le fan n'en ont aucune : quitter le dashboard pour `wallet.php` ou `edit-profile.php` fait perdre toute navigation contextuelle. `history.php` conserve même la navigation publique, produisant une rupture visuelle au sein d'un même parcours.

**À faire :**
1. Une coquille unique, trois menus selon le rôle (arborescence détaillée au §7.4 de l'audit). Composant unique : coût de maintenance divisé par trois, cohérence garantie.
2. **Une URL par vue** (`/dashboard/artist/revenus/versements`), donc partageable, marquable, traçable.
3. Fil d'Ariane, titre de page explicite, barre d'actions contextuelle.
4. **Bandeau d'état global** : « 12 titres en attente de modération », « 3 versements à valider ». C'est ce qui transforme un tableau de bord en outil de travail.
5. Remplacer la « navigation secondaire » actuelle, qui n'est qu'une table des matières par ancres vers des sections de la même page.

**Critères d'acceptation :**
- Les trois rôles disposent d'une navigation persistante équivalente.
- Chaque vue a une URL propre qui la restaure intégralement.
- Aucune page d'espace connecté ne revient à la navigation publique.

---

### DASH-02 — Barre de contrôle analytique

**Charge :** 2 j · **Dépend de :** `STAT-05`

Commune à toutes les vues statistiques :

- Périodes : `7 j`, `30 j`, `Cette semaine`, `Ce mois`, `Ce trimestre`, `Cette année`, `Personnalisé`.
- Comparaison : `vs période précédente`, `vs même période N-1`.
- **Granularité : Jour / Semaine / Mois** — la demande explicite « par semaines, mois » se traite ici.
- Filtres : sortie, format, genre, région.
- **L'état de la barre se reflète dans l'URL.**

**Critères d'acceptation :** changer de période met à jour tous les indicateurs et graphiques de la vue ; l'URL obtenue restitue exactement le même écran.

---

### DASH-03 — Dashboard artiste : aperçu et indicateurs

**Charge :** 3 j · **Dépend de :** `DASH-02`, `STAT-06`

Six indicateurs, **chacun avec évolution et micro-courbe** — aujourd'hui les cartes affichent une valeur brute sans comparaison, ce qui ne permet pas de savoir si la situation s'améliore.

| Indicateur | Définition | Comparaison |
|---|---|---|
| Écoutes certifiées | ≥ 30 s, dédupliquées | vs période précédente, en % |
| Auditeurs uniques | Comptes et empreintes distincts | vs période précédente |
| Ventes | Articles vendus | vs période précédente |
| Chiffre d'affaires brut | Avant commission | vs période précédente |
| Revenu net | Après commission et frais | Cumul disponible au versement |
| Taux de conversion | Ventes / auditeurs uniques | vs médiane de son genre |

**États vides travaillés :** pour un artiste sans écoute, afficher un parcours (« Publiez votre première sortie », « Complétez votre profil », « Partagez votre lien artiste ») plutôt qu'un graphique plat. Le dashboard doit être utile dès le premier jour.

**Critères d'acceptation :** aucune carte n'affiche un nombre sans unité ni comparaison ; un zéro est toujours distinguable d'une absence de données.

---

### DASH-04 — Dashboard artiste : graphiques

**Charge :** 4 j · **Dépend de :** `DASH-03`

1. **Évolution des écoutes** — courbe, granularité au choix, série de comparaison en pointillé.
2. **Montants encaissés par semaine / par mois** — histogramme empilé par format (single / maxi / EP / album) et courbe de cumul sur un second axe. C'est la réponse directe à la demande ; la granularité choisie pilote l'axe.
3. **Répartition des revenus par format** — barres horizontales (pas de camembert : illisible au-delà de trois parts et sur mobile).
4. **Ventes par titre** — tableau trié : titre, format, écoutes, ventes, CA net, conversion, tendance 7 j.
5. **Audience géographique** — barres horizontales par province, plus une ligne « diaspora ».
6. **Sources d'écoute** — recherche, playlist, radio, profil artiste, partage externe. C'est l'indicateur sur lequel l'artiste peut agir.

**Technique :** auto-héberger Chart.js et ne le charger que sur les vues qui en ont besoin (`UX-03`).

**Critères d'acceptation :** basculer de `Semaine` à `Mois` change l'axe et les agrégats de tous les graphiques de façon cohérente.

---

### DASH-05 — Dashboard artiste : revenus et catalogue

**Charge :** 3 j · **Dépend de :** `PAYOUT-01`, `MOD-05`

- **Revenus** : solde disponible / en rétention / déjà versé, bouton de demande de versement, tableau des versements avec relevé téléchargeable, détail ligne à ligne des ventes.
- **Catalogue** : sorties et titres, statut de modération visible, actions de correction, accès au parcours de publication.
- **Export CSV** des ventes et des écoutes sur la période sélectionnée — aujourd'hui aucun export n'existe, donc aucun reporting possible vers un producteur ou un partenaire.

**Critères d'acceptation :** un artiste peut suivre une vente depuis l'achat jusqu'au versement, et exporter le détail.

---

### DASH-06 — Dashboard admin : aperçu

**Charge :** 2 j · **Dépend de :** `DASH-02`, `STAT-06`

- **Bandeau d'actions requises** : modération en attente, dossiers artistes, versements à valider, signalements, anomalies anti-fraude.
- Huit indicateurs avec évolution : utilisateurs actifs, nouveaux comptes, artistes vérifiés, titres publiés, écoutes certifiées, ventes, CA plateforme (commissions), taux de conversion global.
- **Supprimer le `try` global** qui enveloppe 14 requêtes et dont le `catch` remet tous les compteurs à zéro : aujourd'hui une seule table manquante fait afficher un dashboard entièrement à zéro, sans aucun signal d'erreur pour l'exploitant.

**Critères d'acceptation :** une erreur de requête affiche un état d'erreur sur la carte concernée, pas un zéro silencieux sur toutes.

---

### DASH-07 — Dashboard admin : baromètre interne

**Charge :** 4 j · **Dépend de :** `CHART-02`

Vues opérationnelles avant publication : classements des vues et des ventes (distincts), statistiques par genre et par catégorie, par région, par format, nouveaux entrants, indice de découverte. Avec filtres, comparaisons et export CSV/PDF.

**Critères d'acceptation :** l'administration voit l'édition en préparation avant sa publication, et peut la comparer aux précédentes.

---

### DASH-08 — Dashboard admin : finance

**Charge :** 3 j · **Dépend de :** `PAY-10`, `PAYOUT-02`

Transactions, écarts de réconciliation, versements à valider et à exécuter, remboursements, rapports par période, exports comptables.

**Critères d'acceptation :** un responsable finance peut clore un mois : encaissements, commissions, versements, écarts résolus.

---

### DASH-09 — Dashboard admin : configuration

**Charge :** 2 j · **Dépend de :** `TAXO-03`, `DATA-04`, `SEC-19`

Genres et catégories, grille tarifaire et commissions, mise en avant éditoriale, rôles et permissions, journal d'audit consultable et filtrable.

**Critères d'acceptation :** les ajustements courants (tarif, genre, mise en avant) se font sans déploiement et sont journalisés.

---

### DASH-10 — Dashboard fan

**Charge :** 1 j · **Dépend de :** `DASH-01`, `SHOP-04`

Reprise de lecture, recommandations, nouveautés des artistes suivis, bibliothèque téléchargeable, factures, et gestion d'abonnement claire (date de renouvellement, historique, **résiliation en un clic**).

**Critères d'acceptation :** un utilisateur retrouve ses achats, ses factures et l'état de son abonnement sans passer par le support.

---

## LOT 14 — Design, accessibilité, performance

### UX-01 — Système de jetons et thème clair

**Charge :** 2 j

Les jetons de couleur Tailwind portent les **valeurs du thème sombre**, et le thème clair est obtenu en surchargeant les classes utilitaires **par leur nom** dans `tailwind-base.css` (`.theme-light .bg-surface\/60 { … }`). Chaque nouvelle variante d'opacité employée dans un gabarit exige une surcharge écrite à la main. Le fichier en contient déjà une trentaine et ne couvre pas `bg-surface/75`, pourtant utilisé partout dans les dashboards : **le thème clair des dashboards est déjà cassé aujourd'hui**, et la dette s'aggrave à chaque écran.

**À faire :** basculer les jetons sur des variables CSS en triplets RGB (`rgb(var(--c-surface) / <alpha-value>)`), avec un bloc `:root` et un bloc `:root.theme-light`. `bg-surface/75` fonctionne alors dans les deux thèmes sans aucune surcharge, et le nombre de règles `.theme-light` tombe de trente à zéro.

**Également :** trancher la palette. Le logo utilise `#0066CC` et `#FFD700` (ancienne palette, aussi présente dans `THEME_COLORS` de `config/constants.php`, jamais lue par le front) tandis que l'interface utilise `#2F6DE0` et `#FFC107` : **la marque et le produit n'ont pas la même couleur**. Ajouter des jetons sémantiques (`success`, `warning`, `danger`, `info`) et interdire l'usage direct des couleurs Tailwind par défaut dans les gabarits.

**Critères d'acceptation :**
- Le thème clair est correct sur les trois dashboards et les 10 pages principales.
- Aucune règle ne surcharge une classe utilitaire Tailwind par son nom.
- Une seule palette est utilisée, logo compris.

---

### UX-02 — Bibliothèque de composants

**Charge :** 3 j · **Dépend de :** `UX-01` · **Bloque :** `DASH-01`

Chaque page réimplémente les mêmes objets en classes utilitaires copiées, avec des variantes de padding et d'arrondi.

**Composants à extraire en partiels PHP :** `stat-card`, `chart-card`, `data-table` (tri, pagination, état vide, état de chargement), `badge`, `empty-state`, `alert`, `modal`, `form-field`, `pagination`, `breadcrumb`, `page-header`, `tabs`, `dropdown`, `avatar`, `media-tile`, `price`, `trend-indicator`.

**Également :** `displayFlashMessages()` et `generatePagination()` de `includes/functions.php` génèrent du balisage **Bootstrap** (`alert alert-dismissible fade show`, `btn-close`, `data-bs-dismiss`, `pagination page-item`) alors que **Bootstrap n'est pas chargé** : messages flash et pagination s'affichent sans style sur tout le site. À réécrire en composants maison.

**Critères d'acceptation :** les composants portent les jetons, les gabarits ne portent plus de couleurs, et les messages flash sont correctement stylés.

---

### UX-03 — Compiler Tailwind et auto-héberger les ressources

**Charge :** 2 j · **Bloque :** la CSP bloquante de `SEC-18`

`includes/header-tailwind.php` charge `https://cdn.tailwindcss.com` sur les 47 pages. Ce script est documenté par Tailwind comme réservé au prototypage : ~400 Ko de JavaScript, **compilation du CSS dans le navigateur à chaque chargement**, flash de contenu non stylé sur connexion lente, et exigence de `unsafe-eval` qui **empêche toute CSP correcte**.

Le contexte d'usage rend ce choix particulièrement coûteux : au Tchad, l'accès se fait très majoritairement en 3G/4G mobile, avec un coût de données réel pour l'utilisateur. Chaque kilo-octet superflu est facturé au public visé.

**À faire :**
1. Compiler Tailwind localement avec purge sur les gabarits `.php` — résultat attendu : 15 à 30 Ko de CSS, servi avec empreinte de version et cache long.
2. Auto-héberger les **fontes** en WOFF2, limitées aux graisses réellement utilisées (2 par famille suffisent), avec `font-display: swap` et `preload` sur la police de titre.
3. Remplacer Font Awesome (~225 Ko) par un **sprite SVG** limité aux icônes employées — le projet en utilise quelques dizaines.
4. Auto-héberger Chart.js et ne le charger que sur les vues qui affichent un graphique ; TinyMCE uniquement sur `admin-blog.php`.
5. Poser `integrity` et `crossorigin` sur tout ce qui resterait externe.

**Gain attendu : 1 Mo à 1,3 Mo par visite initiale**, plus le déblocage de la CSP.

**Critères d'acceptation :**
- Aucune origine tierce bloquante au chargement.
- CSS total ≤ 40 Ko, JavaScript total ≤ 120 Ko hors page éditeur.
- La CSP bloquante peut être activée sans casser le site.

---

### UX-04 — Budget de performance et optimisations serveur

**Charge :** 3 j · **Dépend de :** `UX-03`, `STAT-05`

**Budget à inscrire comme contrainte de projet**, mesuré sur profil Moto G Power / 3G rapide (pas sur poste de développement) :

| Métrique | Cible |
|---|---|
| Poids page d'accueil, première visite | ≤ 400 Ko |
| CSS total | ≤ 40 Ko |
| JavaScript total | ≤ 120 Ko |
| Largest Contentful Paint | ≤ 2,5 s |
| Interaction to Next Paint | ≤ 200 ms |
| Cumulative Layout Shift | ≤ 0,1 |
| Requêtes SQL par page | ≤ 12 |
| Origines tierces | ≤ 1 |

**Corrections serveur :** supprimer les requêtes N+1 (6 requêtes pour le revenu mensuel artiste, 18 + 24 dans l'écran d'analyse) au profit d'agrégations uniques ; remplacer `SELECT *` par des sélections explicites ; retirer les appels `tableExists()` à chaud (3 requêtes `information_schema` par affichage de dashboard) ; mémoïser `getCurrentUser()` ; implémenter réellement le cache (`CACHE_ENABLED` et `CACHE_LIFETIME` sont déclarés, sans aucune implémentation) ; paginer par curseur.

**Critères d'acceptation :** le budget est respecté sur les 10 pages principales, mesuré et consigné.

---

### UX-05 — Médias et images

**Charge :** 2 j · **Dépend de :** `MOD-04`

Aucun `srcset`, aucun WebP/AVIF, aucun `loading="lazy"` généralisé.

- Générer les variantes à l'upload (miniature, carte, pleine résolution) en WebP avec repli JPEG.
- `srcset` et `sizes` sur toutes les images de catalogue, `loading="lazy"` hors première zone visible, dimensions explicites pour éviter les décalages de mise en page.
- Extrait audio 30 s à faible débit pour la pré-écoute.

**Critères d'acceptation :** aucune image n'est servie en pleine résolution dans une vignette ; le CLS reste sous 0,1.

---

### UX-06 — Accessibilité WCAG 2.2 AA

**Charge :** 3 j · **Dépend de :** `UX-02`

**Défauts mesurés :** 166 champs de formulaire sans association programmatique à un libellé ; 4 images sur 11 sans `alt` ; aucun `:focus-visible` dans les CSS en production ; aucune gestion de `prefers-reduced-motion` ; aucun lien d'évitement ; menu `<details>` sans fermeture clavier ni `aria-expanded` piloté ; contraste non déterministe sur les fonds translucides posés sur des dégradés.

**À faire :**
1. Le composant `form-field` de `UX-02` (libellé associé, aide reliée par `aria-describedby`, erreur reliée, `aria-invalid`) résout à lui seul la majorité des 166 défauts.
2. Anneau de focus global à fort contraste (`*:focus-visible`).
3. Bloc `@media (prefers-reduced-motion: reduce)` neutralisant animations et transitions.
4. Lien d'évitement en première position du `<body>`.
5. Fonds translucides porteurs de texte : opacité garantissant le contraste, ou couche opaque sous le texte.
6. Composant de menu accessible en remplacement de `<details>`.
7. `role="status" aria-live="polite"` sur le conteneur de notifications.
8. `aria-hidden="true"` systématique sur les icônes décoratives.
9. Structure de titres corrigée (plusieurs pages passent de `h1` à `h3`, ou utilisent `<p>` avec des classes de titre).
10. Contraste : l'accent `#2F6DE0` sur blanc donne ≈ 4,7:1, soit un passage de justesse en AA. **Viser 7:1 sur les éléments d'action principaux** — le surcoût est nul et la marge protège des régressions.

**Critères d'acceptation :**
- `axe-core` ne remonte aucune violation critique sur les 10 pages principales.
- Les cinq parcours critiques sont réalisables entièrement au clavier.
- Passe manuelle au lecteur d'écran effectuée et consignée.

---

## LOT 15 — Référencement, partage social, PWA

### SEO-01 — Données structurées et sitemap

**Charge :** 1,5 j

- JSON-LD : `MusicRecording`, `MusicAlbum`, `MusicGroup`, `Offer`, `BreadcrumbList`, `Organization`, `Article` pour le blog.
- `sitemap.xml` généré (index, titres, artistes, genres, classements, blog) et `robots.txt` — **les deux sont absents** aujourd'hui.
- Soumission à la Search Console.

---

### SEO-02 — URL parlantes et pages par titre

**Charge :** 2 j · **Dépend de :** `DATA-03`

- `/album/nom-artiste/titre-album` plutôt que `albums.php?id=12`, avec redirections 301 depuis les anciennes URL.
- **Créer une page dédiée par titre** (`/titre/{slug}`) : lecteur d'extrait, bouton d'achat, crédits, paroles. C'est le premier point d'entrée naturel depuis une recherche, et il n'existe pas.
- Pages de destination par genre et par province, éditorialisées — le levier SEO naturel d'un baromètre national.
- Corriger `generateSlug()`, qui supprime les caractères accentués au lieu de les translittérer (« Émission » → « mission »).

---

### SEO-03 — Images de partage social

**Charge :** 1 j

Les métadonnées Open Graph référencent `assets/images/og-image.jpg` et `twitter-image.jpg` — **les deux fichiers sont absents**. Tout partage sur WhatsApp, Facebook ou X affiche une carte cassée, sur toutes les pages du site. Sur un marché où WhatsApp est le principal canal de découverte, c'est un handicap majeur pour un correctif de quelques heures.

- Produire les images par défaut.
- Générer une **carte dynamique par titre, sortie et artiste** (pochette, nom, format), et par édition du baromètre.
- Corriger la référence à `assets/images/logo.png` dans le `README` (seul `logo.svg` existe).
- Unifier l'icône d'onglet : `favicon.ico` et `favicon.svg` existent et ne sont pas utilisés, au profit d'un SVG en `data:` URI inline.

**Critères d'acceptation :** un partage WhatsApp d'un titre affiche la pochette et le nom de l'artiste.

---

### SEO-04 — PWA

**Charge :** 1,5 j

`sw.js` existe et les icônes PWA sont présentes, mais **il n'y a aucun `manifest.json`** : sans manifeste, pas d'installation sur l'écran d'accueil — or c'est le mode de distribution pertinent au Tchad, où installer une application depuis un magasin consomme données et espace.

- Produire `manifest.json` (nom, nom court, icônes **PNG** 192 et 512 plus maskable — les icônes actuelles sont en SVG, que plusieurs navigateurs mobiles refusent pour l'écran d'accueil, `start_url`, `display: standalone`, `theme_color`, `lang: fr`).
- Durcir le service worker : réseau-d'abord pour le HTML, cache-d'abord pour les seuls actifs versionnés, **exclusion stricte** de `/admin*`, `*-dashboard.php` et `/api/*` — le cache actuel ne distingue pas les pages authentifiées, avec un risque de servir à un utilisateur le contenu d'un autre sur appareil partagé, situation courante.
- Service worker préparé pour le mode hors ligne de `SHOP-08` : c'est lui qui servira le contenu chiffré mis de côté. **Aucun média en clair dans le cache du service worker** — le cache d'un navigateur se copie trop facilement.

> **`SHOP-08` dépend de cette tâche** (le mode hors ligne « à la Netflix » demandé le 23/09/2026 s'appuie sur ce service worker). Faire les deux dans cet ordre.

---

### SEO-05 — Mode économie de données

**Charge :** 2 j · **Dépend de :** `UX-05`

Débit réduit au choix, taille affichée avant téléchargement, téléchargement différé en Wi-Fi. Répond au premier frein réel du marché.

---

## LOT 16 — Qualité, outillage, intégration continue

### QA-01 — Gestion des dépendances et autoloading

**Charge :** 1,5 j

Le projet n'a **ni `composer.json` ni `package.json`** : tout est chargé depuis des CDN, donc non versionné et non auditable. Le `.gitignore` ignore par ailleurs `composer.lock` et `package-lock.json` — exactement les fichiers qu'il faut versionner pour garantir des installations reproductibles.

- `composer.json` avec autoloading PSR-4, et les dépendances réelles (`getID3` ou équivalent, transport mail, GeoIP, générateur PDF).
- `package.json` pour Tailwind et la chaîne de construction.
- **Versionner les fichiers de verrouillage.**
- Remplacer progressivement les `require_once` relatifs par l'autoloading.

---

### QA-02 — Envoi d'e-mails réel

**Charge :** 1 j

`sendEmail()` utilise `mail()` alors que la configuration SMTP existe et n'est pas branchée.

- Transport SMTP authentifié (PHPMailer ou Symfony Mailer), configuré par le fichier d'environnement.
- En local, pilote `log` écrivant dans `storage/logs/mail.log` — aucun envoi réel depuis un poste de développement.
- Gabarits d'e-mails : vérification, réinitialisation, facture, notification de versement, décision de modération.
- SPF, DKIM et DMARC à configurer côté domaine avant la mise en production.

---

### QA-03 — Tests automatisés

**Charge :** 3 j · **Dépend de :** `QA-01`

Aucun test n'existe aujourd'hui. Priorité à ce qui coûte le plus cher en cas de régression :

| Domaine | Type | Priorité |
|---|---|---|
| Machine à états du paiement, idempotence des callbacks | Unitaire + intégration | Critique |
| Calcul des commissions et des soldes artistes | Unitaire | Critique |
| Droits d'accès et livraison des fichiers | Intégration | Critique |
| Comptage et certification des écoutes | Unitaire | Critique |
| Contrôles d'accès par rôle | Intégration | Élevée |
| Protection CSRF | Intégration | Élevée |
| Règles de format de sortie | Unitaire | Élevée |
| Parcours d'achat de bout en bout | Bout en bout | Élevée |

**Critères d'acceptation :** les tests critiques passent en local et en intégration continue ; un correctif de régression s'accompagne d'un test qui échouait avant.

---

### QA-04 — Intégration continue et analyse statique

**Charge :** 1,5 j · **Dépend de :** `QA-01`, `QA-03`

- Pipeline sur chaque proposition de fusion : lint PHP, PHPStan (niveau 5 pour commencer), PHP-CS-Fixer (PSR-12), tests, construction Tailwind.
- Contrôle automatique de l'absence de secrets dans le diff.
- Contrôle automatique de l'absence de `var_dump`, `dd`, `die(` et `display_errors` dans le code livré.
- Contrôle d'accessibilité (`axe-core` ou `pa11y`) sur les pages principales.

---

### QA-05 — Documentation honnête

**Charge :** 1 j

Le `README.md` décrit une soixantaine de fonctionnalités dont la grande majorité n'existe pas : recommandations intelligentes, paroles synchronisées, mode hors-ligne, royalties, certification, concours, forums, parrainage, sauvegarde automatique. S'il sert de support de présentation à des partenaires ou des financeurs, l'écart est un risque de réputation sérieux.

**À faire :** réécrire le `README` avec l'état réel et une feuille de route datée, distinguant clairement « livré », « en cours » et « prévu ». Compléter `database/README.md`, `docs/paiement/`, `docs/methodologie/` et `docs/exploitation/`.

---

### QA-06 — Supervision, journalisation, sauvegarde

**Charge :** 2 j

- Journalisation structurée (JSON) avec niveaux, dans `storage/logs/`, hors racine web, avec rotation.
- Supervision : disponibilité, temps de réponse, taux d'erreur, échecs de paiement, retard de la tâche d'agrégation.
- Alertes sur : signature de callback invalide, écart de réconciliation, pic d'anomalies anti-fraude, échec de sauvegarde.
- **Sauvegarde automatisée quotidienne** (base + fichiers), hors du serveur, avec **restauration testée mensuellement**. `BACKUP_ENABLED=true` est annoncé dans le fichier d'environnement et n'a aucune implémentation.
- Page d'état du service (radio, API, paiements).

**Critères d'acceptation :** une restauration complète est réalisée avec succès et chronométrée avant la mise en production.

---

## LOT 17 — Mise en production

### DEPLOY-01 — Préparation du serveur

**Charge :** 1,5 j

- Versions PHP, MySQL et Apache **identiques au local** (`PREP-03`).
- HTTPS avec renouvellement automatique du certificat.
- `mod_xsendfile` activé (nécessaire à `SHOP-05`), `mod_headers`, `mod_expires`, `mod_rewrite`.
- Utilisateur MySQL applicatif aux droits limités ; base **non joignable depuis Internet**.
- `storage/` hors racine web, droits corrects.
- Tâches planifiées : agrégation nocturne, arrêté hebdomadaire des classements, expiration des abonnements, réconciliation quotidienne, sauvegarde, purge des données expirées.

---

### DEPLOY-02 — Procédure de déploiement

**Charge :** 1 j · **Dépend de :** `CFG-04`, `CFG-05`

Séquence à écrire dans `docs/exploitation/deploiement.md` et à automatiser :

1. Sauvegarde complète, vérifiée.
2. Mise en mode maintenance.
3. Récupération du code.
4. `php scripts/env-switch.php production` — **génère `.htaccess` depuis `.htaccess.production`**. Cette étape n'est pas optionnelle : Apache ne lit que `.htaccess`, et son absence désactive silencieusement toutes les règles de sécurité.
5. Retirer du serveur : `.env.local`, `.htaccess.local`, `mock-gateways/`, `tests/`, `database/seeds/demo.sql`, `docs/` si non nécessaire.
6. Déposer `.env.production` (jamais versionné) avec les secrets de production.
7. `composer install --no-dev --optimize-autoloader`, construction des actifs.
8. `php scripts/migrate.php up`.
9. Contrôles automatiques : `env-switch --status`, garde-fou `CFG-05`, en-têtes HTTP, accès refusé aux fichiers sensibles.
10. Sortie du mode maintenance.
11. Vérification des cinq parcours critiques.

**Critères d'acceptation :** le déploiement est reproductible et documenté ; une étape oubliée est détectée par le garde-fou plutôt que par un incident.

---

### DEPLOY-03 — Contrôles avant ouverture

**Charge :** 1 j

| Contrôle | Attendu |
|---|---|
| `.env.local` absent du serveur | Vérifié par `CFG-05` |
| `.htaccess.local` absent | Vérifié |
| `.htaccess` présent et issu de `.htaccess.production` | Vérifié par empreinte |
| `mock-gateways/` absent | Vérifié |
| `PAYMENT_DRIVER=live` | Vérifié — démarrage refusé sinon |
| `APP_DEBUG=false`, `display_errors` inactif | Vérifié |
| Aucun secret à sa valeur de modèle | Vérifié, variable nommée si échec |
| `install.php`, `execute-sql.php`, `update-passwords.php`, `api/docs.php` absents | 404 |
| Accès direct à un fichier audio | 403 |
| Accès à `.env`, `*.sql`, `*.log` par URL | 403 |
| En-têtes de sécurité et HSTS | Présents |
| Pages 403/404/500 | Habillées, bon code HTTP |
| Compte `super_admin` | Mot de passe changé, 2FA active |
| Sauvegarde | Exécutée et **restaurée avec succès** |
| Transaction réelle de bout en bout | Réussie sur chaque passerelle, avec remboursement |
| Tâches planifiées | Actives, premier passage vérifié |

---

### DEPLOY-04 — Retour arrière

**Charge :** 0,5 j

Procédure écrite et **testée** : retour à l'étiquette précédente, annulation des migrations (`migrate.php down`), restauration de la base, critères de décision de retour arrière, personne habilitée à le déclencher.

**Critères d'acceptation :** le retour arrière est exécuté une fois sur l'environnement de préproduction, et chronométré.

---

## Annexe A — Fichiers créés par ce plan

| Chemin | Rôle | Versionné | Déployé en production |
|---|---|:--:|:--:|
| `.env.local` | Configuration XAMPP locale | Non | **Non — retiré** |
| `.env.local.example` | Modèle local | Oui | Sans effet |
| `.env.production` | Configuration réelle | Non | Oui, déposé manuellement |
| `.env.production.example` | Modèle production | Oui | Sans effet |
| `.htaccess.local` | Règles Apache développement | Oui | **Non — retiré** |
| `.htaccess.production` | Règles Apache durcies | Oui | Source de `.htaccess` |
| `.htaccess` | **Généré** par `env-switch` | Non | Oui |
| `scripts/env-switch.php` | Bascule d'environnement | Oui | Oui |
| `scripts/migrate.php` | Migrations (CLI uniquement) | Oui | Oui |
| `scripts/seed.php` | Données de référence (CLI) | Oui | Oui |
| `scripts/create-admin.php` | Premier administrateur (CLI) | Oui | Oui |
| `scripts/rebuild-counters.php` | Reconstruction des agrégats | Oui | Oui |
| `scripts/mock-gateways.bat` / `.sh` | Lanceur des simulateurs | Oui | **Non** |
| `includes/environment-guard.php` | Garde-fou de cohérence | Oui | Oui |
| `includes/csrf-guard.php` | Garde CSRF centralisé | Oui | Oui |
| `includes/payment/` | Interface et adaptateurs | Oui | Oui |
| `media.php` | Livraison protégée des fichiers | Oui | Oui |
| `mock-gateways/` | Simulateurs Airtel, Moov, VISA, KONOOM + console | Oui | **Non — retiré** |
| `database/migrations/` | Migrations numérotées | Oui | Oui |
| `database/seeds/referentiel.sql` | Genres, tarifs, rôles, provinces | Oui | Oui |
| `database/seeds/demo.sql` | Jeu de démonstration | Oui | **Non** |
| `storage/` | Uploads, journaux, cache | Non (contenu) | Oui (structure) |
| `tests/` | Tests automatisés | Oui | **Non** |
| `docs/` | Exploitation, paiement, méthodologie | Oui | Optionnel |

---

## Annexe B — Ports des simulateurs locaux

| Service | Port | URL locale |
|---|---:|---|
| Console de pilotage | 9100 | `http://127.0.0.1:9100` |
| Airtel Money | 9101 | `http://127.0.0.1:9101` |
| Moov Money | 9102 | `http://127.0.0.1:9102` |
| VISA / carte | 9103 | `http://127.0.0.1:9103` |
| KONOOM | 9104 | `http://127.0.0.1:9104` |
| Icecast (radio, existant) | 8000 | `http://127.0.0.1:8000` |
| Application (XAMPP) | 80 | `http://localhost/tchadok` |

Tous les simulateurs écoutent sur `127.0.0.1` uniquement, jamais sur `0.0.0.0`.

---

## Annexe C — Ordre d'exécution recommandé

```
Semaine 1          LOT 0 + LOT 1 + LOT 2 (SEC-01 à SEC-08)   ← blocant, rien d'autre en parallèle
Semaine 2          LOT 2 (SEC-09 à SEC-20) + LOT 3
Semaines 3 à 4     LOT 4 (schéma et migrations)
Semaines 5 à 7     LOT 5 (paiement et simulateurs)  ║  LOT 9 (mesure des écoutes)
Semaines 8 à 10    LOT 6 (achat) + LOT 7 (Premium)  ║  LOT 10 (agrégats et classements)
Semaines 11 à 12   LOT 8 (versements)               ║  LOT 11 + LOT 12 (taxonomie, modération)
Semaines 13 à 16   LOT 13 (dashboards)              ║  LOT 14 (design, a11y, perf)
Semaines 17 à 18   LOT 15 + LOT 16 (SEO, PWA, qualité)
Semaine 19         LOT 17 (mise en production)
Semaine 20         Lancement du baromètre public
```

`║` indique des travaux menables en parallèle par deux équipes.

**En parallèle et dès la semaine 1**, sans attendre la technique : engager les démarches partenaires (`PAY-11`), trancher la question du statut pour l'encaissement pour compte de tiers, et arrêter la définition de l'écoute comptabilisée (`STAT-01`). Ce sont les trois éléments dont les délais ne dépendent pas de l'équipe de développement et qui peuvent bloquer la mise en production.

---

## Annexe D — Trois décisions à prendre avant de commencer

1. **Modèle commercial.** Vente à l'unité, abonnement, ou les deux. Le code amorce les deux sans terminer aucun. Recommandation : **vente à l'unité plus portefeuille prépayé en priorité**, abonnement en second temps — la vente à l'unité correspond à l'usage local, ne suppose pas d'atteindre une taille critique de catalogue, et rémunère l'artiste immédiatement, ce qui est le meilleur argument pour en recruter.

2. **Statut pour l'encaissement pour compte de tiers.** À clarifier juridiquement **avant** `PAY-11` : la réponse peut changer l'architecture (encaissement par la plateforme, ou redirection du paiement vers l'artiste), donc l'ensemble des lots 5 à 8.

3. **Définition officielle de l'écoute comptabilisée** (`STAT-01`). À arrêter, publier et inscrire au contrat artiste **avant** tout lancement du baromètre. Une définition modifiée après coup invaliderait tout l'historique et ruinerait la crédibilité du classement.

---

*Plan établi le 21 septembre 2026, en complément de `AUDIT-PLATEFORME-TCHADOK.md`. Les charges sont des estimations pour un développeur familier de la base de code ; elles n'incluent ni la recette métier, ni la rédaction juridique, ni les délais de contractualisation avec les opérateurs de paiement.*
