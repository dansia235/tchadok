# 🔧 Guide d'Importation des Comptes de Test

> **22/09/2026 (SEC-05).** Les scripts de comptes de test (create-test-accounts*.sql, `import-test-accounts.sql`, `create-admin-account.sql`, `reset-admin-password.sql`) et `README-COMPTES-TEST.md` ont ete supprimes : ils creaient des comptes partageant un mot de passe documente comme public. Premier administrateur : `php scripts/create-admin.php`. Comptes de demonstration locaux : `database/seeds/demo.sql`. L'ensemble du repertoire `sql/` sera retire en CLEAN-04, au profit de `database/migrations/`.


## ❗ Problème Identifié

La table `users` de votre base de données contient **DEUX colonnes de mot de passe** :
```sql
`password` varchar(255) NOT NULL,
`password_hash` varchar(255) NOT NULL,
```

Les deux colonnes sont **obligatoires** (NOT NULL), mais nos scripts SQL précédents ne remplissaient que `password_hash`.

**Résultat :** Les insertions échouaient silencieusement car MySQL rejetait les lignes sans la colonne `password`.

## ✅ Solution

Trois méthodes pour créer les comptes de test :

---

## Méthode 1 : Import Direct via phpMyAdmin (RECOMMANDÉ)

### Étapes :

1. **Ouvrir phpMyAdmin**
   - Accédez à : `http://localhost/phpmyadmin`
   - Connectez-vous avec : `dansia` / `dansia`

2. **Sélectionner la base de données**
   - Cliquez sur `tchadok` dans la liste à gauche

3. **Importer le fichier SQL**
   - Cliquez sur l'onglet **"Importer"** en haut
   - Cliquez sur **"Choisir un fichier"**
   - Sélectionnez : `sql/import-test-accounts.sql`
   - Cliquez sur **"Exécuter"**

4. **Vérifier les résultats**
   - Vous devriez voir : "✅ 7 comptes de test créés avec succès !"
   - Cliquez sur la table `users` pour voir les nouveaux comptes

### Avantages :
- ✅ Méthode la plus fiable
- ✅ Affiche les erreurs clairement
- ✅ Supprime automatiquement les anciens comptes de test
- ✅ Crée les 7 nouveaux comptes en une seule opération

---

## Méthode 2 : Via Ligne de Commande MySQL

### Pour Windows (XAMPP) :

```bash
cd C:\xampp\mysql\bin
mysql.exe -u dansia -p tchadok < C:\xampp\htdocs\tchadok\sql\import-test-accounts.sql
```

### Pour Linux/Mac :

```bash
mysql -u dansia -p tchadok < /chemin/vers/tchadok/sql/import-test-accounts.sql
```

**Mot de passe :** `dansia`

---

## Méthode 3 : Via Interface Web PHP (Mise à Jour)

Le fichier `admin/create-test-accounts.php` a été corrigé et devrait maintenant fonctionner.

### Étapes :

1. **Récupérer les dernières modifications :**
   ```bash
   git pull origin claude/update-style-011CUv8ybt1mc56Gmj4QiTRU
   ```

2. **Accéder à la page :**
   ```
   http://localhost/tchadok/admin/create-test-accounts.php
   ```

3. **Tester la connexion :**
   - Cliquez sur "Tester la connexion à la base de données"
   - Vérifiez que tout est OK

4. **Exécuter le script :**
   - Cliquez sur "Exécuter le Script"
   - Développez "Mode Debug" pour voir les détails

---

## 📋 Comptes Créés

Après l'import, vous aurez **7 comptes de test** :

| Username | Email | Mot de passe | Type | Détails |
|----------|-------|--------------|------|---------|
| `admin_test` | admin@test.tchadok.td | `tchadok2024` | Admin | Super administrateur |
| `fan_test1` | fan1@test.tchadok.td | `tchadok2024` | Fan | Premium - 5,000 FCFA |
| `fan_test2` | fan2@test.tchadok.td | `tchadok2024` | Fan | Standard - 2,500 FCFA |
| `fan_test3` | fan3@test.tchadok.td | `tchadok2024` | Fan | Étudiant - 1,200 FCFA |
| `artist_test1` | artist1@test.tchadok.td | `tchadok2024` | Artiste | Ngar Star (vérifié) |
| `artist_test2` | artist2@test.tchadok.td | `tchadok2024` | Artiste | Sasa Voice (émergente) |
| `artist_test3` | artist3@test.tchadok.td | `tchadok2024` | Artiste | Ibro Beats (débutant) |

---

## 🔍 Vérification

### Via phpMyAdmin :

1. Ouvrez la table `users`
2. Recherchez les emails contenant `@test.tchadok.td`
3. Vous devriez voir 7 lignes

### Via SQL :

```sql
SELECT username, email, first_name, last_name
FROM users
WHERE email LIKE '%@test.tchadok.td';
```

### Via Interface Web :

Essayez de vous connecter avec un des comptes :
```
Email : fan1@test.tchadok.td
Mot de passe : tchadok2024
```

---

## ⚠️ Dépannage

### Erreur : "Duplicate entry"
**Solution :** Le script supprime automatiquement les anciens comptes avant de créer les nouveaux. Si l'erreur persiste, supprimez manuellement :

```sql
DELETE FROM artists WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@test.tchadok.td');
DELETE FROM admins WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@test.tchadok.td');
DELETE FROM users WHERE email LIKE '%@test.tchadok.td';
```

### Erreur : "Column 'password' cannot be null"
**Solution :** Utilisez le fichier `import-test-accounts.sql` qui remplit maintenant les DEUX colonnes `password` et `password_hash`.

### Aucun compte créé, pas d'erreur
**Cause :** Contraintes de clés étrangères
**Solution :** Le script désactive temporairement les vérifications avec `SET FOREIGN_KEY_CHECKS = 0;`

---

## 📁 Fichiers SQL Disponibles

| Fichier | Usage | Description |
|---------|-------|-------------|
| `import-test-accounts.sql` | ⭐ **RECOMMANDÉ** | Import direct phpMyAdmin - Inclut password ET password_hash |
| `create-test-accounts-simple.sql` | Automatique | Utilisé par admin/create-test-accounts.php |
| `create-test-accounts.sql` | Référence | Version complète avec statistiques |

---

## 🎯 Résumé Rapide

**Pour créer les comptes de test maintenant :**

1. Ouvrez phpMyAdmin : `http://localhost/phpmyadmin`
2. Sélectionnez la base `tchadok`
3. Importez le fichier : `sql/import-test-accounts.sql`
4. Vérifiez que 7 comptes sont créés
5. Connectez-vous avec `fan1@test.tchadok.td` / `tchadok2024`

---

**Dernière mise à jour :** 2025
**Problème corrigé :** Colonnes password et password_hash maintenant remplies
