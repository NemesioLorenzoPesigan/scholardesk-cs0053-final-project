@echo off
cd /d "%~dp0"
"C:\xampp\php\php.exe" tests\unit.php
if errorlevel 1 goto failed
"C:\xampp\php\php.exe" tests\integration.php
if errorlevel 1 goto failed
echo All PHP tests passed.
pause
exit /b 0
:failed
echo A test failed. Read the output above.
pause
exit /b 1
