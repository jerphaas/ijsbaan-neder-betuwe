@echo off
cd /d "%~dp0"
python scripts\publish.py --publish
if errorlevel 1 (
  echo.
  echo Publicatie niet bevestigd. Zie de melding hierboven.
)
pause
