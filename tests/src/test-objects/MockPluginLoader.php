<?php

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;


class MockPluginLoader
{
    public $plugins;
    public $formatters;
    public $templateEngines;
    public $processors;
    public $importers;
    public $commands;

    public function getPlugins()
    {
        return $this->plugins;
    }

    public function getFormatters()
    {
        return $this->formatters;
    }

    public function getTemplateEngines()
    {
        return $this->templateEngines;
    }

    public function getProcessors()
    {
        return $this->processors;
    }

    public function getImporters()
    {
        return $this->importers;
    }

    public function getCommands()
    {
        return $this->commands;
    }
}

