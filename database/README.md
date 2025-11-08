# Installation et Génération de Données - Tchadok Platform

Ce dossier contient les scripts pour installer la base de données et générer des données de test pour la plateforme musicale Tchadok.

## 📋 Contenu

- `schema.sql` - Schéma complet de la base de données (toutes les tables)
- `install.php` - Script d'installation qui crée toutes les tables
- `generate-test-data.php` - Script de génération de données de test

## 🚀 Installation

### Prérequis

- PHP 7.4+ avec extension PDO
- MySQL 5.7+ ou MariaDB 10.3+
- Base de données créée (nommée `tchadok` par défaut)
- Fichier `.env` configuré avec les informations de connexion

### Étape 1: Créer les Tables

```bash
php database/install.php
```

Ce script va créer toutes les tables nécessaires :
- `genres` - Genres musicaux
- `albums` - Albums des artistes
- `songs` - Chansons
- `playlists` - Playlists des utilisateurs
- `playlist_songs` - Liaison playlists-chansons
- `listening_history` - Historique d'écoute
- `favorites` - Favoris des utilisateurs
- `subscriptions` - Abonnements premium
- `payment_transactions` - Transactions de paiement
- `artist_followers` - Suivis d'artistes
- `daily_stats` - Statistiques quotidiennes

### Étape 2: Générer les Données de Test

```bash
php database/generate-test-data.php
```

Ce script va créer:
- **10 genres musicaux** (Afrobeat, Hip-Hop Tchadien, R&B Afro, etc.)
- **10 artistes tchadiens** avec leurs profils complets
- **6 albums** avec descriptions
- **~30 chansons** avec liens YouTube temporaires
- Chansons premium et gratuites

## 🎵 Artistes Générés

Le script crée des artistes tchadiens réalistes :
- Cleo Grae (Hip-Hop)
- Mister You TD (Afrobeat)
- Ngariety (R&B)
- Akon One (Hip-Hop/Trap)
- La Diva du Logone (Zouk/Afro-Pop)
- Black Stone (Afro-Trap)
- DJ Tchadiano (DJ/Producer)
- Sister Grace (Gospel)
- Le Roi du Sahel (Traditionnel/Fusion)
- Aminata Star (Dancehall/Afro-Pop)

## 📊 Statistiques Générées

Après l'exécution du script, vous aurez :
- ~30 chansons disponibles
- ~10 chansons Premium (marquées ⭐)
- Plusieurs albums complets
- Tous les genres musicaux principaux

## 🔗 Liens YouTube

Les chansons utilisent des liens YouTube temporaires. Pour utiliser des vraies chansons :
1. Remplacez les URLs YouTube dans la table `songs`
2. Ou uploadez les fichiers MP3 et mettez à jour le champ `file_path`

## 🔄 Réinitialiser les Données

Pour supprimer toutes les données et recommencer :

```sql
-- Attention : Cela supprime TOUTES les données !
TRUNCATE TABLE playlist_songs;
TRUNCATE TABLE playlists;
TRUNCATE TABLE listening_history;
TRUNCATE TABLE favorites;
TRUNCATE TABLE artist_followers;
TRUNCATE TABLE payment_transactions;
TRUNCATE TABLE subscriptions;
TRUNCATE TABLE songs;
TRUNCATE TABLE albums;
TRUNCATE TABLE artists;
TRUNCATE TABLE genres;
-- Puis relancer generate-test-data.php
```

## 🛠️ Personnalisation

### Ajouter Plus d'Artistes

Modifiez le tableau `$artists` dans `generate-test-data.php` :

```php
$artists[] = [
    'stage_name' => 'Nom de Scène',
    'real_name' => 'Vrai Nom',
    'bio' => 'Biographie...',
    'country' => 'Tchad',
    'city' => 'N\'Djamena'
];
```

### Ajouter Plus de Chansons

Modifiez le tableau `$songs` dans `generate-test-data.php` :

```php
$songs[] = [
    'artist' => 'Nom Artiste',
    'album' => 'slug-album',  // ou null
    'genre' => 'slug-genre',
    'title' => 'Titre de la Chanson',
    'duration' => 195,  // en secondes
    'youtube' => 'https://youtube.com/watch?v=...',
    'premium' => 0  // 0 = gratuit, 1 = premium
];
```

## 📝 Notes Importantes

1. **Données de Test** : Ces données sont pour le développement uniquement
2. **YouTube URLs** : Les URLs actuelles sont des exemples, remplacez-les par de vraies URLs
3. **Comptes Artistes** : Tous les artistes générés ont le mot de passe `artist123`
4. **Format Email** : Les emails suivent le format `nom_artiste@tchadok.com`

## 🐛 Dépannage

### Erreur "Database connection failed"
- Vérifiez que MySQL est démarré
- Vérifiez les identifiants dans le fichier `.env`
- Assurez-vous que la base de données existe

### Erreur "Table already exists"
- Normal si vous relancez le script
- Les erreurs de tables existantes sont ignorées

### Erreur "Duplicate entry"
- Normal si vous relancez `generate-test-data.php`
- Le script vérifie les doublons mais ne les met pas à jour

## 📞 Support

Pour toute question ou problème, consultez la documentation principale du projet.
