@echo off

if not defined PHP_PEAR_BIN_DIR (
	echo Can't find the PHP executable. Do you have PEAR installed?
	exit /b 1
)
set PHP="%PHP_PEAR_BIN_DIR%\php.exe"
%PHP% %~dp0chef.php %*
