<#
.SYNOPSIS
    Tests d'integration SEC-06 : controle d'acces aux fichiers audio.

.DESCRIPTION
    Verifie, contre le site local en fonctionnement :
      - l'interdiction de l'acces direct aux fichiers audio ;
      - l'absence de tout chemin de fichier dans la reponse de api/track.php ;
      - la lecture complete et partielle (Range) via media.php ;
      - le rejet des URL falsifiees, expirees, detournees ou copiees dans
        une autre session ;
      - la decision d'acces : gratuit, payant, achete, Premium actif ou
        expire, proprietaire, brouillon, URL externe, traversee de repertoire.

    Met en place son jeu d'essai, et le retire a la fin, y compris en cas
    d'echec. Ne s'execute QUE sur un environnement local.

    Les requetes media passent par curl.exe : PowerShell 5.1 interdit
    l'en-tete Range dans -Headers.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tests\securite\sec06-acces-media.ps1
#>

$ErrorActionPreference = 'Continue'
$racine = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$mysql  = 'C:\xampp\mysql\bin\mysql.exe'
$base   = 'http://localhost/tchadok'

# ---------------------------------------------------------------------
# Garde-fous : environnement local uniquement
# ---------------------------------------------------------------------
if (-not (Test-Path (Join-Path $racine '.env.local'))) {
    Write-Error "Refus : .env.local absent. Ces tests ne s'executent qu'en local."
    exit 1
}
$envLocal = @{}
Get-Content (Join-Path $racine '.env.local') | Where-Object { $_ -match '^\s*[A-Z_]+=' } | ForEach-Object {
    $k, $v = $_ -split '=', 2; $envLocal[$k.Trim()] = $v.Trim().Trim('"')
}
$baseDb = $envLocal['DB_DATABASE']
if ($baseDb -notmatch 'local|test|dev') {
    Write-Error "Refus : la base '$baseDb' ne ressemble pas a une base locale."
    exit 1
}

function Sql([string]$fichier) {
    Get-Content $fichier -Raw -Encoding UTF8 | & $mysql -u root -D $baseDb --default-character-set=utf8mb4
}

# ---------------------------------------------------------------------
# Outils
# ---------------------------------------------------------------------
$script:ok = 0; $script:ko = 0

function Verif([string]$libelle, [bool]$condition, [string]$detail = '') {
    if ($condition) { $script:ok++; Write-Output ("  OK  " + $libelle) }
    else { $script:ko++; Write-Output ("  !!  " + $libelle + $(if ($detail) { "  -> $detail" } else { '' })) }
}

function CookieSession($session) {
    if (-not $session) { return $null }
    return ($session.Cookies.GetCookies("$base/") | Where-Object Name -eq 'PHPSESSID').Value
}

function Requete([string]$url, $session = $null, [hashtable]$entetes = @{}) {
    $tmpEntetes = [System.IO.Path]::GetTempFileName()
    $tmpCorps   = [System.IO.Path]::GetTempFileName()
    $a = @('-s', '-D', $tmpEntetes, '-o', $tmpCorps, '-w', '%{http_code}|%{size_download}', '--max-time', '60')
    $c = CookieSession $session
    if ($c) { $a += @('-b', "PHPSESSID=$c") }
    foreach ($k in $entetes.Keys) { $a += @('-H', "${k}: $($entetes[$k])") }
    $a += $url
    $res = & curl.exe @a
    $parts = "$res".Split('|')
    $h = @{}
    foreach ($l in (Get-Content $tmpEntetes)) {
        $i = $l.IndexOf(':')
        if ($i -gt 0) { $h[$l.Substring(0, $i).Trim()] = $l.Substring($i + 1).Trim() }
    }
    $corps = if ((Get-Item $tmpCorps).Length -lt 200000) { Get-Content $tmpCorps -Raw } else { '' }
    [System.IO.File]::Delete($tmpEntetes); [System.IO.File]::Delete($tmpCorps)
    return @{ Code = [int]$parts[0]; Entetes = $h; Corps = $corps; Octets = [long]$parts[1] }
}

function Titre([int]$id, $session) {
    $r = Requete "$base/api/track.php?action=get&id=$id" $session
    $json = $null
    try { $json = $r.Corps | ConvertFrom-Json } catch {}
    return @{ Code = $r.Code; Json = $json; Brut = $r.Corps }
}

function Connexion([string]$email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-WebRequest "$base/login.php" -UseBasicParsing -WebSession $s | Out-Null
    try {
        Invoke-WebRequest "$base/login.php" -Method POST -UseBasicParsing -WebSession $s -MaximumRedirection 0 `
            -Body @{ email = $email; password = 'tchadok2026' } -ErrorAction Stop | Out-Null
    } catch {}
    return $s
}

# ---------------------------------------------------------------------
# Mise en place
# ---------------------------------------------------------------------
$audio = (Get-ChildItem (Join-Path $racine 'infra\stream\media') -Recurse -Filter *.mp3 |
          Sort-Object Length | Select-Object -First 1).FullName
$fichiersEssai = @(
    (Join-Path $racine 'storage\uploads\audio\test-gratuit.mp3'),
    (Join-Path $racine 'storage\uploads\audio\test-payant.mp3'),
    (Join-Path $racine 'storage\uploads\audio\test-extrait.mp3'),
    (Join-Path $racine 'uploads\audio\test-historique.mp3')
)
Sql (Join-Path $PSScriptRoot 'sec06-nettoyage.sql')   # etat propre si un essai precedent a avorte
foreach ($f in $fichiersEssai) { Copy-Item $audio $f -Force }
Sql (Join-Path $PSScriptRoot 'sec06-fixtures.sql')
$TAILLE = (Get-Item $audio).Length

try {
    Write-Output "`n=== A. Acces direct aux fichiers (doit etre interdit) ==="
    Verif "storage/uploads/audio en acces direct -> 403" ((Requete "$base/storage/uploads/audio/test-payant.mp3").Code -eq 403)
    Verif "uploads/audio (historique) en acces direct -> 403" ((Requete "$base/uploads/audio/test-historique.mp3").Code -eq 403)

    Write-Output "`n=== B. Visiteur anonyme ==="
    $anon = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-WebRequest "$base/" -UseBasicParsing -WebSession $anon | Out-Null

    $t = Titre 9001 $anon
    Verif "9001 gratuit : access=full" ($t.Json.data.access -eq 'full') $t.Json.data.access
    Verif "9001 : stream_url fourni" ([bool]$t.Json.data.stream_url)
    Verif "Aucun chemin de fichier dans la reponse API" (-not ($t.Brut -match 'audio_file|preview_file|storage/uploads|uploads/audio'))
    Verif "Aucun en-tete CORS sur l'API" (-not (Requete "$base/api/track.php?id=9001" $anon).Entetes['Access-Control-Allow-Origin'])
    $url = $t.Json.data.stream_url

    $r = Requete $url $anon
    Verif "Lecture complete -> 200" ($r.Code -eq 200) $r.Code
    Verif "Type audio/mpeg" ("$($r.Entetes['Content-Type'])" -like 'audio/mpeg*') "$($r.Entetes['Content-Type'])"
    Verif "Taille exacte ($TAILLE octets)" ($r.Octets -eq $TAILLE) $r.Octets
    Verif "Accept-Ranges: bytes" ("$($r.Entetes['Accept-Ranges'])" -eq 'bytes')
    Verif "Cache-Control private (cache navigateur, jamais partage)" ("$($r.Entetes['Cache-Control'])" -like 'private*') "$($r.Entetes['Cache-Control'])"

    $r = Requete $url $anon @{ Range = 'bytes=0-1023' }
    Verif "Requete partielle -> 206" ($r.Code -eq 206) $r.Code
    Verif "Requete partielle : 1024 octets" ($r.Octets -eq 1024) $r.Octets
    Verif "Content-Range correct" ("$($r.Entetes['Content-Range'])" -eq "bytes 0-1023/$TAILLE") "$($r.Entetes['Content-Range'])"

    $r = Requete $url $anon @{ Range = 'bytes=-500' }
    Verif "Plage suffixe (500 derniers octets) -> 206 / 500" ($r.Code -eq 206 -and $r.Octets -eq 500) "$($r.Code) / $($r.Octets)"

    $r = Requete $url $anon @{ Range = "bytes=$($TAILLE + 10)-" }
    Verif "Plage hors fichier -> 416" ($r.Code -eq 416) $r.Code

    $autre = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Verif "URL copiee dans une AUTRE session -> 403" ((Requete $url $autre).Code -eq 403)

    $falsifiee = $url -replace 'sig=[^&]+', 'sig=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
    Verif "Signature falsifiee -> 403" ((Requete $falsifiee $anon).Code -eq 403)

    $expiree = $url -replace 'exp=\d+', ('exp=' + ([DateTimeOffset]::UtcNow.ToUnixTimeSeconds() - 60))
    Verif "Expiration modifiee -> 403" ((Requete $expiree $anon).Code -eq 403)

    $autreTitre = $url -replace 'id=9001', 'id=9003'
    Verif "Identifiant de titre modifie -> 403" ((Requete $autreTitre $anon).Code -eq 403)

    $t = Titre 9002 $anon
    Verif "9002 payant+extrait : access=preview" ($t.Json.data.access -eq 'preview') $t.Json.data.access
    Verif "9002 : AUCUN stream_url" (-not $t.Json.data.stream_url)
    Verif "9002 : preview_url fourni" ([bool]$t.Json.data.preview_url)
    Verif "9002 : message de refus fourni" ([bool]$t.Json.data.access_message)
    $prev = $t.Json.data.preview_url
    Verif "Extrait lisible -> 200" ((Requete $prev $anon).Code -eq 200)
    $escalade = $prev -replace 't=preview', 't=audio'
    Verif "Extrait transforme en audio complet (t=audio) -> 403" ((Requete $escalade $anon).Code -eq 403)

    $t = Titre 9003 $anon
    Verif "9003 payant sans extrait : access=none" ($t.Json.data.access -eq 'none') $t.Json.data.access
    Verif "9003 : aucune URL" (-not $t.Json.data.stream_url -and -not $t.Json.data.preview_url)

    Verif "9004 brouillon -> 404 (non revele)" ((Titre 9004 $anon).Code -eq 404)

    $t = Titre 9007 $anon
    Verif "9007 fichier historique : lisible via media.php" ((Requete $t.Json.data.stream_url $anon).Code -eq 200)

    Write-Output "`n=== C. Utilisateurs connectes ==="
    $fan = Connexion 'fan@essai.local'
    Verif "Fan sans achat : 9002 en extrait seulement" ((Titre 9002 $fan).Json.data.access -eq 'preview')

    $ach = Connexion 'acheteur@essai.local'
    $t = Titre 9002 $ach
    Verif "Acheteur : 9002 acces complet (motif achete)" ($t.Json.data.access -eq 'full' -and $t.Json.data.access_reason -eq 'achete') "$($t.Json.data.access)/$($t.Json.data.access_reason)"
    Verif "Acheteur : lecture complete -> 200" ((Requete $t.Json.data.stream_url $ach).Code -eq 200)
    Verif "Acheteur : 9003 (non achete) refuse" ((Titre 9003 $ach).Json.data.access -eq 'none')

    $pre = Connexion 'premium@essai.local'
    $t = Titre 9002 $pre
    Verif "Premium actif : 9002 acces complet" ($t.Json.data.access -eq 'full' -and $t.Json.data.access_reason -eq 'premium') "$($t.Json.data.access)/$($t.Json.data.access_reason)"

    $exp = Connexion 'expire@essai.local'
    Verif "Premium EXPIRE : 9002 en extrait seulement" ((Titre 9002 $exp).Json.data.access -eq 'preview')

    $art = Connexion 'artiste@essai.local'
    $t = Titre 9004 $art
    Verif "Artiste proprietaire : son brouillon 9004 lisible" ($t.Json.data.access -eq 'full' -and $t.Json.data.access_reason -eq 'proprietaire') "$($t.Code) $($t.Json.data.access_reason)"

    $adm = Connexion 'admin@tchadok.td'
    $t = Titre 9005 $adm
    Verif "URL externe (9005) : media.php refuse de la servir -> 404" ((Requete $t.Json.data.stream_url $adm).Code -eq 404)
    $t = Titre 9006 $adm
    Verif "Traversee de repertoire (9006) -> 404" ((Requete $t.Json.data.stream_url $adm).Code -eq 404)

    Write-Output ""
    Write-Output ("Resultat : {0} reussi(s), {1} echec(s)" -f $script:ok, $script:ko)
}
finally {
    # Nettoyage, meme en cas d'echec
    Sql (Join-Path $PSScriptRoot 'sec06-nettoyage.sql')
    foreach ($f in $fichiersEssai) { if (Test-Path $f) { [System.IO.File]::Delete($f) } }
}

exit $(if ($script:ko -eq 0) { 0 } else { 1 })
