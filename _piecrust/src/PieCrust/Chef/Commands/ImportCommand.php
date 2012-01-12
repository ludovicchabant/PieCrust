<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\Chef\ChefContext;
use PieCrust\Interop\PieCrustImporter;
use PieCrust\IO\FileSystem;
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


EOT;
    }
    
    public function run(ChefContext $context)
    {
        $result = $context->getResult();

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
        $importer = new PieCrustImporter($context->getApp(), $context->getLog());
        $importer->import($format, $source);
    }
}
