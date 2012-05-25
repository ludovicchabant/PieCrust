<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PageHelper;


/**
 * A class that lists pages in buckets defined by a
 * configuration property (typically "pages by category"
 * and "pages by tag").
 */
class PagePropertyArrayData implements \Iterator, \ArrayAccess, \Countable
{
    protected $page;
    protected $propertyName;
    protected $blogKey;

    protected $values;

    public function __construct(IPage $page, $blogKey, $propertyName)
    {
        $this->page = $page;
        $this->blogKey = $blogKey;
        $this->propertyName = $propertyName;
    }

    // {{{ Countable members
    public function count()
    {
        return count($this->values);
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
 
    protected function ensureNotLoaded($func)
    {
        if ($this->values != null)
            throw new PieCrustException("Can't call '{$func}' after the posts have been loaded for property '{$this->propertyName}'.");
    }

    protected function ensureLoaded()
    {
        if ($this->values != null)
            return;

        // Gather all posts sorted by the property we want.
        $dataSources = array();
        $posts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);
        foreach ($posts as $post)
        {
            $this->addPageValue($post, $dataSources);
        }

        // Now for each property bucket, create a pagination iterator.
        $this->values = array();
        foreach ($dataSources as $property => $dataSource)
        {
            $this->values[$property] = new PagePropertyData(
                $this->page, 
                $this->blogKey, 
                $property,
                $dataSource
            );
        }
        ksort($this->values);
    }

    protected function addPageValue(IPage $page, &$dataSources)
    {
        $propertyValues = $page->getConfig()->getValue($this->propertyName);
        if ($propertyValues)
        {
            if (!is_array($propertyValues))
                $propertyValues = array($propertyValues);

            foreach ($propertyValues as $v)
            {
                if (!isset($dataSources[$v]))
                {
                    $dataSources[$v] = array();
                }
                $dataSources[$v][] = $page;
            }
        }
    }
}

