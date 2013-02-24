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

    public function __construct($iterator, $offset = 0, $limit = null)
    {
        $this->iterator = $iterator;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->hadMore= false;
    }

    public function getInnerIterator()
    {
        return $this->iterator;
    }

    public function hadMoreItems()
    {
        return $this->hadMore;
    }

    protected function load()
    {
        $items = iterator_to_array($this->iterator);

        $this->hadMore = ($this->limit !== null && ($this->limit + $this->offset < count($items)));
        if ($this->offset > 0 || $this->hadMore)
        {
            $items = array_slice($items, $this->offset, $this->limit);
        }

        return $items;
    }
}
