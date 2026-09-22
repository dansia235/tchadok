# Scripts SQL - Comptes de Test

> **22/09/2026 (SEC-05).** Les scripts de comptes de test (create-test-accounts*.sql, `import-test-accounts.sql`, `create-admin-account.sql`, `reset-admin-password.sql`) et `README-COMPTES-TEST.md` ont ete supprimes : ils creaient des comptes partageant un mot de passe documente comme public. Premier administrateur : `php scripts/create-admin.php`. Comptes de demonstration locaux : `database/seeds/demo.sql`. L'ensemble du repertoire `sql/` sera retire en CLEAN-04, au profit de `database/migrations/`.


## 📁 Fichiers Disponibles

### create-test-accounts.sql
**Fichier SQL complet avec vérifications**

- Contient toutes les requêtes de création des comptes
- Inclut des requêtes SELECT pour affichage des résultats
- Inclut des statistiques et résumés
- Recommandé pour exécution manuelle via phpMyAdmin

**Utilisation :**
```bash
mysql -u dansia -p tchadok < create-test-accounts.sql
```

### create-test-accounts-simple.sql
**Fichier SQL optimisé pour PHP PDO**

- Version simplifiée sans requêtes SELECT de vérification
- Optimisé pour exécution via PHP prepare/execute
- Meilleure compatibilité avec les transactions PDO
- Utilisé automatiquement par `admin/create-test-accounts.php`

**Avantages :**
- ✅ Pas de requêtes SELECT inutiles
- ✅ Meilleure gestion des erreurs
- ✅ Compatible avec PDO transactions
- ✅ Exécution plus rapide

## 🔄 Ordre d'Exécution

Le script PHP `admin/create-test-accounts.php` utilise automatiquement le fichier approprié :
1. Cherche d'abord `create-test-accounts-simple.sql`
2. Si non disponible, utilise `create-test-accounts.sql`

## 📝 Comptes Créés

Les deux fichiers créent les mêmes 7 comptes de test :

| Type | Username | Email | Mot de passe | Description |
|------|----------|-------|--------------|-------------|
| **Admin** | admin_test | admin@test.tchadok.td | tchadok2024 | Administrateur complet |
| **Fan** | fan_test1 | fan1@test.tchadok.td | tchadok2024 | Premium - 5,000 FCFA |
| **Fan** | fan_test2 | fan2@test.tchadok.td | tchadok2024 | Standard - 2,500 FCFA |
| **Fan** | fan_test3 | fan3@test.tchadok.td | tchadok2024 | Étudiant - 1,200 FCFA |
| **Artiste** | artist_test1 | artist1@test.tchadok.td | tchadok2024 | Ngar Star (vérifié) |
| **Artiste** | artist_test2 | artist2@test.tchadok.td | tchadok2024 | Sasa Voice (émergente) |
| **Artiste** | artist_test3 | artist3@test.tchadok.td | tchadok2024 | Ibro Beats (débutant) |

## 🔐 Sécurité

- ⚠️ Ces scripts ne fonctionnent qu'en mode développement
- ⚠️ Vérifiez que `APP_ENV=development` dans votre `.env`
- ⚠️ Assurez-vous que `ENABLE_TEST_ACCOUNTS=true`
- ⚠️ **JAMAIS** utiliser en production !

## 🛠️ Dépannage

### Erreur: "Duplicate entry"
**Cause :** Les comptes existent déjà
**Solution :** Le script supprime automatiquement les anciens comptes avant de créer les nouveaux

### Erreur: "Unknown column"
**Cause :** La structure de la base ne correspond pas
**Solution :** Vérifiez que vous avez importé le dernier schéma de la base

### Transaction échoue
**Cause :** Erreur dans une des requêtes
**Solution :** Utilisez le mode debug de `admin/create-test-accounts.php` pour identifier la requête problématique

## 📊 Différences entre les Fichiers

| Caractéristique | create-test-accounts.sql | create-test-accounts-simple.sql |
|-----------------|--------------------------|----------------------------------|
| Requêtes SELECT | ✅ Oui (vérifications) | ❌ Non |
| Transactions SQL | ✅ START/COMMIT | ❌ Géré par PHP |
| Statistiques | ✅ Affiche résumé | ❌ Non |
| Optimisé pour PHP | ⚠️ Partiel | ✅ Oui |
| Taille du fichier | 📦 Plus grand | 📦 Plus petit |
| Rapidité | 🐢 Plus lent | 🚀 Plus rapide |

## 💡 Recommandations

**Pour utilisation manuelle (phpMyAdmin, MySQL Workbench) :**
→ Utilisez `create-test-accounts.sql`

**Pour utilisation via PHP (admin/create-test-accounts.php) :**
→ Utilisez `create-test-accounts-simple.sql` (automatique)

---

**Dernière mise à jour :** 2025
**Version :** 2.0
