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

**Charge :** 2 h · **Fichier :** `includes/database.php`

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

**Charge :** 1 j

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

**Charge :** 4 h · **Fichiers :** `includes/functions.php`, `includes/auth.php`

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

**Charge :** 1 j · **Fichier :** `includes/auth.php` (lignes 170-199)

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

**Charge :** 1 j

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

**Charge :** 3 h · **Fichiers :** `includes/auth.php` (l. 155-165), `includes/functions.php` (l. 339-353)

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

**Charge :** 3 h · **Fichiers :** `security-settings.php`, `includes/advanced-auth.php`, `assets/js/security-settings.js`

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

**Charge :** 4 h · **Fichiers :** `config/constants.php`, les deux `.htaccess`, points d'entrée

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

**Charge :** 3 h

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

**Charge :** 1 j · **Fichiers :** `artist-add-song.php`, `artist-add-album.php`, `upload.php`, `includes/functions.php`

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

**Charge :** 4 h · **Dépend de :** `CFG-04`

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

**Charge :** 5 j · **Dépend de :** `DATA-01`

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

**Charge :** 3 j · **Dépend de :** `SEC-14`, `SEC-19`

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

**Charge :** 4 h · **Bloque :** `CLEAN-02` à `CLEAN-05`

Pour chaque fichier candidat, confirmer l'absence d'usage par trois contrôles : recherche du nom de fichier dans les `include`/`require`, recherche dans les liens (`href`, `action`, `src`), recherche des sélecteurs CSS dans les gabarits pour les feuilles de style.

**Produire `docs/nettoyage.md`** : un tableau fichier / raison / preuve de non-usage / décision. Ce document sert de trace en cas de régression.

**Critères d'acceptation :**
- Chaque retrait est justifié par une preuve écrite.
- Les fichiers au statut incertain sont listés séparément, pour arbitrage explicite.

---

### CLEAN-02 — Retirer les dashboards de la génération précédente

**Charge :** 3 h · **Dépend de :** `CLEAN-01`

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

**Charge :** 4 h · **Dépend de :** `CLEAN-01`

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

**Charge :** 3 h · **Dépend de :** `CLEAN-01`, `DATA-01`

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

**Charge :** 2 h

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

**Charge :** 2 j · **Bloque :** `DATA-02` à `DATA-08`, `SEC-19`, tous les lots suivants

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

**Charge :** 1 j · **Dépend de :** `DATA-01`, `PREP-02`

La table `users` porte `password` **et** `password_hash`, toutes deux `NOT NULL`, et l'authentification accepte l'une **ou** l'autre. Toute divergence crée un second mot de passe valide permanent : après passage de l'ancien `admin/update-passwords.php`, l'ancien mot de passe reste valide via la colonne `password`.

**Migration en trois temps :**
1. **Analyse** — compter les comptes où les deux colonnes diffèrent, où l'une est vide, où l'une n'est pas un hash bcrypt valide. Produire un rapport avant toute écriture.
2. **Convergence** — pour chaque cas, décider explicitement quelle valeur fait foi. Si les deux colonnes portent des hash valides et distincts, **forcer une réinitialisation de mot de passe** pour ce compte plutôt que d'en choisir un arbitrairement.
3. **Bascule** — faire porter tout le code sur `password_hash` uniquement (`includes/auth.php` l. 44-51, `includes/database.php` fonction `checkAdminCredentials`, `register.php`, `api/user.php`), puis retirer la colonne `password`.

**Également :** ajouter `password_changed_at` (utilisé par `SEC-10`) et vérifier que `checkAdminCredentials()` contrôle bien `is_active`, ce qu'elle ne fait pas aujourd'hui.

**Critères d'acceptation :**
- La colonne `password` n'existe plus.
- Aucun compte ne dispose de deux mots de passe valides.
- Les comptes en conflit ont reçu un e-mail de réinitialisation.

---

### DATA-03 — Introduire l'entité « sortie » (release) et les formats de vente

**Charge :** 2 j · **Dépend de :** `DATA-01` · **Bloque :** `SHOP-*`, `MOD-*`

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

**Charge :** 1 j · **Dépend de :** `DATA-01`

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

**Charge :** 1,5 j · **Dépend de :** `DATA-01`, `DATA-03`, `DATA-04` · **Bloque :** `LOT 6`, `LOT 7`, `LOT 8`

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

**Charge :** 1 j · **Dépend de :** `DATA-01`

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
- Mise en cache hors ligne du contenu sous droit valide.

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
