@echo off
rem ======================================================================
rem  Simulateurs de paiement Tchadok (PAY-03) - LOCAL UNIQUEMENT
rem
rem    scripts\mock-gateways.bat          demarre les simulateurs
rem    scripts\mock-gateways.bat stop     les arrete
rem
rem  Airtel Money  http://127.0.0.1:9101
rem  Moov Money    http://127.0.0.1:9102
rem  VISA          http://127.0.0.1:9103   (page hebergee + 3-D Secure)
rem  GIMAC         http://127.0.0.1:9104
rem  Console      http://127.0.0.1:9100   (pilotage : valider, refuser, rejouer...)
rem  + le distributeur des callbacks (fenetre "Tchadok simulateur - callbacks")
rem
rem  Ecoute sur 127.0.0.1 uniquement : jamais accessible depuis une autre
rem  machine. Scenarios : docs\paiement\jeux-de-test.md
rem ======================================================================
setlocal
cd /d "%~dp0.."

set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"

if /i "%~1"=="stop" goto :arret

if not exist ".env.local" (
    echo [ERREUR] .env.local absent : les simulateurs ne tournent qu'en local.
    exit /b 1
)

start "Tchadok simulateur - Airtel Money" /min "%PHP%" -S 127.0.0.1:9101 mock-gateways\airtel\index.php
start "Tchadok simulateur - Moov Money"   /min "%PHP%" -S 127.0.0.1:9102 mock-gateways\moov\index.php
start "Tchadok simulateur - VISA"         /min "%PHP%" -S 127.0.0.1:9103 mock-gateways\visa\index.php
start "Tchadok simulateur - GIMAC"        /min "%PHP%" -S 127.0.0.1:9104 mock-gateways\gimac\index.php
start "Tchadok simulateur - Console"       /min "%PHP%" -S 127.0.0.1:9100 mock-gateways\console\index.php
start "Tchadok simulateur - callbacks"         "%PHP%" mock-gateways\distributeur.php

echo Simulateurs demarres. Console : http://127.0.0.1:9100
echo Arret : scripts\mock-gateways.bat stop
exit /b 0

:arret
taskkill /FI "WINDOWTITLE eq Tchadok simulateur*" /T /F >nul 2>&1
echo Simulateurs arretes.
exit /b 0
