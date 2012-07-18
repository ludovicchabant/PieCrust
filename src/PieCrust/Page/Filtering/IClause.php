<?php

namespace PieCrust\Page\Filtering;

use PieCrust\IPage;


/**
 * A filter clause.
 */
interface IClause
{
    public function addClause(IClause $clause);
    public function postMatches(IPage $post);
}
