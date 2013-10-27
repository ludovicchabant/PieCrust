<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;


/**
 * Provides a simple implementation of `IDataProvider`.
 */
class DataProvider implements IDataProvider
{
    protected $name;
    /**
     * Gets the name of this data provider, used as the
     * end-point in site or page configuration.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Creates a new instance of `DataProvider`.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the page data for the given page.
     */
    public function getPageData(IPage $page)
    {
        return null;
    }
}

