<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\IO\FileSystem;
use PieCrust\Interop\PieCrustImporter;
use PieCrust\Util\PathHelper;


class ImportCommand extends ChefCommand
{
    public function getName()
    {
        return 'import';
    }
    
    public function setupParser(Console_CommandLine $importParser)
    {
        $importParser->description = 'Imports content from another CMS into PieCrust.';
        $importParser->addOption('format', array(
            'short_name'  => '-f',
            'long_name'   => '--format',
            'description' => "The format of the source data to import.",
            'help_name'   => 'FORMAT'
        ));
        $importParser->addOption('source', array(
            'short_name'  => '-s',
            'long_name'   => '--source',
            'description' => 'The path or resource string for the source data, depending on the `format`.',
            'help_name'   => 'SOURCE'
        ));
    }
    
    public function run(IPieCrust $pieCrust, Console_CommandLine_Result $result)
    {
        // Validate arguments.
        $format = $result->command->options['format'];
        if (empty($format))
        {
            throw new PieCrustException("No format was specified.");
        }
        $source = $result->command->options['source'];
        if (empty($source))
        {
            throw new PieCrustException("No source was specified.");
        }
        
        // Start importing!
        $importer = new PieCrustImporter($pieCrust);
        $importer->import($format, $source);
    }
}
