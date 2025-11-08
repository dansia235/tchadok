<?php
require_once 'assets/images/placeholders.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Placeholders SVG - Tchadok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-5">Test des Placeholders SVG Tchadok</h1>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <h3>Albums</h3>
                <div class="row">
                    <div class="col-6">
                        <?php echo createAlbumCover('Renaissance', 'Mounira Mitchala', 'Album', '#0066CC'); ?>
                    </div>
                    <div class="col-6">
                        <?php echo createAlbumCover('Beats du Tchad', 'H2O Assoumane', 'EP', '#CC3333'); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <h3>Artistes</h3>
                <div class="row">
                    <div class="col-6">
                        <?php echo createArtistAvatar('Clément Masdongar', 150, '#FFD700'); ?>
                    </div>
                    <div class="col-6">
                        <?php echo createArtistAvatar('Caleb Rimtobaye', 150, '#228B22'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <h3>Tracks</h3>
                <div class="row">
                    <div class="col-6">
                        <?php echo createTrackCover('Dounya', 'Mounira', '4:15', '#667eea'); ?>
                    </div>
                    <div class="col-6">
                        <?php echo createTrackCover('N\'Djamena', 'Various', '3:42', '#f093fb'); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <h3>Blog & Avatars</h3>
                <div class="mb-3">
                    <?php echo createBlogThumbnail('Festival de Musique Tchadienne 2024', 'Événement', '#4facfe'); ?>
                </div>
                <div class="d-flex gap-2">
                    <?php echo createUserAvatar('Admin', 60); ?>
                    <?php echo createUserAvatar('Djamil', 60); ?>
                    <?php echo createUserAvatar('Fatima', 60); ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12 mb-4">
                <h3>Placeholders par défaut</h3>
                <div class="row">
                    <div class="col-md-3">
                        <h5>Genre</h5>
                        <img src="<?php echo getDefaultGenreCover(); ?>" alt="Genre" class="img-fluid">
                    </div>
                    <div class="col-md-3">
                        <h5>Playlist</h5>
                        <img src="<?php echo getDefaultPlaylistCover(); ?>" alt="Playlist" class="img-fluid">
                    </div>
                    <div class="col-md-3">
                        <h5>Radio</h5>
                        <img src="<?php echo getDefaultRadioCover(); ?>" alt="Radio" class="img-fluid">
                    </div>
                    <div class="col-md-3">
                        <h5>Événement</h5>
                        <img src="<?php echo getDefaultEventCover(); ?>" alt="Événement" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h3>Bannière</h3>
                <img src="<?php echo getDefaultBanner(); ?>" alt="Bannière" class="img-fluid">
            </div>
        </div>
    </div>
</body>
</html>