<?php

namespace PieCrust\Page;

use \Exception;
use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;
use PieCrust\Util\UriBuilder;


/**
 * A class that exposes the list of pages in a folder to another page.
 */
class Linker implements \ArrayAccess, \Iterator
{
    protected $parentPage;
    protected $baseDir;
    protected $selfName;
    protected $linksCache;
    
    /**
     * Creates a new instance of Linker.
     */
    public function __construct(IPage $page, $dir = null)
    {
        $this->page = $page;
        if ($dir)
        {
            $this->baseDir = $dir;
            $this->selfName = null;
        }
        else
        {
            $this->baseDir = dirname($page->getPath()) . '/';
            $this->selfName = basename($page->getPath());
        }
    }
    
    public function is_dir()
    {
        return true;
    }
    
    public function is_self()
    {
        return false;
    }
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureLinksCache();
        return isset($this->linksCache[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensureLinksCache();
        return $this->linksCache[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('Linker is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('Linker is read-only.');
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensureLinksCache();
        return reset($this->linksCache);
    }
  
    public function current()
    {
        $this->ensureLinksCache();
        return current($this->linksCache);
    }
  
    public function key()
    {
        $this->ensureLinksCache();
        return key($this->linksCache);
    }
  
    public function next()
    {
        $this->ensureLinksCache();
        $res = next($this->linksCache);
        while ($res and $res instanceof Linker)
        {
            $res = next($this->linksCache);
        }
        return $res;
    }
  
    public function valid()
    {
        $this->ensureLinksCache();
        return key($this->linksCache) !== null;
    }
    // }}}
    
    protected function ensureLinksCache()
    {
        if ($this->linksCache === null)
        {
            try
            {
                $this->linksCache = array();
                $it = new FilesystemIterator($this->baseDir);
                foreach ($it as $item)
                {
                    $basename = $item->getBasename();
                    if (!$basename or $basename[0] == '.')
                    {
                        continue;
                    }
                    else if ($item->isDir())
                    {
                        $linker = new Linker($this->page, $item->getPathname());
                        $this->linksCache[$basename . '_'] = $linker;
                        // We add '_' at the end of the directory name to avoid
                        // collisions with a possibly existing page with the same
                        // name (since we strip out the '.html' extension).
                        // This means the user must access directories with
                        // 'link.dirname_' instead of 'link.dirname' but hey, if
                        // you have a better idea, send me an email!
                    }
                    else if (pathinfo($basename, PATHINFO_EXTENSION) == 'html')
                    {
                        $path = $item->getPathname();
                        $key = $item->getBasename('.html');
                        try
                        {
                            $relativePath = PathHelper::getRelativePagePath($this->page->getApp(), $path, $this->page->getPageType());
                            $uri = UriBuilder::buildUri($relativePath);
                            $page = PageRepository::getOrCreatePage($this->page->getApp(), $uri, $path);
                            $this->linksCache[$key] = array(
                                'uri' => $uri,
                                'name' => $key,
                                'is_dir' => false,
                                'is_self' => ($basename == $this->selfName),
                                'page' => $page->getConfig()->get()
                            );
                        }
                        catch (Exception $e)
                        {
                            throw new PieCrustException("Error while loading page '" . $path .
                                                        "' for linking from '" . $this->page->getUri() .
                                                        "': " . $e->getMessage(), 0, $e);
                        }
                    }
                }
                
                if ($this->selfName != null)
                {
                    // Add a link to go up to the parent directory, but stay inside
                    // the app's pages directory.
                    $parentBaseDir = dirname($this->baseDir);
                    if (strlen($parentBaseDir) >= $this->page->getApp()->getPagesDir())
                    {
                        $linker = new Linker($this->page, dirname($this->baseDir));
                        $this->linksCache['_'] = $linker;
                    }
                    
                    // Also add a shortcut to the pages directory.
                    $linker = new Linker($this->page, $this->page->getApp()->getPagesDir());
                    $this->linksCache['_pages_'] = $linker;
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error while building the links from page '" . $this->page->getUri() .
                                            "': " . $e->getMessage(), 0, $e);
            }
        }
    }
}
