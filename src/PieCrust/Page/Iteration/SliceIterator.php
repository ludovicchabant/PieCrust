<?php

namespace PieCrust\Page\Iteration;


/**
 * A re-implementation of the `LimitIterator`, but with some
 * knowledge as to whether it actually stripped items or not
 * from the inner iterator.
 */
class SliceIterator extends BaseIterator implements \OuterIterator
{
    protected $iterator;
    protected $offset;
    protected $limit;

    protected $hadMore;
    protected $innerCount;

    protected $page;
    protected $nextPage;
    protected $previousPage;

    public function __construct($iterator, $offset = 0, $limit = null)
    {
        $this->iterator = $iterator;
        $this->offset = $offset;
        $this->limit = $limit;

        $this->hadMore = false;
        $this->innerCount = 0;

        $this->page = null;
        $this->nextPage = null;
        $this->previousPage = null;
    }

    public function getInnerIterator()
    {
        return $this->iterator;
    }

    public function setCurrentPage($page)
    {
        if ($this->isLoaded())
            throw new PieCrustException("Can't set the current pagination page when this iterator has already been loaded.");

        $this->page = $page;
    }

    public function hadMoreItems()
    {
        return $this->hadMore;
    }

    public function getInnerCount()
    {
        return $this->innerCount;
    }

    public function getNextPage()
    {
        return $this->nextPage;
    }

    public function getPreviousPage()
    {
        return $this->previousPage;
    }

    protected function load()
    {
        $items = iterator_to_array($this->iterator);

        // Store some information for the pagination system.
        $this->innerCount = count($items);
        $this->hadMore = ($this->limit !== null && ($this->limit + $this->offset < $this->innerCount));
        $this->nextPage = null;
        $this->previousPage = null;
        if ($this->page != null)
        {
            $pageIndex = -1;
            foreach ($items as $i => $item)
            {
                if ($item == $this->page)
                {
                    $pageIndex = $i;
                    break;
                }
            }
            if ($pageIndex >= 0)
            {
                // Posts are sorted by reverse time, so watch out for what's
                // "previous" and what's "next"!
                if ($pageIndex > 0)
                    $this->nextPage = $items[$pageIndex - 1];
                if ($pageIndex < $this->innerCount - 1)
                    $this->previousPage = $items[$pageIndex + 1];
            }
        }

        // Now do our actual job of slicing things up.
        if ($this->offset > 0 || $this->hadMore)
        {
            $items = array_slice($items, $this->offset, $this->limit);
        }

        return $items;
    }
}
