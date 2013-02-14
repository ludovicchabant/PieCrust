<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;


class PrepareCommand extends ChefCommand
{
    public function getName()
    {
        return 'prepare';
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Helps with the creation of content in the website.";

        $extensions = $pieCrust->getEnvironment()->getCommandExtensions($this->getName());
        foreach ($extensions as $ext)
        {
            $extensionParser = $parser->addCommand($ext->getName());
            $ext->setupParser($extensionParser, $pieCrust);
        }
    }

    public function run(ChefContext $context)
    {
        $app = $context->getApp();
        $result = $context->getResult();
        $log = $context->getLog();

        $extensionName = $result->command->command_name;
        $extensions = $app->getEnvironment()->getCommandExtensions($this->getName());
        foreach ($extensions as $ext)
        {
            if ($ext->getName() == $extensionName)
            {
                $ext->run($context);
                return;
            }
        }
        throw new PieCrustException("No such extension for the `prepare` command: " . $extensionName);
    }
}

