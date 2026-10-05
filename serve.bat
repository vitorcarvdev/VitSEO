@echo off
set PHP=php
where php >nul 2>nul
if errorlevel 1 set PHP=D:\LocalHost\bin\php\php8.4.15\php.exe
echo VitSEO em http://127.0.0.1:8094/
"%PHP%" -d xdebug.mode=off -d display_errors=0 -d html_errors=0 -S 127.0.0.1:8094 -t public
