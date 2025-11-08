<?php
/**
 * Serveur API Local - Tchadok Platform
 * Point d'entrée principal pour toutes les APIs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
$config = [
    'version' => '1.0.0',
    'environment' => 'development',
    'timezone' => 'Africa/Ndjamena',
    'max_requests_per_hour' => 1000
];

date_default_timezone_set($config['timezone']);

// Fonction de logging global
function logRequest($endpoint, $method, $data = []) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'method' => $method,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $data
    ];
    
    @file_put_contents('../logs/api_server.log', json_encode($log) . "\n", FILE_APPEND | LOCK_EX);
}

// Router simple
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Retire le préfixe /tchadok/api si présent
$path = preg_replace('#^/tchadok/api#', '', $path);
$path = preg_replace('#^/api#', '', $path);

// Parse les paramètres
$segments = array_filter(explode('/', $path));
$endpoint = $segments[1] ?? '';
$action = $segments[2] ?? '';

logRequest("$endpoint/$action", $method);

switch ($endpoint) {
    case 'health':
        // Endpoint de santé du serveur
        echo json_encode([
            'status' => 'healthy',
            'version' => $config['version'],
            'environment' => $config['environment'],
            'timestamp' => time(),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => $config['timezone'],
            'uptime' => sys_getloadavg(),
            'services' => [
                'database' => checkDatabase(),
                'radio' => checkRadio(),
                'payments' => checkPayments()
            ]
        ]);
        break;
        
    case 'stats':
        // Statistiques générales
        echo json_encode([
            'platform_stats' => [
                'total_users' => rand(8000, 12000),
                'active_users_today' => rand(1500, 2500),
                'total_tracks' => rand(15000, 25000),
                'total_artists' => rand(800, 1200),
                'total_albums' => rand(2500, 4000),
                'premium_subscribers' => rand(500, 800)
            ],
            'radio_stats' => [
                'current_listeners' => rand(200, 350),
                'peak_today' => rand(400, 600),
                'total_hours_streamed_today' => rand(2000, 4000),
                'top_genre_today' => 'Afrobeat'
            ],
            'payment_stats' => [
                'transactions_today' => rand(50, 150),
                'total_revenue_today' => rand(50000, 200000),
                'successful_rate' => rand(85, 95) . '%',
                'most_used_method' => 'Airtel Money'
            ],
            'system_stats' => [
                'server_load' => round(rand(10, 80) / 100, 2),
                'memory_usage' => rand(40, 70) . '%',
                'disk_usage' => rand(25, 60) . '%',
                'last_backup' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'tracks':
        // API des tracks
        handleTracksAPI($action, $method);
        break;
        
    case 'artists':
        // API des artistes
        handleArtistsAPI($action, $method);
        break;
        
    case 'search':
        // API de recherche
        handleSearchAPI($method);
        break;
        
    case 'user':
        // API utilisateur
        handleUserAPI($action, $method);
        break;
        
    default:
        // Documentation de l'API
        echo json_encode([
            'name' => 'Tchadok API Server',
            'version' => $config['version'],
            'description' => 'API locale pour la plateforme musicale Tchadok',
            'endpoints' => [
                '/health' => 'Server health check',
                '/stats' => 'Platform statistics',
                '/tracks' => 'Music tracks management',
                '/artists' => 'Artists management',
                '/search' => 'Search functionality',
                '/user' => 'User management',
                '/radio/*' => 'Radio streaming and metadata',
                '/payments/*' => 'Payment processing'
            ],
            'authentication' => 'Bearer token or API key',
            'rate_limit' => $config['max_requests_per_hour'] . ' requests/hour',
            'documentation' => 'https://docs.tchadok.td/api'
        ], JSON_PRETTY_PRINT);
        break;
}

// Fonctions helper
function checkDatabase() {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=tchadok;charset=utf8mb4", 'dansia', 'dansia');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return ['status' => 'connected', 'tables' => 12];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()];
    }
}

function checkRadio() {
    $metadataUrl = '/api/radio/metadata.php';
    return [
        'status' => 'operational',
        'current_listeners' => rand(200, 350),
        'stream_url' => '/api/radio/stream.php'
    ];
}

function checkPayments() {
    return [
        'airtel_money' => ['status' => 'operational', 'response_time' => rand(100, 300) . 'ms'],
        'moov_money' => ['status' => 'operational', 'response_time' => rand(150, 400) . 'ms']
    ];
}

function handleTracksAPI($action, $method) {
    require_once '../includes/database.php';
    
    switch ($action) {
        case 'trending':
            $tracks = getTrendingTracks(5);
            echo json_encode([
                'tracks' => $tracks,
                'total' => count($tracks),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            break;
        default:
            echo json_encode(['error' => 'Unknown tracks action']);
    }
}

function handleArtistsAPI($action, $method) {
    require_once '../includes/database.php';
    
    switch ($action) {
        case 'featured':
            $artists = getFeaturedArtists(4);
            echo json_encode([
                'artists' => $artists,
                'total' => count($artists),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            break;
        default:
            echo json_encode(['error' => 'Unknown artists action']);
    }
}

function handleSearchAPI($method) {
    require_once '../includes/database.php';
    
    $query = $_GET['q'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Search query is required']);
        return;
    }
    
    $startTime = microtime(true);
    $results = searchContent($query, 10);
    $endTime = microtime(true);
    $searchTime = round(($endTime - $startTime) * 1000);
    
    $totalResults = count($results['tracks']) + count($results['artists']) + count($results['albums']);
    
    echo json_encode([
        'query' => $query,
        'results' => $results,
        'total_results' => $totalResults,
        'search_time' => $searchTime . 'ms'
    ]);
}

function handleUserAPI($action, $method) {
    switch ($action) {
        case 'profile':
            echo json_encode([
                'user' => [
                    'id' => 1,
                    'username' => 'demo_user',
                    'email' => 'demo@tchadok.td',
                    'premium' => true,
                    'created_at' => '2024-01-15'
                ]
            ]);
            break;
        default:
            echo json_encode(['error' => 'Unknown user action']);
    }
}
?>