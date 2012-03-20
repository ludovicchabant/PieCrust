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
    protected $values;

    public function __construct(IPage $page)
    {
        $this->page = $page;
    }

    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensurePageLoaded();
        return isset($this->values[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensurePageLoaded();
        return $this->values[$offset];
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
        return reset($this->values);
    }
  
    public function current()
    {
        $this->ensurePageLoaded();
        return current($this->values);
    }
  
    public function key()
    {
        $this->ensurePageLoaded();
        return key($this->values);
    }
  
    public function next()
    {
        $this->ensurePageLoaded();
        return next($this->values);
    }
  
    public function valid()
    {
        $this->ensurePageLoaded();
        return key($this->values) !== null;
    }
    // }}}
    
    protected function ensurePageLoaded()
    {
        if ($this->values == null)
        {
            $this->values = $this->page->getConfig()->get();

            // Sub-classes can overload `addCustomValues` to add
            // more stuff to the values array besides the page's
            // configuration.
            $this->addCustomValues();
        }
    }

    protected function addCustomValues()
    {
    }
}

