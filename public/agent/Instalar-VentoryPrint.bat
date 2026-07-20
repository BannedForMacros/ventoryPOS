@echo off
setlocal enableextensions enabledelayedexpansion
title VentoryPrint - Instalar / Actualizar
color 0B

REM ==========================================================
REM  Instalador/actualizador del agente VentoryPrint.
REM  - Cierra el agente viejo.
REM  - Copia el VentoryPrint.exe nuevo a una carpeta del usuario.
REM  - Deja el arranque automatico y lo enciende.
REM  Conserva impresora y token (estan en %APPDATA%, no se tocan).
REM
REM  USO: pon este .bat en la MISMA carpeta que el VentoryPrint.exe
REM       que bajaste de WhatsApp, y haz doble clic. Sin admin.
REM ==========================================================

REM 1) Ubicar el exe nuevo: al lado de este .bat, o en Descargas.
set "NEW=%~dp0VentoryPrint.exe"
if not exist "%NEW%" set "NEW=%USERPROFILE%\Downloads\VentoryPrint.exe"

set "DESTDIR=%LOCALAPPDATA%\VentoryPrint"
set "DEST=%DESTDIR%\VentoryPrint.exe"

echo(
echo ==================================================
echo    VentoryPrint - Instalar / Actualizar agente
echo ==================================================
echo(

if not exist "%NEW%" (
  echo [ERROR] No encuentro el archivo VentoryPrint.exe
  echo(
  echo   Deja este .bat en la MISMA carpeta que el VentoryPrint.exe
  echo   que descargaste de WhatsApp ^(o dejalo en Descargas^) y
  echo   vuelve a hacer doble clic.
  echo(
  pause
  exit /b 1
)

echo Programa nuevo : "%NEW%"
echo Se instalara en: "%DEST%"
echo(

echo [1/4] Cerrando el agente anterior si esta abierto...
taskkill /F /IM VentoryPrint.exe /T >nul 2>&1
ping -n 3 127.0.0.1 >nul

echo [2/4] Copiando el programa nuevo...
if not exist "%DESTDIR%" mkdir "%DESTDIR%"

set /a i=0
:copiar
copy /Y "%NEW%" "%DEST%" >nul 2>&1
if %errorlevel%==0 goto copiado
set /a i+=1
if !i! GEQ 12 (
  echo(
  echo   [ERROR] No se pudo copiar el programa.
  echo   Puede que el agente viejo siga abierto: cierralo desde el
  echo   Administrador de tareas ^(Ctrl+Shift+Esc, VentoryPrint,
  echo   Finalizar tarea^) y vuelve a ejecutar este instalador.
  echo(
  pause
  exit /b 1
)
ping -n 2 127.0.0.1 >nul
goto copiar
:copiado

REM Quitar la marca de "descargado de internet" para evitar avisos de SmartScreen.
powershell -NoProfile -Command "Unblock-File -LiteralPath '%DEST%'" >nul 2>&1

echo [3/4] Activando el arranque automatico con Windows...
"%DEST%" --install-autostart >nul 2>&1

echo [4/4] Iniciando el agente...
start "" "%DEST%" --autostart

echo(
echo ==================================================
echo    LISTO. VentoryPrint quedo actualizado y corriendo.
echo(
echo    - Mira el icono junto al reloj ^(abajo a la derecha^).
echo    - Tu impresora y tu token se conservaron.
echo    - Haz una venta de prueba para ver el logo.
echo ==================================================
echo(
pause
endlocal
