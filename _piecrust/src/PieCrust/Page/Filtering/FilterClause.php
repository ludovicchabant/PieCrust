<?php

namespace PieCrust\Page\Filtering;


/**
 * Base filter clause.
 */
abstract class FilterClause implements IClause
{
    protected $settingName;
    protected $settingValue;
    
    protected function __construct($settingName, $settingValue)
    {
        $this->settingName = $settingName;
        $this->settingValue = $settingValue;
    }
    
    public function addClause(IClause $clause)
    {
        throw new PieCrustException("Filter clauses can't have child clauses. Use a boolean clause instead.");
    }
}
