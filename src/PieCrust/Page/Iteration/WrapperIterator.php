<?php

namespace PieCrust\Page\Iteration;


/**
 * An iterator that just wraps a data-source, which can be
 * set at any time.
 */
class WrapperIterator extends BaseIterator implements \OuterIterator
{
    public function __construct(array $dataSource = null)
    {
        $this->dataSource = $dataSource;
    }

    public function getInnerIterator()
    {
        return null;
    }

    public function setDataSource($items)
    {
        $this->unload();
        $this->dataSource = $dataSource;
    }

    protected function load()
    {
        return $this->dataSource;
    }
}
