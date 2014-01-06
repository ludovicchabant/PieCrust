<?php

namespace PieCrust\Page\Iteration;

use PieCrust\PieCrustException;
use PieCrust\Page\Filtering\PaginationFilter;


/**
 * A `RecursiveIteratorIterator` specifically designed to iterate over a tree
 * of `Linker` objects, while also allowing manual access to individual branches
 * of the tree through template accessors.
 *
 * @formatObject
 * @explicitInclude
 * @documentation The list of pages.
 */
class RecursiveLinkerIterator implements \ArrayAccess, \OuterIterator
{
    protected $rootLinker;
    protected $innerIterator;

    public function __construct($linker, $mode = \RecursiveIteratorIterator::LEAVES_ONLY)
    {
        $this->rootLinker = $linker;
        $this->innerIterator = new \RecursiveIteratorIterator($linker, $mode);
    }

    // {{{ Template data members
    /**
     * @include
     * @noCall
     * @documentation Skip `n` pages.
     */
    public function skip($count)
    {
        $this->innerIterator = new \LimitIterator($this->innerIterator, $count);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return `n` pages.
     */
    public function limit($count)
    {
        $this->innerIterator = new \LimitIterator($this->innerIterator, 0, $count);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Sort posts by a page setting.
     */
    public function sort($name, $reverse = false)
    {
        $this->innerIterator = new ConfigSortIterator(
            $this->innerIterator,
            $name,
            $reverse,
            function ($data, $name) { return $data[$name]; });
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Apply a named filter from the page's config (similar to `posts_filters`).
     */
    public function filter($filterName)
    {
        $page = $this->rootLinker->getPage();
        if ($page == null)
            throw new PieCrustException("Can't use 'filter()' because no parent page was set for the linker iterator.");
        if (!$page->getConfig()->hasValue($filterName))
            throw new PieCrustException("Couldn't find filter '{$filterName}' in the configuration header for page: {$page->getPath()}");
        
        $filterDefinition = $page->getConfig()->getValue($filterName);
        $filter = new PaginationFilter();
        $filter->addClauses($filterDefinition);
        $this->innerIterator = new ConfigFilterIterator($this->innerIterator, $filter, function ($data) { return $data->getPage(); });
        return $this;
    }
    // }}}
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        return isset($this->rootLinker[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->rootLinker[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('RecursiveLinkerIterator is read-only.');
    }

    public function offsetUnset($offset)
    {
        throw new PieCrustException('RecursiveLinkerIterator is read-only.');
    }
    // }}}

    // {{{ OuterIterator members
    public function getInnerIterator()
    {
        return $this->innerIterator->getInnerIterator();
    }
    // }}}
    
    // {{{ Iterator members
    public function current()
    {
        return $this->innerIterator->current();
    }

    public function key()
    {
        return $this->innerIterator->key();
    }

    public function next()
    {
        $this->innerIterator->next();
    }

    public function rewind()
    {
        $this->innerIterator->rewind();
    }

    public function valid()
    {
        return $this->innerIterator->valid();
    }
    // }}}
}

