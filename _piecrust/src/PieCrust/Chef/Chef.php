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
        $parser->addOption('root_url', array(
            'short_name'  => '-r',
            'long_name'   => '--rooturl',
            'description' => "Overrides the 'site/root' configuration setting (root URL of the site).",
            'default'     => '/',
            'help_name'   => 'URL_BASE'
        ));
        $parser->addOption('pretty_urls', array(
            'long_name'   => '--prettyurls',
            'description' => "Overrides the 'site/pretty_urls' configuration setting (use 'pretty URLS').",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'PRETTY_URLS'
        ));
        $parser->addArgument('root', array(
            'description' => "The directory in which we'll find '_content' and other such directories.",
            'help_name'   => 'ROOT_DIR',
            'optional'    => false
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
