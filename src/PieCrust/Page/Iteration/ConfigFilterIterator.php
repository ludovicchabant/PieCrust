<?php

namespace PieCrust\Page\Iteration;


/**
 * An iterator that filters an input iterator using a pagination
 * filter.
 */
class ConfigFilterIterator extends \FilterIterator
{
    protected $filter;
    protected $pageAccessor;

    public function __construct($iterator, $filter, $pageAccessor = null)
    {
        parent::__construct($iterator);

        $this->filter = $filter;
        $this->pageAccessor = $pageAccessor;
    }

    public function accept()
    {
        $item = $this->getInnerIterator()->current();

        $pageAccessor = $this->pageAccessor;
        if ($pageAccessor != null)
            $page = $pageAccessor($item);
        else
            $page = $item;

        return $this->filter->postMatches($page);
    }
}

