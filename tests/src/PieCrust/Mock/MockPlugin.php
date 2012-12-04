<?php

namespace PieCrust\Mock;

use PieCrust\PieCrustPlugin;


class MockPlugin extends PieCrustPlugin
{
    public $name;
    public $formatters;
    public $templateEngines;
    public $processors;
    public $importers;
    public $commands;

    public function __construct($name = 'Mock')
    {
        $this->name = $name;
        $this->formatters = array();
        $this->templateEngines = array();
        $this->processors = array();
    }

    public function getName()
    {
        return $this->name;
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


