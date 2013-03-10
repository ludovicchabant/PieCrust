<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Baker\IBaker;
use PieCrust\IO\Cache;


/**
 * A convenient base class for file processors who only
 * process one input file into one output file.
 *
 * Extend this class and pass the input and output extensions
 * to its constructor. Then, implement the 'doProcess()' method.
 *
 * Look at the LessProcessor or SassProcessor classes for examples
 * of how to use this class.
 */
class SimpleFileProcessor implements IProcessor
{
    protected $pieCrust;
    protected $baker;
    protected $logger;

    protected $name;
    protected $priority;
    protected $inputExtensions;
    protected $outputExtensions;

    protected $cache;
    
    public function __construct($name = '__unnamed__', $inputExtensions = array(), $outputExtensions = array(), $priority = IProcessor::PRIORITY_DEFAULT)
    {
        $this->doConstruct($name, $inputExtensions, $outputExtensions, $priority);
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function initialize(IPieCrust $pieCrust, $logger = null)
    {
        $this->pieCrust = $pieCrust;

        if ($logger == null)
            $logger = \Log::singleton('null', '', '');
        $this->logger = $logger;
    }
    
    public function getPriority()
    {
        return $this->priority;
    }

    public function onBakeStart(IBaker $baker)
    {
        $this->baker = $baker;
    }
    
    public function supportsExtension($extension)
    {
        if ($extension == null or $extension == '') return false;
        return in_array($extension, $this->inputExtensions);
    }

    public function isBypassingStructuredProcessing()
    {
        return false;
    }

    public function isDelegatingDependencyCheck()
    {
        return true;
    }

    public function getDependencies($path)
    {
        return null;
    }
    
    public function getOutputFilenames($filename)
    {
        $pathinfo = pathinfo($filename);
        if (!isset($pathinfo['extension']))
        {
            throw new PieCrustException("The filename doesn't have an extension -- " .
                                        "it should have been declared as unsupported by 'supportsExtension()'.");
        }
        
        $key = array_search($pathinfo['extension'], $this->inputExtensions);
        if ($key === false)
        {
            throw new PieCrustException("Extension '" . $pathinfo['extension'] . "' is not supported.");
        }
        
        return $pathinfo['filename'] . '.' . $this->outputExtensions[$key];
    }
    
    public function process($inputPath, $outputDir)
    {
        $outputPath = $outputDir . $this->getOutputFilenames(pathinfo($inputPath, PATHINFO_BASENAME));
        $this->doProcess($inputPath, $outputPath);
        return true;
    }

    public function onBakeEnd()
    {
        $this->baker = null;
    }
    
    protected function doConstruct($name, $inputExtensions, $outputExtensions, $priority = IProcessor::PRIORITY_DEFAULT)
    {
        if (is_array($inputExtensions))
        {
            $this->inputExtensions = $inputExtensions;
            
            if (is_array($outputExtensions))
            {
                if (count($inputExtensions) != count($outputExtensions)) throw new PieCrustException('The input and output extensions arrays must have the same length');
                $this->outputExtensions = $outputExtensions;
            }
            else
            {
                $this->outputExtensions = array_fill(0, count($inputExtensions), $outputExtensions);
            }
        }
        else
        {
            if (is_array($outputExtensions)) throw new PieCrustException('The output extensions parameter can only be an array if the input extensions parameter is an array of the same length.');
            $this->inputExtensions = array($inputExtensions);
            $this->outputExtensions = array($outputExtensions);
        }
        
        $this->name = $name;
        $this->priority = $priority;
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
    }

    protected function readCacheData($cacheUri)
    {
        if ($this->ensureCache() === false)
            return false;
        if (!$this->cache->has($cacheUri, 'json'))
            return false;
        $dataStr = $this->cache->read($cacheUri, 'json');
        return json_decode($dataStr, true);
    }

    protected function writeCacheData($cacheUri, $data)
    {
        if ($this->ensureCache() === false)
            return;
        $dataStr = json_encode($data);
        $this->cache->write($cacheUri, 'json', $dataStr);
    }

    protected function ensureCache()
    {
        if (!$this->pieCrust->isCachingEnabled())
            return false;
        if ($this->cache == null)
            $this->cache = new Cache($this->pieCrust->getCacheDir() . 'bake_t/procs');
    }
}
