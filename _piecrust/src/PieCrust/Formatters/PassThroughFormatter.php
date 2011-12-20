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

    public function isExclusive()
    {
        return true;
    }
    
    public function supportsFormat($format)
    {
        return $format == 'none';
    }
    
    public function format($text)
    {
        return $text;
    }
}
