<?php

namespace PieCrust;

use \Phar;
use PieCrust\Util\PathHelper;


/**
 * A class that compiles PieCrust into a `.phar` file.
 */
class Compiler
{
    public function compile($pharFile = 'piecrust.phar', $options = array())
    {
        $options = array_merge(
            array(
                'core_libs_only' => false
            ),
            $options
        );

        if (file_exists($pharFile))
            unlink($pharFile);

        $version = shell_exec('hg id -i');
        if ($version == null)
            $version = false;
        else
            $version = trim($version);

        $phar = new Phar($pharFile, 0, 'piecrust.phar');
        $phar->setSignatureAlgorithm(Phar::SHA1);

        $phar->startBuffering();
        $fileCount = 0;
        $baseDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $dirs = array('libs', 'res', 'src');
        $includes = array();
        $excludes = array(
            '#^libs/\.composer#',
            '#^libs/[\w\d]+/[\w\d]+/(\.hg|\.git|\.travis)#',
            '#^libs/[\w\d]+/[\w\d]+/(bin|ext|doc|example|test|phpunit)#',
            '#^libs/mikey179/vfsStream/src/test#'
        );

        if ($options['core_libs_only'])
        {
            $includes = array(
                '#^src#',
                '#^res#',
                '#^libs/autoload#',
                '#^libs/pear#',
                '#^libs/composer#',
                '#^libs/symfony#'
            );
        }

        foreach ($dirs as $dir)
        {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir . DIRECTORY_SEPARATOR . $dir)
            );
            foreach ($it as $path) 
            {
                $relativePath = str_replace(
                    '\\', 
                    '/',
                    substr($path->getPathname(), strlen($baseDir) + 1)
                );
                $shouldAdd = true;
                if ($shouldAdd && $includes)
                {
                    $isIncluded = false;
                    foreach ($includes as $include)
                    {
                        if (preg_match($include, $relativePath))
                        {
                            $isIncluded = true;
                            break;
                        }
                    }
                    $shouldAdd = $isIncluded;
                }
                if ($shouldAdd)
                {
                    foreach ($excludes as $exclude)
                    {
                        if (preg_match($exclude, $relativePath))
                        {
                            $shouldAdd = false;
                            break;
                        }
                    }
                }
                if ($shouldAdd)
                {
                    echo "Adding {$relativePath}\n";
                    $phar->addFile($path->getPathname(), $relativePath);
                    ++$fileCount;
                }
            }
        }
        $phar->addFile($baseDir . 'piecrust.php', 'piecrust.php');
        $phar->setMetadata(array('version' => $version));
        $phar->setStub($this->getStub());
        $phar->stopBuffering();
        echo "Added {$fileCount} files to phar archive.\n";
        unset($phar);
    }
    
    private function getStub()
    {
        $stub = <<<'EOF'
#!/usr/bin/env php
<?php
Phar::mapPhar('piecrust.phar');
require 'phar://piecrust.phar/piecrust.php';
piecrust_chef();
__HALT_COMPILER();
EOF;
        return $stub;
    }
}

