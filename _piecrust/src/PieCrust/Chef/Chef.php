<?php

namespace PieCrust\Chef;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
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
        $parser->addOption('debug', array(
            'long_name'   => '--debug',
            'description' => "Show debug information.",
            'default'     => false,
            'help_name'   => 'DEBUG',
            'action'      => 'StoreTrue'
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
            self::displayDebugInformation($this, $e);
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
                        $this->parser->displayError(self::getErrorMessage($result, $e));
                        die();
                    }
                }
            }
        }
        
        $this->parser->displayUsage();
    }

    public static function getErrorMessage(Console_CommandLine_Result $result, Exception $e)
    {
        $message = $e->getMessage();
        if ($result->command->options['debug'])
        {
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Debug Information" . PHP_EOL;
            while ($e)
            {
                $message .= "-----------------" . PHP_EOL;
                $message .= $e->getTraceAsString();
                $message .= PHP_EOL;
                $e = $e->getPrevious();
            }
            $message .= "-----------------" . PHP_EOL;
        }
        return $message;
    }
}

