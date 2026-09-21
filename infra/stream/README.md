# Stream Engine - Tchadok

Configuration recommandee pour une vraie radio 24/7:

- `Icecast` = serveur de flux
- `Liquidsoap` = moteur de lecture continue (MP3, podcasts, emissions)
- `Apache` = reverse proxy HTTPS du sous-domaine radio

Si vous preferez LibreTime, voir `infra/stream/LIBRETIME.md`.

## 1) Prerequis

- VPS Linux (Hostinger VPS recommande)
- Hebergement mutualise/non-root: non supporte pour ce stack
- Docker + Docker Compose
- DNS `radio.tchadok.td` vers le VPS
- Certificat TLS (Let's Encrypt)

## 2) Demarrage local/VPS

```bash
cd infra/stream
cp .env.example .env
mkdir -p media/music media/podcasts media/shows
```

Ajoutez vos fichiers MP3 dans `infra/stream/media/`:

- `music/*.mp3`
- `podcasts/*.mp3`
- `shows/*.mp3`
- `fallback.mp3` (obligatoire)

Mettez a jour `infra/stream/liquidsoap/rotation.m3u`.

Generation automatique de `rotation.m3u`:

```bash
 C:\xampp\php\php.exe scripts/generate-rotation.php
```

Option melange:

```bash
C:\xampp\php\php.exe scripts/generate-rotation.php --shuffle
```

Le script scanne `infra/stream/media` et ecrit les chemins container `/opt/radio/media/...`.

Puis lancez:

```bash
docker compose --env-file .env up -d
```

Tests:

```bash
curl http://127.0.0.1:8000/status-json.xsl
```

Le flux doit etre accessible sur:

- `http://<ip-vps>:8000/tchadok.mp3`

## 3) Apache (HTTPS public)

Utilisez `infra/stream/apache/radio.vhost.conf` comme base.

Activer modules:

```bash
sudo a2enmod proxy proxy_http ssl headers rewrite
sudo systemctl reload apache2
```

Le flux public devient:

- `https://radio.tchadok.td/tchadok.mp3`

## 4) Configurer Tchadok (.env applicatif)

Dans le `.env` de l'app principale:

```env
RADIO_ENGINE_ENABLED=true
RADIO_STREAM_PUBLIC_URL=https://radio.tchadok.td/tchadok.mp3
RADIO_STREAM_FALLBACK_URL=/api/radio/stream
ICECAST_STATUS_URL=http://127.0.0.1:8000/status-json.xsl
ICECAST_ADMIN_USER=admin
ICECAST_ADMIN_PASSWORD=change-me-admin
RADIO_ENGINE_MOUNT=/tchadok.mp3
RADIO_ENGINE_TIMEOUT=5
RADIO_ENGINE_PREFER_STREAM=true
```

## 5) Healthcheck & sync

Endpoints utiles:

- `/api/radio/health.php`
- `/api/radio/metadata.php`

Synchronisation optionnelle DB (cron):

```bash
* * * * * /usr/bin/php /var/www/tchadok/scripts/radio-sync.php >> /var/log/tchadok-radio-sync.log 2>&1
```

## 6) Entrer en direct (DJ)

Liquidsoap expose un harbor sur le port `9000` (configurable).

Exemple `ffmpeg`:

```bash
ffmpeg -re -i live.mp3 -content_type audio/mpeg -f mp3 icecast://source:LIQ_HARBOR_PASSWORD@127.0.0.1:9000/live
```

Ce flux live est prioritaire sur la rotation automatique.
