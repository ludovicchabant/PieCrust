<?php

namespace PieCrust\Plugins;

use \ReflectionClass;
use \FilesystemIterator;
use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * A class that loads PieCrust plugins.
 */
class PluginLoader
{
    protected $pieCrust;
    protected $plugins;
    protected $pluginMeta;
    protected $cachedComponents;

    /**
     * Builds a new instance of PluginLoader with the specified plugin 
     * directories.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->plugins = null;
        $this->pluginMeta = null;
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
     * Gets the metadata for the given plugin.
     */
    public function getPluginMeta($pluginName)
    {
        $this->ensureLoaded();
        if (!isset($this->pluginMeta[$pluginName]))
            throw new PieCrustException("No such plugin: {$pluginName}");
        return $this->pluginMeta[$pluginName];
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
     * Gets all the custom template data providers from the loaded plugins.
     */
    public function getDataProviders()
    {
        return $this->getPluginsComponents('getDataProviders');
    }

    /**
     * Gets all the custom file systems from the loaded plugins.
     */
    public function getFileSystems()
    {
        return $this->getPluginsComponents('getFileSystems');
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
        return $this->getPluginsComponents('getRepositories', true);
    }

    /**
     * Gets all the baking assistants from the loaded plugins.
     */
    public function getBakerAssistants()
    {
        return $this->getPluginsComponents('getBakerAssistants');
    }

    protected function ensureLoaded()
    {
        if ($this->plugins != null)
            return;

        // Always load the 'built-in' plugin first.
        $this->plugins = array(
            new BuiltinPlugin()
        );
        $this->pluginMeta = array(
            $this->plugins[0]->getName() => false
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

                if (strpos($pluginDir->getFilename(), '__backup') !== false)
                    throw new PieCrustException("Found temporary plugin backup '{$pluginDir->getPathname()}', probably from an interrupted plugins operation.");

                $this->loadPlugin($pluginDir->getPathname());
            }
        }

        // Initialize all the plugins.
        foreach ($this->plugins as $plugin)
        {
            $plugin->initialize($this->pieCrust);
        }
    }

    protected function loadPlugin($pluginDir)
    {
        // Find the main plugin class' source file.
        $pluginFile = null;
        $pluginClassName = null;
        $srcFiles = new FilesystemIterator($pluginDir);
        foreach ($srcFiles as $srcFile)
        {
            if (preg_match('/Plugin.php$/', $srcFile->getFilename()))
            {
                $pluginFile = $srcFile->getPathname();
                $pluginClassName = $srcFile->getBasename('.php');
                break;
            }
        }
        if (!$pluginClassName)
            throw new PieCrustException("No plugin file ('*Plugin.php') found in '{$pluginDir}'.");

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

        $pluginMeta = new PluginMetadata();
        $pluginMeta->directory = $pluginDir;
        $this->pluginMeta[$plugin->getName()] = $pluginMeta;
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
 
