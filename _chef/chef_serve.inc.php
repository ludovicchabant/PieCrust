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
    
    // Start serving!
    $server = new ChefServer($rootDir);
    $server->run(array(
                       'list_directories' => false,
                       'run_browser' => $result->command->options['run_browser'],
                       'templates_dir' => $result->command->options['templates_dir']
                       ));
}
