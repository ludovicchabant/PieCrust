<?php

namespace PieCrust\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * The context object for installing/uninstalling
 * themes.
 */
class ThemeInstallContext extends InstallContext
{
    public function __construct(IPieCrust $pieCrust, $log)
    {
        parent::__construct($pieCrust, $log);
    }

    public function getLocalThemeDir($autoCreate = true)
    {
        $dir = $this->pieCrust->getRootDir() . PieCrustDefaults::CONTENT_THEME_DIR;
        if (!is_dir($dir) && $autoCreate)
        {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

