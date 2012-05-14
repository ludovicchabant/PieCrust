<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\PieCrustDefaults;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PathHelper;

require_once 'sfYaml/lib/sfYamlDumper.php';


class InitCommand extends ChefCommand
{
    protected $log;

    public function getName()
    {
        return 'init';
    }

    public function requiresWebsite()
    {
        return false;
    }
    
    public function setupParser(Console_CommandLine $initParser, IPieCrust $pieCrust)
    {
        $initParser->description = "Creates a new empty PieCrust website.";
        $initParser->addArgument('destination', array(
            'description' => "The destination directory in which to create the website.",
            'help_name'   => 'DESTINATION',
            'optional'    => true,
            'default'     => null
        ));
        $initParser->addOption('apache', array(
            'long_name'   => '--apache',
            'description' => "Adds the files needed to run or preview the website using Apache.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $initParser->addOption('iis', array(
            'long_name'   => '--iis',
            'description' => "Adds the files needed to run or preview the website using IIS.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
    }
    
    public function run(ChefContext $context)
    {
        $this->log = $context->getLog();
        $result = $context->getResult();

        $rootDir = $result->command->args['destination'];
        if (!$rootDir)
            $rootDir = getcwd();

        $apache = $result->command->options['apache'];
        $iis = $result->command->options['iis'];
        $this->initializeWebsite($rootDir, array('apache' => $apache, 'iis' => $iis));
    }

    protected function initializeWebsite($rootDir, $options = array())
    {
        // Validate the options.
        $options = array_merge(
            array(
                'apache' => false,
                'iis' => false
            ),
            $options
        );

        // Make sure the root directory exists.
        $rootDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($rootDir))
        {
            $this->createDirectory($rootDir, '');
        }

        // Create the directory structure.
        $this->createDirectory($rootDir, PieCrustDefaults::CACHE_DIR, true);
        $this->createDirectory($rootDir, PieCrustDefaults::CONTENT_DIR);
        $this->createDirectory($rootDir, PieCrustDefaults::CONTENT_PAGES_DIR);
        $this->createDirectory($rootDir, PieCrustDefaults::CONTENT_POSTS_DIR);
        $this->createDirectory($rootDir, PieCrustDefaults::CONTENT_TEMPLATES_DIR);
        
        // Create the basic files.
        $this->createYamlFile($rootDir, PieCrustDefaults::CONFIG_PATH, array(
            'site' => array(
                'title' => 'My New Website',
                'description' => 'A website recently generated with PieCrust.',
                'author' => get_current_user(),
                'pretty_urls' => false
            ),
            'smartypants' => array(
                'enable' => true
            )
        ));
        $this->createSystemFile('default_template.html', $rootDir, PieCrustDefaults::CONTENT_TEMPLATES_DIR . PieCrustDefaults::DEFAULT_PAGE_TEMPLATE_NAME . '.html');
        $this->createSystemFile('default_index.html', $rootDir, PieCrustDefaults::CONTENT_PAGES_DIR . PieCrustDefaults::INDEX_PAGE_NAME . '.html');

        if ($options['apache'])
        {
            $this->createSystemFile('htaccess', $rootDir, '.htaccess');
        }
        if ($options['iis'])
        {
            $this->createSystemFile('web.config', $rootDir, 'web.config');
        }
        if ($options['apache'] || $options['iis'])
        {
            $this->createBootstraper($rootDir);
        }
        
        $this->log->info("PieCrust website created in: " . $rootDir);
        $this->log->info("");

        if ($options['apache'] || $options['iis'])
        {
            $this->log->info("Please edit '".$rootDir."index.php' to fix the relative path to '_piecrust/piecrust.php'.");
            $this->log->info("");
            $this->log->info("If your webserver runs under a different user, you may have to change the root directory's permissions so that it's readable.");
            $this->log->info("You may have to also change the permissions on the '_cache' directory so that it's readable.");
        }
        else
        {
            $this->log->info("Run 'chef serve' on this directory to preview it.");
            $this->log->info("Run 'chef bake' on this directory to generate the static files.");
        }
    }
    
    protected function createDirectory($rootDir, $dir, $makeWritable = false)
    {
        PathHelper::ensureDirectory($rootDir . $dir, $makeWritable);
    }
    
    protected function createSystemFile($fileName, $rootDir, $destination)
    {
        $source = __DIR__ . '/../../../../resources/webinit/' . $fileName;
        $contents = file_get_contents($source);
        $this->log->debug("Writing '{$destination}'");
        file_put_contents($rootDir . $destination, $contents);
    }
    
    protected function createBootstraper($rootDir)
    {
        $bootstrapCode =
<<<'EOD'
<?php
require '../_piecrust/piecrust.php';
piecrust_run();
EOD;
        $this->log->debug("Writing 'index.php'");
        file_put_contents($rootDir . 'index.php', $bootstrapCode);
    }
    
    protected function createYamlFile($rootDir, $destination, $data)
    {
        $yaml = new \sfYamlDumper();
        $contents = $yaml->dump($data, 3);
        $this->log->debug("Writing '{$destination}'");
        file_put_contents($rootDir . $destination, $contents);
    }
}
