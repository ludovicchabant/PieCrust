<?php

namespace PieCrust\Page;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\Cache;


/**
 * A class responsible for loading a PieCrust template.
 */
class TemplateLoader
{
    protected $pieCrust;
    protected $cache;
    
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        
        if ($this->pieCrust->isCachingEnabled())
            $cache = new Cache($this->pieCrust->getCacheDir() . 'template_configs');
        else
            $cache = false;
    }
    
    public function parseTemplateSource($path)
    {
        $sourceTime = filemtime($path);
        $rawContents = file_get_contents($path);
        $cacheUri = pathinfo($path, PATHINFO_BASENAME) . '.' . md5($path);
        
        // See if we can get some help from the cache.
        if ($this->cache && $this->cache->isValid($cacheUri, 'json', $sourceTime))
        {
            $cacheText = $this->cache->read($cacheUri, 'json');
            $cacheData = json_decode($cacheText, true);
            return array(
                'config' => $cacheData['config'],
                'source' => substr($rawContents, $cacheData['source_offset'])
            );
        }
        
        // Parse the template source.
        $parsedContents = Configuration::parseHeader($rawContents);
        $result = array(
            'config' => $this->validateConfig($parsedContents['config']),
            'source' => $parsedContents['text']
        );
        
        // Cache it if we can.
        if ($this->cache)
        {
            $cacheData = array(
                'config' => $result['config'],
                'source_offset' => $parsedContents['text_offset']
            );
            $cacheText = json_encode($cacheData);
            $this->cache->write($cacheUri, 'json', $cacheText);
        }
        
        return $result;
    }
    
    protected function validateConfig(array $config)
    {
        return $config;
    }
}