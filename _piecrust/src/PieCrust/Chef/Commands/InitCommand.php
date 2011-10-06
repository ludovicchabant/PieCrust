<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\PieCrust;

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
    }
    
    public function run(Console_CommandLine $parser, Console_CommandLine_Result $result)
    {
        $rootDir = $result->command->args['root'];
        $rootDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($rootDir))
        {
            throw new Exception("The given root is not a directory: ".$rootDir);
        }
        if (!is_writable($rootDir))
        {
            throw new Exception("The given root directory is not writeable: ".$rootDir);
        }
        
        // Create the directory structure.
        $this->createDirectory($rootDir, PieCrust::CACHE_DIR, true);
        $this->createDirectory($rootDir, PieCrust::CONTENT_DIR);
        $this->createDirectory($rootDir, PieCrust::CONTENT_PAGES_DIR);
        $this->createDirectory($rootDir, PieCrust::CONTENT_POSTS_DIR);
        $this->createDirectory($rootDir, PieCrust::CONTENT_TEMPLATES_DIR);
        
        // Create the basic files.
        $this->createSystemFile('htaccess', $rootDir, '.htaccess');
        $this->createSystemFile('web.config', $rootDir, 'web.config');
        $this->createBootstraper($rootDir);
        $this->createYamlFile($rootDir, PieCrust::CONFIG_PATH, array(
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
        $this->createSystemFile('default_template.html', $rootDir, PieCrust::CONTENT_TEMPLATES_DIR . PieCrust::DEFAULT_PAGE_TEMPLATE_NAME . '.html');
        $this->createSystemFile('default_index.html', $rootDir, PieCrust::CONTENT_PAGES_DIR . PieCrust::INDEX_PAGE_NAME . '.html');
        
        echo PHP_EOL;
        echo "PieCrust website created in: " . $rootDir . PHP_EOL;
        echo PHP_EOL;
        echo "Please edit '".$rootDir."index.php' to fix the relative path to '_piecrust/piecrust.php'.";
        echo PHP_EOL;
        echo "If your webserver runs under a different user, you may have to change the root directory's permissions ".
             "so that it's readable." . PHP_EOL;
        echo "You may have to also change the permissions on the '_cache' directory." . PHP_EOL;
        echo PHP_EOL;
    }
    
    protected function createDirectory($rootDir, $dir, $makeWritable = false)
    {
        if (!is_dir($rootDir . $dir))
        {
            echo "Creating " . $dir . PHP_EOL;
            mkdir($rootDir . $dir);
            if ($makeWritable)
            {
                chmod($rootDir . $dir, 0777);
            }
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
        $contents = $yaml->dump($data);
        file_put_contents($rootDir . $destination, $contents);
    }
}
