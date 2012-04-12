<?php

namespace PieCrust\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * The base context object for installing/uninstalling
 * plugins.
 */
class InstallContext
{
    protected $pieCrust;
    /**
     * The application to install the plugin into.
     */
    public function getApp()
    {
        return $this->pieCrust;
    }

    protected $log;
    /**
     * Gets the logger.
     */
    public function getLog()
    {
        return $this->log;
    }

    public function __construct(IPieCrust $pieCrust, $log)
    {
        $this->pieCrust = $pieCrust;
        $this->log = $log;
    }
}

