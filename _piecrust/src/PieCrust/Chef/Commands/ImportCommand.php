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
            'optional'    => false
        ));
        $importParser->addArgument('source', array(
            'description' => 'The path or resource string for the source data, depending on the `format`.',
            'optional'    => false
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
        $format = $result->command->args['format'];
        if (empty($format))
        {
            throw new PieCrustException("No format was specified.");
        }
        $source = $result->command->args['source'];
        if (empty($source))
        {
            throw new PieCrustException("No source was specified.");
        }
        
        // Start importing!
        $importer = new PieCrustImporter($context->getApp(), $context->getLog());
        $importer->import($format, $source);
    }
}
