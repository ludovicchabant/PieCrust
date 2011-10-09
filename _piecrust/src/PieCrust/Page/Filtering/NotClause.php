<?php

namespace PieCrust\Page\Filtering;

use PieCrust\PieCrustException;
use PieCrust\Page\Page;


/**
 * A 'not' filter clause.
 */
class NotClause implements IClause
{
    protected $child;
    
    public function __construct()
    {
    }
    
    public function addClause(IClause $clause)
    {
        if ($this->child)
            throw new PieCrustException("'not' filtering clauses can only have one child clause.");
        $this->child = $clause;
    }
    
    public function postMatches(Page $post)
    {
        if (!$this->child)
            throw new PieCrustException("'not' filtering clauses must have one child clause.");
        return !$this->child->postMatches($post);
    }
}
