<?php

namespace PieCrust\Page;


/**
 * An `Iterator` that can filter something like looks like a page config
 * according to a given `PaginationFilter`.
 */
class FilterLinkerIterator extends \FilterIterator
{
    protected $filter;

    public function __construct($iterator, $filter)
    {
        parent::__construct($iterator);
        $this->filter = $filter;
    }

    public function accept()
    {
        $linkData = $this->getInnerIterator()->current();
        return $this->filter->postMatches($linkData->getPage());
    }
}

