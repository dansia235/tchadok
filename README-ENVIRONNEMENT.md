# Configuration de l'Environnement - Tchadok Platform

## 📋 Vue d'ensemble

Le projet Tchadok utilise des fichiers de configuration d'environnement pour gérer les différences entre l'environnement de développement local et la production.

## 🔧 Installation Initiale

### 1. Configuration Locale (XAMPP/WAMP)

```bash
# Le fichier .env est déjà configuré pour l'environnement local
# Vérifiez simplement que les paramètres correspondent à votre configuration
```

**Fichiers pour l'environnement local :**
- `.env` - Variables d'environnement (déjà configuré)
- `.htaccess` - Configuration Apache (déjà configuré)

**Configuration de la base de données locale :**
```env
DB_HOST=localhost
DB_DATABASE=tchadok
DB_USERNAME=dansia
DB_PASSWORD=dansia
```

**URL locale :**
```env
APP_URL=http://localhost/tchadok
SITE_URL=http://localhost/tchadok
```

### 2. Création de la Base de Données

```sql
-- Créer la base de données
CREATE DATABASE IF NOT EXISTS tchadok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer l'utilisateur (si nécessaire)
CREATE USER 'dansia'@'localhost' IDENTIFIED BY 'dansia';
GRANT ALL PRIVILEGES ON tchadok.* TO 'dansia'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Importation de la Structure

```bash
# Via phpMyAdmin : Importer database/tchadok.sql

# Ou via ligne de commande :
mysql -u dansia -p tchadok < database/tchadok.sql
```

### 4. Vérification de l'Installation

Accédez à : `http://localhost/tchadok/`

Si tout est configuré correctement, la page d'accueil devrait s'afficher.

## 🚀 Déploiement en Production

### 1. Préparer les Fichiers de Configuration

```bash
# Sur le serveur de production, renommer les fichiers templates
cp .env.production .env
cp .htaccess.production .htaccess
```

### 2. Configurer le Fichier .env

Éditez le fichier `.env` et configurez :

```env
# Environnement
APP_ENV=production
APP_DEBUG=false

# URL de production (IMPORTANT !)
APP_URL=https://tchadok.td
SITE_URL=https://tchadok.td

# Base de données
DB_HOST=localhost
DB_DATABASE=tchadok
DB_USERNAME=dansia
DB_PASSWORD=dansia

# Clés de sécurité (GÉNÉRER DE NOUVELLES CLÉS !)
APP_KEY=base64:NOUVELLE_CLE_ICI
SESSION_SECRET=NOUVEAU_SECRET_ICI
```

### 3. Générer de Nouvelles Clés de Sécurité

```bash
# Générer une nouvelle clé APP_KEY
openssl rand -base64 32

# Générer un nouveau SESSION_SECRET
openssl rand -base64 32
```

**Copiez ces valeurs dans votre fichier `.env`**

### 4. Configurer les Services Externes

#### Email (SMTP)
```env
MAIL_HOST=smtp.votre-domaine.com
MAIL_PORT=587
MAIL_USERNAME=votre-email@domaine.com
MAIL_PASSWORD=votre-mot-de-passe
```

#### Paiements
```env
PAYMENT_MODE=live
AIRTEL_MONEY_API_KEY=votre-cle-api-production
MOOV_MONEY_API_KEY=votre-cle-api-production
```

#### Réseaux Sociaux
```env
FACEBOOK_APP_ID=votre-app-id-production
GOOGLE_CLIENT_ID=votre-client-id-production
```

### 5. Permissions des Fichiers

```bash
# Répertoire uploads
chmod 755 uploads/
chmod 755 uploads/music/
chmod 755 uploads/images/
chmod 755 uploads/profiles/

# Répertoire cache et logs
chmod 755 cache/
chmod 755 storage/logs/

# Fichiers sensibles
chmod 600 .env
chmod 644 .htaccess
```

### 6. Vérifications de Sécurité

✅ Le fichier `.env` n'est PAS accessible via le navigateur
✅ Le fichier `.htaccess` bloque l'accès aux fichiers sensibles
✅ `APP_DEBUG` est sur `false`
✅ `FORCE_HTTPS` est sur `true`
✅ Les clés de sécurité ont été changées

## 🔐 Sécurité

### Fichiers à NE JAMAIS Commiter dans Git

- `.env` (contient les mots de passe)
- `config.local.php`
- Fichiers de backup `.sql`

### Fichiers à Commiter

- ✅ `.env.production` (template sans valeurs sensibles)
- ✅ `.htaccess.production` (template)
- ✅ `.gitignore`

## 📂 Structure des Fichiers de Configuration

```
tchadok/
├── .env                      # Configuration locale (NON commité)
├── .env.production          # Template pour production (commité)
├── .htaccess                # Configuration Apache locale (NON commité)
├── .htaccess.production     # Template Apache production (commité)
├── .gitignore               # Fichiers à ignorer
├── config/
│   ├── env.php              # Chargeur de variables d'environnement
│   └── constants.php        # Constantes de l'application
└── README-ENVIRONNEMENT.md  # Ce fichier
```

## 🛠️ Variables d'Environnement Importantes

### Environnement
- `APP_ENV` : `development` ou `production`
- `APP_DEBUG` : `true` ou `false`
- `APP_URL` : URL complète du site

### Base de Données
- `DB_HOST` : Hôte MySQL (généralement `localhost`)
- `DB_DATABASE` : Nom de la base de données
- `DB_USERNAME` : Utilisateur MySQL
- `DB_PASSWORD` : Mot de passe MySQL

### Sécurité
- `APP_KEY` : Clé de chiffrement de l'application
- `SESSION_SECRET` : Secret pour les sessions

### Fonctionnalités de Développement
- `ENABLE_TEST_ACCOUNTS` : Activer les comptes de test
- `ENABLE_DEBUG_TOOLBAR` : Afficher la barre de debug
- `ENABLE_QUERY_LOG` : Logger les requêtes SQL

### Radio Engine (Icecast/Liquidsoap)
- `RADIO_ENGINE_ENABLED` : Active l'integration moteur de stream
- `RADIO_STREAM_PUBLIC_URL` : URL publique du flux (ex: `https://radio.tchadok.td/tchadok.mp3`)
- `RADIO_STREAM_FALLBACK_URL` : fallback interne (par defaut `/api/radio/stream`)
- `ICECAST_STATUS_URL` : endpoint status JSON Icecast (ex: `http://127.0.0.1:8000/status-json.xsl`)
- `ICECAST_ADMIN_USER` : user admin Icecast
- `ICECAST_ADMIN_PASSWORD` : mot de passe admin Icecast
- `RADIO_ENGINE_MOUNT` : mount du flux principal (ex: `/tchadok.mp3`)
- `RADIO_ENGINE_TIMEOUT` : timeout de lecture status (secondes)
- `RADIO_ENGINE_PREFER_STREAM` : priorise l'URL du moteur sur l'URL en base
- `RADIO_ENGINE_USE_STATUS_LISTENURL` : utilise `listenurl` Icecast (a activer si DNS public resolu)

## 🧪 Comptes de test

> **Modifié le 21/09/2026 (SEC-02).** La page web `admin/create-test-accounts.php`
> a été supprimée, ainsi que les scripts `sql/create-test-accounts*.sql`.
> Les comptes qu'ils créaient partageaient tous un mot de passe unique et
> documenté comme public.

La création d'un compte administrateur passera par une commande en ligne de
commande (`scripts/create-admin.php`, tâche `SEC-05`), qui impose un mot de
passe fort et refuse de s'exécuter via HTTP.

Le jeu de démonstration sera fourni dans `database/seeds/demo.sql`, jamais
importé en production.

> Ce document décrit encore l'ancien mécanisme de configuration (fichier `.env`
> unique). Il sera entièrement réécrit à la tâche `CFG-06`, après la mise en
> place de `.env.local` / `.env.production`. En attendant, se reporter à
> `docs/exploitation/pre-requis.md` pour l'installation locale.

## 🆘 Dépannage

### Erreur "Ce script ne peut être exécuté qu'en mode développement"

**Solution :** Vérifiez votre fichier `.env` :
```env
APP_ENV=development
ENABLE_TEST_ACCOUNTS=true
```

### Erreur de connexion à la base de données

**Solution :** Vérifiez les identifiants dans `.env` :
```env
DB_HOST=localhost
DB_DATABASE=tchadok
DB_USERNAME=dansia
DB_PASSWORD=dansia
```

### Page blanche ou erreur 500

**Solution :**
1. Vérifiez que le fichier `.env` existe
2. Vérifiez les permissions des fichiers
3. Consultez les logs PHP : `storage/logs/`

### URL incorrectes (liens cassés)

**Solution :** Vérifiez `SITE_URL` dans `.env` :
```env
# Local
SITE_URL=http://localhost/tchadok

# Production
SITE_URL=https://tchadok.td
```

## 📝 Notes Importantes

1. **Ne jamais** éditer `.env.production` avec des vraies valeurs
2. **Toujours** créer un nouveau `.env` en production
3. **Toujours** générer de nouvelles clés pour la production
4. **Toujours** vérifier que `.env` n'est pas accessible publiquement
5. **Toujours** faire un backup avant de déployer

## 📞 Support

Pour toute question sur la configuration :
1. Consultez ce README
2. Vérifiez les fichiers templates (`.env.production`, `.htaccess.production`)
3. Consultez la documentation du serveur web

---

**Dernière mise à jour** : 2025
**Version** : 1.0
