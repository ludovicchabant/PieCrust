<?php

namespace PieCrust\Mock;

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;


class MockPluginLoader
{
    public $plugins;
    public $formatters;
    public $templateEngines;
    public $dataProviders;
    public $fileSystems;
    public $processors;
    public $importers;
    public $commands;
    public $twigExtensions;
    public $bakerAssistants;

    public function __construct()
    {
        $this->plugins = array();
        $this->formatters = array();
        $this->templateEngines = array();
        $this->dataProviders = array();
        $this->fileSystems = array();
        $this->processors = array();
        $this->importers = array();
        $this->commands = array();
        $this->twigExtensions = array();
        $this->bakerAssistants = array();
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

    public function getDataProviders()
    {
        return $this->dataProviders;
    }

    public function getFileSystems()
    {
        return $this->fileSystems;
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

    public function getBakerAssistants()
    {
        return $this->bakerAssistants;
    }
}

