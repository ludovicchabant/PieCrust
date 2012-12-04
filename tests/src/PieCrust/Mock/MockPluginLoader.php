<?php

namespace PieCrust\Mock;

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
    public $twigExtensions;

    public function __construct()
    {
        $this->plugins = array();
        $this->formatters = array();
        $this->templateEngines = array();
        $this->processors = array();
        $this->importers = array();
        $this->commands = array();
        $this->twigExtensions = array();
    }

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

    public function getTwigExtensions()
    {
        return $this->twigExtensions;
    }
}

