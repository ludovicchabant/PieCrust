<?php

namespace PieCrust\Util;


/**
 * Information about a configuration header in a text file.
 */
class ConfigurationHeader
{
    public $config;
    public $textOffset;

    public function __construct(array $config, $textOffset)
    {
        $this->config = $config;
        $this->textOffset = $textOffset;
    }
}

