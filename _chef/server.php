#!/usr/bin/php
<?php

error_reporting(E_ALL);

require 'ChefServer.class.php';

require_once 'Console/CommandLine.php';


// Set up the command line parser.
$parser = new Console_CommandLine(array(
    'description' => 'Chef HTTP server, a web server that lets you easily preview and author your PieCrust website.',
    'version' => PieCrust::VERSION
));
$parser->addOption('root', array(
    'short_name'  => '-r',
    'long_name'   => '--root',
    'description' => "The directory in which we'll find '_content' and other such directories.",
    'default'     => PIECRUST_ROOT_DIR,
    'help_name'   => 'ROOT_DIR'
));
$parser->addOption('run_browser', array(
    'short_name'  => '-n',
    'long_name'   => '--nobrowser',
    'description' => "Disables auto-running the default web browser when the server starts.",
    'default'     => true,
    'action'      => 'StoreFalse',
    'help_name'   => 'RUN_BROWSER'
));
$parser->addOption('templates_dir', array(
    'short_name'  => '-t',
    'long_name'   => '--templates',
    'description' => "An optional additional templates directory.",
    'help_name'   => 'TEMPLATES_DIR'
));
$parser->addOption('pretty_urls', array(
    'short_name'  => '-r',
    'long_name'   => '--prettyurls',
    'description' => "Enables pretty URLs for the baking (overrides the 'site/pretty_urls' configuration).",
    'default'     => false,
    'action'      => 'StoreTrue',
    'help_name'   => 'PRETTY_URLS'
));

// Parse the command line.
try
{
    $result = $parser->parse();
}
catch (Exception $exc)
{
    $parser->displayError($exc->getMessage());
    die();
}

// Validate arguments.
$rootDir = $result->options['root'];
if (!is_dir($rootDir))
{
    $parser->displayError("No such root directory: " . $rootDir, 1);
    die();
}

try
{
    $server = new ChefServer($rootDir);
    $server->run(array(
                       'run_browser' => $result->options['run_browser'],
                       'templates_dir' => $result->options['templates_dir']
                       ));
}
catch (Exception $e)
{
    echo $e;
    echo PHP_EOL;
}
