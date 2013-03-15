<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Server\PieCrustServer;


class ServeCommand extends ChefCommand
{
    public function getName()
    {
        return 'serve';
    }
    
    public function setupParser(Console_CommandLine $serverParser, IPieCrust $pieCrust)
    {
        $serverParser->description = 'Serves your PieCrust website using a tiny development web server.';
        $serverParser->addOption('run_browser', array(
            'short_name'  => '-n',
            'long_name'   => '--no-browser',
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
        $serverParser->addOption('address', array(
            'short_name'  => '-a',
            'long_name'   => '--address',
            'description' => "Sets the address for the server.",
            'default'     => 'localhost',
            'help_name'   => 'PORT'
        ));
        $serverParser->addOption('log_file', array(
            'short_name'  => '-l',
            'long_name'   => '--log',
            'description' => "The file to which the server should log its activity.",
            'default'     => null,
            'help_name'   => 'LOG_FILE'
        ));
        $serverParser->addOption('config_variant', array(
            'short_name'  => '-c',
            'long_name'   => '--config',
            'description' => "Apply the configuration settings from the named baker configuration variant.",
            'default'     => null,
            'help_name'   => 'VARIANT'
        ));

        // Deprecated stuff.
        $serverParser->addOption('run_browser_old', array(
            'long_name'   => '--nobrowser',
            'description' => "Deprecated. Same as `--no-browser`.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();

        // Warn about deprecated stuff.
        if ($result->command->options['run_browser_old'])
        {
            $context->getLog()->warning("The `--nobrowser` option has been renamed to `--no-browser`.");
            $result->command->options['run_browser'] = false;
        }

        $rootDir = $context->getApp()->getRootDir();
        $port = intval($result->command->options['port']);
        $address = $result->command->options['address'];
        $runBrowser = $result->command->options['run_browser'];
        $logFile = $result->command->options['log_file'];
        $configVariant = $result->command->options['config_variant'];
        $nocache = $result->options['no_cache'];
        $debug = $result->options['debug'];

        // Start serving!
        $server = new PieCrustServer($rootDir,
            array(
                'port' => $port,
                'address' => $address,
                'log_file' => $logFile,
                'debug' => $debug,
                'cache' => !$nocache,
                'config_variant' => $configVariant
            ),
            $context->getLog());
        $server->run(array(
            'list_directories' => false,
            'run_browser' => $runBrowser
        ));
    }
}
