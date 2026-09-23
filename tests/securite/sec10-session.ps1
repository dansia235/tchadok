<#
.SYNOPSIS
    Tests d'integration SEC-10 : durcissement des sessions.

.DESCRIPTION
    Verifie, contre le site local en fonctionnement :
      - les attributs du cookie de session ;
      - la regeneration de l'identifiant a la connexion ;
      - le refus d'un identifiant forge (mode strict) ;
      - qu'un identifiant anonyme obtenu par un tiers ne vaut rien une fois
        la victime connectee (fixation de session) ;
      - la fermeture des autres sessions, et de la connexion automatique,
        apres un changement de mot de passe ;
      - le delai d'inactivite propre a l'administration ;
      - l'absence de connexion automatique pour un administrateur ;
      - le registre des sessions sans copie des donnees de session ;
      - la reinitialisation du mot de passe administrateur, qui ferme toutes
        les sessions et ne supprime plus la page ;
      - la deconnexion par Auth::logout(), qui laisse une session utilisable.

    Le test du delai d'inactivite abaisse temporairement
    ADMIN_SESSION_LIFETIME a 60 secondes dans .env.local, puis restaure la
    valeur d'origine, y compris en cas d'echec. Il dure donc un peu plus
    d'une minute.

    Environnement local uniquement.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tests\securite\sec10-session.ps1
#>

$ErrorActionPreference = 'Continue'
$racine = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$mysql  = 'C:\xampp\mysql\bin\mysql.exe'
$base   = 'http://localhost/tchadok'
$envLocalPath = Join-Path $racine '.env.local'
$COOKIE = 'TCHADOKSESSID'

if (-not (Test-Path $envLocalPath)) {
    Write-Error "Refus : .env.local absent. Ces tests ne s'executent qu'en local."
    exit 1
}
$baseDb = ((Get-Content $envLocalPath | Where-Object { $_ -match '^DB_DATABASE=' }) -split '=', 2)[1].Trim()
if ($baseDb -notmatch 'local|test|dev') { Write-Error "Refus : base '$baseDb' non locale."; exit 1 }

function Sql([string]$fichier) { Get-Content $fichier -Raw -Encoding UTF8 | & $mysql -u root -D $baseDb --default-character-set=utf8mb4 }
function SqlValeur([string]$requete) { (& $mysql -u root -D $baseDb -N -B -e $requete 2>$null) }

$script:ok = 0; $script:ko = 0
function Verif([string]$libelle, [bool]$condition, [string]$detail = '') {
    if ($condition) { $script:ok++; Write-Output ("  OK  " + $libelle) }
    else { $script:ko++; Write-Output ("  !!  " + $libelle + $(if ($detail) { "  -> $detail" } else { '' })) }
}

# --- Outils curl (fichier de cookies par "appareil") ---
function Appareil { return @{ Jar = [System.IO.Path]::GetTempFileName() } }

function Obtenir($ap, [string]$chemin) {
    $tmp = [System.IO.Path]::GetTempFileName()
    $code = & curl.exe -s -o $tmp -w '%{http_code}' -c $ap.Jar -b $ap.Jar --max-time 30 "$base$chemin"
    $corps = Get-Content $tmp -Raw -ErrorAction SilentlyContinue
    [System.IO.File]::Delete($tmp)
    return @{ Code = [int]$code; Corps = "$corps" }
}

function Jeton($ap, [string]$chemin = '/login.php') {
    $r = Obtenir $ap $chemin
    if ($r.Corps -match 'name="csrf-token" content="([a-f0-9]+)"') { return $matches[1] }
    return ''
}

function Poster($ap, [string]$chemin, [string[]]$champs) {
    $tmp = [System.IO.Path]::GetTempFileName()
    $a = @('-s', '-o', $tmp, '-w', '%{http_code}', '-c', $ap.Jar, '-b', $ap.Jar, '-X', 'POST', '--max-time', '30')
    foreach ($c in $champs) { $a += @('--data-urlencode', $c) }
    $a += "$base$chemin"
    $code = & curl.exe @a
    $corps = Get-Content $tmp -Raw -ErrorAction SilentlyContinue
    [System.IO.File]::Delete($tmp)
    return @{ Code = [int]$code; Corps = "$corps" }
}

function IdSession($ap) {
    $l = Get-Content $ap.Jar | Where-Object { $_ -match "\t$COOKIE\t" } | Select-Object -Last 1
    if ($l) { return ($l -split "`t")[-1] } return ''
}

function Connecter($ap, [string]$email, [bool]$souvenir = $false) {
    $j = Jeton $ap
    $champs = @("csrf_token=$j", "email=$email", 'password=tchadok2026')
    if ($souvenir) { $champs += 'remember=1' }
    return Poster $ap '/login.php' $champs
}

# Page reservee aux utilisateurs connectes : settings.php redirige sinon.
function EstConnecte($ap) { return ((Obtenir $ap '/settings.php').Code -eq 200) }
function EstConnecteAdmin($ap) { return ((Obtenir $ap '/admin-dashboard.php').Code -eq 200) }

# --- Mise en place ---
$envOrigine = [System.IO.File]::ReadAllText($envLocalPath)
Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
Sql (Join-Path $PSScriptRoot 'sec10-fixtures.sql')
$appareils = @()

try {
    Write-Output "`n=== A. Cookie de session ==="
    $tmp = [System.IO.Path]::GetTempFileName()
    & curl.exe -s -o NUL -D $tmp "$base/login.php"
    $sc = (Get-Content $tmp | Where-Object { $_ -match '^Set-Cookie:' }) -join ' | '
    [System.IO.File]::Delete($tmp)
    Verif "Nom du cookie : $COOKIE" ($sc -match "$COOKIE=") $sc
    Verif "Plus de cookie PHPSESSID" ($sc -notmatch 'PHPSESSID')
    Verif "HttpOnly" ($sc -match 'HttpOnly')
    Verif "SameSite=Lax" ($sc -match 'SameSite=Lax')
    Verif "Pas de Secure en local (HTTP)" ($sc -notmatch '; secure')
    Verif "Cookie de session (sans date d'expiration)" ($sc -notmatch 'expires=')

    Write-Output "`n=== B. Regeneration a la connexion ==="
    $a1 = Appareil; $appareils += $a1
    $null = Jeton $a1
    $avant = IdSession $a1
    $null = Connecter $a1 'fan10@essai.local'
    $apres = IdSession $a1
    Verif "Connexion reussie" (EstConnecte $a1)
    Verif "L'identifiant de session change a la connexion" ($avant -ne '' -and $apres -ne '' -and $avant -ne $apres) "$avant -> $apres"

    Write-Output "`n=== C. Identifiant forge (mode strict) ==="
    $forge = 'attaquant' + ('0' * 20)
    $tmp = [System.IO.Path]::GetTempFileName()
    & curl.exe -s -o NUL -D $tmp -b "$COOKIE=$forge" "$base/login.php"
    $sc = (Get-Content $tmp | Where-Object { $_ -match "^Set-Cookie:\s*$COOKIE=" }) -join ''
    [System.IO.File]::Delete($tmp)
    $emis = if ($sc -match "$COOKIE=([^;]+)") { $matches[1] } else { '' }
    Verif "Identifiant forge refuse : le serveur en emet un autre" ($emis -ne '' -and $emis -ne $forge) "emis='$emis'"

    Write-Output "`n=== D. Fixation : identifiant anonyme obtenu par un tiers ==="
    $victime = Appareil; $appareils += $victime
    $null = Jeton $victime
    $idAnonyme = IdSession $victime
    $null = Connecter $victime 'fan10@essai.local'
    $attaquant = Appareil; $appareils += $attaquant
    Set-Content $attaquant.Jar "localhost`tFALSE`t/`tFALSE`t0`t$COOKIE`t$idAnonyme" -Encoding ASCII
    Verif "La victime est connectee" (EstConnecte $victime)
    Verif "L'attaquant, avec l'identifiant anonyme de la victime, n'est PAS connecte" (-not (EstConnecte $attaquant))

    Write-Output "`n=== E. Registre des sessions ==="
    $data = SqlValeur "SELECT COUNT(*) FROM user_sessions WHERE user_id = 931 AND data IS NOT NULL"
    Verif "Plus aucune copie des donnees de session en base (colonne data vide)" ("$data".Trim() -eq '0') "$data"
    $nb = SqlValeur "SELECT COUNT(*) FROM user_sessions WHERE user_id = 931"
    Verif "Chaque connexion inscrite au registre" ([int]"$nb".Trim() -ge 2) "$nb"

    Write-Output "`n=== F. Changement de mot de passe : autres sessions fermees ==="
    $pc = Appareil; $telephone = Appareil; $appareils += $pc, $telephone
    $null = Connecter $pc 'fan10@essai.local'
    $null = Connecter $telephone 'fan10@essai.local' $true
    Verif "Prealable : connecte sur deux appareils" ((EstConnecte $pc) -and (EstConnecte $telephone))
    $souvenirPose = (Get-Content $telephone.Jar | Where-Object { $_ -match "`tremember_token`t\S+" }) -ne $null
    Verif "Prealable : 'se souvenir de moi' actif sur le telephone" $souvenirPose

    $idPcAvant = IdSession $pc
    $j = Jeton $pc '/settings.php'
    $r = Poster $pc '/settings.php' @("csrf_token=$j", 'action=change_password', 'current_password=tchadok2026', 'new_password=Nouveau-2026!', 'confirm_password=Nouveau-2026!')
    Verif "Changement de mot de passe accepte" ($r.Corps -match 'modifie avec succes') ''
    Verif "L'appareil qui change le mot de passe reste connecte" (EstConnecte $pc)
    Verif "... avec un nouvel identifiant de session" ((IdSession $pc) -ne $idPcAvant)
    Verif "L'AUTRE appareil est deconnecte" (-not (EstConnecte $telephone))
    Verif "... sans reconnexion par 'se souvenir de moi'" (-not (EstConnecte $telephone))
    $page = Obtenir $telephone '/login.php'
    Verif "La page de connexion explique la fermeture" ($page.Corps -match 'mot de passe du compte a ete modifie') ''
    # SEC-11 : les jetons vivent dans remember_tokens, plus dans users.
    $token = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL"
    Verif "Jeton de connexion automatique invalide en base" ("$token".Trim() -eq '0') "$token"
    & $mysql -u root -D $baseDb -e "UPDATE users SET password = password_hash, password_hash = '`$2y`$12`$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', password = '`$2y`$12`$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG' WHERE id = 931" 2>$null

    Write-Output "`n=== G. Administrateur : pas de connexion automatique ==="
    $ad = Appareil; $appareils += $ad
    $null = Connecter $ad 'admin10@essai.local' $true
    Verif "Administrateur connecte" (EstConnecteAdmin $ad)
    $cookieAdmin = (Get-Content $ad.Jar | Where-Object { $_ -match "`tremember_token`t\S+" }) -ne $null
    Verif "Aucun cookie 'se souvenir de moi' pose pour un administrateur" (-not $cookieAdmin)

    Write-Output "`n=== H. Delai d'inactivite de l'administration (60 s pour le test) ==="
    $modifie = $envOrigine -replace '(?m)^ADMIN_SESSION_LIFETIME=.*$', 'ADMIN_SESSION_LIFETIME=60'
    [System.IO.File]::WriteAllText($envLocalPath, $modifie, (New-Object System.Text.UTF8Encoding $false))
    $adm = Appareil; $fan = Appareil; $appareils += $adm, $fan
    $null = Connecter $adm 'admin10@essai.local'
    $null = Connecter $fan 'fan10@essai.local'
    Verif "Prealable : admin et fan connectes" ((EstConnecteAdmin $adm) -and (EstConnecte $fan))
    Write-Output "      attente de 65 secondes d'inactivite..."
    Start-Sleep -Seconds 65
    Verif "Administrateur deconnecte apres 60 s d'inactivite" (-not (EstConnecteAdmin $adm))
    Verif "Le fan, soumis a SESSION_LIFETIME, reste connecte" (EstConnecte $fan)
    $page = Obtenir $adm '/login.php'
    Verif "La page de connexion explique l'inactivite" ($page.Corps -match "periode d(&#039;|')inactivite") ''
    [System.IO.File]::WriteAllText($envLocalPath, $envOrigine, (New-Object System.Text.UTF8Encoding $false))

    Write-Output "`n=== I. Reinitialisation du mot de passe administrateur ==="
    $s1 = Appareil; $appareils += $s1
    $null = Connecter $s1 'admin10@essai.local'
    Verif "Prealable : session administrateur ouverte" (EstConnecteAdmin $s1)
    $brut = 'jeton-essai-' + [guid]::NewGuid().ToString('N')
    $hashJeton = & C:\xampp\php\php.exe -r "echo password_hash('$brut', PASSWORD_BCRYPT);"
    & $mysql -u root -D $baseDb -e "UPDATE users SET reset_token = '$hashJeton', reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = 932" 2>$null
    $rs = Appareil; $appareils += $rs
    $j = Jeton $rs "/admin/reset-password.php?email=admin10@essai.local&token=$brut"
    $r = Poster $rs '/admin/reset-password.php' @("csrf_token=$j", 'action=reset', 'email=admin10@essai.local', "token=$brut", 'password=Reinit-2026!', 'confirm_password=Reinit-2026!')
    Verif "Reinitialisation acceptee" ($r.Corps -match 'reinitialise avec succes') ''
    Verif "La page de reinitialisation existe toujours (plus d'auto-suppression)" (Test-Path (Join-Path $racine 'admin\reset-password.php'))
    Verif "Le lien 'mot de passe oublie' reste affiche" ((Obtenir (Appareil) '/admin/login.php').Corps -match 'reset-password\.php')
    Verif "La session administrateur ouverte ailleurs est fermee" (-not (EstConnecteAdmin $s1))

    Write-Output "`n=== J. Auth::logout() laisse une session utilisable ==="
    $x = Appareil; $appareils += $x
    $j = Jeton $x '/admin/login.php'
    $r = Poster $x '/admin/login.php' @("csrf_token=$j", 'username=fan10@essai.local', 'password=tchadok2026')
    Verif "Non-administrateur refuse sur la console" ($r.Corps -match 'reserve aux administrateurs') ''
    $j2 = if ($r.Corps -match 'name="csrf_token" value="([a-f0-9]+)"') { $matches[1] } else { '' }
    $r2 = Poster $x '/admin/login.php' @("csrf_token=$j2", 'username=x', 'password=y')
    Verif "Le formulaire suivant fonctionne (pas de 403 : jeton bien stocke)" ($r2.Code -ne 403) $r2.Code
}
finally {
    [System.IO.File]::WriteAllText($envLocalPath, $envOrigine, (New-Object System.Text.UTF8Encoding $false))
    Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
    foreach ($ap in $appareils) { if ($ap.Jar -and (Test-Path $ap.Jar)) { [System.IO.File]::Delete($ap.Jar) } }
}

Write-Output ""
Write-Output ("Resultat : {0} reussi(s), {1} echec(s)" -f $script:ok, $script:ko)
exit $(if ($script:ko -eq 0) { 0 } else { 1 })
