<?php

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;


class MockPluginLoader
{
    protected $plugins;

    public function __construct(array $plugins)
    {
        $this->plugins = $plugins;
    }

    public function getPlugins()
    {
        return $this->plugins;
    }
}

