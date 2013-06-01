#!/usr/bin/env php
<?php

install($argv);


class CheckEnvironmentResult
{
    public $errors;

    public function __construct()
    {
        $this->errors = array();
    }

    public function hasError($key)
    {
        return in_array($key, $this->errors);
    }

    public function isOk()
    {
        return (count($this->errors) == 0);
    }

    public function getExitCode()
    {
        return $this->isOk() ? 0 : 1;
    }
}

class Logger
{
    public function __construct()
    {
        $this->hasColors = (DIRECTORY_SEPARATOR != '\\' || (getenv('ANSICON') !== false));
        $this->colorFormats = array(
            'success' => "\033[0;32m%s\033[0m",
            'error' => "\033[31;31m%s\033[0m",
            'warning' => "\033[33;33m%s\033[0m"
        );
    }

    public function log($level, $message)
    {
        $messageFormat = "%s";
        if ($this->hasColors && isset($this->colorFormats[$level]))
            $messageFormat = $this->colorFormats[$level];
        printf($messageFormat, $message);
        printf(PHP_EOL);
    }

    public function error($message)
    {
        $this->log('error', $message);
    }

    public function success($message)
    {
        $this->log('success', $message);
    }

    public function warning($message)
    {
        $this->log('warning', $message);
    }

    public function info($message)
    {
        $this->log('info', $message);
    }
}

function install($argv)
{
    $checkOnly = false;
    $helpOnly = false;
    $quiet = false;
    $installDir = getcwd();
    $version = 'stable';

    $skipNextArg = false;
    foreach ($argv as $i => $arg)
    {
        if ($skipNextArg)
        {
            $skipNextArg = false;
            continue;
        }

        if ($arg == '--check')
        {
            $checkOnly = true;
        }
        elseif ($arg == '--quiet')
        {
            $quiet = true;
        }
        elseif ($arg == '--help' or $arg == '-h')
        {
            $helpOnly = true;
        }
        elseif ($arg == '--install-dir')
        {
            $skipNextArg = true;
            $installDir = trim($argv[$i + 1]);
        }
        elseif ($arg == '--version')
        {
            $skipNextArg = true;
            $version = trim($argv[$i + 1]);
            if ($version == 'master')
                $version = 'default';
        }
    }

    if ($helpOnly)
    {
        showHelp();
        exit(0);
    }

    $checkResult = checkEnvironment($installDir);
    reportProblems($checkResult, ($checkOnly && !$quiet));
    if ($checkOnly || !$checkResult->isOk())
    {
        exit($checkResult->getExitCode());
    }

    $exitCode = installPieCrust($version, $installDir, $quiet);
    exit($exitCode);
}

function showHelp()
{
    echo <<<EOF
PieCrust Installer

Usage: install.php <options>

Options:
    --help
        Show this help.

    --check
        Only check the environment.

    --install-dir  PATH
        Where to install PieCrust.
        (defaults to current directory)

    --version VERSION
        The version to install.
        Can be:
            - 'stable': the head of the stable branch.
            - 'master': the head of the dev branch.
            - 'default': same as 'master'.
            - 'x.y.z': a version number (e.g. 0.9.1)
        (defaults to 'stable')

EOF;
}

function checkEnvironment($installDir)
{
    $result = new CheckEnvironmentResult();

    if (version_compare(PHP_VERSION, '5.3.15', '<'))
    {
        $result->errors[] = 'php_version';
    }
    if (!extension_loaded('Phar'))
    {
        $result->errors[] = 'phar';
    }
    if (!extension_loaded('sockets'))
    {
        $result->errors[] = 'sockets';
    }

    return $result;
}

function reportProblems($result, $reportOk = false)
{
    $logger = new Logger();
    if ($result->hasError('php_version'))
    {
        $logger->error("Your PHP is too old. You need version 5.3.15 or higher.");
    }
    if ($result->hasError('phar'))
    {
        $logger->error("The phar extension is missing.");
        $logger->error("  - install it or recompile PHP without `--disable-phar`.");
    }
    if ($result->hasError('sockets'))
    {
        $logger->error("The sockets extension is missing.");
        if (DIRECTORY_SEPARATOR == '\\')
        {
            $logger->error("  - open your `php.ini` configuration file and enable it with:\n".
                           "      extension=php_sockets.dll\n".
                           "    this line most likely exists, but is commented with a `;`.");
            $logger->error("    otherwise, install the extension, or recompile PHP with `--enable-sockets`.");
        }
        else
        {
            $logger->error("  - install it or recompile PHP with `--enable-sockets`.");
        }
    }

    if ($result->isOk() and $reportOk)
    {
        $logger->success("PieCrust is compatible with your environment.");
    }
}

function installPieCrust($version, $installDir, $quiet)
{
    $logger = new Logger();

    // Figure out the destination file.
    if (!is_dir($installDir) || !is_writable($installDir))
    {
        $logger->error("Can't install in: {$installDir}\n".
                       "Directory doesn't exist or is not writable.");
        return 1;
    }
    $installDir = rtrim($installDir, '/\\') . DIRECTORY_SEPARATOR;
    $destination = $installDir . 'piecrust.phar';

    // Remove existing destination.
    if (is_readable($destination))
    {
        if (!$quiet)
            $logger->warning("Removing existing file: {$destination}");
        @unlink($destination);
    }

    // Download Phar file from the server.
    $source = 'http://backend.bolt80.com/piecrust/'.$version.'/piecrust.phar';
    $streamContextOptions = array('http' => array());
    $streamContext = stream_context_create($streamContextOptions);

    if (!$quiet)
        $logger->info("Downloading from: {$source}");
    $data = file_get_contents($source, false, $streamContext);
    if ($data === false)
    {
        $logger->error("Could not download file from: {$source}");
        return 3;
    }

    $destFd = fopen($destination, 'w');
    if ($destFd === false)
    {
        $logger->error("Could not create destination file: {$destination}");
        return 2;
    }

    if (fwrite($destFd, $data) === false)
    {
        $logger->error("Could not write data to destination file: {$destination}");
        fclose($destFd);
        return 3;
    }
    fclose($destFd);

    // Test the Phar file.
    try
    {
        $archive = new Phar($destination);
        unset($archive);
    }
    catch (Exception $e)
    {
        $logger->error("Could not open downloaded file: {$destination}");
        $logger->error("  - {$e->getMessage()}");
        $logger->error("  - the downloaded file is most likely corrupted, please try again.");
        @unlink($destination);
        return 4;
    }

    // Make it an executable.
    chmod($destination, 0755);

    // Create a `chef` bootstrap script.
    if (DIRECTORY_SEPARATOR == '\\')
    {
        $chefPath = $installDir . 'chef.cmd';
        if (file_put_contents($chefPath, getChefBootstrapWindows()) === false)
        {
            $logger->error("Could not create the bootstrap script at: {$chefPath}");
            return 5;
        }
        chmod($chefPath, 0755);
    }
    else
    {
        $chefPath = $installDir . 'chef';
        if (file_put_contents($chefPath, getChefBootstrapUnix()) === false)
        {
            $logger->error("Could not create the bootstrap script at: {$chefPath}");
            return 5;
        }
        chmod($chefPath, 0755);
    }

    // Done!
    if (!$quiet)
    {
        $logger->success("PieCrust was successfully installed at: {$destination}");
        if (isset($chefPath))
        {
            $logger->success("You can run `{$chefPath}` now.");
        }
        else
        {
            $logger->success("You can run `php {$destination}` now.");
        }
    }
}


//   W A R N I N G
//   -------------
//
// Below is slightly modified code from the `bin` scripts.
// Any changes in those scripts should be ported here.

function getChefBootstrapUnix()
{
   return <<<'EOD'
#!/bin/sh

POSSIBLES="/usr/bin/php-5.3 /usr/local/bin/php-5.3 /usr/bin/php /usr/local/bin/php"
for CUR in $POSSIBLES; do
    if [ -x $CUR ]; then
        PHP=$CUR
        break
    fi
done
if [ -z "$PHP" ]; then
    echo "Couldn't find PHP in any of the known locations."
    exit 1
fi

CHEF_DIR=`dirname $0`
if `hash readlink 2>&-`; then
    LINKED_EXE=`readlink $0`
    if [ -n "$LINKED_EXE" ]; then
        CHEF_DIR=`dirname $LINKED_EXE`
    fi
fi
$PHP $CHEF_DIR/piecrust.phar $@
EOD;
}

function getChefBootstrapWindows()
{
    return <<<'EOD'
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
%PHPEXE% %~dp0piecrust.phar %*
EOD;
}

