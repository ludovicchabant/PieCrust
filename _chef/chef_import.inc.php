<?php

require_once 'PieCrust.class.php';
require_once 'PieCrustImporter.class.php';


function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = $result->command->args['root'];
    if (!is_dir($rootDir))
    {
        $parser->displayError("No such root directory: " . $rootDir, 1);
        die();
    }
    $format = $result->command->options['format'];
    if (empty($format))
    {
        $parser->displayError("No format was specified.");
        die();
    }
    $source = $result->command->options['source'];
    if (empty($source))
    {
        $parser->displayError("No source was specified.");
        die();
    }
    
    // Start importing!
    PieCrust::setup('shell');
    $pieCrust = new PieCrust(array('root' => $rootDir));
    $importer = new PieCrustImporter($pieCrust);
    $importer->import($format, $source);
}
