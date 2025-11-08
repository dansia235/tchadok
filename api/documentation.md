# Documentation API - Tchadok Platform

## Vue d'ensemble

L'API Tchadok est une API REST complète qui fournit des services pour la plateforme musicale tchadienne. Elle inclut des fonctionnalités de streaming radio, de gestion des paiements mobiles, et de gestion du contenu musical.

**Base URL:** `http://localhost/tchadok/api/`

**Version:** v1.0.0

**Format de réponse:** JSON

---

## Authentification

Actuellement en mode développement, aucune authentification n'est requise. En production, utilisez:

```http
Authorization: Bearer YOUR_TOKEN
```

ou

```http
X-API-Key: YOUR_API_KEY
```

---

## Endpoints Principaux

### 🏥 Health Check

**GET** `/health`

Vérifie l'état du serveur et des services.

**Réponse:**
```json
{
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
      "airtel_money": {"status": "operational", "response_time": "150ms"},
      "moov_money": {"status": "operational", "response_time": "200ms"}
    }
  }
}
```

### 📊 Statistiques

**GET** `/stats`

Retourne les statistiques générales de la plateforme.

**Réponse:**
```json
{
  "platform_stats": {
    "total_users": 10543,
    "active_users_today": 1987,
    "total_tracks": 18765,
    "total_artists": 987,
    "total_albums": 3421,
    "premium_subscribers": 654
  },
  "radio_stats": {
    "current_listeners": 287,
    "peak_today": 543,
    "total_hours_streamed_today": 3456,
    "top_genre_today": "Afrobeat"
  },
  "payment_stats": {
    "transactions_today": 98,
    "total_revenue_today": 124500,
    "successful_rate": "92%",
    "most_used_method": "Airtel Money"
  }
}
```

---

## 🎵 Radio API

### Métadonnées Radio

**GET** `/radio/metadata.php`

Retourne les informations actuelles de la radio en temps réel.

**Réponse:**
```json
{
  "success": true,
  "timestamp": 1640995200,
  "server_time": "2024-01-01 12:00:00",
  "station": {
    "name": "Tchadok Radio",
    "tagline": "24/7 Musique Tchadienne",
    "frequency": "101.5 FM",
    "website": "https://tchadok.td",
    "is_live": true
  },
  "current_track": {
    "id": 1,
    "title": "Dounya",
    "artist": "Mounira Mitchala",
    "album": "Renaissance Africaine",
    "duration": 255,
    "progress": 120,
    "remaining": 135,
    "genre": "Soul/R&B",
    "year": 2024,
    "cover_url": "/assets/images/albums/mounira_dounya.jpg",
    "percentage": 47.1
  },
  "next_track": {
    "id": 2,
    "title": "N'Djamena City",
    "artist": "H2O Assoumane",
    "album": "Révolution Urbaine",
    "duration": 198
  },
  "current_show": {
    "id": 1,
    "title": "Réveil Musical",
    "host": "Abakar Mahamat",
    "description": "Commencez la journée avec les hits du moment",
    "start_time": "06:00",
    "end_time": "09:00"
  },
  "stats": {
    "listeners": 287,
    "peak_today": 543,
    "total_tracks_today": 176,
    "uptime": "99.8%"
  }
}
```

### Stream Radio

**GET** `/radio/stream.php`

Flux audio en direct de la radio.

**Headers de réponse:**
- `Content-Type: audio/mpeg`
- `Cache-Control: no-cache`

**Utilisation:**
```html
<audio controls>
  <source src="http://localhost/tchadok/api/radio/stream.php" type="audio/mpeg">
</audio>
```

---

## 💰 Payment APIs

### Airtel Money

#### Initier un paiement

**POST** `/payments/airtel-money.php?action=initiate`

**Body:**
```json
{
  "phone": "62123456",
  "amount": 5000,
  "reference": "TCHADOK_001",
  "description": "Abonnement Premium"
}
```

**Réponse succès:**
```json
{
  "success": true,
  "transaction_id": "AIRTEL_20240101120000_1234",
  "status": "PENDING",
  "message": "Payment initiated successfully",
  "details": {
    "phone": "62123456",
    "amount": 5000,
    "currency": "XAF",
    "reference": "TCHADOK_001",
    "description": "Abonnement Premium",
    "fee": 100,
    "total_amount": 5100
  },
  "next_step": "Customer will receive SMS to confirm payment"
}
```

**Réponse erreur:**
```json
{
  "success": false,
  "error": "Invalid Airtel Money number",
  "code": "INVALID_PHONE",
  "details": "This number is not a valid Airtel Money number for Chad"
}
```

#### Vérifier le statut

**GET** `/payments/airtel-money.php?action=status&transaction_id=AIRTEL_20240101120000_1234`

**Réponse:**
```json
{
  "success": true,
  "transaction_id": "AIRTEL_20240101120000_1234",
  "status": "COMPLETED",
  "message": "Payment completed successfully",
  "timestamp": "2024-01-01 12:05:00",
  "payment_details": {
    "airtel_reference": "AMT123456",
    "fee_charged": 100,
    "completion_time": "2024-01-01 12:05:00"
  }
}
```

#### Vérifier le solde

**GET** `/payments/airtel-money.php?action=balance&phone=62123456`

**Réponse:**
```json
{
  "success": true,
  "phone": "62123456",
  "balance": 75000,
  "currency": "XAF",
  "account_status": "ACTIVE"
}
```

### Moov Money

#### Initier un paiement

**POST** `/payments/moov-money.php?action=initiate`

**Body:**
```json
{
  "phone": "90123456",
  "amount": 5000,
  "reference": "TCHADOK_002",
  "description": "Achat de crédits"
}
```

**Réponse succès:**
```json
{
  "success": true,
  "transaction_id": "MOOV_20240101120000_5678",
  "status": "PENDING",
  "message": "Payment request sent to customer",
  "details": {
    "phone": "90123456",
    "amount": 5000,
    "currency": "XAF",
    "reference": "TCHADOK_002",
    "description": "Achat de crédits",
    "fee": 75,
    "total_amount": 5075
  },
  "next_step": "Customer will receive USSD push to confirm payment",
  "expires_in": 300
}
```

#### Remboursement

**POST** `/payments/moov-money.php?action=refund`

**Body:**
```json
{
  "original_transaction_id": "MOOV_20240101120000_5678",
  "amount": 5000,
  "reason": "Customer request"
}
```

**Réponse:**
```json
{
  "success": true,
  "refund_id": "MOOV_REFUND_20240101130000_9012",
  "original_transaction_id": "MOOV_20240101120000_5678",
  "status": "PROCESSING",
  "amount": 5000,
  "currency": "XAF",
  "reason": "Customer request",
  "estimated_completion": "2024-01-01 14:00:00",
  "message": "Refund is being processed"
}
```

---

## 🎶 Music API

### Tracks Tendances

**GET** `/tracks/trending`

**Réponse:**
```json
{
  "tracks": [
    {
      "id": 1,
      "title": "Dounya",
      "artist": "Mounira Mitchala",
      "plays": 45230
    },
    {
      "id": 2,
      "title": "N'Djamena City",
      "artist": "H2O Assoumane",
      "plays": 38950
    }
  ],
  "total": 5,
  "updated_at": "2024-01-01 12:00:00"
}
```

### Artistes en Vedette

**GET** `/artists/featured`

**Réponse:**
```json
{
  "artists": [
    {
      "id": 1,
      "name": "Mounira Mitchala",
      "genre": "Soul/R&B",
      "followers": 45000
    },
    {
      "id": 2,
      "name": "H2O Assoumane",
      "genre": "Hip Hop",
      "followers": 67000
    }
  ],
  "total": 4,
  "updated_at": "2024-01-01 12:00:00"
}
```

### Recherche

**GET** `/search?q=mounira`

**Réponse:**
```json
{
  "query": "mounira",
  "results": {
    "tracks": [
      {
        "id": 1,
        "title": "Dounya",
        "artist": "Mounira Mitchala",
        "relevance": 0.95
      }
    ],
    "artists": [
      {
        "id": 1,
        "name": "Mounira Mitchala",
        "genre": "Soul/R&B",
        "relevance": 0.90
      }
    ],
    "albums": [
      {
        "id": 1,
        "title": "Renaissance Africaine",
        "artist": "Mounira Mitchala",
        "relevance": 0.85
      }
    ]
  },
  "total_results": 3,
  "search_time": "120ms"
}
```

### Profil Utilisateur

**GET** `/user/profile`

**Réponse:**
```json
{
  "user": {
    "id": 1,
    "username": "demo_user",
    "email": "demo@tchadok.td",
    "premium": true,
    "created_at": "2024-01-15"
  }
}
```

---

## ⚙️ Configuration et Limites

### Limites de taux
- **Développement:** 1000 requêtes/heure
- **Production:** À définir selon le plan

### Formats supportés
- **Audio:** MP3, AAC
- **Images:** JPG, PNG, WebP
- **Données:** JSON uniquement

### Codes d'erreur HTTP

| Code | Description |
|------|-------------|
| 200  | Succès |
| 400  | Requête malformée |
| 401  | Non autorisé |
| 404  | Ressource non trouvée |
| 405  | Méthode non autorisée |
| 429  | Trop de requêtes |
| 500  | Erreur serveur |

### Préfixes téléphoniques supportés

**Airtel Money:** 62, 63, 64, 65, 66, 68, 69

**Moov Money:** 90, 91, 92, 93, 94, 95, 96, 97, 98, 99

### Limites de paiement

| Provider | Minimum | Maximum | Frais |
|----------|---------|---------|-------|
| Airtel Money | 100 XAF | 500,000 XAF | 2% |
| Moov Money | 200 XAF | 750,000 XAF | 1.5% |

---

## 🛠️ Environnement de Développement

### Installation

1. Exécutez le fichier d'installation:
```bash
http://localhost/tchadok/install.php
```

2. Testez la santé de l'API:
```bash
curl http://localhost/tchadok/api/health
```

### Logs

Les logs sont stockés dans `/logs/`:
- `api_server.log` - Logs généraux de l'API
- `radio.log` - Logs du streaming radio
- `radio_metadata.log` - Logs des métadonnées
- `payments_airtel.log` - Logs Airtel Money
- `payments_moov.log` - Logs Moov Money

### Variables d'environnement

```php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tchadok_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('API_SECRET_KEY', 'generated_key');
```

---

## 📝 Exemples d'utilisation

### JavaScript/Fetch

```javascript
// Récupérer les métadonnées radio
async function getRadioMetadata() {
  const response = await fetch('/tchadok/api/radio/metadata.php');
  const data = await response.json();
  console.log('Current track:', data.current_track.title);
}

// Initier un paiement Airtel
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
  console.log('Payment initiated:', result.transaction_id);
}
```

### PHP/cURL

```php
// Vérifier le statut d'un paiement
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tchadok/api/payments/airtel-money.php?action=status&transaction_id=AIRTEL_20240101120000_1234');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo "Payment status: " . $data['status'];
```

### Python/Requests

```python
import requests

# Recherche de contenu
response = requests.get('http://localhost/tchadok/api/search', params={'q': 'mounira'})
data = response.json()
print(f"Found {data['total_results']} results")
```

---

## 🔄 Webhooks

Pour recevoir des notifications de paiement en temps réel:

**POST** `/payments/airtel-money.php?action=webhook`
**POST** `/payments/moov-money.php?action=webhook`

**Format webhook:**
```json
{
  "event": "payment.completed",
  "transaction_id": "AIRTEL_20240101120000_1234",
  "status": "COMPLETED",
  "amount": 5000,
  "timestamp": "2024-01-01 12:05:00"
}
```

---

## 📞 Support

Pour toute question ou problème:
- **Email:** dev@tchadok.td
- **Documentation complète:** https://docs.tchadok.td/api
- **Status page:** http://localhost/tchadok/api/health

---

*Documentation générée automatiquement - Version 1.0.0*