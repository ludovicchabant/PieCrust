<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;

require_once 'Console/CommandLine.php';


/**
 * The interface for a PieCrust Chef command.
 */
interface IChefCommand
{
    /**
     * Gets the name of the command.
     */
    public function getName();
    
    /**
     * Returns whether this command supports the default options.
     */
    public function supportsDefaultOptions();
    
    /**
     * Creates the command's sub-parser.
     */
    public function setupParser(Console_CommandLine $parser);
    
    /**
     * Runs the command.
     */
    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result);
}
