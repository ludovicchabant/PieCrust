<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class LessProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
    }

    public function isDelegatingDependencyCheck()
    {
        return false;
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $less = new \lessc();
        if ($this->pieCrust->isCachingEnabled())
        {
            $cacheUri = 'less/' . sha1($inputPath);
            $cacheData = $this->readCacheData($cacheUri);
            if ($cacheData)
            {
                $lastUpdated = $cacheData['updated'];
            }
            else
            {
                $lastUpdated = false;
                $cacheData = $inputPath;
            }

            $cacheData = $less->cachedCompile($cacheData);
            $this->writeCacheData($cacheUri, $cacheData);

            if (!$lastUpdated || $cacheData['updated'] > $lastUpdated)
                file_put_contents($outputPath, $cacheData['compiled']);
        }
        else
        {
            $less->compileFile($inputPath, $outputPath);
        }
    }
}

