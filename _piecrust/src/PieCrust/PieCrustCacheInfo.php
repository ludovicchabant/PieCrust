<?php

namespace PieCrust;

use PieCrust\Util\PathHelper;


/**
 * A class that gives information on a PieCrust application's cache.
 */
class PieCrustCacheInfo
{
    protected $pieCrust;
    protected $cacheCleaningSkipPatterns;
    
    /**
     * Creates a new instance of PieCrustCacheInfo
     */
    public function __construct(IPieCrust $pieCrust, $cacheCleaningSkipPatterns = '/^(\.?empty)|(server_cache)$/')
    {
        $this->pieCrust = $pieCrust;
        $this->cacheCleaningSkipPatterns = $cacheCleaningSkipPatterns;
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
        // - changing the configuration
        $hashArray = $this->pieCrust->getConfig()->get();
        $hashArray['_version'] = PieCrustDefaults::VERSION;
        $hashString = json_encode($hashArray);
        $hash = hash('sha1', $hashString);
        
        $isCacheValid = false;
        $cacheInfoFileName = $this->pieCrust->getCacheDir() . PieCrustDefaults::CACHE_INFO_FILENAME;
        if (file_exists($cacheInfoFileName))
        {
            $previousHash = file_get_contents($cacheInfoFileName);
            $isCacheValid = ($previousHash == $hash);
        }
        $cacheValidity = array(
            'is_valid' => $isCacheValid,
            'path' => $cacheInfoFileName,
            'hash' => $hash,
            'was_cleaned' => false
        );
        if ($cleanCache && !$isCacheValid)
        {
            // Clean the cache!
            PathHelper::deleteDirectoryContents($this->pieCrust->getCacheDir(), $this->cacheCleaningSkipPatterns);
            file_put_contents($cacheInfoFileName, $hash);
            $cacheValidity['is_valid'] = true;
            $cacheValidity['was_cleaned'] = true;
        }
        return $cacheValidity;
    }
}
