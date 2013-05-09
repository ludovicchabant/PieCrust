<?php

namespace PieCrust\Page;

use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


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
    
    protected $page;
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
        if ($this->assetsCache != null)
            throw new PieCrustException("The base URL can only be set before the assets are loaded.");
        $this->urlBase = rtrim($urlBase, '/');
    }
    
    /**
     * Gets all the asset path-names.
     */
    public function getAssetPathnames()
    {
        if ($this->assetsDir === false)
            return null;

        $paths = array();
        $it = new FilesystemIterator($this->assetsDir, FilesystemIterator::SKIP_DOTS);
        foreach ($it as $p)
        {
            if ($it->isFile())
                $paths[] = $p->getPathname();
            else if ($it->isDir())
                throw new PieCrustException("Page asset sub-directories are not supported: " . $p->getPathname());
        }
        return $paths;
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
            $urlBaseRemap = $page->getAssetUrlBaseRemap();
            if ($urlBaseRemap == null)
                $urlBaseRemap = '%site_root%%path%' . self::ASSET_DIR_SUFFIX;

            $this->urlBase = self::buildUrlBase($page, $urlBaseRemap);
        }
        else
        {
            $this->assetsDir = false;
            $this->urlBase = false;
        }
        $this->page = $page;
    }
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureAssetsCache();
        if (!isset($this->assetsCache[$offset]))
            throw new PieCrustException(
                "Asset '{$offset}' doesn't exist. " .
                "If you're trying to access a file with invalid characters (such as a dash), " .
                "you may have to use an alternate templating syntax. " .
                "For example, with Twig: {{ asset['some-file'] }}");
        return true;
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
                    if (isset($this->assetsCache[$key]))
                        throw new PieCrustException("Directory '{$this->assetsDir}' contains several assets with filename '{$key}'.");
                    $this->assetsCache[$key] = $this->urlBase . '/' . $filename;
                }
            }
        }
    }
    
    protected static function buildUrlBase(IPage $page, $assetUrlBaseRemap)
    {
        $siteRoot = $page->getApp()->getConfig()->getValueUnchecked('site/root');
        $relativePath = str_replace(
            '\\', 
            '/', 
            PieCrustHelper::getRelativePath($page->getApp(), $page->getPath(), true)
        );
        $uri = $page->getUri();
        $prettyUrls = PageHelper::getConfigValue($page, 'pretty_urls', 'site');
        if (!$prettyUrls)
        {
            // Remove the extension from the URI (if any), because without 'pretty URLs',
            // we want to copy assets to a directory named after the page's filename
            // (without the extension). See `PageBaker` for more information.
            $uriInfo = pathinfo($uri);
            $uri = $uriInfo['dirname'];
            if ($uri == '.')
                $uri = '';
            else
                $uri .= '/';
            $uri .= $uriInfo['filename'];
        }

        $replacements = array(
            '%site_root%' => $siteRoot,
            '%path%' => $relativePath,
            '%uri%' => $uri
        );
        return str_replace(
            array_keys($replacements), 
            array_values($replacements),
            $assetUrlBaseRemap
        );
    }
}
