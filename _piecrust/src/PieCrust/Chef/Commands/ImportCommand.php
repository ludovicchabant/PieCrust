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
            'description' => 'The path or resource string for the source data, depending on the `format`.',
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
        $helpParser->helpTopics['about-import'] = <<<EOT
The `import` command lets you import content from another CMS into PieCrust.

If format is `wordpress`:

 - The source must be a path to an XML file exported from the Wordpress dashboard,
   or a connection string to the MySQL database the blog is running on. That 
   connection string must be of the form:

     username:password@server/database_name

   A suffix of the form `/prefix` can also be specified if the tables in the 
   database don't have the default `wp_` prefix.

If the format is `jekyll`:

 - The source must be a path to the root of a Jekyll website.

EOT;
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
}
