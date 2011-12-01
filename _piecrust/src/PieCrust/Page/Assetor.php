<?php

namespace PieCrust\Page;

use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


/**
 * The asset manager for PieCrust pages.
 *
 * The Assetor (worst class name ever) handles lazy loading of a page's
 * assets, stored in a subdirectory with the same name as the page file.
 */
class Assetor implements \ArrayAccess, \Iterator
{
    /**
     * Default file names.
     */
    const ASSET_DIR_SUFFIX = '-assets';
    const ASSET_URL_SUFFIX = self::ASSET_DIR_SUFFIX;
    
    
    protected $assetsDir;
    protected $assetsCache;
    
    protected $urlBase;
    /**
     * Gets the base URL for the assets.
     */
    public function getUrlBase()
    {
        return $this->urlBase;
    }
    
    /**
     * Sets the base URL for the assets.
     */
    public function setUrlBase($urlBase)
    {
        if ($this->assetsCache != null) throw new PieCrustException("The base URL can only be set before the assets are loaded.");
        $this->urlBase = rtrim($urlBase, '/');
    }
    
    /**
     * Gets all the asset path-names.
     */
    public function getAssetPathnames()
    {
        if ($this->assetsDir === false) return null;
        return new FilesystemIterator($this->assetsDir, FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS);
    }
    
    /**
     * Creates a new instance of Assetor.
     */
    public function __construct(IPage $page)
    {
        $pathParts = pathinfo($page->getPath());
        $this->assetsDir = $pathParts['dirname'] . '/' . $pathParts['filename'] . self::ASSET_DIR_SUFFIX;
        if (is_dir($this->assetsDir))
        {
            if ($page->getAssetUrlBaseRemap() != null)
            {
                $this->urlBase = self::buildUrlBase($page);
            }
            else
            {
                $relativePath = str_replace('\\', '/', PathHelper::getRelativePath($page->getApp(), $page->getPath(), true));
                $this->urlBase = $page->getApp()->getConfig()->getValueUnchecked('site/root') . $relativePath . self::ASSET_DIR_SUFFIX;
            }
        }
        else
        {
            $this->assetsDir = false;
            $this->urlBase = false;
        }
    }
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureAssetsCache();
        return isset($this->assetsCache[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensureAssetsCache();
        return $this->assetsCache[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('Assetor is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('Assetor is read-only.');
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensureAssetsCache();
        return reset($this->assetsCache);
    }
  
    public function current()
    {
        $this->ensureAssetsCache();
        return current($this->assetsCache);
    }
  
    public function key()
    {
        $this->ensureAssetsCache();
        return key($this->assetsCache);
    }
  
    public function next()
    {
        $this->ensureAssetsCache();
        return next($this->assetsCache);
    }
  
    public function valid()
    {
        $this->ensureAssetsCache();
        return key($this->assetsCache) !== null;
    }
    // }}}
    
    protected function ensureAssetsCache()
    {
        if ($this->assetsCache === null)
        {
            $this->assetsCache = array();
            
            if ($this->assetsDir !== false)
            {
                $paths = new FilesystemIterator($this->assetsDir);
                foreach ($paths as $p)
                {
                    $filename = $p->getFilename();
                    $key = preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename);
                    $this->assetsCache[$key] = $this->urlBase . '/' . $filename;
                }
            }
        }
    }
    
    protected static function buildUrlBase(IPage $page)
    {
        $replacements = array(
            '%site_root%' => $page->getApp()->getConfig()->getValueUnchecked('site/root'),
            '%path' => PathHelper::getRelativePath($page->getApp(), $page->getPath(), true),
            '%uri%' => $page->getUri()
        );
        return str_replace(array_keys($replacements), array_values($replacements), $page->getAssetUrlBaseRemap());
    }
}
