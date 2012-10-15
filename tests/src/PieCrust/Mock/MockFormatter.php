<?php

namespace PieCrust\Mock;

use PieCrust\IPieCrust;
use PieCrust\Formatters\IFormatter;
use PieCrust\PieCrustConfiguration;


class MockFormatter implements IFormatter
{
    public $name;
    public $priority;
    public $formats;
    public $exclusive;

    public function __construct($formats, $priority = IFormatter::PRIORITY_DEFAULT, $name = null)
    {
        $this->name = $name;
        $this->priority = $priority;
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
        if ($this->name == null)
            return "Formatted: {$text}";
        return "Formatted [{$this->name}]: {$text}";
    }
}

