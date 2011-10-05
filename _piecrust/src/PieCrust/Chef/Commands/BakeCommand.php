<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IO\FileSystem;
use PieCrust\Baker\PieCrustBaker;


class BakeCommand implements IChefCommand
{
    public function getName()
    {
        return 'bake';
    }
    
    public function supportsDefaultOptions()
    {
        return true;
    }
    
    public function setupParser(Console_CommandLine $bakerParser)
    {
        $bakerParser->description = 'Bakes your PieCrust website into a bunch of static HTML files.';
        $bakerParser->addOption('output', array(
            'short_name'  => '-o',
            'long_name'   => '--output',
            'description' => "The directory to put all the baked HTML files in.",
            'default'     => null,
            'help_name'   => 'OUTPUT_DIR'
        ));
        $bakerParser->addOption('page', array(
            'short_name'  => '-p',
            'long_name'   => '--page',
            'description' => "The path to a specific page to bake instead of the whole website.",
            'default'     => null,
            'help_name'   => 'PAGE_PATH'
        ));
        $bakerParser->addOption('force', array(
            'short_name'  => '-f',
            'long_name'   => '--force',
            'description' => "Force re-baking the entire website.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'FORCE'
        ));
        $bakerParser->addOption('file_urls', array(
            'long_name'   => '--fileurls',
            'description' => "Uses local file paths for URLs (for previewing website locally).",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'FILE_URLS'
        ));
        $bakerParser->addOption('info_only', array(
            'long_name'   => '--info',
            'description' => "Prints only high-level information about what the baker will do.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'INFO_ONLY'
        ));
    }

    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result)
    {
        // Validate arguments.
        $rootDir = FileSystem::getAbsolutePath($result->command->args['root']);
        if (!is_dir($rootDir))
        {
            $parser->displayError("No such root directory: " . $rootDir, 1);
            die();
        }
        $outputDir = $result->command->options['output'];
        
        // Start baking!
        $appParameters = array('root' => $rootDir);
        $bakerParameters = array(
            'smart' => !$result->command->options['force'],
            'clean_cache' => $result->command->options['force'],
            'info_only' => $result->command->options['info_only']
        );
        $baker = new PieCrustBaker($appParameters, $bakerParameters);
        if ($outputDir)
        {
            $baker->setBakeDir($outputDir);
        }
        if ($result->command->options['pretty_urls'])
        {
            $baker->getApp()->setConfigValue('site', 'pretty_urls', true);
        }
        if ($result->command->options['root_url'])
        {
            $baker->getApp()->setConfigValue('site', 'root', $result->command->options['root_url']);
        }
        if ($result->command->options['file_urls'])
        {
            $baker->getApp()->setConfigValue('site', 'root', str_replace(DIRECTORY_SEPARATOR, '/', $baker->getBakeDir()));
        }
        
        try
        {
            $baker->bake();
        }
        catch (Exception $e)
        {
            echo 'ERROR: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
        }
    }
}
