@echo off
cd /d "%~dp0"
python scripts\open_sponsor_admin.py
if errorlevel 1 pause
