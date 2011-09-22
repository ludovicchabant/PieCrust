<?php

namespace PieCrust\Page;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * A wrapper around a page to access configuration values like an array.
 */
class PageWrapper implements \ArrayAccess
{
    protected $page;
    
    public function __construct(Page $page)
    {
        $this->page = $page;
    }
    
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
        return true;
    }
    
    public function offsetGet($offset) 
    {
        return $this->page->getConfigValue($offset);
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('TemplatePageWrapper is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('TemplatePageWrapper is read-only.');
    }
}

