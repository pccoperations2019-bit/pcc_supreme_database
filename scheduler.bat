@echo off
cd /d C:\laragon\www\supreme-cbed-poc

:loop
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan schedule:run

timeout /t 60 /nobreak >nul

goto loop
