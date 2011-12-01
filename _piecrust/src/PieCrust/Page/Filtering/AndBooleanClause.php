<?php

namespace PieCrust\Page\Filtering;

use PieCrust\IPage;


/**
 * 'AND' boolean filter clause.
 */
class AndBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(IPage $post)
    {
        foreach ($this->clauses as $c)
        {
            if (!$c->postMatches($post))
                return false;
        }
        return true;
    }
}
