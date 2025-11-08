# Placeholders SVG Tchadok

Ce fichier contient toutes les fonctions pour générer des images placeholder SVG par défaut pour la plateforme Tchadok.

## Fonctions disponibles

### 1. Couvertures d'albums
```php
createAlbumCover($title, $artist, $type = 'Album', $color = '#0066CC', $size = 300)
```
- Génère une couverture d'album avec le titre et l'artiste
- Couleur personnalisable selon le style musical

### 2. Avatars d'artistes
```php
createArtistAvatar($name, $size = 200, $color = '#0066CC')
```
- Crée un avatar circulaire avec l'initiale de l'artiste
- Couleur de fond personnalisable

### 3. Couvertures de tracks
```php
createTrackCover($title, $artist, $duration = '3:30', $color = '#495057', $size = 200)
```
- Génère une miniature pour les singles/tracks
- Style vinyle avec bouton play

### 4. Thumbnails de blog
```php
createBlogThumbnail($title, $category = 'News', $color = '#0066CC', $width = 400, $height = 200)
```
- Images pour les articles de blog
- Badge de catégorie inclus

### 5. Avatars utilisateurs
```php
createUserAvatar($name = 'User', $size = 100)
```
- Avatar simple avec initiale pour les utilisateurs

## Placeholders par défaut

Ces fonctions retournent des data URLs directement utilisables :

- `getDefaultUserAvatar($size)`
- `getDefaultArtistAvatar($size)`
- `getDefaultAlbumCover($width, $height)`
- `getDefaultTrackCover($width, $height)`
- `getDefaultPlaylistCover($width, $height)`
- `getDefaultEventCover($width, $height)`
- `getDefaultGenreCover($width, $height)`
- `getDefaultRadioCover($width, $height)`
- `getDefaultBanner($width, $height)`
- `getDefaultCategoryCover($width, $height)`

## Fonction helper
```php
getPlaceholder($type, $width = null, $height = null)
```
Types supportés : 'user', 'artist', 'album', 'track', 'playlist', 'event', 'genre', 'radio', 'banner', 'category'

## Couleurs Tchadok

Les couleurs utilisées respectent l'identité visuelle :
- Bleu Tchadien : #0066CC
- Jaune Solaire : #FFD700
- Rouge Terre : #CC3333
- Vert Savane : #228B22
- Gris Harmattan : #2C3E50

## Utilisation

1. Inclure le fichier dans votre page :
```php
require_once 'assets/images/placeholders.php';
```

2. Utiliser les fonctions directement dans le HTML :
```php
echo createAlbumCover('Mon Album', 'Mon Artiste', 'Album', '#0066CC');
```

3. Pour les images statiques :
```html
<img src="<?php echo getDefaultAlbumCover(300, 300); ?>" alt="Album par défaut">
```

## Test

Visitez `/test-placeholders.php` pour voir tous les placeholders en action.