<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Interop\PieCrustImporter;


class ImportCommand extends ChefCommand
{
    public function getName()
    {
        return 'import';
    }
    
    public function setupParser(Console_CommandLine $importParser)
    {
        $importParser->description = 'Imports content from another CMS into PieCrust.';
        $importParser->addArgument('format', array(
            'description' => "The format of the source data to import.",
            'help_name'   => 'FORMAT',
            'optional'    => true
        ));
        $importParser->addArgument('source', array(
            'description' => 'The path or resource string for the source data, depending on the `format`. For more information on formats and sources, type `chef help about-import`.',
            'help_name'   => 'SOURCE',
            'optional'    => true
        ));
        $importParser->addOption('legacy_format', array(
            'description' => "Old way to specify the format. Don't use.",
            'short_name'  => '-f',
            'long_name'   => '--format',
            'help_name'   => 'FORMAT'
        ));
        $importParser->addOption('legacy_source', array(
            'description' => "Old way to specify the source. Don't use.",
            'short_name'  => '-s',
            'long_name'   => '--source',
            'help_name'   => 'SOURCE'
        ));

        $helpParser = $importParser->parent->commands['help'];
        $helpParser->helpTopics['about-import'] = array(
            '\PieCrust\Chef\Commands\ImportCommand',
            'aboutImportHelpTopic'
        );
    }
    
    public function run(ChefContext $context)
    {
        $result = $context->getResult();

        // Validate arguments.
        $format = $result->command->args['format'];
        $source = $result->command->args['source'];
        if (!$format or !$source)
        {
            // Warning for the old syntax.
            throw new PieCrustException("The syntax for this command has changed: specify the format and the source as arguments. See `chef import -h` for help.");
        }
        
        // Start importing!
        $importer = new PieCrustImporter($context->getApp(), $context->getLog());
        $importer->import($format, $source);
    }

    public static function aboutImportHelpTopic(ChefContext $context)
    {
        $importers = $context->getApp()->getPluginLoader()->getImporters();

        echo "The `import` command lets you import content from another CMS into PieCrust.\n";
        echo "\n";
        echo "Available formats:\n";
        echo "\n";

        foreach ($importers as $importer)
        {
            echo "`{$importer->getName()}`: " .
                wordwrap($importer->getDescription(), 70, "\n  ") .
                "\n";
            echo "\n";
            echo "  - " . 
                wordwrap($importer->getHelpTopic(), 70, "\n    ") .
                "\n\n";
        }
    }
}
