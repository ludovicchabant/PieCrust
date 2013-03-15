<?php

namespace PieCrust\Page\Iteration;

use \OutOfRangeException;
use PieCrust\PieCrustException;


/**
 * The base class for a lazy-loading iterator.
 */
abstract class BaseIterator implements \Iterator, \ArrayAccess, \Countable
{
    protected $items;
    protected $unloadOnRewind;

    // {{{ Countable members
    public function count()
    {
        $this->ensureLoaded();
        return $this->items->count();
    }
    // }}}
    
    // {{{ Iterator members
    public function current()
    {
        $this->ensureLoaded();
        return $this->items->current();
    }
    
    public function key()
    {
        $this->ensureLoaded();
        return $this->items->key();
    }
    
    public function next()
    {
        $this->ensureLoaded();
        $this->items->next();
    }
    
    public function rewind()
    {
        if ($this->unloadOnRewind)
            $this->unload();
        $this->ensureLoaded();
        $this->items->rewind();
    }
    
    public function valid()
    {
        if (!$this->isLoaded())
            return false;
        $this->ensureLoaded();
        return $this->items->valid();
    }
    // }}}
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureLoaded();
        return $this->items->offsetExists($offset);
    }
    
    public function offsetGet($offset)
    {
        $this->ensureLoaded();
        return $this->items->offsetGet($offset);
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException("'".get_class($this)."' is read-only.");
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException("'".get_class($this)."' is read-only.");
    }
    // }}}

    // {{{ Protected members
    protected function __construct()
    {
        $this->items = null;
        $this->unloadOnRewind = false;
    }

    protected function isLoaded()
    {
        return ($this->items != null);
    }

    protected function unload()
    {
        $this->items = null;
    }

    protected function ensureLoaded()
    {
        if ($this->items != null)
            return;

        $items = $this->load();
        if (is_array($items))
            $this->items = new \ArrayIterator($items);
        else
            $this->items = $items;
    }

    protected abstract function load();
    // }}}
}

