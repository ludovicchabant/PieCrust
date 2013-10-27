<?php

namespace PieCrust;


/**
 * The base class for a PieCrust plugin.
 */
abstract class PieCrustPlugin
{
    /**
     * Gets the name of the plugin.
     */
    public abstract function getName();

    /**
     * Gets the formatters in this plugin.
     */
    public function getFormatters()
    {
        return array();
    }

    /**
     * Gets the template engines in this plugin.
     */
    public function getTemplateEngines()
    {
        return array();
    }

    /**
     * Gets the custom template data providers in this plugin.
     */
    public function getDataProviders()
    {
        return array();
    }

    /**
     * Gets the file-systems.
     */
    public function getFileSystems()
    {
        return array();
    }

    /**
     * Gets the file processors in this plugin.
     */
    public function getProcessors()
    {
        return array();
    }

    /**
     * Gets the importers in this plugin.
     */
    public function getImporters()
    {
        return array();
    }

    /**
     * Gets the chef commands in this plugin.
     */
    public function getCommands()
    {
        return array();
    }

    /**
     * Gets the Twig extensions in this plugin.
     */
    public function getTwigExtensions()
    {
        return array();
    }

    /**
     * Gets the repository types in this plugin.
     */
    public function getRepositories()
    {
        return array();
    }

    /**
     * Gets the baker assistants in this plugin.
     */
    public function getBakerAssistants()
    {
        return array();
    }

    /**
     * Runs custom initialization code.
     */
    public function initialize(IPieCrust $pieCrust)
    {
    }
}

