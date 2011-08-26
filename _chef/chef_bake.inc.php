<?php

require_once 'PieCrustBaker.class.php';


function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = realpath($result->command->args['root']);
    if (!is_dir($rootDir))
    {
        $parser->displayError("No such root directory: " . $rootDir, 1);
        die();
    }
    $outputDir = $result->command->options['output'];
    
    // Start baking!
    PieCrust::setup('shell');
    $appParameters = array('root' => $rootDir, 'url_base' => $result->command->options['url_base']);
    $bakerParameters = array('smart' => !$result->command->options['force'], 'clean_cache' => $result->command->options['force']);
    $baker = new PieCrustBaker($appParameters, $bakerParameters);
    if ($result->command->options['pretty_urls'])
    {
        $baker->getApp()->setConfigValue('site', 'pretty_urls', true);
    }
    if (isset($result->command->options['templates_dir']))
    {
        $baker->getApp()->addTemplatesDir($result->command->options['templates_dir']);
    }
    if ($outputDir != null)
    {
        $baker->setBakeDir($outputDir);
    }
    $baker->bake();
}
