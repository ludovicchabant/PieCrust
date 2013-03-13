<?php

namespace PieCrust\Chef;

use PieCrust\Environment\CachedEnvironment;


/**
 * An environment that runs under a `Chef` instance in
 * command line.
 */
class ChefEnvironment extends CachedEnvironment
{
    public function setLog($logger)
    {
        $this->logger = $logger;
    }

    protected $commandExtensions;
    /**
     * Gets the command extensions.
     */
    public function getCommandExtensions($commandName)
    {
        if (isset($this->commandExtensions[$commandName]))
            return $this->commandExtensions[$commandName];
        return array();
    }

    /**
     * Adds a command extension.
     */
    public function addCommandExtension($commandName, $extension)
    {
        if (!isset($this->commandExtensions[$commandName]))
            $this->commandExtensions[$commandName] = array();

        $this->commandExtensions[$commandName][] = $extension;
    }

    /**
     * Creates a new instance of `ChefEnvironment`.
     */
    public function __construct()
    {
        parent::__construct();
    }
}

