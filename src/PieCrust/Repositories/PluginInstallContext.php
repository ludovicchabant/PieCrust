<?php

namespace PieCrust\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * The context object for installing/uninstalling
 * plugins.
 */
class PluginInstallContext extends InstallContext
{
    public function __construct(IPieCrust $pieCrust, $log)
    {
        parent::__construct($pieCrust, $log);
    }

    public function getLocalPluginDir($pluginName, $autoCreate = true)
    {
        $dir = $this->pieCrust->getRootDir() . 
            PieCrustDefaults::CONTENT_PLUGINS_DIR . 
            $pluginName;
        if (!is_dir($dir) && $autoCreate)
        {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

