<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class LessProcessor extends SimpleFileProcessor
{
    protected $jsToolOptions;

    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
        $this->jsToolOptions = null;
    }

    public function isDelegatingDependencyCheck()
    {
        return false;
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $this->ensureInitialized();

        if ($this->jsToolOptions === false)
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
        else
        {
            $exe = $this->jsToolOptions['bin'];
            $options = $this->jsToolOptions['options'];
            $cmd = "{$exe} {$options} \"{$inputPath}\" \"{$outputPath}\"";
            $this->logger->debug('$> '.$cmd);
            shell_exec($cmd);
        }
    }

    protected function ensureInitialized()
    {
        if ($this->jsToolOptions !== null)
            return;

        $config = $this->pieCrust->getConfig();
        if ($config->getValue('less/use_lessc') === true)
        {
            $defaultOptions = array(
                'bin' => 'lessc',
                'options' => '-rp="'.$config->getValue('site/root').'"'
            );
            $this->jsToolOptions = array_merge(
                $defaultOptions,
                $config->getValue('less')
            );
            $this->logger->debug("Will use `lessc` for processing LESS files.");
        }
        else
        {
            $this->jsToolOptions = false;
        }
    }
}

