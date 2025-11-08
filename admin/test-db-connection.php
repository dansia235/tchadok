<?php
/**
 * Test de connexion à la base de données
 * Tchadok Platform
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'details' => []
];

try {
    // Test 1: Vérifier les variables d'environnement
    $response['details']['env'] = [
        'DB_HOST' => env('DB_HOST'),
        'DB_DATABASE' => env('DB_DATABASE'),
        'DB_USERNAME' => env('DB_USERNAME'),
        'DB_PASSWORD' => env('DB_PASSWORD') ? '***' : 'NOT SET'
    ];

    // Test 2: Obtenir l'instance de la base de données
    $dbInstance = TchadokDatabase::getInstance();
    if (!$dbInstance) {
        throw new Exception("Impossible d'obtenir l'instance de la base de données");
    }
    $response['details']['instance'] = 'OK';

    // Test 3: Obtenir la connexion PDO
    $db = $dbInstance->getConnection();
    if (!$db) {
        throw new Exception("La connexion PDO est null");
    }
    $response['details']['connection'] = 'OK';

    // Test 4: Tester la connexion avec une requête simple
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['test'] !== 1) {
        throw new Exception("La requête de test n'a pas retourné le bon résultat");
    }
    $response['details']['query_test'] = 'OK';

    // Test 5: Vérifier l'existence des tables
    $tables = ['users', 'artists', 'admins'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $response['details']['tables'][$table] = 'MISSING';
        } else {
            $response['details']['tables'][$table] = 'EXISTS';
        }
    }

    // Test 6: Vérifier la structure de la table users
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response['details']['users_columns'] = $columns;

    // Test 7: Compter les utilisateurs existants
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['details']['users_count'] = $count['count'];

    // Test 8: Compter les comptes de test
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email LIKE :email");
    $stmt->execute(['email' => '%@test.tchadok.td']);
    $testCount = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['details']['test_accounts_count'] = $testCount['count'];

    $response['success'] = true;
    $response['message'] = 'La connexion à la base de données fonctionne correctement';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['details']['error'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
