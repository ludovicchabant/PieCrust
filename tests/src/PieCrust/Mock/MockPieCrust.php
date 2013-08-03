<?php

namespace PieCrust\Mock;

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Environment\CachedEnvironment;


class MockPieCrust implements IPieCrust
{
    public $rootDir;
    
    public function getRootDir()
    {
        return $this->rootDir;
    }
    
    public $isCachingEnabled;
    
    public function isCachingEnabled()
    {
        return $this->isCachingEnabled;
    }
    
    public $isDebuggingEnabled;
    
    public function isDebuggingEnabled()
    {
        return $this->isDebuggingEnabled;
    }
    
    public $templateDirs;
    
    public function getTemplatesDirs()
    {
        return $this->templateDirs;
    }
    
    public function setTemplatesDirs($dir)
    {
        $this->templateDirs = $dir;
    }
    
    public function addTemplatesDir($dir)
    {
        $templateDirs[] = $dir;
    }
    
    public $pagesDir;
    
    public function getPagesDir()
    {
        return $this->pagesDir;
    }
    
    public function setPagesDir($dir)
    {
        $this->pagesDir = $dir;
    }
    
    public $postsDir;
    
    public function getPostsDir()
    {
        return $this->postsDir;
    }
    
    public function setPostsDir($dir)
    {
        $this->postsDir = $dir;
    }
    
    public $cacheDir;
    
    public function getCacheDir()
    {
        return $this->cacheDir;
    }
    
    public function setCacheDir($dir)
    {
        $this->cacheDir = $dir;
    }

    public $pluginsDirs;

    public function getPluginsDirs()
    {
        return $this->pluginsDirs;
    }

    public function setPluginsDirs($dirs)
    {
        $this->pluginsDirs = $dirs;
    }

    public function addPluginsDir($dir)
    {
        $this->pluginsDirs[] = $dir;
    }

    public $themeDir;

    public function getThemeDir()
    {
        return $this->themeDir;
    }

    public function setThemeDir($dir)
    {
        $this->themeDir = dir;
    }

    public $pluginLoader;

    public function getPluginLoader()
    {
        return $this->pluginLoader;
    }
    
    public $config;
    
    public function getConfig()
    {
        return $this->config;
    }

    public $environment;
    
    public function getEnvironment()
    {
        return $this->environment;
    }
    
    public function __construct($rootDir = null)
    {
        $this->config = new PieCrustConfiguration();
        $this->templateDirs = array();
        $this->pluginsDirs = array();
        $this->pluginLoader = new MockPluginLoader();
        $this->environment = new CachedEnvironment();
        $this->addFormatter('none', 'PassThroughFormatter');
        $this->addTemplateEngine('none', 'PassThroughTemplateEngine');

        if ($rootDir != null)
        {
            $this->rootDir = $rootDir;
            $this->pagesDir = $rootDir . '_content/pages/';
            $this->postsDir = $rootDir . '_content/posts/';
            $this->templateDirs[] = $rootDir . '_content/templates';
        }

        $this->environment->initialize($this);
    }

    public function addFormatter($name, $className)
    {
        $className = 'PieCrust\\Formatters\\' . $className;
        $formatter = new $className;
        $formatter->initialize($this);
        $this->pluginLoader->formatters[$name] = $formatter;
    }

    public function addTemplateEngine($name, $className)
    {
        $className = 'PieCrust\\TemplateEngines\\' . $className;
        $engine = new $className;
        $engine->initialize($this);
        $this->pluginLoader->templateEngines[$name] = $engine;
    }
}
