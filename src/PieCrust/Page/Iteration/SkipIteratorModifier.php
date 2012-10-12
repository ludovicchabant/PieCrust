<?php

namespace PieCrust\Page\Iteration;


class SkipIteratorModifier extends BaseIteratorModifier
{
    protected $skip;

    public function __construct($skip)
    {
        $this->skip = $skip;
    }

    public function modify($items)
    {
        return array_slice($items, $this->skip);
    }

    public function __toString()
    {
        return substr(get_class($this), 24, -16) . "(" . $this->skip . ")";
    }
}

