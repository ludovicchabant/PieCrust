<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\Iteration\PageIterator;
use PieCrust\Util\PageHelper;


/**
 * A class that lists pages in a single bucket defined by a
 * time interval (typically "posts from year X" or "posts from
 * month Y").
 *
 * @formatObject
 * @explicitInclude
 */
class PageTimeData implements \Iterator, \ArrayAccess, \Countable
{
    protected $timeValue;
    protected $timestamp;
    protected $posts;

    public function __construct(IPage $page, $blogKey, $timeValue, $timestamp, array $dataSource)
    {
        $this->timeValue = $timeValue;
        $this->timestamp = $timestamp;
        $this->posts = new PageIterator($page->getApp(), $blogKey, $dataSource);
        $this->posts->setCurrentPage($page);
    }

    public function __toString()
    {
        return $this->timeValue;
    }

    // {{{ Template members
    /**
     * @include
     * @documentation The formatted time.
     */
    public function name()
    {
        return $this->timeValue;
    }

    /**
     * @include
     * @documentation The timestamp.
     */
    public function timestamp()
    {
        return $this->timestamp;
    }

    /**
     * @include
     * @noCall
     * @documentation The list of posts. Available properties and functions are the same as `pagination.posts`.
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
        return $this->posts->count();
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

