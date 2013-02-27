<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Chef\ChefContext;


class BakeCommand extends ChefCommand
{
    public function getName()
    {
        return 'bake';
    }
    
    public function setupParser(Console_CommandLine $bakerParser, IPieCrust $pieCrust)
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
        $bakerParser->addOption('portable_urls', array(
            'long_name'   => '--portable',
            'description' => "Uses relative paths for all URLs.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'PORTABLE'
        ));

        // Deprecated stuff.
        $bakerParser->addOption('root_url_old', array(
            'long_name'   => '--rooturl',
            'description' => "Deprecated. You can use a config variant instead."
        ));
        $bakerParser->addOption('pretty_urls_old', array(
            'long_name'   => '--prettyurls',
            'description' => "Deprecated. You can use a config variant instead.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $bakerParser->addOption('file_urls', array(
            'long_name'   => '--fileurls',
            'description' => "Deprecated. Same as `--portable`.",
            'default'     => false,
            'action'      => 'StoreTrue',
            'help_name'   => 'FILE_URLS'
        ));
    }

    public function run(ChefContext $context)
    {
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        $outputDir = $result->command->options['output'];

        // Warn about deprecated stuff.
        if ($result->command->options['file_urls'])
        {
            $context->getLog()->warning("The `--fileurls` option has been deprecated. Please use `--portable` instead.");
            $result->command->options['portable_urls'] = true;
        }
        if ($result->command->options['pretty_urls_old'])
        { 
            $context->getLog()->err("The `--prettyurls` option has been deprecated. Please use a config variant instead.");
            return;
        }
        if ($result->command->options['root_url_old'])
        { 
            $context->getLog()->err("The `--rooturl` option has been deprecated. Please use a config variant instead.");
            return;
        }

        // Set-up the app and the baker.
        $bakerParameters = array(
            'smart' => !$result->command->options['force'],
            'clean_cache' => $result->command->options['force'],
            'config_variant' => $result->command->options['config_variant']
        );
        $baker = new PieCrustBaker($pieCrust, $bakerParameters, $context->getLog());
        if ($outputDir)
        {
            $baker->setBakeDir($outputDir);
        }
        if ($result->command->options['portable_urls'])
        {
            $pieCrust->getConfig()->setValue('baker/portable_urls', true);
            // Also disable pretty URLs because it doesn't make much sense
            // when there's no web server handling default documents.
            $pieCrust->getConfig()->setValue('site/pretty_urls', false);
        }

        // Start baking!
        $baker->bake();
    }
}
