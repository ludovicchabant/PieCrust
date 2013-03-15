<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\Chef\ChefContext;


/**
 * The extension to a command, most of the time a sub-command.
 */
abstract class ChefCommandExtension
{
    /**
     * Gets the name of the extension.
     */
    public abstract function getName();

    /**
     * Extends or modifies the command's parser.
     */
    public abstract function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust);

    /**
     * Runs the command extension.
     */
    public abstract function run(ChefContext $context);
}

