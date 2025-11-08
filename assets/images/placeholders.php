<?php
/**
 * SVG Placeholder Images for Tchadok Platform
 * Ces images peuvent être utilisées comme placeholders par défaut
 */

// Fonction pour créer une couverture d'album avec titre et artiste
function createAlbumCover($title, $artist, $type = 'Album', $color = '#0066CC', $size = 300) {
    $bgColor = urlencode($color);
    $titleFormatted = urlencode(substr($title, 0, 15));
    $artistFormatted = urlencode(substr($artist, 0, 20));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 300 300'%3E%3Crect width='300' height='300' fill='$bgColor'/%3E%3Ccircle cx='150' cy='150' r='80' fill='%23FFD700' opacity='0.2'/%3E%3Ccircle cx='150' cy='150' r='60' fill='%23FFFFFF' opacity='0.3'/%3E%3Ccircle cx='150' cy='150' r='40' fill='%23FFD700'/%3E%3Ccircle cx='150' cy='150' r='15' fill='%232C3E50'/%3E%3Ctext x='150' y='250' text-anchor='middle' fill='white' font-family='Arial' font-size='16' font-weight='bold'%3E$titleFormatted%3C/text%3E%3Ctext x='150' y='270' text-anchor='middle' fill='white' font-family='Arial' font-size='12' opacity='0.8'%3E$artistFormatted%3C/text%3E%3C/svg%3E\" alt=\"$title - $artist\" class=\"img-fluid\">";
}

// Fonction pour créer un avatar d'artiste
function createArtistAvatar($name, $size = 200, $color = '#0066CC') {
    $bgColor = urlencode($color);
    $initial = urlencode(strtoupper(substr($name, 0, 1)));
    $nameFormatted = urlencode(substr($name, 0, 15));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 200 200'%3E%3Ccircle cx='100' cy='100' r='95' fill='$bgColor'/%3E%3Ccircle cx='100' cy='100' r='85' fill='%23FFD700'/%3E%3Ctext x='100' y='120' text-anchor='middle' fill='%232C3E50' font-family='Arial' font-size='60' font-weight='bold'%3E$initial%3C/text%3E%3Ctext x='100' y='180' text-anchor='middle' fill='%232C3E50' font-family='Arial' font-size='14' font-weight='bold'%3E$nameFormatted%3C/text%3E%3C/svg%3E\" alt=\"$name\" class=\"img-fluid rounded-circle\">";
}

// Fonction pour créer une couverture de track
function createTrackCover($title, $artist, $duration = '3:30', $color = '#495057', $size = 200) {
    $bgColor = urlencode($color);
    $titleFormatted = urlencode(substr($title, 0, 12));
    $artistFormatted = urlencode(substr($artist, 0, 15));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='$bgColor'/%3E%3Ccircle cx='100' cy='100' r='70' fill='%231a1a1a'/%3E%3Ccircle cx='100' cy='100' r='60' fill='$bgColor'/%3E%3Ccircle cx='100' cy='100' r='25' fill='%231a1a1a'/%3E%3Cpath d='M100 40 L100 100 L130 80 Z' fill='%23FFD700'/%3E%3Ctext x='100' y='170' text-anchor='middle' fill='white' font-family='Arial' font-size='12' font-weight='bold'%3E$titleFormatted%3C/text%3E%3Ctext x='100' y='185' text-anchor='middle' fill='white' font-family='Arial' font-size='10' opacity='0.8'%3E$artistFormatted%3C/text%3E%3C/svg%3E\" alt=\"$title - $artist\" class=\"img-fluid\">";
}

// Fonction pour créer un placeholder de blog
function createBlogThumbnail($title, $category = 'News', $color = '#0066CC', $width = 400, $height = 200) {
    $bgColor = urlencode($color);
    $titleFormatted = urlencode(substr($title, 0, 20));
    $categoryFormatted = urlencode($category);
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 400 200'%3E%3Crect width='400' height='200' fill='$bgColor'/%3E%3Cg opacity='0.3'%3E%3Cpath d='M0 100 Q100 50 200 100 T400 100 L400 200 L0 200 Z' fill='%23FFD700'/%3E%3C/g%3E%3Crect x='20' y='20' width='80' height='20' rx='10' fill='%23FFD700'/%3E%3Ctext x='60' y='33' text-anchor='middle' fill='%232C3E50' font-family='Arial' font-size='12' font-weight='bold'%3E$categoryFormatted%3C/text%3E%3Ctext x='200' y='120' text-anchor='middle' fill='white' font-family='Arial' font-size='16' font-weight='bold'%3E$titleFormatted%3C/text%3E%3C/svg%3E\" alt=\"$title\" class=\"img-fluid\">";
}

// Fonction pour créer un avatar utilisateur simple
function createUserAvatar($name = 'User', $size = 100) {
    $initial = urlencode(strtoupper(substr($name, 0, 1)));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23e9ecef'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='%236c757d' font-family='Arial' font-size='30' font-weight='bold'%3E$initial%3C/text%3E%3C/svg%3E\" alt=\"$name\" class=\"img-fluid rounded-circle\">";
}

// Alias pour createAvatarPlaceholder (pour compatibilité)
function createAvatarPlaceholder($name = 'User', $color = '#0066CC', $size = 60) {
    $bgColor = urlencode($color);
    $initial = urlencode(strtoupper(substr($name, 0, 1)));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='$bgColor'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='white' font-family='Arial' font-size='30' font-weight='bold'%3E$initial%3C/text%3E%3C/svg%3E\" alt=\"$name\" class=\"img-fluid rounded-circle\">";
}

// Fonction pour créer une couverture de podcast
function createPodcastCover($title, $episode = '', $color = '#667eea', $size = 200) {
    $bgColor = urlencode($color);
    $titleFormatted = urlencode(substr($title, 0, 15));
    $episodeFormatted = urlencode(substr($episode, 0, 10));
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' rx='20' fill='$bgColor'/%3E%3Ccircle cx='100' cy='80' r='40' fill='white' opacity='0.2'/%3E%3Cpath d='M85 65 L85 95 L115 80 Z' fill='white'/%3E%3Ctext x='100' y='140' text-anchor='middle' fill='white' font-family='Arial' font-size='14' font-weight='bold'%3E$titleFormatted%3C/text%3E%3Ctext x='100' y='160' text-anchor='middle' fill='white' font-family='Arial' font-size='12' opacity='0.8'%3E$episodeFormatted%3C/text%3E%3C/svg%3E\" alt=\"$title\" class=\"img-fluid\">";
}

// Fonction pour créer une icône musicale décorative
function createMusicNoteIcon($color = '#FFD700', $size = 50) {
    $iconColor = urlencode($color);
    
    return "<img src=\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 50 50'%3E%3Cpath d='M15 10 L15 35 C15 40 20 45 25 45 C30 45 35 40 35 35 C35 30 30 25 25 25 C20 25 15 30 15 35 M15 10 L35 5 L35 25' stroke='$iconColor' stroke-width='3' fill='none'/%3E%3C/svg%3E\" alt=\"Note musicale\" style=\"opacity: 0.7;\">";
}

// Avatar par défaut pour les utilisateurs
function getDefaultUserAvatar($size = 100) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23e9ecef'/%3E%3Ccircle cx='50' cy='35' r='15' fill='%236c757d'/%3E%3Cpath d='M50 55 C30 55 20 70 20 85 L80 85 C80 70 70 55 50 55' fill='%236c757d'/%3E%3C/svg%3E";
}

// Avatar par défaut pour les artistes
function getDefaultArtistAvatar($size = 200) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 200 200'%3E%3Ccircle cx='100' cy='100' r='95' fill='%230066CC'/%3E%3Ccircle cx='100' cy='100' r='85' fill='%23FFD700'/%3E%3Cg transform='translate(100,100)'%3E%3Cpath d='M-30 -10 L-30 10 L-20 15 L-20 -15 Z M-5 -20 L-5 20 L5 25 L5 -25 Z M20 -15 L20 15 L30 10 L30 -10 Z' fill='%230066CC'/%3E%3C/g%3E%3C/svg%3E";
}

// Placeholder pour album
function getDefaultAlbumCover($width = 300, $height = 300) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 300 300'%3E%3Crect width='300' height='300' fill='%232C3E50'/%3E%3Ccircle cx='150' cy='150' r='80' fill='%23FFD700' opacity='0.2'/%3E%3Ccircle cx='150' cy='150' r='60' fill='%230066CC' opacity='0.3'/%3E%3Ccircle cx='150' cy='150' r='40' fill='%23FFD700'/%3E%3Ccircle cx='150' cy='150' r='15' fill='%232C3E50'/%3E%3Cpath d='M120 120 L120 180 L135 190 L135 110 Z M145 100 L145 200 L160 210 L160 90 Z M170 110 L170 190 L185 180 L185 120 Z' fill='%232C3E50' opacity='0.8'/%3E%3C/svg%3E";
}

// Placeholder pour single/track
function getDefaultTrackCover($width = 200, $height = 200) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23495057'/%3E%3Ccircle cx='100' cy='100' r='70' fill='%231a1a1a'/%3E%3Ccircle cx='100' cy='100' r='60' fill='%23495057'/%3E%3Ccircle cx='100' cy='100' r='25' fill='%231a1a1a'/%3E%3Cpath d='M100 40 L100 100 L130 80 Z' fill='%23FFD700'/%3E%3C/svg%3E";
}

// Placeholder pour playlist
function getDefaultPlaylistCover($width = 300, $height = 300) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 300 300'%3E%3Crect width='300' height='300' fill='%230066CC'/%3E%3Crect x='50' y='50' width='60' height='60' fill='%23FFD700' opacity='0.8'/%3E%3Crect x='120' y='50' width='60' height='60' fill='%23FFD700' opacity='0.6'/%3E%3Crect x='190' y='50' width='60' height='60' fill='%23FFD700' opacity='0.4'/%3E%3Crect x='50' y='120' width='60' height='60' fill='%23FFD700' opacity='0.6'/%3E%3Crect x='120' y='120' width='60' height='60' fill='%23FFD700' opacity='0.4'/%3E%3Crect x='190' y='120' width='60' height='60' fill='%23FFD700' opacity='0.2'/%3E%3Crect x='50' y='190' width='60' height='60' fill='%23FFD700' opacity='0.4'/%3E%3Crect x='120' y='190' width='60' height='60' fill='%23FFD700' opacity='0.2'/%3E%3Crect x='190' y='190' width='60' height='60' fill='%23FFD700' opacity='0.1'/%3E%3Cpath d='M130 130 L130 170 L160 150 Z' fill='white'/%3E%3C/svg%3E";
}

// Placeholder pour événement/concert
function getDefaultEventCover($width = 400, $height = 200) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 400 200'%3E%3Crect width='400' height='200' fill='%23CC3333'/%3E%3Cg opacity='0.3'%3E%3Cpath d='M0 100 Q100 50 200 100 T400 100 L400 200 L0 200 Z' fill='%230066CC'/%3E%3C/g%3E%3Ccircle cx='100' cy='100' r='40' fill='%23FFD700' opacity='0.8'/%3E%3Ccircle cx='200' cy='80' r='30' fill='%23FFD700' opacity='0.6'/%3E%3Ccircle cx='300' cy='90' r='35' fill='%23FFD700' opacity='0.7'/%3E%3Cpath d='M180 120 L180 160 L195 170 L195 110 Z M205 100 L205 180 L220 190 L220 90 Z M230 110 L230 170 L245 160 L245 120 Z' fill='white'/%3E%3C/svg%3E";
}

// Placeholder pour genre musical
function getDefaultGenreCover($width = 250, $height = 250) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 250 250'%3E%3Crect width='250' height='250' fill='%23228B22'/%3E%3Cg transform='translate(125,125)'%3E%3Cg opacity='0.3'%3E%3Ccircle r='100' fill='none' stroke='%23FFD700' stroke-width='2'/%3E%3Ccircle r='80' fill='none' stroke='%23FFD700' stroke-width='2'/%3E%3Ccircle r='60' fill='none' stroke='%23FFD700' stroke-width='2'/%3E%3Ccircle r='40' fill='none' stroke='%23FFD700' stroke-width='2'/%3E%3C/g%3E%3Cpath d='M-30 -20 L-30 20 L-15 30 L-15 -30 Z M0 -35 L0 35 L15 45 L15 -45 Z M30 -30 L30 30 L45 20 L45 -20 Z' fill='%23FFD700'/%3E%3C/g%3E%3C/svg%3E";
}

// Placeholder pour radio/station
function getDefaultRadioCover($width = 300, $height = 300) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 300 300'%3E%3Crect width='300' height='300' fill='%231a2332'/%3E%3Cg transform='translate(150,150)'%3E%3Ccircle r='80' fill='none' stroke='%230066CC' stroke-width='4'/%3E%3Ccircle r='60' fill='none' stroke='%230066CC' stroke-width='3' opacity='0.7'/%3E%3Ccircle r='40' fill='none' stroke='%230066CC' stroke-width='2' opacity='0.5'/%3E%3Ccircle r='20' fill='%23FFD700'/%3E%3Cg%3E%3Cpath d='M0 -80 L0 -60' stroke='%230066CC' stroke-width='4'/%3E%3Cpath d='M0 80 L0 60' stroke='%230066CC' stroke-width='4'/%3E%3Cpath d='M-80 0 L-60 0' stroke='%230066CC' stroke-width='4'/%3E%3Cpath d='M80 0 L60 0' stroke='%230066CC' stroke-width='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E";
}

// Placeholder pour bannière
function getDefaultBanner($width = 1200, $height = 400) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 1200 400'%3E%3Cdefs%3E%3ClinearGradient id='grad1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%230066CC;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23FFD700;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='1200' height='400' fill='url(%23grad1)'/%3E%3Cg opacity='0.3'%3E%3Cpath d='M200 150 L200 250 L230 270 L230 130 Z M280 100 L280 300 L310 320 L310 80 Z M360 130 L360 270 L390 250 L390 150 Z' fill='white'/%3E%3Cpath d='M600 120 L600 280 L640 300 L640 100 Z M680 80 L680 320 L720 340 L720 60 Z M760 100 L760 300 L800 280 L800 120 Z' fill='white'/%3E%3Cpath d='M900 140 L900 260 L930 280 L930 120 Z M970 90 L970 310 L1000 330 L1000 70 Z' fill='white'/%3E%3C/g%3E%3C/svg%3E";
}

// Placeholder pour catégorie
function getDefaultCategoryCover($width = 200, $height = 200) {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' rx='20' fill='%23f8f9fa'/%3E%3Cg transform='translate(100,100)'%3E%3Crect x='-40' y='-40' width='35' height='35' rx='5' fill='%230066CC'/%3E%3Crect x='5' y='-40' width='35' height='35' rx='5' fill='%23FFD700'/%3E%3Crect x='-40' y='5' width='35' height='35' rx='5' fill='%23CC3333'/%3E%3Crect x='5' y='5' width='35' height='35' rx='5' fill='%23228B22'/%3E%3C/g%3E%3C/svg%3E";
}

// Fonction helper pour obtenir n'importe quel placeholder
function getPlaceholder($type, $width = null, $height = null) {
    switch($type) {
        case 'user':
            return getDefaultUserAvatar($width ?? 100);
        case 'artist':
            return getDefaultArtistAvatar($width ?? 200);
        case 'album':
            return getDefaultAlbumCover($width ?? 300, $height ?? 300);
        case 'track':
            return getDefaultTrackCover($width ?? 200, $height ?? 200);
        case 'playlist':
            return getDefaultPlaylistCover($width ?? 300, $height ?? 300);
        case 'event':
            return getDefaultEventCover($width ?? 400, $height ?? 200);
        case 'genre':
            return getDefaultGenreCover($width ?? 250, $height ?? 250);
        case 'radio':
            return getDefaultRadioCover($width ?? 300, $height ?? 300);
        case 'banner':
            return getDefaultBanner($width ?? 1200, $height ?? 400);
        case 'category':
            return getDefaultCategoryCover($width ?? 200, $height ?? 200);
        default:
            return getDefaultAlbumCover($width ?? 300, $height ?? 300);
    }
}
?>