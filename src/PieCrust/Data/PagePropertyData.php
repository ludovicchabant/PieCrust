<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\Iteration\PageIterator;
use PieCrust\Util\PageHelper;


/**
 * A class that lists pages in a single bucket defined by a
 * configuration property (typically "pages in category X"
 * and "pages with tag Y").
 *
 * @formatObject
 * @explicitInclude
 */
class PagePropertyData implements \Iterator, \ArrayAccess, \Countable
{
    protected $propertyValue;
    protected $posts;
    protected $postCount;

    public function __construct(IPage $page, $blogKey, $propertyValue, array $dataSource)
    {
        $this->propertyValue = $propertyValue;
        $this->posts = new PageIterator($page->getApp(), $blogKey, $dataSource);
        $this->posts->setCurrentPage($page);
        $this->postCount = count($dataSource); // Backwards compatibility (should use posts.count)
    }

    public function __toString()
    {
        return $this->propertyValue;
    }

    // {{{ Template members
    /**
     * @include
     * @documentation The name of the category or tag. Use this instead of the array key if the name contains special characters.
     */
    public function name()
    {
        return $this->propertyValue;
    }

    /**
     * @include
     * @noCall
     * @documentation The list of posts in this category or tag. Available properties and functions are the same as `pagination.posts`.
     */
    public function posts()
    {
        return $this->posts;
    }

    /**
     * The number of posts, available for backwards compatibility
     */
    public function post_count()
    {
        return $this->postCount;
    }
    // }}}

    // {{{ Countable members
    public function count()
    {
        return $this->posts->count();
    }
    // }}}
    
    // {{{ Iterator members
    public function current()
    {
        return $this->posts->current();
    }
    
    public function key()
    {
        return $this->posts->key();
    }
    
    public function next()
    {
        $this->posts->next();
    }
    
    public function rewind()
    {
        $this->posts->rewind();
    }
    
    public function valid()
    {
        return $this->posts->valid();
    }
    // }}}
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        return $this->posts->offsetExists($offset);
    }
    
    public function offsetGet($offset)
    {
        return $this->posts->offsetGet($offset);
    }
    
    public function offsetSet($offset, $value)
    {
        $this->posts->offsetSet($offset, $value);
    }
    
    public function offsetUnset($offset)
    {
        $this->posts->offsetUnset($offset);
    }
    // }}}
}

