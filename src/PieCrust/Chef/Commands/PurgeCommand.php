<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PathHelper;


class PurgeCommand extends ChefCommand
{
    public function getName()
    {
        return 'purge';
    }
    
    public function setupParser(Console_CommandLine $rootParser, IPieCrust $pieCrust)
    {
        $rootParser->description = "Purges the website's cache directory.";
    }

    public function run(ChefContext $context)
    {
        $cacheDir = $context->getApp()->getCacheDir();
        if (!$cacheDir)
            throw new PieCrustException("The website seems to have caching disabled.");
        if (!is_dir($cacheDir))
            throw new PieCrustException("The cache directory doesn't exist: {$cacheDir}");

        $context->getLog()->info("Purging cache: {$cacheDir}");
        PathHelper::deleteDirectoryContents($cacheDir);
    }
}

