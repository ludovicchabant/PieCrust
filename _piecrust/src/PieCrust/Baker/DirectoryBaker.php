<?php

namespace PieCrust\Baker;

use \Exception;
use \DirectoryIterator;
use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PluginLoader;


/**
 * A class responsible for baking non-PieCrust content.
 */
class DirectoryBaker
{
    protected $pieCrust;
    protected $rootDirLength;
    protected $bakeDir;
    protected $parameters;
    
    protected $processorsLoader;
    /**
    * Gets the PluginLoader for the file processors.
    */
    public function getProcessorsLoader()
    {
        if ($this->processorsLoader === null)
        {
            $processorsToFilter = $this->parameters['processors'];
            $this->processorsLoader = new PluginLoader(
                                            'PieCrust\\Baker\\Processors\\IProcessor',
                                            PieCrust::APP_DIR . '/Baker/Processors',
                                            function ($p1, $p2) { return $p1->getPriority() < $p2->getPriority(); },
                                            $processorsToFilter == '*' ?
                                                null :
                                                function ($p) use ($processorsToFilter) { return in_array($p->getName(), $processorsToFilter); },
                                            'SimpleFileProcessor.php'
                                            );
            foreach ($this->processorsLoader->getPlugins() as $proc)
            {
                $proc->initialize($this->pieCrust);
            }
        }
        return $this->processorsLoader;
    }
    
    /**
     * Creates a new instance of DirectoryBaker.
     */
    public function __construct(PieCrust $pieCrust, $bakeDir, array $parameters = array())
    {
        $this->pieCrust = $pieCrust;
        $this->bakeDir = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $bakeDir), '/\\') . DIRECTORY_SEPARATOR;
        $this->parameters = array_merge(
                                        array(
                                              'smart' => true,
                                              'skip_patterns' => array(),
                                              'processors' => array('copy')
                                              ),
                                        $parameters
                                        );
        
        $rootDir = $this->pieCrust->getRootDir();
        $this->rootDirLength = strlen($rootDir);
        
        if (!is_dir($this->bakeDir) or !is_writable($this->bakeDir))
        {
            throw new PieCrustException('The bake directory is not writable, or does not exist: ' . $this->bakeDir);
        }
    }
    
    /**
     * Bakes the given directory and all its files and sub-directories.
     */
    public function bake()
    {
        $this->bakeDirectory($this->pieCrust->getRootDir(), 0);
    }
    
    protected function bakeDirectory($currentDir, $level)
    {
        $it = new DirectoryIterator($currentDir);
        foreach ($it as $i)
        {
            if ($i->isDot())
            {
                continue;
            }
            if ($i->getPathname() . DIRECTORY_SEPARATOR == $this->bakeDir)
            {
                continue;
            }
            $shouldSkip = false;
            foreach ($this->parameters['skip_patterns'] as $p)
            {
                if (preg_match($p, $i->getFilename()))
                {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) continue;
            
            $relative = substr($i->getPathname(), $this->rootDirLength);
            if ($i->isDir())
            {
                $destination = $this->bakeDir . $relative;
                if (!is_dir($destination))
                {
                    @mkdir($destination, 0777, true);
                }
                $this->bakeDirectory($i->getPathname(), $level + 1);
            }
            else if ($i->isFile())
            {
                $fileProcessor = null;
                foreach ($this->getProcessorsLoader()->getPlugins() as $proc)
                {
                    if ($proc->supportsExtension(pathinfo($i->getFilename(), PATHINFO_EXTENSION)))
                    {
                        $fileProcessor = $proc;
                        break;
                    }
                }
                if ($fileProcessor != null)
                {
                    $isUpToDate = false;
                    $destinationDir = $this->bakeDir . dirname($relative) . DIRECTORY_SEPARATOR;
                    $outputFilenames = $fileProcessor->getOutputFilenames($i->getFilename());
                    if (!is_array($outputFilenames))
                    {
                        $outputFilenames = array($outputFilenames);
                    }
                    if ($this->parameters['smart'])
                    {
                        $isUpToDate = true;
                        foreach ($outputFilenames as $f)
                        {
                            $destination = $destinationDir . $f;
                            if (!is_file($destination) or $i->getMTime() >= filemtime($destination))
                            {
                                $isUpToDate = false;
                                break;
                            }
                        }
                    }
                    if (!$isUpToDate)
                    {
                        $previousErrorHandler = set_error_handler('piecrust_error_handler');
                        try
                        {
                            $start = microtime(true);
                            $fileProcessor->process($i->getPathname(), $destinationDir);
                            echo PieCrustBaker::formatTimed($start, $relative) . PHP_EOL;
                        }
                        catch (Exception $e)
                        {
                            throw new PieCrustException("Error processing '" . $relative . "': " . $e->getMessage());
                        }
                        if ($previousErrorHandler)
                        {
                            set_error_handler($previousErrorHandler);
                        }
                    }
                }
                else
                {
                    echo "Warning: no processor for " . $relative . PHP_EOL;
                }
            }
        }
    }
}
