# ============================================================
# Tchadok Radio - Liquidsoap
# Rotation automatique + entree live optionnelle (harbor)
# ============================================================

set("server.telnet", false)
set("harbor.bind_addrs", ["0.0.0.0"])
set("log.stdout", true)
set("frame.audio.samplerate", 44100)

mount = getenv("/tchadok.mp3", "ICECAST_MOUNT")
site_url = getenv("https://tchadok.td", "SITE_URL")
icecast_host = getenv("icecast", "ICECAST_HOST")

# Rotation principale: /opt/radio/rotation.m3u
rotation = playlist(
  id="rotation",
  mode="randomize",
  reload_mode="watch",
  "/opt/radio/rotation.m3u"
)

# Entree live (DJ) via harbor:
# ffmpeg -re -i input.mp3 -content_type audio/mpeg -f mp3 icecast://source:PASSWORD@HOST:9000/live
live = input.harbor(
  id="harbor_live",
  "live",
  port=9000,
  password=getenv("change-me-harbor", "LIQ_HARBOR_PASSWORD")
)

# Fallback de securite
# Evite un crash si aucun fichier local n'est disponible.
fallback_track = blank()

radio = fallback(track_sensitive=false, [live, rotation, fallback_track])
radio = normalize(radio)
radio = mksafe(radio)

output.icecast(
  %mp3(bitrate=192, stereo=true),
  host=icecast_host,
  # Port fixe (compose expose Icecast sur 8000 en interne)
  port=8000,
  password=getenv("change-me-source", "ICECAST_SOURCE_PASSWORD"),
  mount=mount,
  name="Tchadok Radio",
  description="Musique tchadienne en continu",
  url=site_url,
  genre="Afro / Tchad",
  radio
)
