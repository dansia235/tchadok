# Script de Création des Comptes de Test - Tchadok

## 📋 Description

Ce script SQL crée automatiquement des comptes de test pour chaque profil de la plateforme Tchadok. Il supprime d'abord tous les anciens comptes de test avant d'insérer les nouveaux, garantissant ainsi une base de données propre.

## 🎯 Profils Créés

Le script crée **7 comptes de test** répartis en 3 catégories :

### 1. Administrateur (1 compte)
- **Username**: `admin_test`
- **Email**: `admin@test.tchadok.td`
- **Rôle**: Super Admin
- **Premium**: Oui
- **Accès**: Complet à toutes les fonctionnalités

### 2. Fans (3 comptes)

#### Fan Premium
- **Username**: `fan_test1`
- **Email**: `fan1@test.tchadok.td`
- **Nom**: Amina Hassan
- **Premium**: Oui (expire dans 1 an)
- **Solde**: 5 000 FCFA
- **Points de fidélité**: 850

#### Fan Standard
- **Username**: `fan_test2`
- **Email**: `fan2@test.tchadok.td`
- **Nom**: Mahamat Idriss
- **Premium**: Non
- **Solde**: 2 500 FCFA
- **Points de fidélité**: 320

#### Fan Étudiant Premium
- **Username**: `fan_test3`
- **Email**: `fan3@test.tchadok.td`
- **Nom**: Fatima Oumar
- **Premium**: Oui (expire dans 6 mois)
- **Solde**: 1 200 FCFA
- **Points de fidélité**: 150

### 3. Artistes (3 comptes)

#### Artiste Vérifié et Populaire
- **Username**: `artist_test1`
- **Email**: `artist1@test.tchadok.td`
- **Nom**: Abdoulaye Ngaradoumbé
- **Nom de scène**: **Ngar Star**
- **Genre**: Rap, Hip-Hop, Afrobeat
- **Statut**: Vérifié ✓ | En vedette ⭐
- **Streams**: 150 000
- **Revenus**: 38 250 FCFA
- **Solde**: 25 000 FCFA

#### Artiste Émergente
- **Username**: `artist_test2`
- **Email**: `artist2@test.tchadok.td`
- **Nom**: Sarah Djimadoum
- **Nom de scène**: **Sasa Voice**
- **Genre**: Afro-Soul, R&B, Pop
- **Statut**: En vedette ⭐
- **Streams**: 32 000
- **Revenus**: 8 330 FCFA
- **Solde**: 8 500 FCFA

#### Artiste Débutant
- **Username**: `artist_test3`
- **Email**: `artist3@test.tchadok.td`
- **Nom**: Ibrahim Ahmat
- **Nom de scène**: **Ibro Beats**
- **Genre**: Afrobeat, Trap, Electronic
- **Statut**: Non vérifié
- **Streams**: 5 400
- **Revenus**: 722,50 FCFA
- **Solde**: 1 200 FCFA

## 🔐 Mot de Passe

**Tous les comptes utilisent le même mot de passe pour faciliter les tests :**

```
tchadok2024
```

Hash bcrypt : `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`

## 🚀 Utilisation

### Méthode 1 : Via phpMyAdmin

1. Ouvrez phpMyAdmin
2. Sélectionnez la base de données `tchadok`
3. Cliquez sur l'onglet **SQL**
4. Copiez-collez le contenu du fichier `create-test-accounts.sql`
5. Cliquez sur **Exécuter**

### Méthode 2 : Via ligne de commande MySQL

```bash
# Depuis le terminal
mysql -u root -p tchadok < sql/create-test-accounts.sql

# Ou en spécifiant le chemin complet
mysql -u root -p tchadok < /chemin/vers/tchadok/sql/create-test-accounts.sql
```

### Méthode 3 : Via l'application PHP

Si vous avez un script PHP pour exécuter des migrations :

```php
<?php
require_once 'includes/database.php';

$sql = file_get_contents(__DIR__ . '/sql/create-test-accounts.sql');
$db->multi_query($sql);
```

## 📊 Vérification

Après l'exécution du script, vous verrez :

1. **Liste des comptes créés** avec leurs informations
2. **Résumé statistique** :
   - Total des comptes créés : 7
   - Administrateurs : 1
   - Artistes : 3
   - Fans : 3
3. **Informations de connexion** complètes pour chaque compte

## ⚠️ Important

### Sécurité
- ⚠️ **NE PAS UTILISER EN PRODUCTION !**
- Ces comptes sont destinés **uniquement au développement et aux tests**
- Le mot de passe est simple et connu publiquement
- Tous les emails utilisent le domaine `@test.tchadok.td`

### Suppression
Le script supprime automatiquement :
- Tous les utilisateurs avec email `@test.tchadok.td` ou `@tchadok.test`
- Tous les utilisateurs avec username contenant `_test`
- Les entrées associées dans les tables `artists` et `admins`

### Tables Affectées
Le script modifie les tables suivantes :
- `users` : Création des utilisateurs
- `artists` : Création des profils artistes
- `admins` : Création du profil admin

## 🔄 Réexécution

Vous pouvez exécuter ce script **plusieurs fois sans problème**. À chaque exécution :
1. Les anciens comptes de test sont supprimés
2. De nouveaux comptes avec les mêmes identifiants sont créés
3. Les données sont réinitialisées aux valeurs par défaut

## 📝 Personnalisation

Pour modifier les comptes créés, éditez le fichier `create-test-accounts.sql` :

- **Ajouter un compte** : Copiez-collez un bloc INSERT et modifiez les valeurs
- **Modifier les données** : Changez les valeurs dans les INSERT existants
- **Changer le mot de passe** : Remplacez le hash bcrypt (générez-en un nouveau avec PHP)

### Générer un nouveau hash de mot de passe

```php
<?php
echo password_hash('votre_mot_de_passe', PASSWORD_BCRYPT);
```

## 🎨 Cas d'Usage

Ce script est utile pour :

✅ Tests de fonctionnalités selon le profil utilisateur
✅ Tests de permissions et d'accès
✅ Tests des flux de paiement avec différents soldes
✅ Tests des fonctionnalités Premium vs Standard
✅ Tests de l'interface artiste vs fan
✅ Démonstrations de la plateforme
✅ Formation des nouveaux développeurs
✅ Tests de performance avec des données réalistes

## 📞 Support

Pour toute question concernant ce script :
- Vérifiez la structure de votre base de données
- Assurez-vous que les tables `users`, `artists` et `admins` existent
- Consultez les logs d'erreur SQL si l'exécution échoue

## 📄 Licence

Ce script fait partie du projet Tchadok et suit la même licence que le projet principal.

---

**Dernière mise à jour** : 2025
**Version** : 1.0
**Auteur** : Équipe Tchadok
