<?php

namespace PieCrust\Chef\Commands;

use \Phar;
use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;


class SelfUpdateCommand extends ChefCommand
{
    public function getName()
    {
        return 'selfupdate';
    }

    public function requiresWebsite()
    {
        return false;
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Updates the PieCrust executable, if run from the .phar binary.";
        $parser->addOption('keep', array(
            'long_name'   => '--keep',
            'description' => "Keep a copy of the existing executable.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addArgument('target', array(
            'description' => "The version to update to. Must be a version number, `master` (or `default`), or `stable`.",
            'help_name'   => 'TARGET_VERSION',
            'optional'    => true,
            'default'     => null
        ));
    }

    public function run(ChefContext $context)
    {
        $app = $context->getApp();
        $result = $context->getResult();
        $log = $context->getLog();

        $matches = array();
        $dryRun = false;
        $requirePhar = true;
        $fromVersion = PieCrustDefaults::VERSION;
        $targetVersion = $result->command->args['target'];
        if (substr($targetVersion, 0, 1) == ':' &&
            preg_match(
            "#".
            "(\\:from\\:([^:]+))?".
            "(\\:to\\:([^:]+))?".
            "(\\:dry)?".
            "#",
            $targetVersion,
            $matches))
        {
            // Secret syntax to debug this whole shit.
            $fromVersion = $matches[2];
            $targetVersion = $matches[4];
            $dryRun = (bool)$matches[5];
            $requirePhar = false;
            if ($fromVersion)
                $log->info("OVERRIDE: from = {$fromVersion}");
            if ($targetVersion)
                $log->info("OVERRIDE: to = {$targetVersion}");
            if ($dryRun)
                $log->info("OVERRIDE: dry run");
        }
        if (!$targetVersion)
        {
            $targetVersion = $this->getNextVersion($fromVersion, $requirePhar, $log);
        }
        if (!$targetVersion)
        {
            $log->info("PieCrust is already up-to-date.");
            return 0;
        }

        $log->info("Updating to version {$targetVersion}...");

        $thisPath = Phar::running(false);
        if (!$requirePhar and !$thisPath)
            $thisPath = getcwd() . '/piecrust.phar';
        if (!$thisPath)
        {
            throw new PieCrustException("The self-update command can only be run from an installed PieCrust.");
        }

        $tempPath = $thisPath . '.downloading';
        if (!is_writable(dirname($thisPath)))
            throw new PieCrustException("PieCrust binary directory is not writable.");
        if (is_file($thisPath) and !is_writable($thisPath))
            throw new PieCrustException("PieCrust binary is not writable.");

        $pharUrl = "http://backend.bolt80.com/piecrust/{$targetVersion}/piecrust.phar";
        $log->debug("Downloading: {$pharUrl}");
        if ($dryRun)
            return 0;

        // Download the file.
        $fout = fopen($tempPath, 'wb');
        if ($fout === false)
            throw new PieCrustException("Can't write to temporary file: {$tempPath}");
        $fin = fopen($pharUrl, 'rb');
        if ($fin === false)
            throw new PieCrustException("Can't download binary file: {$pharUrl}");
        while (!feof($fin))
        {
            $data = fread($fin, 8192);
            fwrite($fout, $data);
        }
        fclose($fin);
        fclose($fout);

        // Test the downloaded file.
        $log->debug("Checking downloaded file: {$tempPath}");
        try
        {
            chmod($tempPath, 0777 & ~umask());
            $phar = new \Phar($tempPath);
            // Free the variable to unlock the file.
            unset($phar);
        }
        catch (\Exception $e)
        {
            @unlink($tempPath);
            if (!$e instanceof \UnexpectedValueException && !$e instanceof \PharException)
                throw $e;

            $log->err("The downloaded binary seems to be corrupted: {$e->getMessage()}");
            $log->err("Please try running the self update command again.");
            return 1;
        }

        // Replace the current binary with the download.
        $log->debug("Replacing running binary with downloaded file.");
        rename($tempPath, $thisPath);
    }

    protected function getNextVersion($version, $requirePhar, $log)
    {
        $log->debug("Currently running version {$version}");

        $matches = array();
        if (!preg_match(
            '/^(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)(?P<suffix>.*)$/',
            $version,
            $matches))
        {
            throw new PieCrustException("Can't parse the current version '{$version}' to determine the upgrade path. Please specify a version to upgrade to.");
        }

        $major = intval($matches['major']);
        $minor = intval($matches['minor']);
        $patch = intval($matches['patch']);
        $suffix = $matches['suffix'];

        if ($suffix == '-dev')
        {
            // Upgrade to the latest build on the main (dev) branch, if it is
            // not the same already.
            $thisPath = Phar::running(false);
            if ($requirePhar and !$thisPath)
            {
                throw new PieCrustException("You're not running PieCrust from an installed version. You can either update your Git or Mercurial repository, or download a new tarball.");
            }

            if ($requirePhar)
            {
                // We're running inside a Phar archive. Find the commit
                // from the metadata.
                $phar = new \Phar($thisPath);
                $metadata = $phar->getMetadata();
                if (!$metadata or
                    !isset($metadata['version']) or
                    !$metadata['version'])
                {
                    throw new PieCrustException("No version was saved in this PieCrust executable. Please specify a version to upgrade to.");
                }
                $thisChange = $metadata['version'];
                if ($thisChange[strlen($thisChange) - 1] == '+')
                {
                    throw new PieCrustException("Your current PieCrust executable was built from a locally modified repository. Please specify a version to upgrade to.");
                }
                // Free the variable to unlock the file.
                unset($phar);
            }
            else
            {
                // We're running from loose files. We can only get the commit
                // if this is a Mercurial repository.
                $thisChange = shell_exec("hg id -i");
                if (!$thisChange)
                {
                    throw new PieCrustException("You're not running PieCrust from an installed version, and this isn't a Mercurial repository either.");
                }
            }

            $lastDevChangeUrl = "http://backend.bolt80.com/piecrust/default/version";
            $lastDevChange = trim(file_get_contents($lastDevChangeUrl));
            if (!$lastDevChange)
                throw new PieCrustException("Couldn't get the latest dev version from the PieCrust website. Please try again, or specify a version to upgrade to.");
            if ($lastDevChange != $thisChange)
            {
                $log->debug("Latest dev change is {$lastDevChange}, you have {$thisChange}.");
                return "default";
            }
            $log->debug("Latest dev change is {$lastDevChange}, which is what you have.");
            return false;
        }
        else if (!$suffix)
        {
            // Upgrade to the latest version on the stable branch.
            $lastStableVersionUrl = "http://backend.bolt80.com/piecrust/stable/version";
            $lastStableVersion = trim(file_get_contents($lastStableVersionUrl));
            if (!$lastStableVersion)
                throw new PieCrustException("Couldn't get the latest stable version from the PieCrust website. Please try again, or specify a version to upgrade to.");
            if ($lastStableVersion != $version)
                return $lastStableVersion;
            return false;
        }
        else
        {
            throw new PieCrustException("Can't figure out how to upgrade from '{$version}'. Please specify a version to upgrade to.");
        }
    }
}

