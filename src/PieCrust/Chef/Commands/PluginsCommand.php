<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Repositories\BitBucketRepository;
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
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
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
        $logger = $context->getLog();

        $pluginLoader = $app->getPluginLoader();
        foreach ($pluginLoader->getPlugins() as $plugin)
        {
            $msg = $plugin->getName();
            if ($context->isDebuggingEnabled())
            {
                $ref = new \ReflectionClass($plugin);
                $msg .= str_repeat(" ", max(0, 15 - strlen($msg))) . " [" . $ref->getfileName() . "]";
            }
            $logger->info($msg);
        }
    }

    protected function findPlugins(ChefContext $context)
    {
        $app = $context->getApp();
        $logger = $context->getLog();
        $result = $context->getResult();

        $sources = $this->getSources($app);
        $query = $result->command->command->args['query'];
        $plugins = $this->getPluginMetadata($app, $sources, $query, false);
        foreach ($plugins as $plugin)
        {
            $logger->info("{$plugin['name']} : {$plugin['description']}");
        }
    }

    protected function installPlugins(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();
        $result = $context->getResult();

        $sources = $this->getSources($app);
        $pluginName = $result->command->command->args['name'];
        $plugins = $this->getPluginMetadata($app, $sources, $pluginName, true);
        if (count($plugins) != 1)
            throw new PieCrustException("Can't find a single plugin named: {$pluginName}");

        $plugin = $plugins[0];
        $log->debug("Installing '{$plugin['name']}' from: {$plugin['source']}");
        $className = $plugin['repository_class'];
        $repository = new $className;
        $context = new PluginInstallContext($app, $log);
        $repository->installPlugin($plugin, $context);
        $log->info("Plugin {$plugin['name']} is now installed.");
    }

    protected function getSources(IPieCrust $pieCrust)
    {
        $sources = $pieCrust->getConfig()->getValue('site/plugins_sources');
        if (!$sources)
            $sources = array();
        $sources[] = PieCrustDefaults::DEFAULT_PLUGIN_SOURCE;
        return $sources;
    }

    protected function getPluginMetadata(IPieCrust $app, $sources, $pattern, $exact)
    {
        $metadata = array();
        foreach ($sources as $source)
        {
            $repository = PieCrustHelper::getRepository($app, $source);
            $repostioryClass = get_class($repository);

            $plugins = $repository->getPlugins($source);
            foreach ($plugins as $plugin)
            {
                // Make sure we have the required properties.
                if (!isset($plugin['name']))
                    $plugin['name'] = 'UNNAMED PLUGIN';
                if (!isset($plugin['description']))
                    $plugin['description'] = 'NO DESCRIPTION AVAILABLE.';
                $plugin['repository_class'] = $repostioryClass;

                // Find if the plugin matches the query.
                $matches = false;
                if ($exact)
                {
                    $matches = strcasecmp($plugin['name'], $pattern) == 0;
                }
                else
                {
                    $matchesName = (stristr($plugin['name'], $pattern) != false);
                    $matchesDescription = (stristr($plugin['description'], $pattern) != false);
                    $matches = ($matchesName or $matchesDescription);
                }

                if ($matches)
                {
                    // Get the plugin, and exit if we only want one.
                    $metadata[] = $plugin;
                    if ($exact)
                        break;
                }
            }
        }
        return $metadata;
    }
}

