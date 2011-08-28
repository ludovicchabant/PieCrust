<?php

require_once 'PieCrust.class.php';
require_once 'FileSystem.class.php';


/**
 * A class that gives information on a PieCrust application's cache.
 */
class PieCrustCacheInfo
{
    protected $pieCrust;
    
    /**
     * Creates a new instance of PieCrustCacheInfo
     */
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    /**
     * Gets the validity information for the cache.
     *
     * If $cleanCache is true and the cache is not valid, it will be wiped.
     */
    public function getValidity($cleanCache)
    {
        // Things that could make the cache invalid:
        // - changing the version of PieCrust
        // - changing the pretty_urls setting
        // - being in/out of bake mode
        // - changing the base URL
        $prettyUrls = ($this->pieCrust->getConfigValueUnchecked('site', 'pretty_urls') ? "true" : "false");
        $isBaking = ($this->pieCrust->getConfigValue('baker', 'is_baking') ? "true" : "false");
        $cacheInfo = "version=". PieCrust::VERSION .
                     "&url_base=" . $this->pieCrust->getUrlBase() .
                     "&debug_mode=" . $this->pieCrust->isDebuggingEnabled() .
                     "&pretty_urls=" . $prettyUrls .
                     "&is_baking=" . $isBaking;
        $cacheInfo = hash('sha1', $cacheInfo);
        
        $isCacheValid = false;
        $cacheInfoFileName = $this->pieCrust->getCacheDir() . PIECRUST_CACHE_INFO_FILENAME;
        if (file_exists($cacheInfoFileName))
        {
            $previousCacheInfo = file_get_contents($cacheInfoFileName);
            $isCacheValid = ($previousCacheInfo == $cacheInfo);
        }
        $cacheValidity = array(
            'is_valid' => $isCacheValid,
            'path' => $cacheInfoFileName,
            'hash' => $cacheInfo,
            'was_cleaned' => false
        );
        if ($cleanCache && !$isCacheValid)
        {
            // Clean the cache!
            FileSystem::deleteDirectoryContents($this->pieCrust->getCacheDir());
            file_put_contents($cacheInfoFileName, $cacheInfo);
            $cacheValidity['is_valid'] = true;
            $cacheValidity['was_cleaned'] = true;
        }
        return $cacheValidity;
    }
}
