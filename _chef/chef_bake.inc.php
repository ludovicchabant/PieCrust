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
    $pieCrust = new PieCrust(array('root' => $rootDir, 'url_base' => $result->command->options['url_base']));
    $baker = new PieCrustBaker($pieCrust, array('smart' => !$result->command->options['force']));
    if ($result->command->options['pretty_urls'])
    {
        $pieCrust->setConfigValue('site', 'pretty_urls', true);
    }
    if (isset($result->command->options['templates_dir']))
    {
        $pieCrust->getTemplateEngine()->addTemplatesPaths($result->command->options['templates_dir']);
    }
    if ($outputDir != null)
    {
        $baker->setBakeDir($outputDir);
    }
    $baker->bake();
}
