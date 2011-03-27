<?php

require_once 'PieCrustServer.class.php';


function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = realpath($result->command->args['root']);
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
    
    // Start serving!
    $server = new PieCrustServer($rootDir,
                                 array(
                                    'port' => $port,
                                    'templates_dir' => $templatesDir,
                                    'autobake' => (($autobakeDir != null) ? $autobakeDir : false),
                                    'full_first_bake' => $fullFirstBake
                                 ));
    $server->run(array(
                       'list_directories' => false,
                       'run_browser' => $runBrowser
                       ));
}
