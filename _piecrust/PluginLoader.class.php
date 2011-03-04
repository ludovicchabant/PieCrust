<?php

/**
 * A simple plugin loader class.
 *
 * Handles including and loading PHP files, instancing plugin classes,
 * and sorting them in custom priority orders.
 */
class PluginLoader
{
    protected $interfaceName;
    protected $baseDir;
    protected $plugins;
    protected $pluginSortFunc;
    
	/**
	 * Creates a new PluginLoader instance.
	 */
    public function __construct($interfaceName, $baseDir, $pluginSortFunc = null)
    {
        $this->interfaceName = $interfaceName;
        $this->baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->pluginSortFunc = $pluginSortFunc;
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
		$paths = new FilesystemIterator($this->baseDir);
		foreach ($paths as $p)
		{
			if (substr($p->getFilename(), -3) !== 'php')	// SplFileInfo::getExtension is only PHP 5.3.6+ 
			{												// so let's not use that just yet.
				continue;
			}
			
			include_once $p->getPathname();
			
			$className = substr($p->getFilename(), 0, strlen($p->getFilename()) - 4);
			if (substr($className, -6) === ".class")
			{
				$className = substr($className, 0, strlen($className) - 6);
			}
			$classNames[] = $className;
        }
        
        $plugins = array();
        foreach ($classNames as $className)
        {
            if (class_exists($className))
            {
                $reflector = new ReflectionClass($className);
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
