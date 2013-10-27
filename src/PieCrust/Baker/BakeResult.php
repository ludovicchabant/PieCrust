<?php

namespace PieCrust\Baker;


/**
 * A class representing the result of a bake operation.
 */
class BakeResult
{
    // Whether the bake operation actually occured.
    public $didBake;

    public function __construct($didBake)
    {
        $this->didBake = $didBake;
    }
}

