<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PageHelper;


class PagePropertyData implements \ArrayAccess, \Iterator
{
    protected $pieCrust;
    protected $propertyName;
    protected $blogKey;

    protected $values;

    public function __construct(IPieCrust $pc, $propertyName)
    {
        $this->pieCrust = $pc;
        $this->propertyName = $propertyName;

        $blogKeys = $pc->getConfig()->getValueUnchecked('site/blogs');
        $this->blogKey = $blogKeys[0];
    }

    // {{{ Template Methods
    public function blog($blogName)
    {
        // This invalidates any values already cached.
        $this->values = null;

        $blogKeys = $pc->getConfig()->getValueUnchecked('site/blogs');
        if (!in_array($blogName, $blogKeys))
            throw new PieCrustException("No blog named '" . $blogName . "' was declared in the configuration file.");
        $this->blogKey = $blogName;

        return $this;
    }
    // }}}

    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureLoaded();
        return isset($this->values[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensureLoaded();
        return $this->values[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('The ' . $this->propertyName . ' list is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('The ' . $this->propertyName . ' list is read-only.');
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensureLoaded();
        return reset($this->values);
    }
  
    public function current()
    {
        $this->ensureLoaded();
        return current($this->values);
    }
  
    public function key()
    {
        $this->ensureLoaded();
        return key($this->values);
    }
  
    public function next()
    {
        $this->ensureLoaded();
        return next($this->values);
    }
  
    public function valid()
    {
        $this->ensureLoaded();
        return key($this->values) !== null;
    }
    // }}}
 
    protected function ensureLoaded()
    {
        if ($this->values != null)
            return;

        $posts = PageHelper::getPosts($this->pieCrust, $this->blogKey);
        $this->values = array();
        foreach ($posts as $post)
        {
            $this->addPageValue($post);
        }
        ksort($this->values);
    }

    protected function addPageValue(IPage $page)
    {
        $propertyValues = $page->getConfig()->getValue($this->propertyName);
        if ($propertyValues)
        {
            if (!is_array($propertyValues))
                $propertyValues = array($propertyValues);

            foreach ($propertyValues as $v)
            {
                if (!isset($this->values[$v]))
                {
                    $this->values[$v] = array(
                        'post_count' => 0,
                        'name' => $v
                    );
                }
                $this->values[$v]['post_count'] += 1;
            }
        }
    }
}

