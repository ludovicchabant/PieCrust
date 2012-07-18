<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\Chef\ChefContext;


class ShowConfigCommand extends ChefCommand
{
    public function getName()
    {
        return 'showconfig';
    }
    
    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Prints part of, or the entirety of, the website's configuration.";
        $parser->addArgument('path', array(
            'description' => "The path to a config section or value.",
            'help_name'   => 'PATH',
            'optional'    => true
        ));
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        $path = $result->command->args['path'];
        if ($path)
        {
            $configToShow = $pieCrust->getConfig()->getValue($path);
        }
        else
        {
            $configToShow = $pieCrust->getConfig()->get();
        }

        if ($configToShow)
        {
            if (is_array($configToShow))
            {
                $this->printConfig($configToShow, $logger);
            }
            else
            {
                $logger->info($configToShow);
            }
        }
    }

    protected function printConfig($config, $logger, $indent = '')
    {
        foreach ($config as $key => $val)
        {
            if (is_array($val) or is_object($val))
            {
                $logger->info("{$indent}{$key}:");
                $this->printConfig($val, $logger, $indent . '  ');
            }
            else
            {
                $logger->info("{$indent}{$key}: {$val}");
            }
        }
    }
}

