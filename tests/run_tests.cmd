@echo off
setlocal

if defined PHPEXE goto RunTests

if defined PHPRC (
    set PHPEXE="%PHPRC%php.exe"
    goto RunTests
)

if defined PHP_PEAR_BIN_DIR (
    set PHPEXE="%PHP_PEAR_BIN_DIR%\php.exe"
    goto RunTests
)

echo Can't find the PHP executable. Is it installed somewhere?
echo (if you're using a portable version, please define a PHPEXE environment
echo  variable pointing to it)
exit /b 1
goto :eof

:RunTests
%PHPEXE% %~dp0run_tests.php %*

