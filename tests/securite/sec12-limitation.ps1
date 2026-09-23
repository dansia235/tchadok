<#
.SYNOPSIS
    Tests d'integration SEC-12 : limitation de debit et verrouillage de compte.

.DESCRIPTION
    Verifie, contre le site local en fonctionnement :
      - le verrouillage apres des echecs de connexion repetes, sur la page
        publique comme sur la console d'administration ;
      - le message identique que le compte existe ou non ;
      - qu'un verrou n'est pas contournable avec le bon mot de passe ;
      - qu'une connexion reussie remet le compteur a zero ;
      - l'aggravation du blocage au-dela de dix echecs ;
      - la garde par adresse, tous identifiants confondus ;
      - qu'un tiers ne peut pas verrouiller le compte de quelqu'un d'autre
        depuis une autre adresse ;
      - la limitation de debit des API et des formulaires (429, Retry-After,
        JSON pour les API, page pour les autres) ;
      - qu'une requete refusee n'allonge pas la punition ;
      - la question de verification apres plusieurs envois ;
      - la purge des enregistrements anciens ;
      - l'interrupteur RATE_LIMIT_ENABLED.

    Le script vide login_attempts et rate_limit_hits entre les sections : il
    est donc a reserver a une base locale, ce qu'il verifie au demarrage.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tests\securite\sec12-limitation.ps1
#>

$ErrorActionPreference = 'Continue'
$racine = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$mysql  = 'C:\xampp\mysql\bin\mysql.exe'
$php    = 'C:\xampp\php\php.exe'
$base   = 'http://localhost/tchadok'
$envLocalPath = Join-Path $racine '.env.local'

if (-not (Test-Path $envLocalPath)) {
    Write-Error "Refus : .env.local absent. Ces tests ne s'executent qu'en local."
    exit 1
}
$baseDb = ((Get-Content $envLocalPath | Where-Object { $_ -match '^DB_DATABASE=' }) -split '=', 2)[1].Trim()
if ($baseDb -notmatch 'local|test|dev') { Write-Error "Refus : base '$baseDb' non locale."; exit 1 }

function Sql([string]$fichier) { Get-Content $fichier -Raw -Encoding UTF8 | & $mysql -u root -D $baseDb --default-character-set=utf8mb4 }
function SqlValeur([string]$requete) { "$(& $mysql -u root -D $baseDb -N -B -e $requete 2>$null)".Trim() }
function SqlExec([string]$requete) { & $mysql -u root -D $baseDb -e $requete 2>$null }
function ViderCompteurs { SqlExec "DELETE FROM login_attempts; DELETE FROM rate_limit_hits;" }

$script:ok = 0; $script:ko = 0
function Verif([string]$libelle, [bool]$condition, [string]$detail = '') {
    if ($condition) { $script:ok++; Write-Output ("  OK  " + $libelle) }
    else { $script:ko++; Write-Output ("  !!  " + $libelle + $(if ($detail) { "  -> $detail" } else { '' })) }
}

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

function Jeton($ap, [string]$chemin) {
    $r = Obtenir $ap $chemin
    if ($r.Corps -match 'name="csrf-token" content="([a-f0-9]+)"') { return $matches[1] }
    return ''
}

function Poster($ap, [string]$chemin, [string[]]$champs) {
    $tmp = [System.IO.Path]::GetTempFileName()
    $ent = [System.IO.Path]::GetTempFileName()
    $a = @('-s', '-o', $tmp, '-D', $ent, '-w', '%{http_code}', '-c', $ap.Jar, '-b', $ap.Jar, '-X', 'POST', '--max-time', '30')
    foreach ($c in $champs) { $a += @('--data-urlencode', $c) }
    $a += "$base$chemin"
    $code = & curl.exe @a
    $corps = Get-Content $tmp -Raw -ErrorAction SilentlyContinue
    $entetes = (Get-Content $ent -ErrorAction SilentlyContinue) -join "`n"
    [System.IO.File]::Delete($tmp); [System.IO.File]::Delete($ent)
    return @{ Code = [int]$code; Corps = "$corps"; Entetes = "$entetes" }
}

# Une tentative de connexion complete (jeton frais a chaque fois)
function Tenter($ap, [string]$identifiant, [string]$motDePasse, [string]$page = '/login.php') {
    $j = Jeton $ap $page
    $champ = if ($page -eq '/login.php') { 'email' } else { 'username' }
    return Poster $ap $page @("csrf_token=$j", "$champ=$identifiant", "password=$motDePasse")
}

function MessageVerrou([string]$corps) {
    if ($corps -match 'Trop de tentatives de connexion[^<]*') { return $matches[0].Trim() }
    return ''
}

$appareils = @()
$envOrigine = [System.IO.File]::ReadAllText($envLocalPath)

Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
Sql (Join-Path $PSScriptRoot 'sec10-fixtures.sql')

try {
    Write-Output "`n=== A. Verrouillage apres echecs repetes ==="
    ViderCompteurs
    $a = Appareil; $appareils += $a
    $messages = @()
    for ($i = 1; $i -le 6; $i++) {
        $r = Tenter $a 'fan10@essai.local' 'mauvais-mot-de-passe'
        $messages += $r.Corps
    }
    Verif "Les 5 premiers essais sont traites normalement" ((MessageVerrou $messages[3]) -eq '') ''
    $verrou = MessageVerrou $messages[5]
    Verif "Le 6e essai est verrouille" ($verrou -ne '') ''
    Verif "Le message annonce un delai" ($verrou -match '\d+ minutes|une heure|une minute') $verrou
    $enregistres = SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'fan10@essai.local' AND success = 0"
    Verif "Seules les 5 tentatives evaluees sont enregistrees" ($enregistres -eq '5') $enregistres

    $r = Tenter $a 'fan10@essai.local' 'tchadok2026'
    Verif "Le bon mot de passe ne contourne pas le verrou" ((MessageVerrou $r.Corps) -ne '') ''
    Verif "... et la session n'est pas ouverte" ((Obtenir $a '/settings.php').Code -ne 200)
    $apres = SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'fan10@essai.local'"
    Verif "Insister n'ajoute rien (le verrou ne s'allonge pas)" ($apres -eq '5') $apres

    Write-Output "`n=== B. Message identique, compte existant ou non ==="
    ViderCompteurs
    $b = Appareil; $appareils += $b
    for ($i = 1; $i -le 6; $i++) { $r = Tenter $b 'inconnu-total@essai.local' 'mauvais' }
    $msgInconnu = MessageVerrou $r.Corps
    ViderCompteurs
    $c = Appareil; $appareils += $c
    for ($i = 1; $i -le 6; $i++) { $r = Tenter $c 'fan10@essai.local' 'mauvais' }
    $msgExistant = MessageVerrou $r.Corps
    Verif "Verrou sur un identifiant inexistant" ($msgInconnu -ne '') ''
    Verif "Message strictement identique dans les deux cas" ($msgInconnu -eq $msgExistant) "$msgInconnu | $msgExistant"

    Write-Output "`n=== C. Une connexion reussie remet le compteur a zero ==="
    ViderCompteurs
    $d = Appareil; $appareils += $d
    for ($i = 1; $i -le 3; $i++) { $null = Tenter $d 'fan10@essai.local' 'mauvais' }
    $r = Tenter $d 'fan10@essai.local' 'tchadok2026'
    Verif "Connexion reussie apres 3 echecs" ((Obtenir $d '/settings.php').Code -eq 200)
    $restants = SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'fan10@essai.local' AND success = 0"
    Verif "Les echecs du couple sont effaces" ($restants -eq '0') $restants
    $reussites = SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'fan10@essai.local' AND success = 1"
    Verif "La reussite reste tracee (piste d'audit)" ($reussites -eq '1') $reussites

    Write-Output "`n=== D. Aggravation au-dela de dix echecs ==="
    ViderCompteurs
    # Dix echecs vieux de 20 minutes : le blocage court (15 min) serait termine.
    $valeurs = 1..10 | ForEach-Object { "('fan10@essai.local', '::1', 0, DATE_SUB(NOW(), INTERVAL 20 MINUTE))" }
    SqlExec ("INSERT INTO login_attempts (identifier, ip_address, success, created_at) VALUES " + ($valeurs -join ',') + ";")
    $e = Appareil; $appareils += $e
    $r = Tenter $e 'fan10@essai.local' 'mauvais'
    $msg = MessageVerrou $r.Corps
    Verif "Toujours verrouille 20 minutes apres (blocage d'une heure)" ($msg -ne '') ''
    Verif "Le delai annonce depasse le blocage court" ($msg -match '(3[0-9]|4[0-9]|5[0-9]) minutes|une heure') $msg

    Write-Output "`n=== E. Garde par adresse, tous identifiants confondus ==="
    ViderCompteurs
    $valeurs = 1..20 | ForEach-Object { "('compte$_@essai.local', '::1', 0, NOW())" }
    SqlExec ("INSERT INTO login_attempts (identifier, ip_address, success, created_at) VALUES " + ($valeurs -join ',') + ";")
    $f = Appareil; $appareils += $f
    $r = Tenter $f 'encore-un-autre@essai.local' 'mauvais'
    Verif "Vingt comptes essayes depuis la meme adresse : la suite est bloquee" ((MessageVerrou $r.Corps) -ne '') ''

    Write-Output "`n=== F. Verrouiller le compte d'un tiers a distance ==="
    ViderCompteurs
    # Quinze echecs sur ce compte, depuis une autre adresse.
    $valeurs = 1..15 | ForEach-Object { "('fan10@essai.local', '203.0.113.7', 0, NOW())" }
    SqlExec ("INSERT INTO login_attempts (identifier, ip_address, success, created_at) VALUES " + ($valeurs -join ',') + ";")
    $g = Appareil; $appareils += $g
    $r = Tenter $g 'fan10@essai.local' 'tchadok2026'
    Verif "Le titulaire se connecte malgre les echecs d'un tiers" ((Obtenir $g '/settings.php').Code -eq 200)

    Write-Output "`n=== G. Console d'administration ==="
    ViderCompteurs
    $h = Appareil; $appareils += $h
    for ($i = 1; $i -le 6; $i++) { $r = Tenter $h 'admin10@essai.local' 'mauvais' '/admin/login.php' }
    Verif "Meme verrou sur /admin/login.php" ((MessageVerrou $r.Corps) -ne '') ''
    ViderCompteurs
    # Identifiants valides mais compte non administrateur : compte comme un echec.
    $i2 = Appareil; $appareils += $i2
    for ($i = 1; $i -le 6; $i++) { $r = Tenter $i2 'fan10@essai.local' 'tchadok2026' '/admin/login.php' }
    Verif "Un compte non administrateur ne sert pas a essayer sans limite" ((MessageVerrou $r.Corps) -ne '') ''

    Write-Output "`n=== H. Limitation de debit des API ==="
    ViderCompteurs
    $codes = @()
    $entetes = ''
    $corps = ''
    for ($i = 1; $i -le 62; $i++) {
        $tmp = [System.IO.Path]::GetTempFileName()
        $ent = [System.IO.Path]::GetTempFileName()
        $codes += & curl.exe -s -o $tmp -D $ent -w '%{http_code}' "$base/api/search.php?q=essai"
        if ($i -eq 62) { $corps = Get-Content $tmp -Raw; $entetes = (Get-Content $ent) -join "`n" }
        [System.IO.File]::Delete($tmp); [System.IO.File]::Delete($ent)
    }
    Verif "Les 60 premieres recherches passent" (($codes[0..59] | Where-Object { $_ -ne '200' }).Count -eq 0) (($codes[0..59] | Select-Object -Unique) -join ',')
    Verif "Au-dela : 429" ($codes[61] -eq '429') $codes[61]
    Verif "Reponse JSON" ($entetes -match 'Content-Type:\s*application/json') ''
    Verif "En-tete Retry-After" ($entetes -match 'Retry-After:\s*\d+') ''
    Verif "Corps JSON explicite (reason debit)" ($corps -match '"reason"\s*:\s*"debit"' -and $corps -match '"code"\s*:\s*429') $corps
    $comptees = SqlValeur "SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = 'recherche'"
    Verif "Les requetes refusees ne sont pas comptees" ($comptees -eq '60') $comptees

    Write-Output "`n=== I. Limitation d'api/stream.php ==="
    ViderCompteurs
    $valeurs = 1..60 | ForEach-Object { "('ecoute', '::1', NOW())" }
    SqlExec ("INSERT INTO rate_limit_hits (bucket, ip_address, created_at) VALUES " + ($valeurs -join ',') + ";")
    $j2 = Appareil; $appareils += $j2
    $jeton = Jeton $j2 '/login.php'
    $r = Poster $j2 '/api/stream.php' @("csrf_token=$jeton", 'track_id=999999999', 'duration=40')
    Verif "Enregistrement d'ecoute refuse en 429 au-dela du seuil" ($r.Code -eq 429) $r.Code

    Write-Output "`n=== J. Limitation d'une page (reponse HTML) ==="
    ViderCompteurs
    $k = Appareil; $appareils += $k
    for ($i = 1; $i -le 6; $i++) {
        $jeton = Jeton $k '/admin/reset-password.php'
        $r = Poster $k '/admin/reset-password.php' @("csrf_token=$jeton", 'action=request', 'identifier=inconnu@essai.local')
    }
    Verif "6e demande de reinitialisation : 429" ($r.Code -eq 429) $r.Code
    Verif "Reponse en HTML, pas en JSON" ($r.Entetes -match 'Content-Type:\s*text/html') ''
    Verif "Message comprehensible" ($r.Corps -match 'Trop de requetes') ''

    Write-Output "`n=== K. Question de verification ==="
    ViderCompteurs
    $l = Appareil; $appareils += $l
    $page1 = Obtenir $l '/contact.php'
    Verif "Aucune question au premier affichage" ($page1.Corps -notmatch 'Combien font') ''
    for ($i = 1; $i -le 2; $i++) {
        $jeton = Jeton $l '/contact.php'
        $null = Poster $l '/contact.php' @("csrf_token=$jeton", 'name=Essai', 'email=essai@essai.local', 'subject=Bonjour', 'message=Un message de test suffisamment long.')
    }
    $page3 = Obtenir $l '/contact.php'
    Verif "La question apparait apres deux envois" ($page3.Corps -match 'Combien font (\d+) \+ (\d+)') ''
    $somme = if ($page3.Corps -match 'Combien font (\d+) \+ (\d+)') { [int]$matches[1] + [int]$matches[2] } else { -1 }
    $jeton = if ($page3.Corps -match 'name="csrf-token" content="([a-f0-9]+)"') { $matches[1] } else { '' }
    $r = Poster $l '/contact.php' @("csrf_token=$jeton", 'name=Essai', 'email=essai@essai.local', 'subject=Bonjour', 'message=Un message de test suffisamment long.', 'captcha=1')
    Verif "Mauvaise reponse : envoi refuse" ($r.Corps -match 'verification est incorrecte') ''
    $page = Obtenir $l '/contact.php'
    $somme = if ($page.Corps -match 'Combien font (\d+) \+ (\d+)') { [int]$matches[1] + [int]$matches[2] } else { -1 }
    $jeton = if ($page.Corps -match 'name="csrf-token" content="([a-f0-9]+)"') { $matches[1] } else { '' }
    $r = Poster $l '/contact.php' @("csrf_token=$jeton", 'name=Essai', 'email=essai@essai.local', 'subject=Bonjour', 'message=Un message de test suffisamment long.', "captcha=$somme")
    Verif "Bonne reponse : message accepte" ($r.Corps -match 'envoye avec succes') ''
    # La page de confirmation arme deja une NOUVELLE question. Comme les sommes
    # vont de 4 a 18, l'ancienne reponse tombe juste par hasard une fois sur dix
    # environ -- ce qui faisait echouer ce controle au hasard. On tire donc
    # jusqu'a obtenir une question differente avant de rejouer l'ancienne
    # reponse : le refus devient certain, et le controle garde son sens.
    $ancienneSomme = $somme
    for ($essai = 0; $essai -lt 10; $essai++) {
        $page = Obtenir $l '/contact.php'
        $nouvelle = if ($page.Corps -match 'Combien font (\d+) \+ (\d+)') { [int]$matches[1] + [int]$matches[2] } else { -1 }
        $jeton = if ($page.Corps -match 'name="csrf-token" content="([a-f0-9]+)"') { $matches[1] } else { '' }
        if ($nouvelle -ne $ancienneSomme) { break }
    }
    $r = Poster $l '/contact.php' @("csrf_token=$jeton", 'name=Essai', 'email=essai@essai.local', 'subject=Bonjour', 'message=Un message de test suffisamment long.', "captcha=$ancienneSomme")
    Verif "La meme reponse ne se rejoue pas" ($r.Corps -match 'verification est incorrecte') "ancienne=$ancienneSomme attendue=$nouvelle"

    Write-Output "`n=== L. Purge ==="
    ViderCompteurs
    SqlExec "INSERT INTO login_attempts (identifier, ip_address, success, created_at) VALUES ('vieux@essai.local', '::1', 0, DATE_SUB(NOW(), INTERVAL 100 DAY)), ('recent@essai.local', '::1', 0, NOW());"
    SqlExec "INSERT INTO rate_limit_hits (bucket, ip_address, created_at) VALUES ('recherche', '::1', DATE_SUB(NOW(), INTERVAL 2 DAY)), ('recherche', '::1', NOW());"
    & $php -r "chdir('$($racine -replace '\\', '/')'); require 'includes/functions.php'; LimiteDebit::purger();" 2>&1 | Out-Null
    Verif "Tentative de plus de 90 jours supprimee" ((SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'vieux@essai.local'") -eq '0')
    Verif "Tentative recente conservee" ((SqlValeur "SELECT COUNT(*) FROM login_attempts WHERE identifier = 'recent@essai.local'") -eq '1')
    Verif "Compteur de debit de plus d'un jour supprime" ((SqlValeur "SELECT COUNT(*) FROM rate_limit_hits") -eq '1')

    Write-Output "`n=== M. Interrupteur RATE_LIMIT_ENABLED ==="
    ViderCompteurs
    $modifie = $envOrigine -replace '(?m)^RATE_LIMIT_ENABLED=.*$', 'RATE_LIMIT_ENABLED=false'
    [System.IO.File]::WriteAllText($envLocalPath, $modifie, (New-Object System.Text.UTF8Encoding $false))
    $m = Appareil; $appareils += $m
    for ($i = 1; $i -le 7; $i++) { $r = Tenter $m 'fan10@essai.local' 'mauvais' }
    Verif "Limitation desactivee : plus de verrou" ((MessageVerrou $r.Corps) -eq '') ''
    [System.IO.File]::WriteAllText($envLocalPath, $envOrigine, (New-Object System.Text.UTF8Encoding $false))
    ViderCompteurs
    $n = Appareil; $appareils += $n
    for ($i = 1; $i -le 6; $i++) { $r = Tenter $n 'fan10@essai.local' 'mauvais' }
    Verif "Reactivee : le verrou revient" ((MessageVerrou $r.Corps) -ne '') ''
}
finally {
    [System.IO.File]::WriteAllText($envLocalPath, $envOrigine, (New-Object System.Text.UTF8Encoding $false))
    ViderCompteurs
    Sql (Join-Path $PSScriptRoot 'sec10-nettoyage.sql')
    foreach ($ap in $appareils) { if ($ap.Jar -and (Test-Path $ap.Jar)) { [System.IO.File]::Delete($ap.Jar) } }
}

Write-Output ""
Write-Output ("Resultat : {0} reussi(s), {1} echec(s)" -f $script:ok, $script:ko)
exit $(if ($script:ko -eq 0) { 0 } else { 1 })
