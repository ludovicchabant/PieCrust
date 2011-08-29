<?php

require_once 'FileSystem.class.php';
require_once 'PieCrustBaker.class.php';


function _chef_run_command($parser, $result)
{
    // Validate arguments.
    $rootDir = FileSystem::getAbsolutePath($result->command->args['root']);
    if (!is_dir($rootDir))
    {
        $parser->displayError("No such root directory: " . $rootDir, 1);
        die();
    }
    $outputDir = $result->command->options['output'];
    if (!$outputDir)
    {
        $outputDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . PIECRUST_BAKE_DIR;
    }
    $urlBase = $result->command->options['url_base'];
    if ($result->command->options['file_urls'])
    {
        $urlBase = str_replace(DIRECTORY_SEPARATOR, '/', $outputDir);
    }
    
    // Start baking!
    PieCrust::setup('shell');
    $appParameters = array(
        'root' => $rootDir,
        'url_base' => $urlBase
    );
    $bakerParameters = array(
        'smart' => !$result->command->options['force'],
        'clean_cache' => $result->command->options['force'],
        'info_only' => $result->command->options['info_only']
    );
    $baker = new PieCrustBaker($appParameters, $bakerParameters);
    $baker->setBakeDir($outputDir);
    if ($result->command->options['pretty_urls'])
    {
        $baker->getApp()->setConfigValue('site', 'pretty_urls', true);
    }
    if (isset($result->command->options['templates_dir']))
    {
        $templatesDir = FileSystem::getAbsolutePath($result->command->options['templates_dir']);
        $baker->getApp()->addTemplatesDir($templatesDir);
    }
    $baker->bake();
}
