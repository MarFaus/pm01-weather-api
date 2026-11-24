@echo off
rem Svodka (Open-Meteo weather) - one-click start.
rem All checks and downloads live in scripts\start.ps1 (Windows PowerShell 5.1,
rem built into Windows 10/11). This file is ASCII-only on purpose: cmd.exe
rem loses batch labels in UTF-8 files, so Russian text lives in the .ps1.
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start.ps1"
