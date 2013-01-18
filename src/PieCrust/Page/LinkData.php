<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\Data\PaginationData;


class LinkData extends PaginationData
{
    protected $customValues;

    public function __construct(IPage $post, array $customValues = null)
    {
        parent::__construct($post);

        $this->customValues = $customValues;
    }

    protected function addCustomValues()
    {
        parent::addCustomValues();

        foreach ($this->customValues as $key => $value)
        {
            $this->values[$key] = $value;
        }
    }
}