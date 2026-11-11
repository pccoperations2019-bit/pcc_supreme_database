@echo off

cd /d "C:\laragon\www\supreme-cbed-poc"

if not exist "storage\logs" mkdir "storage\logs"

echo ================================================== >> storage\logs\queue_worker.log
echo Worker started at %date% %time% >> storage\logs\queue_worker.log
echo ================================================== >> storage\logs\queue_worker.log

:loop

echo [%date% %time%] Starting queue worker... >> storage\logs\queue_worker.log

"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" ^
    -d memory_limit=6144M ^
    artisan queue:work ^
    --sleep=0 ^
    --tries=1 ^
    --timeout=3600 ^
    --max-jobs=50 ^
    --backoff=10 ^
    >> storage\logs\queue_worker.log 2>&1

echo [%date% %time%] Worker stopped with exit code %ERRORLEVEL%. Restarting... >> storage\logs\queue_worker.log

timeout /t 2 /nobreak >nul

goto loop
