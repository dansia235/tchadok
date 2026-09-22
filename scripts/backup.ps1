<#
.SYNOPSIS
    Sauvegarde complete de Tchadok : base de donnees + fichiers deposes.

.DESCRIPTION
    Tache PREP-02. Produit une archive horodatee contenant :
      - un export SQL complet (structure, donnees, vues, triggers, routines)
      - le contenu du repertoire de stockage (fichiers deposes)
      - un fichier manifeste decrivant le contenu et l'environnement

    Le script lit la configuration depuis le fichier d'environnement du projet :
    aucun identifiant n'est ecrit ici.

    Une sauvegarde non restauree n'est pas une sauvegarde. Utilisez
    scripts\restore.ps1 sur une base vierge pour la verifier, et consignez
    la date du dernier test dans docs\exploitation\sauvegarde.md.

.PARAMETER Destination
    Repertoire de destination. Doit etre HORS de la racine web et hors du depot.
    Defaut : %USERPROFILE%\tchadok-sauvegardes

.PARAMETER Retention
    Nombre de jours de conservation. Les archives plus anciennes sont effacees.
    Defaut : 30

.EXAMPLE
    .\scripts\backup.ps1
    .\scripts\backup.ps1 -Destination D:\sauvegardes -Retention 90
#>

[CmdletBinding()]
param(
    [string] $Destination = (Join-Path $env:USERPROFILE 'tchadok-sauvegardes'),
    [int]    $Retention   = 30,
    [string] $MysqlDump   = 'C:\xampp\mysql\bin\mysqldump.exe'
)

$ErrorActionPreference = 'Stop'
$projet = Split-Path -Parent $PSScriptRoot

# ---------------------------------------------------------------------------
# Lecture de la configuration d'environnement
# ---------------------------------------------------------------------------
function Read-EnvFile {
    param([string] $Chemin)
    $vars = @{}
    if (-not (Test-Path $Chemin)) { return $vars }
    foreach ($ligne in Get-Content $Chemin -Encoding UTF8) {
        $l = $ligne.Trim()
        if ($l -eq '' -or $l.StartsWith('#')) { continue }
        $i = $l.IndexOf('=')
        if ($i -lt 1) { continue }
        $cle = $l.Substring(0, $i).Trim()
        $val = $l.Substring($i + 1).Trim().Trim('"', "'")
        $vars[$cle] = $val
    }
    return $vars
}

# Meme ordre de resolution que config/env.php
$fichierEnv = $null
foreach ($candidat in @('.env.local', '.env', '.env.production')) {
    $p = Join-Path $projet $candidat
    if (Test-Path $p) { $fichierEnv = $p; break }
}
if (-not $fichierEnv) {
    throw "Aucun fichier d'environnement trouve dans $projet. Sauvegarde impossible."
}

$env_ = Read-EnvFile $fichierEnv
foreach ($requis in @('DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD')) {
    if (-not $env_.ContainsKey($requis) -or [string]::IsNullOrWhiteSpace($env_[$requis])) {
        throw "Variable $requis absente de $fichierEnv."
    }
}

$base = $env_['DB_DATABASE']

# Compte de sauvegarde dedie.
#
# Le compte applicatif est volontairement limite au DML (SELECT, INSERT,
# UPDATE, DELETE) : il ne peut donc PAS lire les definitions de vues ni de
# triggers, et mysqldump echoue avec "SHOW VIEW command denied".
#
# La sauvegarde utilise un compte distinct en lecture seule, portant
# SELECT, SHOW VIEW, LOCK TABLES, TRIGGER et EVENT sur la seule base du
# projet. Ne jamais elargir ces droits a *.* : le compte pourrait alors
# lire toutes les bases du serveur.
if ($env_.ContainsKey('BACKUP_DB_USERNAME') -and -not [string]::IsNullOrWhiteSpace($env_['BACKUP_DB_USERNAME'])) {
    $user   = $env_['BACKUP_DB_USERNAME']
    $motDeP = $env_['BACKUP_DB_PASSWORD']
    Write-Host "Compte de sauvegarde dedie : $user"
} else {
    $user   = $env_['DB_USERNAME']
    $motDeP = $env_['DB_PASSWORD']
    Write-Warning "BACKUP_DB_USERNAME non defini : utilisation du compte applicatif."
    Write-Warning "L'export echouera si ce compte n'a pas SHOW VIEW et TRIGGER."
}

$hote   = if ($env_.ContainsKey('DB_HOST')) { $env_['DB_HOST'] } else { '127.0.0.1' }
$port   = if ($env_.ContainsKey('DB_PORT')) { $env_['DB_PORT'] } else { '3306' }
$appEnv = if ($env_.ContainsKey('APP_ENV')) { $env_['APP_ENV'] } else { 'inconnu' }

# ---------------------------------------------------------------------------
# Preparation
# ---------------------------------------------------------------------------
if (-not (Test-Path $MysqlDump)) { throw "mysqldump introuvable : $MysqlDump" }

$horodatage = Get-Date -Format 'yyyyMMdd-HHmmss'
$nom        = "tchadok-$appEnv-$horodatage"
$dossier    = Join-Path $Destination $nom
New-Item -ItemType Directory -Force -Path $dossier | Out-Null

Write-Host "Sauvegarde $nom"
Write-Host "  environnement : $appEnv"
Write-Host "  base          : $base sur $hote`:$port"
Write-Host "  destination   : $dossier"

# ---------------------------------------------------------------------------
# 1. Export de la base
# ---------------------------------------------------------------------------
$fichierSql = Join-Path $dossier 'base.sql'
Write-Host "  [1/3] export de la base..."

# Le mot de passe passe par MYSQL_PWD pour ne pas apparaitre dans la liste
# des processus, contrairement a -p<motdepasse>.
$env:MYSQL_PWD = $motDeP
try {
    & $MysqlDump `
        --host=$hote --port=$port --user=$user `
        --single-transaction --quick --routines --triggers --events `
        --default-character-set=utf8mb4 `
        --result-file=$fichierSql `
        $base
    if ($LASTEXITCODE -ne 0) { throw "mysqldump a echoue (code $LASTEXITCODE)." }
} finally {
    $env:MYSQL_PWD = $null
}

$tailleSql = [math]::Round((Get-Item $fichierSql).Length / 1MB, 2)
Write-Host "        base.sql : $tailleSql Mo"

# ---------------------------------------------------------------------------
# 2. Fichiers deposes
# ---------------------------------------------------------------------------
Write-Host "  [2/3] fichiers deposes..."
$sources = @('storage\uploads', 'uploads') | ForEach-Object { Join-Path $projet $_ } | Where-Object { Test-Path $_ }
$nbFichiers = 0
if ($sources) {
    $cible = Join-Path $dossier 'fichiers'
    New-Item -ItemType Directory -Force -Path $cible | Out-Null
    foreach ($src in $sources) {
        $feuille = Split-Path $src -Leaf
        Copy-Item -Path $src -Destination (Join-Path $cible $feuille) -Recurse -Force
        $nbFichiers += (Get-ChildItem $src -Recurse -File | Measure-Object).Count
    }
    Write-Host "        $nbFichiers fichier(s)"
} else {
    Write-Host "        aucun repertoire de depot trouve"
}

# ---------------------------------------------------------------------------
# 3. Manifeste et archive
# ---------------------------------------------------------------------------
Write-Host "  [3/3] manifeste et archive..."
$commit = try { (& git -C $projet rev-parse --short HEAD 2>$null) } catch { 'inconnu' }

$manifeste = [ordered]@{
    horodatage      = (Get-Date -Format 'o')
    environnement   = $appEnv
    base            = $base
    hote            = "$hote`:$port"
    commit          = $commit
    taille_sql_mo   = $tailleSql
    fichiers_copies = $nbFichiers
    machine         = $env:COMPUTERNAME
    operateur       = $env:USERNAME
}
$manifeste | ConvertTo-Json | Set-Content (Join-Path $dossier 'manifeste.json') -Encoding UTF8

$archive = Join-Path $Destination "$nom.zip"
Compress-Archive -Path "$dossier\*" -DestinationPath $archive -Force
Remove-Item $dossier -Recurse -Force

$tailleZip = [math]::Round((Get-Item $archive).Length / 1MB, 2)
Write-Host ""
Write-Host "Archive : $archive ($tailleZip Mo)"

# ---------------------------------------------------------------------------
# Purge selon la retention
# ---------------------------------------------------------------------------
$limite = (Get-Date).AddDays(-$Retention)
$anciennes = Get-ChildItem $Destination -Filter 'tchadok-*.zip' -File |
             Where-Object { $_.LastWriteTime -lt $limite }
foreach ($a in $anciennes) {
    Remove-Item $a.FullName -Force
    Write-Host "Purgee (> $Retention j) : $($a.Name)"
}

Write-Host ""
Write-Host "RAPPEL : une sauvegarde non restauree n'est pas une sauvegarde."
Write-Host "Verifiez-la avec scripts\restore.ps1 sur une base vierge."
