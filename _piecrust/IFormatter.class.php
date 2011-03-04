<?php

/**
 * The base interface for PieCrust page formatters.
 *
 */
interface IFormatter
{
    const PRIORITY_HIGH = 1;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = -1;
    
    public function initialize(PieCrust $pieCrust);
    public function getPriority();
    public function supportsFormat($format, $isUnformatted);
    public function format($text);
}

