<?php

namespace PieCrust\Page\Filtering;

use PieCrust\Page\Page;


/**
 * A filter clause.
 */
interface IClause
{
    public function addClause(IClause $clause);
    public function postMatches(Page $post);
}
