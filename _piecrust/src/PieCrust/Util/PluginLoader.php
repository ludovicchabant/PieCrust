<?php

namespace PieCrust\Util;

use PieCrust\PieCrustException;


/**
 * A simple plugin loader class.
 *
 * Handles including and loading PHP files, instancing plugin classes,
 * and sorting them in custom priority orders.
 */
class PluginLoader
{
    protected $interfaceName;
    protected $namespacePrefix;
    protected $baseDir;
    protected $plugins;
    protected $pluginSortFunc;
    protected $pluginFilterFunc;
    protected $skipFilenames;
    
    /**
     * Creates a new PluginLoader instance.
     */
    public function __construct($interfaceName, $baseDir, $pluginSortFunc = null, $pluginFilterFunc = null, $skipFilenames = null)
    {
        $this->interfaceName = $interfaceName;
        $this->namespacePrefix = '';
        $lastSlash = strrpos($interfaceName, '\\');
        if ($lastSlash !== false)
        {
            $this->namespacePrefix = substr($interfaceName, 0, $lastSlash + 1);
        }

        $this->baseDir = rtrim($baseDir, '/\\') . '/';
        $this->pluginSortFunc = $pluginSortFunc;
        $this->pluginFilterFunc = $pluginFilterFunc;
        $this->skipFilenames = $skipFilenames;
        if ($skipFilenames != null and !is_array($skipFilenames))
        {
            $this->skipFilenames = array($skipFilenames);
        }
    }
    
    /**
     * Lazy-loads all registered plugins and returns an instance of each,
     * sorted using the custom specified sort function (if any).
     */
    public function getPlugins()
    {
        if ($this->plugins == null)
        {
            $this->plugins = $this->loadPlugins();
            if ($this->pluginFilterFunc != null)
            {
                $this->plugins = array_filter($this->plugins, $this->pluginFilterFunc);
            }
            if ($this->pluginSortFunc != null)
            {
                usort($this->plugins, $this->pluginSortFunc);
            }
        }
        return $this->plugins;
    }
    
    protected function loadPlugins()
    {
        $classNames = array();
        $paths = new \FilesystemIterator($this->baseDir);
        foreach ($paths as $p)
        {
            if (substr($p->getFilename(), -4) !== '.php')    // SplFileInfo::getExtension is only PHP 5.3.6+ 
            {                                                // so let's not use that just yet.
                continue;
            }
            if ($this->skipFilenames && in_array($p->getFilename(), $this->skipFilenames))
            {
                continue;
            }
            
            require_once $p->getPathname();
            
            $className = substr($p->getFilename(), 0, strlen($p->getFilename()) - 4);
            $qualifiedClassName = $this->namespacePrefix . $className;
            if ($qualifiedClassName != $this->interfaceName)
            {
                $classNames[] = $qualifiedClassName;
            }
        }
        
        $plugins = array();
        foreach ($classNames as $className)
        {
            if (class_exists($className))
            {
                $reflector = new \ReflectionClass($className);
                if (!$reflector->implementsInterface($this->interfaceName))
                    throw new PieCrustException('Class "' . $className . '" doesn\'t implement interface "' . $this->interfaceName . '".');
                $plugin = $reflector->newInstance();
                $plugins[] = $plugin;
            }
            else
            {
                throw new PieCrustException('Class "' . $className . '" does not exist but there\'s a file with that name in the "' . basename($this->baseDir) . '" plugin directory.');
            }
        }
        return $plugins;
    }
}
