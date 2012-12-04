<?php

namespace PieCrust\Page\Iteration;


class FilterIteratorModifier extends BaseIteratorModifier
{
    protected $filter;

    public function __construct($filter)
    {
        $this->filter = $filter;
    }

    public function dependsOnOrder()
    {
        return false;
    }

    public function modify($items)
    {
        $filteredItems = array();
        foreach ($items as $item)
        {
            if ($this->filter->postMatches($item))
            {
                $filteredItems[] = $item;
            }
        }
        return $filteredItems;
    }
}

