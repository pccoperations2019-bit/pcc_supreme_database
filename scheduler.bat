@echo off
cd /d C:\laragon\www\supreme-cbed-poc

:loop
"C:\laragon\bin\php\php-8.4.27-nts-Win32-vs17-x64\php.exe" artisan schedule:run

timeout /t 60 /nobreak >nul

goto loop
