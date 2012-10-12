<?php

namespace PieCrust\Page\Iteration;


class LimitIteratorModifier extends BaseIteratorModifier
{
    protected $limit;
    protected $stripped;

    public function __construct($limit)
    {
        $this->limit = $limit;
        $this->stripped = false;
    }

    public function didStripItems()
    {
        return $this->stripped;
    }

    public function modify($items)
    {
        if (count($items) <= $this->limit)
            return $items;

        $this->stripped = true;
        return array_slice($items, 0, $this->limit);
    }

    public function __toString()
    {
        return substr(get_class($this), 24, -16) . "(" . $this->limit . ")";
    }
}

