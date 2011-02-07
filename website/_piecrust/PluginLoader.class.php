<?php

class PluginLoader
{
    protected $interfaceName;
    protected $baseDir;
    protected $plugins;
    protected $pluginSortFunc;
    
    public function __construct($interfaceName, $baseDir, $pluginSortFunc = null)
    {
        $this->interfaceName = $interfaceName;
        $this->baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->pluginSortFunc = $pluginSortFunc;
    }
    
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
        if ($handle = opendir($this->baseDir))
        {
            while (false !== ($file = readdir($handle)))
            {
                if ($file == '.' || $file == '..')
                    continue;
                $fileInfo = pathinfo($file);
                if ($fileInfo['extension'] != 'php')
                    continue;
                
                include_once($this->baseDir . $file);
                $className = $fileInfo['filename'];
                if (substr($className, -6) === ".class")
                {
                    $className = substr($className, 0, strlen($className) - 6);
                }
                array_push($classNames, $className);
            }
        }
        closedir($handle);
        
        $plugins = array();
        foreach ($classNames as $className)
        {
            if (class_exists($className))
            {
                $reflector = new ReflectionClass($className);
                if (!$reflector->implementsInterface($this->interfaceName))
                    throw new PieCrustException('Class "' . $className . '" doesn\'t implement interface "' . $this->interfaceName . '".');
                $plugin = $reflector->newInstance();
                array_push($plugins, $plugin);
            }
            else
            {
                throw new PieCrustException('Class "' . $className . '" does not exist but there\'s a file with that name in the "' . basename($this->baseDir) . '" plugin directory.');
            }
        }
        return $plugins;
    }
}
