<?php

namespace PieCrust\Page;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * A class that exposes the list of pages in a folder to another page.
 */
class Linker implements \ArrayAccess, \Iterator
{
    /**
     * Default naming.
     */
    const DIR_SUFFIX = '_';
    
    protected $pieCrust;
    protected $baseDir;
    protected $selfKey;
    protected $linksCache;
    
    /**
     * Creates a new instance of Linker.
     */
    public function __construct(PieCrust $pieCrust, $pageOrBaseDir)
    {
        $this->pieCrust = $pieCrust;
        if ($pageOrBaseDir instanceof Page)
        {
            $this->baseDir = dirname($pageOrBaseDir->getPath()) . '/';
            $this->selfKey = basename($pageOrBaseDir->getPath(), '.html');
        }
        else
        {
            $this->baseDir = $pageOrBaseDir;
            $this->selfKey = null;
        }
    }
    
    // {{{ ArrayAccess members
    public function __isset($name)
    {
        return $this->offsetExists($name);
    }
    
    public function __get($name)
    {
        return $this->offsetGet($name);
    }
    
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
        while ($res !== null and ($res instanceof Linker))
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
            $this->linksCache = array();
            $it = new \FilesystemIterator($this->baseDir);
            foreach ($it as $item)
            {
                if ($item->isDir())
                {
                    $key = $item->getBasename() . self::DIR_SUFFIX;
                    $linker = new Linker($this->pieCrust, $item->getPathname(), null);
                    $this->linksCache[$key] = $linker;
                }
                else
                {
                    $key = $item->getBasename('.html');
                    $uri = Page::buildUri($item->getPathname(), Page::TYPE_REGULAR);
                    $pageInfo = array(
                        'uri' => $uri,
                        'name' => $key,
                        'is_self' => ($key == $this->selfKey),
                        'page' => new PageWrapper(PageRepository::getOrCreatePage($this->pieCrust, $uri, $item->getPathname()))
                    );
                    $this->linksCache[$key] = $pageInfo;
                }
            }
        }
    }
}
