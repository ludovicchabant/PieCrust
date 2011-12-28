<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class RootCommand extends ChefCommand
{
    public function getName()
    {
        return 'root';
    }
    
    public function setupParser(Console_CommandLine $rootParser)
    {
        $rootParser->description = "Get the root directory of the current website.";
    }

    public function run(IPieCrust $pieCrust, Console_CommandLine_Result $result)
    {
        echo rtrim($pieCrust->getRootDir(), '/\\') . PHP_EOL;
    }
}

