<?php

use PieCrust\IPieCrust;
use PieCrust\Formatters\IFormatter;
use PieCrust\PieCrustConfiguration;


class MockFormatter implements IFormatter
{
    public $priority;
    public $formats;
    public $exclusive;

    public function __construct($formats)
    {
        $this->priority = IFormatter::PRIORITY_DEFAULT;
        $this->exclusive = true;

        if (!is_array($formats))
            $formats = array($formats);
        $this->formats = $formats;
    }

    public function initialize(IPieCrust $pieCrust)
    {
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function isExclusive()
    {
        return $this->exclusive;
    }
    
    public function supportsFormat($format)
    {
        return in_array($format, $this->formats);
    }
    
    public function format($text)
    {
        return "Formatted: " . $text;
    }
}

