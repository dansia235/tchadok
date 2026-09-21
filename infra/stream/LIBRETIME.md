# Option LibreTime

Si vous utilisez LibreTime, gardez la meme integration application:

- `RADIO_STREAM_PUBLIC_URL=https://radio.tchadok.td/tchadok.mp3`
- `ICECAST_STATUS_URL=http://127.0.0.1:8000/status-json.xsl`
- `RADIO_ENGINE_MOUNT=/tchadok.mp3`

LibreTime pilote Liquidsoap automatiquement.

## Points cle

1. Configurer LibreTime pour publier sur le mount `/tchadok.mp3`.
2. Exposer Icecast via Apache sur `radio.tchadok.td`.
3. Pointer Tchadok vers l'URL publique du mount.
4. Verifier via `/api/radio/health.php`.

