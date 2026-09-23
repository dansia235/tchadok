<#
.SYNOPSIS
    Tests d'integration SEC-11 : connexion automatique « se souvenir de moi ».

.DESCRIPTION
    Verifie, contre le site local en fonctionnement :
      - le format du cookie (selecteur + verificateur) et ses attributs ;
      - le cout d'une requete portant un cookie inconnu : une lecture indexee,
        aucun bcrypt (l'ancienne version en faisait un par compte) ;
      - la connexion automatique, sans creation d'un second jeton ;
      - la rotation du verificateur a chaque usage, la tolerance pour les
        requetes menees en parallele, et la revocation generale lorsqu'un
        verificateur perime est presente (cookie copie) ;
      - le refus d'un jeton expire ;
      - la deconnexion, qui ne touche que l'appareil concerne ;
      - le changement de mot de passe, qui retire la connexion automatique
        partout ;
      - l'ecran « Appareils connectes » : liste, revocation unitaire, globale,
        et absence d'identifiant de session dans la page ;
      - l'absence de connexion automatique pour un administrateur ;
      - la disparition de la colonne users.remember_token.

    Jeu d'essai partage avec SEC-10 (utilisateurs 931 et 932).
    Environnement local uniquement.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tests\securite\sec11-souvenir.ps1
#>

$ErrorActionPreference = 'Continue'
$racine = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$mysql  = 'C:\xampp\mysql\bin\mysql.exe'
$base   = 'http://localhost/tchadok'
$SOUVENIR = 'remember_token'

if (-not (Test-Path (Join-Path $racine '.env.local'))) {
    Write-Error "Refus : .env.local absent. Ces tests ne s'executent qu'en local."
    exit 1
}
$baseDb = ((Get-Content (Join-Path $racine '.env.local') | Where-Object { $_ -match '^DB_DATABASE=' }) -split '=', 2)[1].Trim()
if ($baseDb -notmatch 'local|test|dev') { Write-Error "Refus : base '$baseDb' non locale."; exit 1 }

function Sql([string]$fichier) { Get-Content $fichier -Raw -Encoding UTF8 | & $mysql -u root -D $baseDb --default-character-set=utf8mb4 }
function SqlValeur([string]$requete) { "$(& $mysql -u root -D $baseDb -N -B -e $requete 2>$null)".Trim() }
function SqlExec([string]$requete) { & $mysql -u root -D $baseDb -e $requete 2>$null }

$script:ok = 0; $script:ko = 0
function Verif([string]$libelle, [bool]$condition, [string]$detail = '') {
    if ($condition) { $script:ok++; Write-Output ("  OK  " + $libelle) }
    else { $script:ko++; Write-Output ("  !!  " + $libelle + $(if ($detail) { "  -> $detail" } else { '' })) }
}

# --- Outils curl : un « appareil » = un fichier de cookies ---
function Appareil { return @{ Jar = [System.IO.Path]::GetTempFileName() } }

function Obtenir($ap, [string]$chemin) {
    $tmp = [System.IO.Path]::GetTempFileName()
    $ent = [System.IO.Path]::GetTempFileName()
    $code = & curl.exe -s -o $tmp -D $ent -w '%{http_code}' -c $ap.Jar -b $ap.Jar --max-time 30 "$base$chemin"
    $corps = Get-Content $tmp -Raw -ErrorAction SilentlyContinue
    $entetes = (Get-Content $ent -ErrorAction SilentlyContinue) -join "`n"
    [System.IO.File]::Delete($tmp); [System.IO.File]::Delete($ent)
    return @{ Code = [int]$code; Corps = "$corps"; Entetes = "$entetes" }
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

# Valeur d'un cookie dans le fichier de cookies. Le « : » du couple
# selecteur/verificateur y figure sous sa forme encodee (%3A) : setcookie()
# encode la valeur, PHP la decode a la lecture.
function Cookie($ap, [string]$nom) {
    $l = Get-Content $ap.Jar -ErrorAction SilentlyContinue | Where-Object { $_ -match "\t$nom\t" } | Select-Object -Last 1
    if ($l) { return ($l -split "`t")[-1] }
    return ''
}
function Selecteur([string]$cookie) { return ($cookie -split '%3A|:')[0] }

function Connecter($ap, [string]$email, [bool]$souvenir = $true) {
    $j = Jeton $ap
    $champs = @("csrf_token=$j", "email=$email", 'password=tchadok2026')
    if ($souvenir) { $champs += 'remember=1' }
    return Poster $ap '/login.php' $champs
}

# settings.php exige une session ouverte : 200 = connecte, 302 = non connecte.
function EstConnecte($ap) { return ((Obtenir $ap '/settings.php').Code -eq 200) }

# Requete portant uniquement le cookie de souvenir (aucune session)
function VisiteAvecCookie([string]$valeurCookie, [string]$chemin = '/settings.php') {
    $jar = [System.IO.Path]::GetTempFileName()
    $ent = [System.IO.Path]::GetTempFileName()
    $code = & curl.exe -s -o NUL -D $ent -w '%{http_code}' -c $jar -b "$SOUVENIR=$valeurCookie" --max-time 60 "$base$chemin"
    $entetes = (Get-Content $ent -ErrorAction SilentlyContinue) -join "`n"
    $nouveau = ''
    if ($entetes -match "Set-Cookie:\s*$SOUVENIR=([^;]*)") { $nouveau = $matches[1] }
    [System.IO.File]::Delete($jar); [System.IO.File]::Delete($ent)
    return @{ Code = [int]$code; Entetes = "$entetes"; Cookie = $nouveau }
}

Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
Sql (Join-Path $PSScriptRoot 'sec10-fixtures.sql')
$appareils = @()

try {
    Write-Output "`n=== A. Colonne et table ==="
    $colonne = SqlValeur "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$baseDb' AND table_name = 'users' AND column_name = 'remember_token'"
    Verif "La colonne users.remember_token n'existe plus" ($colonne -eq '0') $colonne
    $index = SqlValeur "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = '$baseDb' AND table_name = 'remember_tokens' AND column_name = 'selector' AND non_unique = 0"
    Verif "Le selecteur porte un index unique" ($index -eq '1') $index
    $reste = Select-String -Path (Join-Path $racine '*.php'), (Join-Path $racine 'includes\*.php'), (Join-Path $racine 'admin\*.php') -Pattern 'users SET remember_token|u\.remember_token' -ErrorAction SilentlyContinue
    Verif "Plus aucun code ne lit ni n'ecrit users.remember_token" ($null -eq $reste) ($reste -join ' ; ')

    Write-Output "`n=== B. Cookie pose a la connexion ==="
    $a1 = Appareil; $appareils += $a1
    $null = Connecter $a1 'fan10@essai.local'
    $c1 = Cookie $a1 $SOUVENIR
    Verif "Cookie pose" ($c1 -ne '')
    Verif "Format selecteur:verificateur (32 + 64 hexadecimaux)" ($c1 -match '^[0-9a-f]{32}(%3A|:)[0-9a-f]{64}$') $c1
    $entetes = (Obtenir $a1 '/settings.php').Entetes
    $ligne = ((Get-Content $a1.Jar) -join ' ')
    Verif "Cookie persistant (date d'expiration)" ($ligne -match "\t\d{10}\t$SOUVENIR\t")
    $jetons = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL"
    Verif "Un seul jeton en base pour cet appareil" ($jetons -eq '1') $jetons
    $ligneBase = SqlValeur "SELECT CONCAT(LENGTH(validator_hash), '|', device_label IS NOT NULL, '|', ip_address IS NOT NULL, '|', expires_at > NOW()) FROM remember_tokens WHERE user_id = 931"
    Verif "Empreinte SHA-256 (64 caracteres), appareil, adresse et expiration renseignes" ($ligneBase -eq '64|1|1|1') $ligneBase
    $enClair = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND validator_hash LIKE '`$2y%'"
    Verif "Le verificateur n'est pas un hash bcrypt (plus de cout par comparaison)" ($enClair -eq '0') $enClair

    Write-Output "`n=== C. Cout d'un cookie inconnu ==="
    # 60 jetons supplementaires : l'ancienne version calculait un bcrypt sur
    # chacun a chaque requete anonyme portant un cookie.
    $valeurs = 1..60 | ForEach-Object {
        $sel = -join ((1..32) | ForEach-Object { '{0:x}' -f (Get-Random -Max 16) })
        $val = -join ((1..64) | ForEach-Object { '{0:x}' -f (Get-Random -Max 16) })
        "(931, '$sel', '$val', DATE_ADD(NOW(), INTERVAL 30 DAY))"
    }
    SqlExec ("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES " + ($valeurs -join ',') + ";")
    $total = SqlValeur "SELECT COUNT(*) FROM remember_tokens"
    Verif "Prealable : $total jetons en base" ([int]$total -ge 61) $total
    $faux = ('0' * 32) + ':' + ('f' * 64)
    $duree = (Measure-Command { $r = VisiteAvecCookie $faux '/index.php' }).TotalSeconds
    Verif ("Cookie inconnu traite en {0:N2} s (61 bcrypt auraient depasse 10 s)" -f $duree) ($duree -lt 2)
    $r = VisiteAvecCookie $faux '/index.php'
    Verif "Cookie inconnu retire du navigateur" ($r.Entetes -match "$SOUVENIR=(;|deleted)" -or $r.Entetes -match 'expires=Thu, 01 Jan 1970|Max-Age=0|expires=\w{3}, \d{2} \w{3} 20(0|1|2[0-5])')
    $malforme = 'pas-un-couple'
    $r = VisiteAvecCookie $malforme '/index.php'
    Verif "Cookie malforme : page servie normalement" ($r.Code -eq 200) $r.Code
    # Les 60 jetons ajoutes n'ont jamais servi : eux seuls ont last_used_at nul.
    SqlExec "DELETE FROM remember_tokens WHERE last_used_at IS NULL;"

    Write-Output "`n=== D. Connexion automatique ==="
    $c1 = Cookie $a1 $SOUVENIR
    $r = VisiteAvecCookie $c1
    Verif "Une page reservee s'ouvre avec le seul cookie de souvenir" ($r.Code -eq 200) $r.Code
    Verif "Le verificateur a tourne" ($r.Cookie -ne '' -and $r.Cookie -ne $c1)
    Verif "Le selecteur ne change pas" ((Selecteur $r.Cookie) -eq (Selecteur $c1))
    $jetons = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL"
    Verif "Aucun second jeton cree pour le meme appareil" ($jetons -eq '1') $jetons
    $sessions = SqlValeur "SELECT COUNT(*) FROM user_sessions WHERE user_id = 931"
    Verif "La session ouverte automatiquement est inscrite au registre" ([int]$sessions -ge 1) $sessions

    Write-Output "`n=== E. Rotation : tolerance puis detection de copie ==="
    $courant = $r.Cookie
    $r2 = VisiteAvecCookie $c1
    Verif "Verificateur precedent accepte juste apres la rotation (requetes parallelees)" ($r2.Code -eq 200) $r2.Code
    $revoques = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NOT NULL"
    Verif "Aucune revocation dans ce cas" ($revoques -eq '0') $revoques
    # Au-dela de la tolerance, le meme verificateur signale une copie.
    SqlExec "UPDATE remember_tokens SET rotated_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE user_id = 931;"
    $r3 = VisiteAvecCookie $c1
    Verif "Verificateur perime refuse" ($r3.Code -ne 200) $r3.Code
    $actifs = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL"
    Verif "Tous les jetons du compte sont revoques (cookie copie)" ($actifs -eq '0') $actifs
    $r4 = VisiteAvecCookie $courant
    Verif "Le cookie legitime ne vaut plus rien non plus" ($r4.Code -ne 200) $r4.Code

    Write-Output "`n=== F. Jeton expire ==="
    $a2 = Appareil; $appareils += $a2
    $null = Connecter $a2 'fan10@essai.local'
    $c2 = Cookie $a2 $SOUVENIR
    SqlExec "UPDATE remember_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE user_id = 931 AND revoked_at IS NULL;"
    $r = VisiteAvecCookie $c2
    Verif "Jeton expire : pas de connexion automatique" ($r.Code -ne 200) $r.Code
    $expireRevoque = SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND expires_at < NOW() AND revoked_at IS NOT NULL"
    Verif "Le jeton expire est marque revoque" ([int]$expireRevoque -ge 1) $expireRevoque

    Write-Output "`n=== G. Deconnexion : un seul appareil concerne ==="
    $pc = Appareil; $tel = Appareil; $appareils += $pc, $tel
    $null = Connecter $pc 'fan10@essai.local'
    $null = Connecter $tel 'fan10@essai.local'
    $cPc = Cookie $pc $SOUVENIR
    $cTel = Cookie $tel $SOUVENIR
    Verif "Prealable : deux appareils memorises" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL") -eq '2')
    $j = Jeton $pc '/settings.php'
    $r = Poster $pc '/logout.php' @("csrf_token=$j")
    Verif "Deconnexion du premier appareil" ($r.Code -eq 302) $r.Code
    Verif "Son cookie ne reconnecte plus" ((VisiteAvecCookie $cPc).Code -ne 200)
    Verif "L'autre appareil se reconnecte toujours" ((VisiteAvecCookie $cTel).Code -eq 200)

    Write-Output "`n=== H. Changement de mot de passe : tous les appareils ==="
    $tel2 = Appareil; $appareils += $tel2
    $null = Connecter $tel2 'fan10@essai.local'
    $cTel2 = Cookie $tel2 $SOUVENIR
    $bureau = Appareil; $appareils += $bureau
    $null = Connecter $bureau 'fan10@essai.local' $false
    $j = Jeton $bureau '/settings.php'
    $r = Poster $bureau '/settings.php' @("csrf_token=$j", 'action=change_password', 'current_password=tchadok2026', 'new_password=Nouveau-2026!', 'confirm_password=Nouveau-2026!')
    Verif "Changement de mot de passe accepte" ($r.Corps -match 'modifie avec succes')
    Verif "Aucun appareil ne peut plus se reconnecter sans mot de passe" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL") -eq '0')
    Verif "Le cookie du telephone est sans effet" ((VisiteAvecCookie $cTel2).Code -ne 200)
    SqlExec "UPDATE users SET password = '`$2y`$12`$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG', password_hash = '`$2y`$12`$bAQyH.x8XRJP/bxEtNdflO43TQL0LxI7lfXhrKFwx4OBfyTgyIqLG' WHERE id = 931;"

    Write-Output "`n=== I. Ecran « Appareils connectes » ==="
    $poste = Appareil; $autre = Appareil; $appareils += $poste, $autre
    $null = Connecter $poste 'fan10@essai.local'
    $null = Connecter $autre 'fan10@essai.local'
    $idAutre = ((Get-Content $autre.Jar | Where-Object { $_ -match "`tTCHADOKSESSID`t" }) -split "`t")[-1]
    $cAutre = Cookie $autre $SOUVENIR
    $page = Obtenir $poste '/settings.php'
    Verif "La page liste les sessions ouvertes" ($page.Corps -match 'Sessions ouvertes')
    Verif "... et les appareils memorises" ($page.Corps -match 'Connexion automatique')
    Verif "L'appareil courant est signale" ($page.Corps -match 'Cet appareil')
    Verif "Aucun identifiant de session dans la page" ($page.Corps -notmatch [regex]::Escape($idAutre)) 'identifiant trouve'
    $empreinteAutre = SqlValeur "SELECT SHA2('$idAutre', 256)"
    Verif "L'empreinte de l'autre session figure dans le formulaire" ($page.Corps -match $empreinteAutre) ''
    $j = Jeton $poste '/settings.php'
    $r = Poster $poste '/settings.php' @("csrf_token=$j", 'action=revoke_session', "empreinte=$empreinteAutre")
    Verif "Revocation unitaire d'une session" ($r.Corps -match 'Session fermee')
    Verif "L'autre session est fermee" (-not (EstConnecte $autre))
    # Fermer une session doit aussi retirer la connexion automatique de cet
    # appareil : sinon il revenait de lui-meme a la requete suivante.
    Verif "Le cookie de l'appareil ecarte ne le reconnecte plus" ((VisiteAvecCookie $cAutre).Code -ne 200)
    Verif "L'appareil courant reste connecte" (EstConnecte $poste)

    $idJeton = SqlValeur "SELECT id FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL ORDER BY id DESC LIMIT 1"
    $j = Jeton $poste '/settings.php'
    $r = Poster $poste '/settings.php' @("csrf_token=$j", 'action=revoke_device', "token_id=$idJeton")
    Verif "Revocation unitaire d'un appareil memorise" ($r.Corps -match 'Connexion automatique retiree')
    Verif "Le jeton revoque n'est plus actif" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE id = $idJeton AND revoked_at IS NULL") -eq '0')

    # Un appareil ne peut pas revoquer le jeton de quelqu'un d'autre.
    SqlExec "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (932, '$('a' * 32)', '$('b' * 64)', DATE_ADD(NOW(), INTERVAL 1 DAY));"
    $idAdmin = SqlValeur "SELECT id FROM remember_tokens WHERE user_id = 932 ORDER BY id DESC LIMIT 1"
    $j = Jeton $poste '/settings.php'
    $r = Poster $poste '/settings.php' @("csrf_token=$j", 'action=revoke_device', "token_id=$idAdmin")
    Verif "Impossible de revoquer le jeton d'un autre compte" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE id = $idAdmin AND revoked_at IS NULL") -eq '1') $r.Code

    $tierce = Appareil; $appareils += $tierce
    $null = Connecter $tierce 'fan10@essai.local'
    $j = Jeton $poste '/settings.php'
    $r = Poster $poste '/settings.php' @("csrf_token=$j", 'action=revoke_all')
    Verif "Revocation globale : message affiche" ($r.Corps -match 'fermee|retiree')
    Verif "Les autres sessions sont fermees" (-not (EstConnecte $tierce))
    Verif "Plus aucune connexion automatique" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 931 AND revoked_at IS NULL") -eq '0')
    Verif "L'appareil courant reste connecte" (EstConnecte $poste)

    Write-Output "`n=== J. Administrateur ==="
    $ad = Appareil; $appareils += $ad
    $null = Connecter $ad 'admin10@essai.local'
    Verif "Administrateur connecte" ((Obtenir $ad '/admin-dashboard.php').Code -eq 200)
    Verif "Aucun jeton de connexion automatique pour un administrateur" ((SqlValeur "SELECT COUNT(*) FROM remember_tokens WHERE user_id = 932 AND revoked_at IS NULL") -eq '0')
}
finally {
    Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
    foreach ($ap in $appareils) { if ($ap.Jar -and (Test-Path $ap.Jar)) { [System.IO.File]::Delete($ap.Jar) } }
}

Write-Output ""
Write-Output ("Resultat : {0} reussi(s), {1} echec(s)" -f $script:ok, $script:ko)
exit $(if ($script:ko -eq 0) { 0 } else { 1 })
