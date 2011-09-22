<?php

namespace PieCrust\Page\Filtering;

use PieCrust\Page\Page;


/**
 * 'OR' boolean filter clause.
 */
class OrBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(Page $post)
    {
        foreach ($this->clauses as $c)
        {
            if ($c->postMatches($post))
                return true;
        }
        return false;
    }
}
