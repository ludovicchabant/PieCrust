<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Repositories\PluginInstallContext;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;


class PluginsCommand extends ChefCommand
{
    public function getName()
    {
        return 'plugins';
    }
    
    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Gets the list of plugins currently loaded, or install new ones.";

        $listParser = $parser->addCommand('list', array(
            'description' => "Lists the plugins installed in the current website."
        ));

        $findParser = $parser->addCommand('find', array(
            'description' => "Finds plugins to install from the internet."
        ));
        $findParser->addArgument('query', array(
            'description' => "Filters the plugins matching the given query.",
            'help_name'   => 'PATTERN',
            'optional'    => true
        ));

        $installParser = $parser->addCommand('install', array(
            'description' => "Installs the given plugin from the internet."
        ));
        $installParser->addArgument('name', array(
            'description' => "The name of the plugin to install.",
            'help_name'   => 'NAME',
            'optional'    => false
        ));

        $updateParser = $parser->addCommand('update', array(
            'description' => "Updates a given plugin, or all installed plugins."
        ));
        $updateParser->addArgument('name', array(
            'description' => "The name of the plugin to update. If omitted, all plugins are updated.",
            'help_name'   => 'NAME',
            'optional'    => true
        ));
    }

    public function installPlugin($pluginName, ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();

        $sources = $this->getSources($app, $log);
        $plugins = $this->getPluginMetadata($app, $sources, $pluginName, true, $log);
        if (count($plugins) != 1)
            throw new PieCrustException("Can't find a single plugin named: {$pluginName}");

        $plugin = $plugins[0];
        $log->info($log->convertColors("%gGET%n %m{$plugin['source']}%n [{$plugin['name']}]"));
        $className = $plugin['repository_class'];
        $repository = new $className;
        $context = new PluginInstallContext($app, $log);
        $repository->installPlugin($plugin, $context);

        return $plugin;
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();

        $action = 'list';
        if ($result->command->command_name)
            $action = $result->command->command_name;
        $action .= 'Plugins';
        if (method_exists($this, $action))
        {
            return $this->$action($context);
        }

        throw new PieCrustException("Unknown action '{$action}'.");
    }

    protected function listPlugins(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();

        $pluginLoader = $app->getPluginLoader();
        foreach ($pluginLoader->getPlugins() as $plugin)
        {
            $msg = $plugin->getName();
            if ($context->isDebuggingEnabled())
            {
                $ref = new \ReflectionClass($plugin);
                $msg .= str_repeat(" ", max(0, 15 - strlen($msg))) . " [" . $ref->getfileName() . "]";
            }
            $log->info($msg);
        }
    }

    protected function findPlugins(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();
        $result = $context->getResult();

        $sources = $this->getSources($app, $log);
        $query = $result->command->command->args['query'];
        $plugins = $this->getPluginMetadata($app, $sources, $query, false, $log);
        foreach ($plugins as $plugin)
        {
            $log->info("{$plugin['name']} : {$plugin['description']}");
        }
    }

    protected function installPlugins(ChefContext $context)
    {
        $log = $context->getLog();
        $result = $context->getResult();
        $pluginName = $result->command->command->args['name'];
        $plugin = $this->installPlugin($pluginName, $context);
        $log->info("Plugin {$plugin['name']} is now installed.");
    }

    protected function updatePlugins(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();
        $result = $context->getResult();
        $pluginName = $result->command->command->args['name'];

        // Right now we do it the brute force way: update everything.
        // TODO: keep some metadata on the installed version so we don't overwrite with the exact same.
        $pluginLoader = $app->getPluginLoader();
        foreach ($pluginLoader->getPlugins() as $plugin)
        {
            $curName = $plugin->getName();
            if ($curName != '__builtin__' && ($curName == $pluginName || !$pluginName))
            {
                $log->info("Updating {$curName}...");

                // First, rename the existing directory.
                $pluginMeta = $pluginLoader->getPluginMeta($curName);
                $pluginDir = $pluginMeta->directory;
                $pluginDirBackup = $pluginDir . '__backup';
                if (!rename($pluginDir, $pluginDirBackup))
                    throw new PieCrustException("Can't rename plugin directory: {$pluginDir}");

                // Then, update.
                try
                {
                    $plugin = $this->installPlugin($curName, $context);
                }
                catch (\Exception $e)
                {
                    $log->debug("Error encountered, restoring backup directory.");
                    rename($pluginDirBackup, $pluginDir);
                    throw new PieCrustException("Error updating plugin '{$curName}'.", 0, $e);
                }

                // Last, cleanup backup directory.
                $log->debug("Cleaning up backup directory: {$pluginDirBackup}");
                PathHelper::deleteDirectoryContents($pluginDirBackup);
                rmdir($pluginDirBackup);
            }
        }
    }

    protected function getSources(IPieCrust $pieCrust, $log)
    {
        $sources = $pieCrust->getConfig()->getValue('site/plugins_sources');
        if ($log)
        {
            $log->debug("Got site plugin sources: ");
            foreach ($sources as $s)
            {
                $log->debug(" - " . $s);
            }
        }
        return $sources;
    }

    protected function getPluginMetadata(IPieCrust $app, $sources, $pattern, $exact, $log)
    {
        $metadata = array();
        foreach ($sources as $source)
        {
            $repository = PieCrustHelper::getRepository($app, $source);
            $repositoryClass = get_class($repository);

            if ($log)
            {
                $log->debug("Loading plugins metadata from: " . $source);
            }
            $plugins = $repository->getPlugins($source);
            foreach ($plugins as $plugin)
            {
                // Make sure we have the required properties.
                if (!isset($plugin['name']))
                    $plugin['name'] = 'UNNAMED PLUGIN';
                if (!isset($plugin['description']))
                    $plugin['description'] = 'NO DESCRIPTION AVAILABLE.';
                $plugin['repository_class'] = $repositoryClass;

                // Find if the plugin matches the query.
                $matches = true;
                if ($exact)
                {
                    $matches = strcasecmp($plugin['name'], $pattern) == 0;
                }
                elseif ($pattern)
                {
                    $matchesName = (stristr($plugin['name'], $pattern) != false);
                    $matchesDescription = (stristr($plugin['description'], $pattern) != false);
                    $matches = ($matchesName or $matchesDescription);
                }

                if ($matches)
                {
                    $metadata[] = $plugin;
                }
            }
        }
        return $metadata;
    }
}

