<?php

namespace PieCrust\Chef;

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;
use PieCrust\PieCrustException;
use PieCrust\Environment\CachedEnvironment;
use PieCrust\Plugins\PluginLoader;


/**
 * A null PieCrust app.
 */
class NullPieCrust implements IPieCrust
{
    public function getRootDir()
    {
        return null;
    }
    
    public function isCachingEnabled()
    {
        return false;
    }
    
    public function isDebuggingEnabled()
    {
        return false;
    }
    
    public function getTemplatesDirs()
    {
        return array();
    }
    
    public function setTemplatesDirs($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }
    
    public function addTemplatesDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }
    
    public function getPagesDir()
    {
        return false;
    }
    
    public function setPagesDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }
    
    public function getPostsDir()
    {
        return false;
    }

    public function setPostsDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }

    public function getPluginsDirs()
    {
        return array();
    }

    public function setPluginsDirs($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }

    public function addPluginsDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }

    public function getThemeDir()
    {
        return false;
    }

    public function setThemeDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }
    
    public function getCacheDir()
    {
        return false;
    }
    
    public function setCacheDir($dir)
    {
        throw new PieCrustException("The NullPieCrust application is read-only.");
    }

    protected $pluginLoader;

    public function getPluginLoader()
    {
        return $this->pluginLoader;
    }

    protected $config;

    public function getConfig()
    {
        return $this->config;
    }

    protected $environment;

    public function getEnvironment()
    {
        return $this->environment;
    }
    
    public function __construct()
    {
        $this->config = new PieCrustConfiguration();
        $this->pluginLoader = new PluginLoader($this);
        $this->environment = new CachedEnvironment($this);
    }
}
