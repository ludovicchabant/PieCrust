<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Server\PieCrustServer;
use PieCrust\Util\PathHelper;


class ServeCommand extends ChefCommand
{
    public function getName()
    {
        return 'serve';
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

    public function run(IPieCrust $pieCrust, Console_CommandLine_Result $result)
    {
        $rootDir = $pieCrust->getRootDir();
        $port = intval($result->command->options['port']);
        $runBrowser = $result->command->options['run_browser'];
        $logFile = $result->command->options['log_file'];
        $logConsole = $result->command->options['log_console'];
        $debug = $result->command->options['debug'];

        // Start serving!
        $server = new PieCrustServer($rootDir,
                                     array(
                                        'port' => $port,
                                        'log_file' => $logFile,
                                        'log_console' => $logConsole,
                                        'debug' => $debug
                                     ));
        $server->run(array(
                           'list_directories' => false,
                           'run_browser' => $runBrowser
                           ));
    }
}
