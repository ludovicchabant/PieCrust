<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;


/**
 * The interface for a custom template data provider.
 */
interface IDataProvider
{
    /**
     * Gets the name of this data provider, used as the
     * end-point in site or page configuration.
     */
    public function getName();

    /**
     * Gets the page data for the given page.
     */
    public function getPageData(IPage $page);
}

