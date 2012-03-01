<?php

namespace PieCrust\Util;

use PieCrust\IPage;


/**
 * A class that wraps a page's configuration array
 * and only loads the page when needed.
 *
 * @formatObject
 * @explicitInclude
 * @documentation The configuration header for that page.
 */
class PageConfigWrapper implements \ArrayAccess, \Iterator
{
    protected $page;
    protected $configArray;

    public function __construct(IPage $page)
    {
        $this->page = $page;
    }

    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensurePageLoaded();
        return isset($this->configArray[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensurePageLoaded();
        return $this->configArray[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('PageConfigWrapper is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('PageConfigWrapper is read-only.');
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensurePageLoaded();
        return reset($this->configArray);
    }
  
    public function current()
    {
        $this->ensurePageLoaded();
        return current($this->configArray);
    }
  
    public function key()
    {
        $this->ensurePageLoaded();
        return key($this->configArray);
    }
  
    public function next()
    {
        $this->ensurePageLoaded();
        return next($this->configArray);
    }
  
    public function valid()
    {
        $this->ensurePageLoaded();
        return key($this->configArray) !== null;
    }
    // }}}
    
    protected function ensurePageLoaded()
    {
        if ($this->configArray == null)
        {
            $this->configArray = $this->page->getConfig()->get();
        }
    }
}

