<?php
/**
 * Documentation API Interactive - Tchadok Platform
 * Interface web pour consulter et tester l'API
 */

// Inclut les en-têtes et styles
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation API - Tchadok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFD700;
            --secondary-color: #2C3E50;
            --accent-color: #0066CC;
            --text-color: #333;
            --bg-color: #f8f9fa;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            height: calc(100vh - 76px);
            overflow-y: auto;
            position: sticky;
            top: 76px;
        }
        
        .sidebar .nav-link {
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background: var(--primary-color);
            color: var(--secondary-color);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: var(--accent-color);
            color: white;
            border-left: 4px solid var(--primary-color);
        }
        
        .content-area {
            padding: 2rem;
        }
        
        .endpoint-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .endpoint-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            padding: 1.5rem;
        }
        
        .method-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .method-get { background: #28a745; }
        .method-post { background: #007bff; }
        .method-put { background: #ffc107; color: #333; }
        .method-delete { background: #dc3545; }
        
        .endpoint-body {
            padding: 2rem;
        }
        
        .try-it-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .response-example {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .section-title {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .parameter-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .parameter-table th {
            background: var(--secondary-color);
            color: white;
            font-weight: 600;
        }
        
        .try-button {
            background: linear-gradient(135deg, var(--primary-color), #ffed4e);
            color: var(--secondary-color);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .try-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-online { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-offline { background: #dc3545; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-code me-2"></i>
                Tchadok API Documentation
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="../index.php">
                    <i class="fas fa-home me-1"></i>
                    Retour au site
                </a>
                <a class="nav-link text-white" href="#" onclick="checkApiHealth()">
                    <span class="status-indicator status-online"></span>
                    Status API
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#overview" onclick="showSection('overview')">
                                <i class="fas fa-info-circle me-2"></i>
                                Vue d'ensemble
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#authentication" onclick="showSection('authentication')">
                                <i class="fas fa-key me-2"></i>
                                Authentification
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#health" onclick="showSection('health')">
                                <i class="fas fa-heartbeat me-2"></i>
                                Health Check
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#radio" onclick="showSection('radio')">
                                <i class="fas fa-broadcast-tower me-2"></i>
                                Radio API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#payments" onclick="showSection('payments')">
                                <i class="fas fa-credit-card me-2"></i>
                                Payments API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#music" onclick="showSection('music')">
                                <i class="fas fa-music me-2"></i>
                                Music API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#examples" onclick="showSection('examples')">
                                <i class="fas fa-code me-2"></i>
                                Exemples
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Content -->
            <div class="col-md-9 col-lg-10">
                <div class="content-area">
                    <!-- Overview Section -->
                    <div id="overview" class="section active">
                        <h1 class="section-title">
                            <i class="fas fa-rocket me-2"></i>
                            Documentation API Tchadok
                        </h1>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-server fa-2x text-primary mb-2"></i>
                                        <h5>Base URL</h5>
                                        <code>http://localhost/tchadok/api/</code>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-code-branch fa-2x text-success mb-2"></i>
                                        <h5>Version</h5>
                                        <span class="badge bg-success">v1.0.0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Information:</strong> Cette API est en mode développement. Aucune authentification n'est requise pour le moment.
                        </div>

                        <h3>Fonctionnalités principales</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <i class="fas fa-broadcast-tower fa-2x text-primary mb-2"></i>
                                        <h5>Radio Streaming</h5>
                                        <p>Diffusion audio en direct avec métadonnées temps réel</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <i class="fas fa-mobile-alt fa-2x text-success mb-2"></i>
                                        <h5>Mobile Money</h5>
                                        <p>Intégration Airtel Money et Moov Money</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <i class="fas fa-music fa-2x text-warning mb-2"></i>
                                        <h5>Contenu Musical</h5>
                                        <p>Gestion des artistes, albums et playlists</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Health Check Section -->
                    <div id="health" class="section" style="display: none;">
                        <h2 class="section-title">Health Check</h2>
                        
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-get">GET</span>
                                <strong>/health</strong>
                                <p class="mb-0 mt-2">Vérifie l'état du serveur et des services</p>
                            </div>
                            <div class="endpoint-body">
                                <h5>Réponse</h5>
                                <div class="response-example">
                                    <pre><code class="language-json">{
  "status": "healthy",
  "version": "1.0.0",
  "environment": "development",
  "timestamp": 1640995200,
  "server_time": "2024-01-01 12:00:00",
  "timezone": "Africa/Ndjamena",
  "services": {
    "database": {"status": "connected", "tables": 12},
    "radio": {"status": "operational", "current_listeners": 245},
    "payments": {
      "airtel_money": {"status": "operational"},
      "moov_money": {"status": "operational"}
    }
  }
}</code></pre>
                                </div>
                                
                                <div class="try-it-section">
                                    <h6><i class="fas fa-play me-2"></i>Tester cet endpoint</h6>
                                    <button class="btn try-button" onclick="testHealthEndpoint()">
                                        <i class="fas fa-rocket me-2"></i>
                                        Exécuter la requête
                                    </button>
                                    <div id="health-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Radio Section -->
                    <div id="radio" class="section" style="display: none;">
                        <h2 class="section-title">Radio API</h2>
                        
                        <!-- Metadata Endpoint -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-get">GET</span>
                                <strong>/radio/metadata.php</strong>
                                <p class="mb-0 mt-2">Métadonnées de la radio en temps réel</p>
                            </div>
                            <div class="endpoint-body">
                                <div class="try-it-section">
                                    <button class="btn try-button" onclick="testRadioMetadata()">
                                        <i class="fas fa-broadcast-tower me-2"></i>
                                        Obtenir les métadonnées
                                    </button>
                                    <div id="radio-metadata-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Stream Endpoint -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-get">GET</span>
                                <strong>/radio/stream.php</strong>
                                <p class="mb-0 mt-2">Flux audio en direct</p>
                            </div>
                            <div class="endpoint-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Cet endpoint retourne un flux audio continu. Utilisez-le dans un lecteur audio.
                                </div>
                                
                                <div class="try-it-section">
                                    <h6>Lecteur Radio</h6>
                                    <audio id="radioPlayer" controls class="w-100">
                                        <source src="../api/radio/stream.php" type="audio/mpeg">
                                        Votre navigateur ne supporte pas l'élément audio.
                                    </audio>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Section -->
                    <div id="payments" class="section" style="display: none;">
                        <h2 class="section-title">Payments API</h2>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        Airtel Money
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Préfixes:</strong> 62, 63, 64, 65, 66, 68, 69</p>
                                        <p><strong>Limites:</strong> 100 - 500,000 XAF</p>
                                        <p><strong>Frais:</strong> 2%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        Moov Money
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Préfixes:</strong> 90-99</p>
                                        <p><strong>Limites:</strong> 200 - 750,000 XAF</p>
                                        <p><strong>Frais:</strong> 1.5%</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Test Form -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-post">POST</span>
                                <strong>/payments/airtel-money.php?action=initiate</strong>
                                <p class="mb-0 mt-2">Initier un paiement Airtel Money</p>
                            </div>
                            <div class="endpoint-body">
                                <div class="try-it-section">
                                    <h6>Tester un paiement</h6>
                                    <form id="paymentForm" onsubmit="testPayment(event)">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Opérateur</label>
                                                    <select class="form-select" id="paymentProvider">
                                                        <option value="airtel">Airtel Money</option>
                                                        <option value="moov">Moov Money</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Numéro de téléphone</label>
                                                    <input type="text" class="form-control" id="paymentPhone" placeholder="62123456" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Montant (XAF)</label>
                                                    <input type="number" class="form-control" id="paymentAmount" placeholder="5000" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Référence</label>
                                                    <input type="text" class="form-control" id="paymentReference" placeholder="ORDER_001" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control" id="paymentDescription" placeholder="Abonnement Premium" required>
                                        </div>
                                        <button type="submit" class="btn try-button">
                                            <i class="fas fa-credit-card me-2"></i>
                                            Initier le paiement
                                        </button>
                                    </form>
                                    <div id="payment-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Music Section -->
                    <div id="music" class="section" style="display: none;">
                        <h2 class="section-title">Music API</h2>
                        
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-get">GET</span>
                                <strong>/tracks/trending</strong>
                                <p class="mb-0 mt-2">Obtenir les titres en tendance</p>
                            </div>
                            <div class="endpoint-body">
                                <div class="try-it-section">
                                    <button class="btn try-button" onclick="testTrendingTracks()">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Voir les tendances
                                    </button>
                                    <div id="trending-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>

                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge method-get">GET</span>
                                <strong>/search?q={query}</strong>
                                <p class="mb-0 mt-2">Rechercher du contenu</p>
                            </div>
                            <div class="endpoint-body">
                                <div class="try-it-section">
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="searchQuery" placeholder="Entrez votre recherche...">
                                        <button class="btn try-button" onclick="testSearch()">
                                            <i class="fas fa-search me-2"></i>
                                            Rechercher
                                        </button>
                                    </div>
                                    <div id="search-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Examples Section -->
                    <div id="examples" class="section" style="display: none;">
                        <h2 class="section-title">Exemples de Code</h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h4>JavaScript/Fetch</h4>
                                <div class="response-example">
                                    <pre><code class="language-javascript">// Métadonnées radio
async function getRadioMetadata() {
  const response = await fetch('/tchadok/api/radio/metadata.php');
  const data = await response.json();
  console.log('Current track:', data.current_track.title);
}

// Paiement Airtel
async function initiatePayment() {
  const response = await fetch('/tchadok/api/payments/airtel-money.php?action=initiate', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      phone: '62123456',
      amount: 5000,
      reference: 'ORDER_001',
      description: 'Abonnement Premium'
    })
  });
  const result = await response.json();
  console.log('Payment ID:', result.transaction_id);
}</code></pre>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>PHP/cURL</h4>
                                <div class="response-example">
                                    <pre><code class="language-php"><?php
// Vérifier statut paiement
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 
  'http://localhost/tchadok/api/payments/airtel-money.php?action=status&transaction_id=AIRTEL_123');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && isset($data['status'])) {
    echo "Status: " . $data['status'];
}

// Recherche
$query = urlencode('mounira');
$url = "http://localhost/tchadok/api/server.php/search?q=$query";
$result = @file_get_contents($url);
if ($result) {
    $data = json_decode($result, true);
}
?></code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <script>
        // Navigation entre sections
        function showSection(sectionId) {
            // Cache toutes les sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Retire la classe active de tous les liens
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Affiche la section sélectionnée
            document.getElementById(sectionId).style.display = 'block';
            
            // Active le lien correspondant
            document.querySelector(`[href="#${sectionId}"]`).classList.add('active');
        }

        // Test Health Endpoint
        async function testHealthEndpoint() {
            const resultDiv = document.getElementById('health-result');
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
            
            try {
                const response = await fetch('../api/server.php/health');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Réponse reçue</h6>
                        <pre class="bg-dark text-light p-3 rounded">${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle me-2"></i>Erreur</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // Test Radio Metadata
        async function testRadioMetadata() {
            const resultDiv = document.getElementById('radio-metadata-result');
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
            
            try {
                const response = await fetch('../api/radio/metadata.php');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h6><i class="fas fa-broadcast-tower me-2"></i>Métadonnées Radio</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Titre actuel:</strong> ${data.current_track.title}<br>
                                <strong>Artiste:</strong> ${data.current_track.artist}<br>
                                <strong>Album:</strong> ${data.current_track.album}<br>
                                <strong>Progression:</strong> ${data.current_track.percentage}%
                            </div>
                            <div class="col-md-6">
                                <strong>Auditeurs:</strong> ${data.stats.listeners}<br>
                                <strong>Pic aujourd'hui:</strong> ${data.stats.peak_today}<br>
                                <strong>Émission:</strong> ${data.current_show ? data.current_show.title : 'Musique Continue'}
                            </div>
                        </div>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle me-2"></i>Erreur</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // Test Payment
        async function testPayment(event) {
            event.preventDefault();
            
            const provider = document.getElementById('paymentProvider').value;
            const phone = document.getElementById('paymentPhone').value;
            const amount = document.getElementById('paymentAmount').value;
            const reference = document.getElementById('paymentReference').value;
            const description = document.getElementById('paymentDescription').value;
            
            const resultDiv = document.getElementById('payment-result');
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Traitement du paiement...</div>';
            
            const apiUrl = provider === 'airtel' ? 
                '../api/payments/airtel-money.php?action=initiate' : 
                '../api/payments/moov-money.php?action=initiate';
            
            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        phone: phone,
                        amount: parseInt(amount),
                        reference: reference,
                        description: description
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Paiement initié avec succès</h6>
                            <p><strong>ID Transaction:</strong> ${data.transaction_id}</p>
                            <p><strong>Statut:</strong> ${data.status}</p>
                            <p><strong>Montant total:</strong> ${data.details.total_amount} XAF (frais: ${data.details.fee} XAF)</p>
                            <button class="btn btn-info btn-sm" onclick="checkPaymentStatus('${data.transaction_id}', '${provider}')">
                                Vérifier le statut
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreur de paiement</h6>
                            <p><strong>Code:</strong> ${data.code}</p>
                            <p><strong>Message:</strong> ${data.error}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle me-2"></i>Erreur réseau</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // Check Payment Status
        async function checkPaymentStatus(transactionId, provider) {
            const apiUrl = provider === 'airtel' ? 
                `../api/payments/airtel-money.php?action=status&transaction_id=${transactionId}` : 
                `../api/payments/moov-money.php?action=status&transaction_id=${transactionId}`;
            
            try {
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                const statusColor = data.status === 'COMPLETED' ? 'success' : 
                                  data.status === 'FAILED' ? 'danger' : 'warning';
                
                document.getElementById('payment-result').innerHTML += `
                    <div class="alert alert-${statusColor} mt-2">
                        <h6><i class="fas fa-info-circle me-2"></i>Statut mis à jour</h6>
                        <p><strong>Statut:</strong> ${data.status}</p>
                        <p><strong>Message:</strong> ${data.message}</p>
                        <p><strong>Heure:</strong> ${data.timestamp}</p>
                    </div>
                `;
            } catch (error) {
                console.error('Erreur lors de la vérification du statut:', error);
            }
        }

        // Test Trending Tracks
        async function testTrendingTracks() {
            const resultDiv = document.getElementById('trending-result');
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
            
            try {
                const response = await fetch('../api/server.php/tracks/trending');
                const data = await response.json();
                
                let tracksHtml = '';
                if (data.tracks && Array.isArray(data.tracks)) {
                    data.tracks.forEach(track => {
                        tracksHtml += `
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <strong>${track.title}</strong><br>
                                    <small class="text-muted">${track.artist}</small>
                                </div>
                                <span class="badge bg-primary">${track.plays.toLocaleString()} écoutes</span>
                            </div>
                        `;
                    });
                } else {
                    tracksHtml = '<p class="text-muted">Aucun titre disponible</p>';
                }
                
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h6><i class="fas fa-chart-line me-2"></i>Titres en tendance</h6>
                        ${tracksHtml}
                        <div class="mt-2">
                            <small class="text-muted">Réponse complète:</small>
                            <pre class="bg-light p-2 rounded mt-1 small">${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle me-2"></i>Erreur</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // Test Search
        async function testSearch() {
            const query = document.getElementById('searchQuery').value;
            if (!query) return;
            
            const resultDiv = document.getElementById('search-result');
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</div>';
            
            try {
                const response = await fetch(`../api/server.php/search?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h6><i class="fas fa-search me-2"></i>Résultats pour "${query}"</h6>
                        <p><strong>Temps de recherche:</strong> ${data.search_time || 'N/A'}</p>
                        <p><strong>Résultats trouvés:</strong> ${data.total_results || 0}</p>
                        <pre class="bg-dark text-light p-3 rounded mt-2">${JSON.stringify(data.results || data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle me-2"></i>Erreur</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // API Health Check
        async function checkApiHealth() {
            try {
                const response = await fetch('../api/server.php/health');
                const data = await response.json();
                
                if (data.status === 'healthy') {
                    document.querySelector('.status-indicator').className = 'status-indicator status-online';
                } else {
                    document.querySelector('.status-indicator').className = 'status-indicator status-warning';
                }
            } catch (error) {
                document.querySelector('.status-indicator').className = 'status-indicator status-offline';
            }
        }

        // Vérifie l'état de l'API au chargement
        document.addEventListener('DOMContentLoaded', function() {
            checkApiHealth();
            // Vérifie toutes les 30 secondes
            setInterval(checkApiHealth, 30000);
        });
    </script>
</body>
</html>