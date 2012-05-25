<?php

namespace PieCrust\Plugins;

use \ReflectionClass;
use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * A class that loads PieCrust plugins.
 */
class PluginLoader
{
    protected $pieCrust;
    protected $plugins;
    protected $cachedComponents;

    /**
     * Builds a new instance of PluginLoader with the specified plugin 
     * directories.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->plugins = null;
        $this->cachedComponents = array();
    }

    /**
     * Gets the plugins found in the plugin directories.
     */
    public function getPlugins()
    {
        $this->ensureLoaded();
        return $this->plugins;
    }

    /**
     * Gets all the formatters from the loaded plugins.
     */
    public function getFormatters()
    {
        return $this->getPluginsComponents(
            'getFormatters',
            true,
            function ($p1, $p2)
            {
                if ($p1->getPriority() == $p2->getPriority())
                    return 0;
                return ($p1->getPriority() > $p2->getPriority()) ? -1 : 1;
            }
        );
    }

    /**
     * Gets all the template engines from the loaded plugins.
     */
    public function getTemplateEngines()
    {
        return $this->getPluginsComponents('getTemplateEngines', true);
    }

    /**
     * Gets all the processors from the loaded plugins.
     */
    public function getProcessors()
    {
        return $this->getPluginsComponents(
            'getProcessors',
            true,
            function ($p1, $p2)
            {
                if ($p1->getPriority() == $p2->getPriority())
                    return 0;
                return ($p1->getPriority() > $p2->getPriority()) ? -1 : 1;
            }
        );
    }

    /**
     * Gets all the importers from the loaded plugins.
     */
    public function getImporters()
    {
        return $this->getPluginsComponents('getImporters');
    }

    /**
     * Gets all the commands from the loaded plugins.
     */
    public function getCommands()
    {
        return $this->getPluginsComponents('getCommands');
    }

    /**
     * Gets all the Twig extensions from the loaded plugins.
     */
    public function getTwigExtensions()
    {
        return $this->getPluginsComponents('getTwigExtensions');
    }

    /**
     * Gets all the repository types from the loaded plugins.
     */
    public function getRepositories()
    {
        return $this->getPluginsComponents('getRepositories');
    }

    protected function ensureLoaded()
    {
        if ($this->plugins != null)
            return;

        // Always load the 'built-in' plugin first.
        $this->plugins = array(
            new BuiltinPlugin()
        );

        // Load custom plugins.
        foreach ($this->pieCrust->getPluginsDirs() as $dir)
        {
            // For each root directory, look for plugin directories inside.
            $pluginDirs = new FilesystemIterator($dir);
            foreach ($pluginDirs as $pluginDir)
            {
                if (!$pluginDir->isDir())
                    continue;

                $this->loadPlugin($pluginDir->getFilename(), $pluginDir->getPathname());
            }
        }

        // Initialize all the plugins.
        foreach ($this->plugins as $plugin)
        {
            $plugin->initialize($this->pieCrust);
        }
    }

    protected function loadPlugin($pluginName, $pluginDir)
    {
        // A plugin should have a '<PluginName>Plugin.php' file
        // with a similarly-named class in it located at the root.
        $pluginClassName = $pluginName . 'Plugin';
        $pluginFile = $pluginDir . DIRECTORY_SEPARATOR . $pluginClassName . '.php';
        if (!is_readable($pluginFile))
            throw new PieCrustException("No plugin class found for plugin '{$pluginName}'.");

        // It may also have a `libs` directory. If that's the case, add it to
        // the include paths.
        $libsDir = $pluginDir . DIRECTORY_SEPARATOR . 'libs';
        if (is_dir($libsDir))
            set_include_path(get_include_path() . PATH_SEPARATOR . $libsDir);

        // Add an instance of the plugin class to our plugins.
        require_once $pluginFile;
        if (!class_exists($pluginClassName))
            throw new PieCrustException("Class '{$pluginClassName}' doesn't exist in file '{$pluginFile}'.");
        $reflector = new ReflectionClass($pluginClassName);
        if (!$reflector->isSubClassOf("PieCrust\\PieCrustPlugin"))
            throw new PieCrustException("Class '{$pluginClassName}' doesn't implement interface 'PieCrust\\IPieCrustPlugin'.");
        $plugin = $reflector->newInstance();
        $this->plugins[] = $plugin;
    }

    protected function getPluginsComponents($getter, $initialize = false, $order = null)
    {
        if (isset($this->cachedComponents[$getter]))
            return $this->cachedComponents[$getter];

        $allComponents = array();

        foreach ($this->getPlugins() as $plugin)
        {
            $pluginComponents = $plugin->$getter();
            foreach ($pluginComponents as $comp)
            {
                if ($initialize)
                    $comp->initialize($this->pieCrust);
                $allComponents[] = $comp;
            }
        }

        if ($order != null)
            usort($allComponents, $order);

        $this->cachedComponents[$getter] = $allComponents;
        return $allComponents;
    }
}
 
