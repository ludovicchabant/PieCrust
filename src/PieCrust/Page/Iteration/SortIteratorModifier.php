<?php

namespace PieCrust\Page\Iteration;

use PieCrust\PieCrustException;


class SortIteratorModifier extends BaseIteratorModifier
{
    protected $sortByName;
    protected $sortByReverse;

    public function __construct($sortByName, $sortByReverse = false)
    {
        $this->sortByName = $sortByName;
        $this->sortByReverse = $sortByReverse;
    }

    public function affectsOrder()
    {
        return true;
    }

    public function dependsOnOrder()
    {
        return false;
    }

    public function modify($items)
    {
        if (false === usort($items, array($this, "sortByCustom")))
            throw new PieCrustException("Error while sorting posts with the specified setting: {$this->sortByName}");
        return $items;
    }

    public function __toString()
    {
        return 'Sort(' . ($this->sortByReverse ? '-' : '') . $this->sortByName . ')';
        //return substr(get_class($this), 24, -16) . "(" . $this->sortByReverse ? '-' : '' . $this->sortByName . ")";
    }

    protected function sortByCustom($post1, $post2)
    {
        $value1 = $post1->getConfig()->getValue($this->sortByName);
        $value2 = $post2->getConfig()->getValue($this->sortByName);
        
        if ($value1 == null && $value2 == null)
            return 0;
        if ($value1 == null && $value2 != null)
            return $this->sortByReverse ? 1 : -1;
        if ($value1 != null && $value2 == null)
            return $this->sortByReverse ? -1 : 1;
        if ($value1 == $value2)
            return 0;
        if ($this->sortByReverse)
            return ($value1 < $value2) ? 1 : -1;
        else
            return ($value1 < $value2) ? -1 : 1;
    }
}

