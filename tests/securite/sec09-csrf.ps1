<#
.SYNOPSIS
    Tests d'integration SEC-09 : protection CSRF centralisee.

.DESCRIPTION
    Verifie, contre le site local en fonctionnement :
      - qu'aucun point d'entree n'accepte une requete modifiante sans jeton,
        ni avec un jeton faux -- c'est la situation d'une attaque CSRF, ou la
        page tierce dispose du cookie de session mais pas du jeton ;
      - qu'un jeton valide laisse passer la requete, par champ de formulaire,
        par en-tete X-CSRF-Token et par corps JSON ;
      - que les requetes GET ne sont pas affectees ;
      - que le jeton change a la connexion ;
      - qu'un envoi au-dela de post_max_size renvoie 413, et non 403 ;
      - que les API renvoient une erreur JSON, les pages une page HTML.

    Environnement local uniquement.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tests\securite\sec09-csrf.ps1
#>

$ErrorActionPreference = 'Continue'
$racine = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$base   = 'http://localhost/tchadok'

if (-not (Test-Path (Join-Path $racine '.env.local'))) {
    Write-Error "Refus : .env.local absent. Ces tests ne s'executent qu'en local."
    exit 1
}

$script:ok = 0; $script:ko = 0
function Verif([string]$libelle, [bool]$condition, [string]$detail = '') {
    if ($condition) { $script:ok++; Write-Output ("  OK  " + $libelle) }
    else { $script:ko++; Write-Output ("  !!  " + $libelle + $(if ($detail) { "  -> $detail" } else { '' })) }
}

# Session curl persistante (fichier de cookies)
function NouvelleSession {
    $jar = [System.IO.Path]::GetTempFileName()
    $html = & curl.exe -s -c $jar -b $jar "$base/login.php"
    $jeton = ''
    foreach ($l in $html) { if ($l -match 'name="csrf-token" content="([a-f0-9]+)"') { $jeton = $matches[1]; break } }
    return @{ Jar = $jar; Jeton = $jeton }
}

# POST ; renvoie code HTTP, type de contenu et debut du corps
function Poster([hashtable]$s, [string]$chemin, [string[]]$donnees = @(), [hashtable]$entetes = @{}, $json = $null) {
    $tmp = [System.IO.Path]::GetTempFileName()
    $a = @('-s', '-o', $tmp, '-w', '%{http_code}|%{content_type}', '-c', $s.Jar, '-b', $s.Jar, '-X', 'POST', '--max-time', '30')
    foreach ($k in $entetes.Keys) { $a += @('-H', "${k}: $($entetes[$k])") }
    $fichierJson = $null
    if ($null -ne $json) {
        # Corps JSON par fichier : PowerShell 5.1 supprime les guillemets doubles
        # des arguments passes a un executable natif ({"a":1} devient {a:1}).
        $fichierJson = [System.IO.Path]::GetTempFileName()
        [System.IO.File]::WriteAllText($fichierJson, $json)
        $a += @('-H', 'Content-Type: application/json', '--data-binary', "@$fichierJson")
    } else {
        foreach ($d in $donnees) { $a += @('--data-urlencode', $d) }
        # PowerShell 5.1 supprime les arguments vides : un -d '' consommerait l'URL
        if ($donnees.Count -eq 0) { $a += @('-d', 'vide=1') }
    }
    $a += "$base$chemin"
    $res = "$(& curl.exe @a)".Split('|')
    $corps = Get-Content $tmp -Raw -ErrorAction SilentlyContinue
    [System.IO.File]::Delete($tmp)
    if ($fichierJson) { [System.IO.File]::Delete($fichierJson) }
    return @{ Code = [int]$res[0]; Type = "$($res[1])"; Corps = "$corps" }
}

$pointsEntree = @(
    '/login.php', '/register.php', '/contact.php', '/admin/login.php', '/admin/reset-password.php',
    '/upload.php', '/artist-add-song.php', '/artist-add-album.php',
    '/admin-add-song.php', '/admin-add-album.php', '/admin-manage-radio.php', '/admin-playlists.php',
    '/admin-podcasts.php', '/admin-blog.php', '/edit-profile.php', '/settings.php', '/security-settings.php',
    '/create-playlist.php', '/premium-payment.php', '/blog-article.php',
    '/api/stream.php', '/api/playlists.php', '/api/follows.php', '/api/notifications.php',
    '/api/blog/share.php', '/api/blog/upload-image.php'
)

$s = NouvelleSession
Verif "Jeton expose par la balise meta" ($s.Jeton.Length -eq 64) "longueur $($s.Jeton.Length)"

Write-Output "`n=== A. Sans jeton : chaque point d'entree refuse (403) ==="
foreach ($p in $pointsEntree) {
    $r = Poster $s $p @('champ=valeur')
    Verif "$p sans jeton -> 403" ($r.Code -eq 403) $r.Code
}

Write-Output "`n=== B. Jeton faux : refuse (403) ==="
$faux = ('0' * 64)
foreach ($p in @('/login.php', '/upload.php', '/api/stream.php', '/api/playlists.php')) {
    $r = Poster $s $p @("csrf_token=$faux", 'champ=valeur')
    Verif "$p jeton faux (champ) -> 403" ($r.Code -eq 403) $r.Code
    $r = Poster $s $p @('champ=valeur') @{ 'X-CSRF-Token' = $faux }
    Verif "$p jeton faux (en-tete) -> 403" ($r.Code -eq 403) $r.Code
}

Write-Output "`n=== C. Jeton d'une AUTRE session : refuse (cas d'une attaque rejouant un jeton vole ailleurs) ==="
$autre = NouvelleSession
$r = Poster $s '/login.php' @("csrf_token=$($autre.Jeton)", 'email=x@y.z', 'password=x')
Verif "Jeton d'une autre session -> 403" ($r.Code -eq 403) $r.Code

Write-Output "`n=== D. Jeton valide : la requete passe ==="
$r = Poster $s '/login.php' @("csrf_token=$($s.Jeton)", 'email=inconnu@essai.local', 'password=mauvais')
Verif "Connexion (champ) : traitee, pas 403" ($r.Code -ne 403 -and $r.Code -lt 500) $r.Code
Verif "Connexion : message d'erreur d'identifiants affiche" ($r.Corps -match 'incorrect|Identifiants') ''

$r = Poster $s '/contact.php' @("csrf_token=$($s.Jeton)", 'name=Essai')
Verif "Contact (champ) : traite, pas 403" ($r.Code -ne 403 -and $r.Code -lt 500) $r.Code

$r = Poster $s '/api/stream.php' @() @{ 'X-CSRF-Token' = $s.Jeton } '{"track_id":999999999,"duration":40}'
Verif "api/stream.php (en-tete) : traite -> 404 titre inexistant" ($r.Code -eq 404) $r.Code

$r = Poster $s '/api/blog/share.php' @() @{} ('{"post_id":999999999,"platform":"copy","csrf_token":"' + $s.Jeton + '"}')
Verif "api/blog/share.php (jeton dans le corps JSON) : pas 403" ($r.Code -ne 403) $r.Code

Write-Output "`n=== E. Format des reponses de refus ==="
$r = Poster $s '/api/playlists.php' @('champ=valeur')
Verif "API : refus en JSON" ($r.Type -like 'application/json*') $r.Type
Verif "API : corps JSON avec code 403 et reason=csrf" ($r.Corps -match '"code"\s*:\s*403' -and $r.Corps -match '"reason"\s*:\s*"csrf"') $r.Corps.Substring(0, [Math]::Min(80, $r.Corps.Length))
$r = Poster $s '/contact.php' @('champ=valeur')
Verif "Page : refus en HTML" ($r.Type -like 'text/html*') $r.Type
Verif "Page : message explicite" ($r.Corps -match 'Session expiree') ''

Write-Output "`n=== F. Requetes GET non affectees ==="
foreach ($p in @('/', '/login.php', '/genres.php', '/api/search.php?q=test', '/api/track.php?id=999999')) {
    $code = & curl.exe -s -o NUL -w '%{http_code}' -b $s.Jar "$base$p"
    Verif "GET $p -> pas 403" ($code -ne '403') $code
}

Write-Output "`n=== G. Jeton renouvele a la connexion ==="
$c = NouvelleSession
$avant = $c.Jeton
$null = Poster $c '/login.php' @("csrf_token=$avant", 'email=admin@tchadok.td', 'password=tchadok2026')
$apres = ''
foreach ($l in (& curl.exe -s -c $c.Jar -b $c.Jar "$base/")) { if ($l -match 'name="csrf-token" content="([a-f0-9]+)"') { $apres = $matches[1]; break } }
Verif "Connexion reussie avec jeton (page protegee accessible)" ((& curl.exe -s -o NUL -w '%{http_code}' -b $c.Jar "$base/admin-dashboard.php") -eq '200')
Verif "Le jeton a change apres connexion" ($apres -ne '' -and $apres -ne $avant) "avant=$($avant.Substring(0,8)) apres=$(if ($apres) { $apres.Substring(0,8) } else { 'vide' })"
$r = Poster $c '/contact.php' @("csrf_token=$avant", 'name=x')
Verif "L'ancien jeton (d'avant connexion) est refuse" ($r.Code -eq 403) $r.Code

Write-Output "`n=== H. Envoi au-dela de post_max_size : 413, pas 403 ==="
$gros = [System.IO.Path]::GetTempFileName()
$fs = [System.IO.File]::OpenWrite($gros); $fs.SetLength(60MB); $fs.Close()
$tmp = [System.IO.Path]::GetTempFileName()
$code = & curl.exe -s -o $tmp -w '%{http_code}' -b $s.Jar -c $s.Jar -X POST -F "csrf_token=$($s.Jeton)" -F "audio_file=@$gros" --max-time 120 "$base/upload.php"
Verif "Fichier de 60 Mo (post_max_size 52M) -> 413" ($code -eq '413') $code
Verif "Message 'trop volumineux'" ((Get-Content $tmp -Raw) -match 'volumineux') ''
[System.IO.File]::Delete($gros); [System.IO.File]::Delete($tmp)

Write-Output "`n=== I. Deconnexion ==="
function Connecter([bool]$souvenir) {
    $x = NouvelleSession
    $champs = @("csrf_token=$($x.Jeton)", 'email=admin@tchadok.td', 'password=tchadok2026')
    if ($souvenir) { $champs += 'remember=1' }
    $null = Poster $x '/login.php' $champs
    # Recuperer le jeton renouvele apres connexion
    foreach ($l in (& curl.exe -s -c $x.Jar -b $x.Jar "$base/")) { if ($l -match 'name="csrf-token" content="([a-f0-9]+)"') { $x.Jeton = $matches[1]; break } }
    return $x
}
function EstConnecte($x) { return ((& curl.exe -s -o NUL -w '%{http_code}' -c $x.Jar -b $x.Jar "$base/admin-dashboard.php") -eq '200') }

$d = Connecter $false
Verif "Prealable : connecte" (EstConnecte $d)
$code = & curl.exe -s -o NUL -w '%{http_code}' -c $d.Jar -b $d.Jar "$base/logout.php"
Verif "GET logout.php : page de confirmation (200), pas de deconnexion" ($code -eq '200' -and (EstConnecte $d)) $code
$r = Poster $d '/logout.php' @('champ=valeur')
Verif "POST logout.php sans jeton -> 403, toujours connecte" ($r.Code -eq 403 -and (EstConnecte $d)) $r.Code
$r = Poster $d '/logout.php' @("csrf_token=$($d.Jeton)")
Verif "POST logout.php avec jeton -> redirection" ($r.Code -eq 302) $r.Code
Verif "Apres deconnexion : plus connecte" (-not (EstConnecte $d))

Write-Output "`n--- Se souvenir de moi ---"
$m = Connecter $true
$cookieSouvenir = (Get-Content $m.Jar | Where-Object { $_ -match 'remember_token' }) -ne $null
Verif "Prealable : cookie remember_token pose" $cookieSouvenir
$null = Poster $m '/logout.php' @("csrf_token=$($m.Jeton)")
Verif "Apres deconnexion : pas de reconnexion automatique" (-not (EstConnecte $m))
$reste = (Get-Content $m.Jar | Where-Object { $_ -match 'remember_token\s+\S+' -and $_ -notmatch 'remember_token\s*$' })
Verif "Cookie remember_token supprime" (-not $reste)

Write-Output "`n--- Redirection ---"
$e = Connecter $false
$tmp = [System.IO.Path]::GetTempFileName()
$null = & curl.exe -s -o NUL -D $tmp -c $e.Jar -b $e.Jar -X POST -H 'Referer: https://site-tiers.example/piege' --data-urlencode "csrf_token=$($e.Jeton)" "$base/logout.php"
$loc = (Get-Content $tmp | Where-Object { $_ -match '^Location:' }) -join ''
[System.IO.File]::Delete($tmp)
Verif "Referer d'un autre site : redirection vers l'accueil du site, pas vers le tiers" ($loc -match 'localhost/tchadok' -and $loc -notmatch 'site-tiers') $loc

foreach ($x in @($s, $autre, $c, $d, $m, $e)) { [System.IO.File]::Delete($x.Jar) }

Write-Output ""
Write-Output ("Resultat : {0} reussi(s), {1} echec(s)" -f $script:ok, $script:ko)
exit $(if ($script:ko -eq 0) { 0 } else { 1 })
