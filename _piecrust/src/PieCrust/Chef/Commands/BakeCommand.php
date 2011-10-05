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
        $bakerParser->addOption('config_variant', array(
            'short_name'  => '-c',
            'long_name'   => '--config',
            'description' => "Apply the configuration settings from the named baker configuration variant.",
            'default'     => null,
            'help_name'   => 'VARIANT'
        ));
        $bakerParser->addOption('output', array(
            'short_name'  => '-o',
            'long_name'   => '--output',
            'description' => "The directory to put all the baked HTML files in (defaults to '_counter').",
            'default'     => null,
            'help_name'   => 'DIR'
        ));
        $bakerParser->addOption('force', array(
            'short_name'  => '-f',
            'long_name'   => '--force',
            'description' => "Force re-baking the entire website.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'FORCE'
        ));
        $bakerParser->addOption('root_url', array(
            'short_name'  => '-r',
            'long_name'   => '--rooturl',
            'description' => "Overrides the 'site/root' configuration setting (root URL of the site).",
            'default'     => null,
            'help_name'   => 'URL_BASE'
        ));
        $bakerParser->addOption('pretty_urls', array(
            'long_name'   => '--prettyurls',
            'description' => "Overrides the 'site/pretty_urls' configuration setting (URL format for links).",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'PRETTY_URLS'
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
            'info_only' => $result->command->options['info_only'],
            'config_variant' => $result->command->options['config_variant']
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
            $baker->getApp()->setConfigValue('baker', 'file_urls', true);
        }
        
        try
        {
            $baker->bake();
        }
        catch (Exception $e)
        {
            $parser->displayError($e->getMessage());
            die();
        }
    }
}
