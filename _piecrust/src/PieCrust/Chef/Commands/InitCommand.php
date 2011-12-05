<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\PieCrustDefaults;
use PieCrust\IO\FileSystem;

require_once 'sfYaml/lib/sfYamlDumper.php';


class InitCommand implements IChefCommand
{
    public function getName()
    {
        return 'init';
    }
    
    public function supportsDefaultOptions()
    {
        return true;
    }
    
    public function setupParser(Console_CommandLine $initParser)
    {
        $initParser->description = "Creates a new empty PieCrust website.";
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
    
    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result)
    {
        $rootDir = $result->command->args['root'];
        $apache = $result->command->options['apache'];
        $iis = $result->command->options['iis'];
        $this->initializeWebsite($rootDir, array('apache' => $apache, 'iis' => $iis));
    }

    public function initializeWebsite($rootDir, $options = array())
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
        
        echo PHP_EOL;
        echo "PieCrust website created in: " . $rootDir . PHP_EOL;
        echo PHP_EOL;

        if ($options['apache'] || $options['iis'])
        {
            echo "Please edit '".$rootDir."index.php' to fix the relative path to '_piecrust/piecrust.php'.";
            echo PHP_EOL;
            echo "If your webserver runs under a different user, you may have to change the root directory's permissions so that it's readable." . PHP_EOL;
            echo "You may have to also change the permissions on the '_cache' directory so that it's readable." . PHP_EOL;
            echo PHP_EOL;
        }
        else
        {
            echo "Run 'chef serve' on this directory to preview it." . PHP_EOL;
            echo "Run 'chef bake' on this directory to generate the static files." . PHP_EOL;
            echo PHP_EOL;
        }
    }
    
    protected function createDirectory($rootDir, $dir, $makeWritable = false)
    {
        if (FileSystem::ensureDirectory($rootDir . $dir, $makeWritable))
        {
            echo "Creating " . (empty($dir) ? "root directory" : $dir) . PHP_EOL;
        }
    }
    
    protected function createSystemFile($fileName, $rootDir, $destination)
    {
        echo "Writing " . $destination . PHP_EOL;
        $source = __DIR__ . '/../../../../resources/webinit/' . $fileName;
        $contents = file_get_contents($source);
        file_put_contents($rootDir . $destination, $contents);
    }
    
    protected function createBootstraper($rootDir)
    {
        echo "Generating bootstraper" . PHP_EOL;
        $bootstrapCode =
<<<'EOD'
<?php
require '../_piecrust/piecrust.php';
piecrust_run();
EOD;
        file_put_contents($rootDir . 'index.php', $bootstrapCode);
    }
    
    protected function createYamlFile($rootDir, $destination, $data)
    {
        echo "Generating " . $destination . PHP_EOL;
        $yaml = new \sfYamlDumper();
        $contents = $yaml->dump($data, 3);
        file_put_contents($rootDir . $destination, $contents);
    }
}
