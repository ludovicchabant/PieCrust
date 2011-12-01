<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


class PassThroughFormatter implements IFormatter
{
    public function initialize(IPieCrust $pieCrust)
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
