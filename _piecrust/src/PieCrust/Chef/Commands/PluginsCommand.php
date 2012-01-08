<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;


class PluginsCommand extends ChefCommand
{
    public function getName()
    {
        return 'plugins';
    }
    
    public function setupParser(Console_CommandLine $pluginsParser)
    {
        $pluginsParser->description = "Gets the list of plugins currently loaded.";
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
        $pluginLoader = $context->getApp()->getPluginLoader();
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
}

