<?php

namespace PieCrust\Page\Filtering;


/**
 * A boolean filter clause.
 */
abstract class BooleanClause implements IClause
{
    protected $clauses;
    
    protected function __construct()
    {
        $this->clauses = array();
    }
    
    public function addClause(IClause $clause)
    {
        $this->clauses[] = $clause;
    }
}

