<?php

namespace PieCrust\Page\Filtering;

use PieCrust\Page\Page;


/**
 * 'AND' boolean filter clause.
 */
class AndBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(Page $post)
    {
        foreach ($this->clauses as $c)
        {
            if (!$c->postMatches($post))
                return false;
        }
        return true;
    }
}
