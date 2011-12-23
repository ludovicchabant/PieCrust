<?php

namespace PieCrust;


/**
 * The base class for a PieCrust plugin.
 */
class PieCrustPlugin
{
    /**
     * Gets the name of the plugin.
     */
    public function getName()
    {
        throw new PieCrustException("No name was defined for this plugin.");
    }

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
     * Runs custom initialization code.
     */
    public function initialize(IPieCrust $pieCrust)
    {
    }
}

