<?php

namespace PieCrust\Page\Filtering;

use PieCrust\Page\Page;


/**
 * Filter clause for having a specific setting value.
 */
class HasFilterClause extends FilterClause
{
    public function __construct($settingName, $settingValue)
    {
        FilterClause::__construct($settingName, $settingValue);
    }
    
    public function postMatches(Page $post)
    {
        $actualValue = $post->getConfigValue($this->settingName);
        return $actualValue != null && in_array($this->settingValue, $actualValue);
    }
}
