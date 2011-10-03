<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\PieCrust;
use PieCrust\Interop\PieCrustImporter;


class ImportCommand implements IChefCommand
{
    public function getName()
    {
        return 'import';
    }
    
    public function supportsDefaultOptions()
    {
        return true;
    }
    
    public function setupParser(Console_CommandLine $importParser)
    {
        $importParser->description = 'Imports content from another CMS into PieCrust.';
        $importParser->addOption('format', array(
            'short_name'  => '-f',
            'long_name'   => '--format',
            'description' => 'The format of the source data to import.',
            'help_name'   => 'FORMAT'
        ));
        $importParser->addOption('source', array(
            'short_name'  => '-s',
            'long_name'   => '--source',
            'description' => 'The path or resource string for the source data.',
            'help_name'   => 'SOURCE'
        ));
    }
    
    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result)
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
}
