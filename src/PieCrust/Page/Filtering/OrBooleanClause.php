<?php

namespace PieCrust\Page\Filtering;

use PieCrust\IPage;


/**
 * 'OR' boolean filter clause.
 */
class OrBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(IPage $post)
    {
        foreach ($this->clauses as $c)
        {
            if ($c->postMatches($post))
                return true;
        }
        return false;
    }
}
