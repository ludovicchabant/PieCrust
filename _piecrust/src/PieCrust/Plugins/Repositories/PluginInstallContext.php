<?php

namespace PieCrust\Plugins\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * The context object for installing/uninstalling
 * plugins.
 */
class PluginInstallContext
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

    public function getLocalPluginDir($pluginName, $autoCreate = true)
    {
        $dir = $this->pieCrust->getRootDir() . PieCrustDefaults::CONTENT_PLUGINS_DIR . $pluginName;
        if (!is_dir($dir) && $autoCreate)
        {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

