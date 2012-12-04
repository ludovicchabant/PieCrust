<?php

namespace PieCrust\Page\Iteration;

use \OutOfRangeException;
use PieCrust\PieCrustException;


abstract class BaseIterator implements \Iterator, \ArrayAccess, \Countable
{
    // {{{ Countable members
    /**
     * @include
     * @noCall
     * @documentation Return the number of matching posts.
     */
    public function count()
    {
        $this->ensureLoaded();
        return count($this->posts);
    }
    // }}}
    
    // {{{ Iterator members
    public function current()
    {
        $this->ensureLoaded();
        return current($this->posts);
    }
    
    public function key()
    {
        $this->ensureLoaded();
        return key($this->posts);
    }
    
    public function next()
    {
        $this->ensureLoaded();
        next($this->posts);
    }
    
    public function rewind()
    {
        $this->unload();
        $this->ensureLoaded();
        reset($this->posts);
    }
    
    public function valid()
    {
        if (!$this->isLoaded())
            return false;
        $this->ensureLoaded();
        return (key($this->posts) !== null);
    }
    // }}}
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        if (!is_int($offset))
            return false;
        
        $this->ensureLoaded();
        return isset($this->posts[$offset]);
    }
    
    public function offsetGet($offset)
    {
        if (!is_int($offset))
           throw new OutOfRangeException();
            
        $this->ensureLoaded();
        return $this->posts[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException("The pagination is read-only.");
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException("The pagination is read-only.");
    }
    // }}}

    // {{{ Protected members
    protected function __construct()
    {
        $this->posts = null;
    }

    protected function isLoaded()
    {
        return ($this->posts != null);
    }

    protected function unload()
    {
        $this->posts = null;
    }

    protected function ensureLoaded()
    {
        if ($this->posts != null)
            return;

        $this->load();
    }

    protected abstract function load();
    // }}}
}

