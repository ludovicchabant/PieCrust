<?php

namespace PieCrust\Formatters;

use PieCrust\PieCrust;


class PassThroughFormatter implements IFormatter
{
    public function initialize(PieCrust $pieCrust)
    {
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_LOW;
    }
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $isUnformatted;
    }
    
    public function format($text)
    {
        return $text;
    }
}
