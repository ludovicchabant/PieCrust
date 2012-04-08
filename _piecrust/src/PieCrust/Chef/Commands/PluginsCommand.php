<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PathHelper;


class PluginsCommand extends ChefCommand
{
    public function getName()
    {
        return 'plugins';
    }
    
    public function setupParser(Console_CommandLine $parser)
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
        $plugins = $this->getPluginMetadata(
            $sources, 
            $result->command->command->args['query'], 
            false
        );
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
        $plugins = $this->getPluginMetadata($sources, $pluginName, true);
        if (count($plugins) == 0)
            throw new PieCrustException("Can't find plugin: {$pluginName}");

        $plugin = $plugins[0];
        $log->info("Installing '{$plugin['name']}' from: {$plugin['source']}");
        $className = $plugin['repository_class'];
        $repository = new $className;
        $context = new \PieCrust\Plugins\Repositories\PluginInstallContext($app, $log);
        $repository->installPlugin($plugin, $context);
    }

    protected function getSources(IPieCrust $pieCrust)
    {
        $sources = $pieCrust->getConfig()->getValue('site/plugins_sources');
        if (!$sources)
            $sources = array();
        $sources[] = PieCrustDefaults::DEFAULT_PLUGIN_SOURCE;
        return $sources;
    }

    protected function getPluginMetadata($sources, $pattern, $exact)
    {
        $metadata = array();
        $repositories = array(
            new \PieCrust\Plugins\Repositories\BitBucketRepository()
        );
        if ($exact)
        {
            $pattern = strtolower($pattern);
        }
        else
        {
            $pattern = PathHelper::globToRegex($pattern) . 'i';
        }
        foreach ($sources as $source)
        {
            $repository = null;
            foreach ($repositories as $repo)
            {
                if ($repo->supportsSource($source))
                {
                    $repository = $repo;
                    break;
                }
            }
            if (!$repository)
                throw new PieCrustException("Can't find a repository handler for source: {$source}");

            $plugins = $repository->getPlugins($source);
            foreach ($plugins as $plugin)
            {
                $pluginName = $plugin['name'];
                if (($exact and strtolower($pluginName) == $pattern) or
                    (!$exact and preg_match($pattern, $pluginName)))
                {
                    $metadata[] = $plugin;
                    if ($exact)
                        break;
                }
            }
        }
        return $metadata;
    }
}

