@echo off
setlocal

:: First see if there's a pre-defined PHP executable path.
if defined PHPEXE goto RunChef

:: Then see if the PHP executable is in the PATH.
for %%i in (php.exe) do (
    if not "%%~dp$PATH:i"=="" (
        set PHPEXE="%%~dp$PATH:i\php.exe"
        goto RunChef
    )
)

:: Ok, let's look for a standard PHP install.
if defined PHPRC (
    set PHPEXE="%PHPRC%php.exe"
    goto RunChef
)

:: Or maybe a PEAR install?
if defined PHP_PEAR_BIN_DIR (
    set PHPEXE="%PHP_PEAR_BIN_DIR%\php.exe"
    goto RunChef
)

:: Or maybe a XAMPP install? (on 32 and 64 bits Windows)
FOR /F "tokens=3" %%G IN ('"reg query HKLM\SOFTWARE\xampp /v Install_Dir 2> nul"') DO (
	set PHPEXE="%%G\php\php.exe"
	goto RunChef
)
FOR /F "tokens=3" %%G IN ('"reg query HKLM\SOFTWARE\Wow6432Node\xampp /v Install_Dir 2> nul"') DO (
	set PHPEXE="%%G\php\php.exe"
	goto RunChef
)

:: Nope. Can't find it.
echo.
echo.Can't find the PHP executable. Is it installed somewhere?
echo.
echo.* If you're using a portable version, please define a PHPEXE environment
echo.  variable pointing to it.
echo.* If you're using EsayPHP, add the EasyPHP's PHP sub-directory to your
echo.  PATH environment variable.
echo.
exit /b 1
goto :eof

:RunChef
%PHPEXE% "%~dp0chef.php" %*

