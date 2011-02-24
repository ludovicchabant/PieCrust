#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../_piecrust/PieCrust.class.php';
require_once dirname(__FILE__) . '/../_piecrust/PieCrustImporter.class.php';

require_once 'Console/Color.php';
require_once 'Console/CommandLine.php';

// Set up the command line parser.
$parser = new Console_CommandLine(array(
	'description' => 'PieCrust importer, a command line utility to import content from other systems into PieCrust.',
	'version' => PieCrust::VERSION
));
$parser->addOption('root', array(
    'short_name'  => '-r',
    'long_name'   => '--root',
    'description' => "The directory in which we'll find '_content' and other such directories.",
	'default'	  => PIECRUST_ROOT_DIR,
    'help_name'   => 'ROOT_DIR'
));

// Parse the command line.
try
{
    $result = $parser->parse();
}
catch (Exception $exc)
{
    $parser->displayError($exc->getMessage());
	die();
}

// Validate arguments.
$rootDir = $result->options['root'];
if (!is_dir($rootDir))
{
    $parser->displayError("No such root directory: " . $rootDir, 1);
	die();
}

// Start importing!
PieCrust::setup();
$pieCrust = new PieCrust(array('root' => $rootDir));
$importer = new PieCrustImporter($pieCrust);
$importer->import();
