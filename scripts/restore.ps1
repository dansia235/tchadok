<#
.SYNOPSIS
    Restauration d'une sauvegarde Tchadok.

.DESCRIPTION
    Tache PREP-02. Restaure une archive produite par scripts\backup.ps1
    dans une base de donnees CIBLE, qui doit etre distincte de la base de
    production sauf intention explicite.

    Le script refuse d'ecrire dans une base non vide sans -Force.

.PARAMETER Archive
    Chemin de l'archive .zip a restaurer.

.PARAMETER Base
    Nom de la base cible. Elle est creee si elle n'existe pas.

.PARAMETER Utilisateur
    Compte MySQL disposant des droits DDL et TRIGGER. Le dump contient des
    CREATE TRIGGER : le compte applicatif, volontairement limite au DML,
    ne suffit pas.

.PARAMETER Force
    Autorise l'ecrasement d'une base existante non vide.

.EXAMPLE
    .\scripts\restore.ps1 -Archive C:\sauvegardes\tchadok-local-20260921-1600.zip -Base tchadok_verif -Utilisateur root
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory)] [string] $Archive,
    [Parameter(Mandatory)] [string] $Base,
    [string] $Utilisateur = 'root',
    [string] $MotDePasse  = '',
    [string] $Hote        = '127.0.0.1',
    [string] $Port        = '3306',
    [string] $Mysql       = 'C:\xampp\mysql\bin\mysql.exe',
    [switch] $Force
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Archive)) { throw "Archive introuvable : $Archive" }
if (-not (Test-Path $Mysql))   { throw "Client mysql introuvable : $Mysql" }

$temp = Join-Path $env:TEMP ("tchadok-restauration-" + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Force -Path $temp | Out-Null

try {
    Write-Host "Extraction de l'archive..."
    Expand-Archive -Path $Archive -DestinationPath $temp -Force

    $manifestePath = Join-Path $temp 'manifeste.json'
    if (Test-Path $manifestePath) {
        $m = Get-Content $manifestePath -Raw | ConvertFrom-Json
        Write-Host "  origine       : $($m.environnement) / base $($m.base)"
        Write-Host "  horodatage    : $($m.horodatage)"
        Write-Host "  commit        : $($m.commit)"
        Write-Host "  fichiers      : $($m.fichiers_copies)"
    }

    $sql = Join-Path $temp 'base.sql'
    if (-not (Test-Path $sql)) { throw "base.sql absent de l'archive." }

    $env:MYSQL_PWD = $MotDePasse
    try {
        # Verification que la cible est vide, sauf -Force
        $existantes = & $Mysql --host=$Hote --port=$Port --user=$Utilisateur -N -B `
            -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$Base';" 2>$null
        if ($existantes -and [int]$existantes -gt 0 -and -not $Force) {
            throw "La base '$Base' contient deja $existantes table(s). Utilisez -Force pour l'ecraser."
        }

        Write-Host "Creation de la base '$Base'..."
        & $Mysql --host=$Hote --port=$Port --user=$Utilisateur `
            -e "DROP DATABASE IF EXISTS ``$Base``; CREATE DATABASE ``$Base`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        if ($LASTEXITCODE -ne 0) { throw "Creation de la base echouee." }

        Write-Host "Import du dump..."
        Get-Content $sql -Raw -Encoding UTF8 |
            & $Mysql --host=$Hote --port=$Port --user=$Utilisateur --default-character-set=utf8mb4 -D $Base
        if ($LASTEXITCODE -ne 0) { throw "Import echoue (code $LASTEXITCODE)." }

        Write-Host ""
        Write-Host "Controles post-restauration :"
        & $Mysql --host=$Hote --port=$Port --user=$Utilisateur -D $Base -e @"
SELECT TABLE_TYPE AS type, COUNT(*) AS nombre
  FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Base' GROUP BY TABLE_TYPE;
SELECT COUNT(*) AS triggers_restaures
  FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$Base';
SELECT COUNT(*) AS utilisateurs FROM users;
"@
    } finally {
        $env:MYSQL_PWD = $null
    }

    $fichiers = Join-Path $temp 'fichiers'
    if (Test-Path $fichiers) {
        $n = (Get-ChildItem $fichiers -Recurse -File | Measure-Object).Count
        Write-Host ""
        Write-Host "L'archive contient $n fichier(s) deposes, extraits dans :"
        Write-Host "  $fichiers"
        Write-Host "Copiez-les manuellement vers le repertoire de stockage cible."
        Write-Host "(Ce repertoire temporaire n'est PAS efface automatiquement.)"
        $script:conserverTemp = $true
    }

    Write-Host ""
    Write-Host "Restauration terminee. Consignez la date de ce test dans"
    Write-Host "docs\exploitation\sauvegarde.md."
}
finally {
    if (-not $script:conserverTemp -and (Test-Path $temp)) {
        Remove-Item $temp -Recurse -Force -ErrorAction SilentlyContinue
    }
}
