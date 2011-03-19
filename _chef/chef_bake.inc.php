<?php

require_once 'PieCrustBaker.class.php';


function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = $result->command->args['root'];
    if (!is_dir($rootDir))
    {
        $parser->displayError("No such root directory: " . $rootDir, 1);
        die();
    }
    $outputDir = $result->command->options['output'];
    if ($outputDir == null) $outputDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . '_counter';
    if (!is_dir($outputDir) or !is_writable($outputDir))
    {
        $parser->displayError("No such destination directory, or directory can't be written to: " . $outputDir, 1);
        die();
    }
    
    // Start baking!
    PieCrust::setup('shell');
    $pieCrust = new PieCrust(array('root' => $rootDir, 'url_base' => $result->options['url_base']));
    $baker = new PieCrustBaker($pieCrust, array('smart' => !$result->command->options['force']));
    if ($result->options['pretty_urls'])
    {
        $pieCrust->setConfigValue('site', 'pretty_urls', true);
    }
    if (isset($result->options['templates_dir']))
    {
        $pieCrust->getTemplateEngine()->addTemplatesPaths($result->options['templates_dir']);
    }
    $baker->setBakeDir($outputDir);
    if ($result->command->options['page'] == null)
    {
        $baker->bake();
    }
    else
    {
        if ($baker->bakePage($result->command->options['page']) === false)
        {
            echo "Page " . $result->command->options['page'] . " was not baked.\n";
        }
    }
}
