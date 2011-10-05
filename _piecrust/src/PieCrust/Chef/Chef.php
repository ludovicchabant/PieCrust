<?php

namespace PieCrust\Chef;

use \Exception;
use \Console_CommandLine;
use PieCrust\PieCrust;
use PieCrust\Util\PluginLoader;

require_once 'Console/CommandLine.php';


/**
 * The PieCrust Chef application.
 */
class Chef
{
    protected $parser;
    protected $commandLoader;
    
    public function __construct()
    {
        // Set up the command line parser.
        $this->parser = new Console_CommandLine(array(
            'name' => 'chef',
            'description' => 'The PieCrust chef manages your website.',
            'version' => PieCrust::VERSION
        ));
        
        $this->commandLoader = new PluginLoader(
            'PieCrust\\Chef\\Commands\\IChefCommand',
            PieCrust::APP_DIR . '/Chef/Commands');
        
        foreach ($this->commandLoader->getPlugins() as $command)
        {
            $commandParser = $this->parser->addCommand($command->getName());
            $command->setupParser($commandParser);
            if ($command->supportsDefaultOptions())
            {
                $this->addCommonOptionsAndArguments($commandParser);
            }
        }
    }
    
    protected function addCommonOptionsAndArguments(Console_CommandLine $parser)
    {
        $parser->addArgument('root', array(
            'description' => "The directory in which we'll find '_content' and other such directories (defaults to current directory).",
            'help_name'   => 'ROOT_DIR',
            'default'     => getcwd(),
            'optional'    => true
        ));
    }
    
    public function run($userArgc = null, $userArgv = null)
    {
        // Parse the command line.
        try
        {
            $result = $this->parser->parse($userArgc, $userArgv);
        }
        catch (Exception $e)
        {
            $this->parser->displayError($e->getMessage());
            die();
        }
        
        // Run the command.
        if (!empty($result->command_name))
        {
            foreach ($this->commandLoader->getPlugins() as $command)
            {
                if ($command->getName() == $result->command_name)
                {
                    try
                    {
                        $command->run($this->parser, $result);
                        return;
                    }
                    catch (Exception $e)
                    {
                        $this->parser->displayError($e->getMessage());
                        die();
                    }
                }
            }
        }
        
        $this->parser->displayUsage();
    }
}
