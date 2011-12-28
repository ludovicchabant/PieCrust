<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;

require_once 'Console/CommandLine.php';


/**
 * The interface for a PieCrust Chef command.
 */
abstract class ChefCommand
{
    /**
     * Gets the name of the command.
     */
    public abstract function getName();

    /**
     * Gets whether this command requires an actual website to run.
     */
    public function requiresWebsite()
    {
        return true;
    }
    
    /**
     * Creates the command's sub-parser.
     */
    public abstract function setupParser(Console_CommandLine $parser);
    
    /**
     * Runs the command.
     */
    public abstract function run(IPieCrust $pieCrust, Console_CommandLine_Result $result);
}
