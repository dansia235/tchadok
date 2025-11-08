<?php
// Test simple du générateur de données
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test insertion d'un utilisateur simple
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, country, city, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $testData = [
        'test_user_' . rand(1000, 9999),
        'test@example.com',
        password_hash('12345678', PASSWORD_DEFAULT),
        'Test',
        'User',
        '+235 12 34 56 78',
        'Tchad',
        'N\'Djamena',
        1,
        1
    ];
    
    $stmt->execute($testData);
    $userId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Test utilisateur créé avec succès',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
?>