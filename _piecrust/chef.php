#!/usr/bin/php
<?php

require_once 'libs/console/CommandLine.php';


function _chef_add_common_command_options_and_args($parser)
{
    $parser->addOption('url_base', array(
        'short_name'  => '-u',
        'long_name'   => '--urlbase',
        'description' => "The base URL of the website.",
        'default'     => '/',
        'help_name'   => 'URL_BASE'
    ));
    $parser->addOption('templates_dir', array(
        'short_name'  => '-t',
        'long_name'   => '--templates',
        'description' => "An optional additional templates directory.",
        'help_name'   => 'TEMPLATES_DIR'
    ));
    $parser->addOption('pretty_urls', array(
        'long_name'   => '--prettyurls',
        'description' => "Overrides the 'site/pretty_urls' configuration setting.",
        'default'     => false,
        'action'      => 'StoreTrue',
        'help_name'   => 'PRETTY_URLS'
    ));
    $parser->addArgument('root', array(
        'description' => "The directory in which we'll find '_content' and other such directories.",
        'help_name'   => 'ROOT_DIR',
        'optional'    => false
    ));
}

// Set up the command line parser.
$parser = new Console_CommandLine(array(
    'description' => 'The PieCrust chef manages your website.',
    'version' => PieCrust::VERSION
));


// Baker command
$bakerParser = $parser->addCommand('bake', array(
    'description' => 'Bakes your PieCrust website into a bunch of static HTML files.'
));
$bakerParser->addOption('output', array(
    'short_name'  => '-o',
    'long_name'   => '--output',
    'description' => "The directory to put all the baked HTML files in.",
    'default'     => null,
    'help_name'   => 'OUTPUT_DIR'
));
$bakerParser->addOption('page', array(
    'short_name'  => '-p',
    'long_name'   => '--page',
    'description' => "The path to a specific page to bake instead of the whole website.",
    'default'     => null,
    'help_name'   => 'PAGE_PATH'
));
$bakerParser->addOption('force', array(
    'short_name'  => '-f',
    'long_name'   => '--force',
    'description' => "Force re-baking the entire website.",
    'default'     => false,
    'action'      => 'StoreTrue',
    'help_name'   => 'FORCE'
));
$bakerParser->addOption('file_urls', array(
    'long_name'   => '--fileurls',
    'description' => "Uses local file paths for URLs (for previewing website locally).",
    'default'     => false,
    'action'      => 'StoreTrue',
    'help_name'   => 'FILE_URLS'
));
$bakerParser->addOption('info_only', array(
    'long_name'   => '--info',
    'description' => "Prints only high-level information about what the baker will do.",
    'default'     => false,
    'action'      => 'StoreTrue',
    'help_name'   => 'INFO_ONLY'
));
_chef_add_common_command_options_and_args($bakerParser);

// Upload command
$uploadParser = $parser->addCommand('upload', array(
    'description' => "Uploads your PieCrust website to an given FTP server."
));
$uploadParser->addOption('remote_root', array(
    'short_name'  => '-r',
    'long_name'   => '--remote_root',
    'description' => "The root directory on the remote server.",
    'help_name'   => 'REMOTE_ROOT'
));
$uploadParser->addOption('passive', array(
    'short_name'  => '-p',
    'long_name'   => '--passive',
    'description' => "Uses passive mode to connect to the FTP server.",
    'action'      => 'StoreTrue',
    'help_name'   => 'PASSIVE'
));
$uploadParser->addOption('sync_mode', array(
    'short_name'  => '-s',
    'long_name'   => '--sync_mode',
    'default'     => 'none',
    'description' => "The sync mode for the FTP transfer (none [default], time, time_and_size)",
    'help_name'   => 'SYNC_MODE'
));
$uploadParser->addOption('simulate', array(
    'long_name'   => '--simulate',
    'default'     => false,
    'description' => "Don't actually transfer anything.",
    'action'      => 'StoreTrue'
));
$uploadParser->addArgument('root', array(
    'description' => "The local directory with your website (e.g. the output directory of your latest PieCrust bake.",
    'help_name'   => 'ROOT_DIR',
    'optional'    => false
));
$uploadParser->addArgument('server', array(
    'description' => "The FTP server to upload to.",
    'help_name'   => 'USER:PASSWORD@DOMAIN.TLD',
    'optional'    => false
));

// Server command
$serverParser = $parser->addCommand('serve', array(
    'description' => 'Serves your PieCrust website using a tiny development web server.'
));
$serverParser->addOption('run_browser', array(
    'short_name'  => '-n',
    'long_name'   => '--nobrowser',
    'description' => "Disables auto-running the default web browser when the server starts.",
    'default'     => true,
    'action'      => 'StoreFalse',
    'help_name'   => 'RUN_BROWSER'
));
$serverParser->addOption('port', array(
    'short_name'  => '-p',
    'long_name'   => '--port',
    'description' => "Sets the port for the server.",
    'default'     => 8080,
    'help_name'   => 'PORT'
));
$serverParser->addOption('autobake', array(
    'short_name'  => '-b',
    'long_name'   => '--autobake',
    'description' => 'Auto-bakes the website to the specified directory, and serve that directory instead of running PieCrust on-demand.',
    'default'     => null,
    'help_name'   => 'OUTPUT_PATH'
));
$serverParser->addOption('full_first_bake', array(
    'short_name'  => '-f',
    'long_name'   => '--forcefirstbake',
    'description' => 'When \'autobake\' is turned on, do a full first bake before running the server.',
    'default'     => false,
    'action'      => 'StoreTrue',
    'help_name'   => 'FORCE'
));
_chef_add_common_command_options_and_args($serverParser);


// Import command
$importParser = $parser->addCommand('import', array(
    'description' => 'Imports content from another CMS into PieCrust.'
));
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
_chef_add_common_command_options_and_args($importParser);


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


// Run the command.
if (!empty($result->command_name))
{
    require ('chef_' . $result->command_name . '.inc.php');
    _chef_run_command($parser, $result);
}
else
{
    $parser->displayUsage();
    die();
}
