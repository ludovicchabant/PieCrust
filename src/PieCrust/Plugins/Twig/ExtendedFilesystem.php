<?php

namespace PieCrust\Plugins\Twig;


/**
 * A Twig file system that can also format an in-memory string.
 */
class ExtendedFilesystem extends \Twig_Loader_Filesystem implements \Twig_LoaderInterface
{
    protected $useTimeInCacheKey;
    protected $templateStrings;
    
    public function __construct($paths, $useTimeInCacheKey = false)
    {
        parent::__construct($paths);
        $this->useTimeInCacheKey = $useTimeInCacheKey;
        $this->templateStrings = array();
    }

    public function setTemplateSource($name, $source)
    {
        $this->templateStrings[$name] = $source;
    }
    
    public function getSource($name)
    {
        if (isset($this->templateStrings[$name]))
            return $this->templateStrings[$name];
        return parent::getSource($name);
    }
    
    public function getCacheKey($name)
    {
        if (isset($this->templateStrings[$name]))
        {
            return $this->templateStrings[$name];
        }

        $cacheKey = parent::getCacheKey($name);
        if ($this->useTimeInCacheKey)
        {
            $path = $this->findTemplate($name);
            $lastModified = filemtime($path);
            $cacheKey .= $lastModified;
        }
        return $cacheKey;
    }
    
    public function isFresh($name, $time)
    {
        if (isset($this->templateStrings[$name]))
            return false;
        return parent::isFresh($name, $time);
    }
}
