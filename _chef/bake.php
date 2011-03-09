#!/usr/bin/php
<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrustBaker.class.php';

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
    'default'     => null,
	'help_name'   => 'OUTPUT_DIR'
));
$parser->addOption('host', array(
	'short_name'  => '-h',
    'long_name'   => '--host',
    'description' => "The host name of the website.",
    'default'     => '/',
	'help_name'   => 'HOST'
));
$parser->addOption('url_base', array(
	'short_name'  => '-u',
    'long_name'   => '--urlbase',
    'description' => "The base URL of the website, if not at the root of the host.",
    'default'     => '/',
	'help_name'   => 'URL_BASE'
));
$parser->addOption('templates_dir', array(
	'short_name'  => '-t',
    'long_name'   => '--templates',
    'description' => "An optional additional templates directory.",
	'help_name'   => 'TEMPLATES_DIR'
));
$parser->addOption('page', array(
	'short_name'  => '-p',
	'long_name'   => '--page',
	'description' => "The path to a specific page to bake instead of the whole website.",
	'default'     => null,
	'help_name'   => 'PAGE_PATH'
));
$parser->addOption('force', array(
	'short_name'  => '-f',
	'long_name'   => '--force',
	'description' => "Force re-baking the entire website.",
	'default'     => false,
	'action'	  => 'StoreTrue',
	'help_name'   => 'FORCE'
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
if ($outputDir == null) $outputDir = $rootDir;
if (!is_dir($outputDir) or !is_writable($outputDir))
{
    $parser->displayError("No such destination directory, or directory can't be written to: " . $outputDir, 1);
	die();
}

// Start baking!
PieCrust::setup('shell');
$pieCrust = new PieCrust(array('root' => $rootDir, 'host' => $result->options['host'], 'url_base' => $result->options['url_base']));
$baker = new PieCrustBaker($pieCrust, array('smart' => !$result->options['force']));
if (isset($result->options['templates_dir']))
{
	$pieCrust->getTemplateEngine()->addTemplatesPaths($result->options['templates_dir']);
}
$baker->setBakeDir($outputDir);
if ($result->options['page'] == null)
{
	$baker->bake();
}
else
{
	if ($baker->bakePage($result->options['page']) === false)
	{
		echo "Page " . $result->options['page'] . " was not baked.\n";
	}
}
