<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;


class RootCommand extends ChefCommand
{
    public function getName()
    {
        return 'root';
    }
    
    public function setupParser(Console_CommandLine $rootParser, IPieCrust $pieCrust)
    {
        $rootParser->description = "Gets the root directory of the current website.";
    }

    public function run(ChefContext $context)
    {
        // Don't use the context logger because we want this to be the "pure" value
        // so it can be re-used in other shell commands.
        echo rtrim($context->getApp()->getRootDir(), '/\\') . PHP_EOL;
    }
}

