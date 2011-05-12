<?php

define('FTP_SYNC_ALWAYS', 0);
define('FTP_SYNC_IF_NEWER', 1);
define('FTP_SYNC_IF_DIFFERENT_SIZE', 2);

$TEXT_FILE_EXTENSIONS = array(
    'html', 'htm', 'txt', 'php', 'php3', 'cgi',
    'c', 'cpp', 'h', 'pas', 'bas', 'tex', 'pl',
    'xtml', 'css', 'cfg', 'ini', 'sh', 'xml', 'js', 'rss', 'json'
);
$TEXT_FILE_NAMES = array(
    '.htaccess'
);

function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = realpath($result->command->args['root']);
    if (!is_dir($rootDir))
    {
        $parser->displayError("No such root directory: " . $rootDir, 1);
        die();
    }

    $fullAddress = $result->command->args['server'];
    $matches = array();
    if (!preg_match('/^([^:]+)(\:([^@]+))?@(.*)$/', $fullAddress, $matches))
    {
        $parser->displayError("The given upload address was not valid. Must be of form: user:password@domain.tld", 1);
        die();
    }
    $user = $matches[1];
    $password = $matches[3];
    $server = $matches[4];
    
    $remoteRootDir = $result->command->options['remote_root'];
    if (!$remoteRootDir)
    {
        $remoteRootDir = '/';
    }
    $remoteRootDir = rtrim($remoteRootDir, '/\\') . '/';
    
    $passiveMode = $result->command->options['passive'];
    
    echo "Uploading to ".$server." [".$remoteRootDir."] as ".$user.PHP_EOL;
    
    $conn = ftp_connect($server);
    if ($conn === false)
    {
        $parser->displayError("Can't connect to FTP server ".$server.".", 1);
        die();
    }
    if (!isset($password) or $password == "")
    {
        $password = prompt_silent("Password: ");
    }
    
    // Start uploading!
    try
    {
        if (ftp_login($conn, $user, $password))
        {
            echo "Connected to FTP server ".$server.PHP_EOL;
            if ($passiveMode)
            {
                echo "Enabling passive mode.".PHP_EOL;
                if (!ftp_pasv($conn, true))
                    throw new PieCrustException("Can't enable passive mode.");
            }
            sync_ftp($conn, $rootDir, $remoteRootDir);
        }
        else
        {
            throw new PieCrustException("Couldn't connect to FTP server ".$server.", login incorrect.");
        }
    }
    catch (Exception $e)
    {
        $parser->displayError($e->getMessage(), 1);
    }
    ftp_close($conn);
}



function sync_ftp($conn, $localRoot, $remoteRoot, $mode = FTP_SYNC_IF_NEWER)
{
    global $TEXT_FILE_NAMES;
    global $TEXT_FILE_EXTENSIONS;
    
    $localRootSize = strlen($localRoot);
    $it = new RecursiveDirectoryIterator($localRoot);
    $itIt = new RecursiveIteratorIterator($it);
    foreach ($itIt as $cur)
    {
        $relativePathname = str_replace('\\', '/', ltrim(substr($cur->getPathname(), $localRootSize), DIRECTORY_SEPARATOR));
        $remotePathname = $remoteRoot . $relativePathname;
        
        $transferMode = FTP_BINARY;
        $relativePathInfo = pathinfo($relativePathname);
        if (in_array($relativePathInfo['extension'], $TEXT_FILE_EXTENSIONS) or
            in_array($relativePathInfo['filename'], $TEXT_FILE_NAMES))
        {
            $transferMode = FTP_ASCII;
        }
        
        $doTransfer = false;
        $doTransferReason = "";
        if ($mode == FTP_SYNC_ALWAYS)
        {
            $doTransfer = true;
        }
        else if ($mode == FTP_SYNC_IF_NEWER)
        {
            $localMtime = $cur->getMTime();
            $remoteMtime = ftp_mdtm($conn, $remotePathname);
            if ($remoteMtime === -1)
            {
                $doTransfer = true;
                $doTransferReason = "new";
            }
            else if ($remoteMtime < $localMtime)
            {
                $doTransfer = true;
                $doTransferReason = "newer";
            }
        }
        else if ($mode == FTP_SYNC_IF_DIFFERENT_SIZE)
        {
            $localSize = $cur->getSize();
            $remoteSize = ftp_size($conn, $remotePathname);
            if ($remoteSize === -1)
            {
                $doTransfer = true;
                $doTransferReason = "new";
            }
            else if ($remoteSize != $localSize)
            {
                $doTransfer = true;
                $doTransferReason = "different size";
            }
        }
        if ($doTransfer)
        {
            echo $relativePathname." [".$doTransferReason."][".($transferMode == FTP_ASCII ? 'A' : 'B')."]".PHP_EOL;
            ftp_put($conn, $remotePathname, $cur->getPathname(), $transferMode);
        }
    }
}

function prompt_silent($prompt)
{
    if (preg_match('/^win/i', PHP_OS))
    {
        $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
        file_put_contents(
            $vbscript, 'wscript.echo(InputBox("' .
            addslashes($prompt) .
            '", "", "password here"))'
            );
        $command = "cscript //nologo " . escapeshellarg($vbscript);
        $password = rtrim(shell_exec($command));
        unlink($vbscript);
        return $password;
    }
    else
    {
        $command = "/usr/bin/env bash -c 'echo OK'";
        if (rtrim(shell_exec($command)) !== 'OK')
        {
            trigger_error("Can't invoke bash");
            return;
        }
        $command = "/usr/bin/env bash -c 'read -s -p \"" .
            addslashes($prompt) .
            "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        echo "\n";
        return $password;
    }
}

