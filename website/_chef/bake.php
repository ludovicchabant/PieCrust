#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../_piecrust/PieCrust.class.php';
require_once dirname(__FILE__) . '/../_piecrust/PieCrustBaker.class.php';

require_once 'Console/Color.php';
require_once 'Console/CommandLine.php';

// Set up the command line parser.
$parser = new Console_CommandLine(array(
	'description' => 'PieCrust baker, a command line utility that bakes your PieCrust website into a bunch of static HTML files.',
	'version' => PieCrust::VERSION
));
$parser->addOption('root', array(
    'short_name'  => '-r',
    'long_name'   => '--root',
    'description' => "The directory in which we'll find '_content' and other such directories.",
	'default'	  => PIECRUST_ROOT_DIR,
    'help_name'   => 'ROOT_DIR'
));
$parser->addOption('output', array(
    'short_name'  => '-o',
    'long_name'   => '--output',
    'description' => "The directory to put all the baked HTML files in.",
    'default'     => PIECRUST_ROOT_DIR,
	'help_name'   => 'OUTPUT_DIR'
));
$parser->addOption('url_base', array(
	'short_name'  => '-u',
    'long_name'   => '--urlbase',
    'description' => "The base URL for all links and references.",
    'default'     => '/',
	'help_name'   => 'URL_BASE'
));
$parser->addOption('templates_dir', array(
	'short_name'  => '-t',
    'long_name'   => '--templates',
    'description' => "An optional additional templates directory.",
	'help_name'   => 'TEMPLATES_DIR'
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
$outputDir = $result->options['output'];
if (!is_dir($outputDir) or !is_writable($outputDir))
{
    $parser->displayError("No such destination directory, or directory can't be written to: " . $outputDir, 1);
	die();
}

// Start baking!
PieCrust::setup();
$pieCrust = new PieCrust(array('root' => $rootDir, 'url_base' => $result->options['url_base']));
$baker = new PieCrustBaker($pieCrust);
if (isset($result->options['templates_dir']))
{
	$pieCrust->getTemplateEngine()->addTemplatesPaths($result->options['templates_dir']);
}
$baker->setBakeDir($outputDir);
$baker->bake();
