<?php

namespace PieCrust\Page\Iteration;


abstract class BaseIteratorModifier
{
    public function dependsOnOrder()
    {
        return true;
    }

    public function affectsOrder()
    {
        return false;
    }

    public function didStripItems()
    {
        return false;
    }

    public abstract function modify($items);

    public function __toString()
    {
        return substr(get_class($this), 24, -16);
    }
}

