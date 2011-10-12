<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IO\FileSystem;
use PieCrust\Server\PieCrustServer;


class ServeCommand implements IChefCommand
{
    public function getName()
    {
        return 'serve';
    }
    
    public function supportsDefaultOptions()
    {
        return true;
    }
    
    public function setupParser(Console_CommandLine $serverParser)
    {
        $serverParser->description = 'Serves your PieCrust website using a tiny development web server.';
        $serverParser->addOption('run_browser', array(
            'short_name'  => '-n',
            'long_name'   => '--nobrowser',
            'description' => "Disables auto-running the default web browser when the server starts.",
            'default'     => true,
            'action'      => 'StoreFalse',
            'help_name'   => 'RUN_BROWSER'
        ));
        $serverParser->addOption('port', array(
            'short_name'  => '-p',
            'long_name'   => '--port',
            'description' => "Sets the port for the server.",
            'default'     => 8080,
            'help_name'   => 'PORT'
        ));
        $serverParser->addOption('autobake', array(
            'short_name'  => '-b',
            'long_name'   => '--autobake',
            'description' => "Auto-bakes the website to the specified directory, and serve that directory instead of running PieCrust on-demand.",
            'default'     => null,
            'help_name'   => 'OUTPUT_PATH'
        ));
        $serverParser->addOption('full_first_bake', array(
            'short_name'  => '-f',
            'long_name'   => '--forcefirstbake',
            'description' => "When 'autobake' is turned on, do a full first bake before running the server.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'FORCE'
        ));
        $serverParser->addOption('log_file', array(
            'short_name'  => '-l',
            'long_name'   => '--log',
            'description' => "The file to which the server should log its activity.",
            'default'     => null,
            'help_name'   => 'LOG_FILE'
        ));
        $serverParser->addOption('log_console', array(
            'short_name'  => '-c',
            'long_name'   => '--console',
            'description' => "Specify whether StupidHttp should output stuff to the console.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'LOG'
        ));
    }

    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result)
    {
        // Validate arguments.
        $rootDir = FileSystem::getAbsolutePath($result->command->args['root']);
        if (!is_dir($rootDir))
        {
            $parser->displayError("No such root directory: " . $rootDir, 1);
            die();
        }
        $port = intval($result->command->options['port']);
        $autobakeDir = $result->command->options['autobake'];
        $fullFirstBake = $result->command->options['full_first_bake'];
        $templatesDir = $result->command->options['templates_dir'];
        $runBrowser = $result->command->options['run_browser'];
        $logFile = $result->command->options['log_file'];
        $logConsole = $result->command->options['log_console'];
        
        // Start serving!
        $server = new PieCrustServer($rootDir,
                                     array(
                                        'port' => $port,
                                        'templates_dir' => $templatesDir,
                                        'autobake' => (($autobakeDir != null) ? $autobakeDir : false),
                                        'full_first_bake' => $fullFirstBake,
                                        'log_file' => $logFile,
                                        'log_console' => $logConsole
                                     ));
        $server->run(array(
                           'list_directories' => false,
                           'run_browser' => $runBrowser
                           ));
    }
}
