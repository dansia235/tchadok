<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

// Gestion des artistes - API pour le dashboard
try {
    $tchadokDB = TchadokDatabase::getInstance();
    $pdo = $tchadokDB->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'list':
            // Récupérer la liste des artistes
            $artists = $pdo->query("
                SELECT id, stage_name, real_name, genres, verified, featured, is_active, created_at
                FROM artists 
                ORDER BY stage_name ASC 
                LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'artists' => $artists]);
            break;
            
        case 'get':
            // Récupérer un artiste spécifique
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'artiste requis');
            }
            
            $artist = $pdo->prepare("
                SELECT a.*, u.username, u.email, u.first_name, u.last_name,
                       COUNT(DISTINCT al.id) as album_count,
                       COUNT(DISTINCT t.id) as track_count,
                       COALESCE(SUM(t.total_streams), 0) as total_streams
                FROM artists a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN albums al ON a.id = al.artist_id
                LEFT JOIN tracks t ON a.id = t.artist_id
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $artist->execute([$id]);
            $artistData = $artist->fetch(PDO::FETCH_ASSOC);
            
            if (!$artistData) {
                throw new Exception('Artiste non trouvé');
            }
            
            echo json_encode(['success' => true, 'artist' => $artistData]);
            break;
            
        case 'create':
            // Créer un nouvel artiste
            $stageName = $_POST['stage_name'] ?? '';
            $realName = $_POST['real_name'] ?? '';
            $genres = $_POST['genres'] ?? '';
            $bio = $_POST['bio'] ?? '';
            $country = $_POST['country'] ?? '';
            $website = $_POST['website'] ?? '';
            $instagram = $_POST['instagram'] ?? '';
            $verified = isset($_POST['verified']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($stageName)) {
                throw new Exception('Le nom de scène est requis');
            }
            
            // Vérifier si le nom de scène existe déjà
            $check = $pdo->prepare("SELECT id FROM artists WHERE stage_name = ?");
            $check->execute([$stageName]);
            if ($check->fetch()) {
                throw new Exception('Ce nom de scène existe déjà');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO artists (stage_name, real_name, genres, bio, country, website, instagram, verified, featured, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $stageName, $realName, $genres, $bio, $country, $website, $instagram, $verified, $featured, $isActive
            ]);
            
            if ($result) {
                $artistId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Artiste créé avec succès', 'artist_id' => $artistId]);
            } else {
                throw new Exception('Erreur lors de la création de l\'artiste');
            }
            break;
            
        case 'update':
            // Mettre à jour un artiste
            $id = $_POST['artist_id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'artiste requis');
            }
            
            $stageName = $_POST['stage_name'] ?? '';
            $realName = $_POST['real_name'] ?? '';
            $genres = $_POST['genres'] ?? '';
            $bio = $_POST['bio'] ?? '';
            $verified = isset($_POST['verified']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($stageName)) {
                throw new Exception('Le nom de scène est requis');
            }
            
            $stmt = $pdo->prepare("
                UPDATE artists 
                SET stage_name = ?, real_name = ?, genres = ?, bio = ?, verified = ?, featured = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$stageName, $realName, $genres, $bio, $verified, $featured, $isActive, $id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Artiste mis à jour avec succès']);
            } else {
                throw new Exception('Erreur lors de la mise à jour de l\'artiste');
            }
            break;
            
        case 'delete':
            // Supprimer un artiste
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'artiste requis');
            }
            
            // Vérifier s'il y a des pistes liées
            $checkTracks = $pdo->prepare("SELECT COUNT(*) FROM tracks WHERE artist_id = ?");
            $checkTracks->execute([$id]);
            $trackCount = $checkTracks->fetchColumn();
            
            if ($trackCount > 0) {
                throw new Exception('Impossible de supprimer: cet artiste a des pistes associées');
            }
            
            $stmt = $pdo->prepare("DELETE FROM artists WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Artiste supprimé avec succès']);
            } else {
                throw new Exception('Erreur lors de la suppression de l\'artiste');
            }
            break;
            
        case 'stats':
            // Statistiques d'un artiste
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID d\'artiste requis');
            }
            
            $stats = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT t.id) as total_tracks,
                    COUNT(DISTINCT al.id) as total_albums,
                    COALESCE(SUM(t.total_streams), 0) as total_streams,
                    COALESCE(SUM(t.total_downloads), 0) as total_downloads,
                    COALESCE(AVG(t.duration), 0) as avg_duration
                FROM artists a
                LEFT JOIN tracks t ON a.id = t.artist_id
                LEFT JOIN albums al ON a.id = al.artist_id
                WHERE a.id = ?
            ");
            $stats->execute([$id]);
            $statsData = $stats->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $statsData]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>