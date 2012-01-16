<?php

namespace PieCrust\Baker;

use \Exception;
use \DirectoryIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Util\PathHelper;


/**
 * A class responsible for baking non-PieCrust content.
 */
class DirectoryBaker
{
    protected $pieCrust;
    protected $logger;
    protected $rootDirLength;
    protected $bakeDir;
    protected $parameters;

    protected $bakedFiles;
    /**
     * Gets the files baked last time.
     */
    public function getBakedFiles()
    {
        return $this->bakedFiles;
    }
    
    protected $processors;
    /**
    * Gets the file processors.
    */
    public function getProcessors()
    {
        if ($this->processors === null)
        {
            // Load the plugin processors.
            $this->processors = $this->pieCrust->getPluginLoader()->getProcessors();

            // Filter processors to use.
            $processorsToFilter = $this->parameters['processors'];
            if ($processorsToFilter != '*')
            {
                $all = in_array('*', $processorsToFilter);
                $filter = function ($p) use ($processorsToFilter, $all)
                    {
                        if ($all)
                            return !in_array('-'.$p->getName(), $processorsToFilter);
                        else
                            return in_array($p->getName(), $processorsToFilter);
                    };
                $this->processors = array_filter($this->processors, $filter);
            }

            // Initialize the processors we have left.
            foreach ($this->processors as $proc)
            {
                $proc->initialize($this->pieCrust);
            }
        }
        return $this->processors;
    }
    
    /**
     * Creates a new instance of DirectoryBaker.
     */
    public function __construct(IPieCrust $pieCrust, $bakeDir, array $parameters = array(), $logger = null)
    {
        $this->pieCrust = $pieCrust;
        $this->bakeDir = rtrim(str_replace('\\', '/', $bakeDir), '/') . '/';
        $this->parameters = array_merge(
            array(
                  'smart' => true,
                  'skip_patterns' => array(),
                  'force_patterns' => array(),
                  'processors' => array('copy')
                  ),
            $parameters
        );
        if ($logger == null)
        {
            require_once 'Log.php';
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
        
        // Validate skip patterns.
        $this->parameters['skip_patterns'] = self::validatePatterns(
            $this->parameters['skip_patterns'],
            array('/^_cache/', '/^_content/', '/^_counter/', '/(\.DS_Store)|(Thumbs.db)|(\.git)|(\.hg)|(\.svn)/')
        );
        
        // Validate force-bake patterns.
        $this->parameters['force_patterns'] = self::validatePatterns(
            $this->parameters['force_patterns']
        );
        
        // Compute the number of characters we need to remove from file paths
        // to get their relative paths.
        $rootDir = $this->pieCrust->getRootDir();
        $rootDir = rtrim($rootDir, '/\\') . '/';
        $this->rootDirLength = strlen($rootDir);

        $this->bakedFiles = null;
        
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
        $this->bakedFiles = array();
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

            // Figure out the root-relative path.
            $relative = substr($i->getPathname(), $this->rootDirLength);

            // See if we need to skip this file/directory.
            $shouldSkip = false;
            foreach ($this->parameters['skip_patterns'] as $p)
            {
                if (preg_match($p, $relative))
                {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip)
                continue;
            
            if ($i->isDir())
            {
                // Current path is a directory... recurse into it, unless it's
                // actually the directory we're baking *into* (which would cause
                // an infinite loop and lots of files being created!).
                $normalizedPathname = rtrim(str_replace('\\', '/', $i->getPathname()), '/') . '/';
                if ($normalizedPathname == $this->bakeDir)
                {
                    continue;
                }

                $destination = $this->bakeDir . $relative;
                if (!is_dir($destination))
                {
                    if (@mkdir($destination, 0777, true) === false)
                        throw new PieCrustException("Can't create directory: " . $destination);
                }
                $this->bakeDirectory($i->getPathname(), $level + 1);
            }
            else if ($i->isFile())
            {
                $this->bakeFile($i->getPathname());
            }
        }
    }

    public function bakeFile($path)
    {
        // Figure out the root-relative path.
        $relative = substr($path, $this->rootDirLength);

        // Find a processor for the given file.
        $fileProcessor = null;
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        foreach ($this->getProcessors() as $proc)
        {
            if ($proc->supportsExtension($extension))
            {
                $fileProcessor = $proc;
                break;
            }
        }
        if ($fileProcessor == null)
        {
            $this->logger->warning("No processor for {$relative}");
            return;
        }

        $destinationDir = $this->bakeDir . dirname($relative) . DIRECTORY_SEPARATOR;

        // Figure out if we need to actually process this file.
        $isUpToDate = true;

        // Should the file be force-baked?
        foreach ($this->parameters['force_patterns'] as $p)
        {
            if (preg_match($p, $relative))
            {
                $isUpToDate = false;
                break;
            }
        }

        // Is the output file up to date with its input and dependencies?
        if ($isUpToDate && 
            $this->parameters['smart'] && 
            $fileProcessor->isDelegatingDependencyCheck())
        {
            // Get the paths and last modification times for the input file and
            // all its dependencies, if any.
            $pathMTime = filemtime($path);
            $inputFilenames = array($path => $pathMTime);
            try
            {
                $dependencies = $fileProcessor->getDependencies($path);
                if ($dependencies)
                {
                    foreach ($dependencies as $dep)
                    {
                        $inputFilenames[$dep] = filemtime($dep);
                    }
                }
            }
            catch(Exception $e)
            {
                $this->logger->warn($e->getMessage() . " -- Will force-bake {$relative}");
                $isUpToDate = false;
            }

            if ($isUpToDate)
            {
                // Get the paths and last modification times for the output files.
                $outputFilenames = array();
                $filename = pathinfo($path, PATHINFO_BASENAME);
                $outputs = $fileProcessor->getOutputFilenames($filename);
                if (!is_array($outputs))
                {
                    $outputs = array($outputs);
                }
                foreach ($outputs as $out)
                {
                    $fullOut = $destinationDir . $out;
                    $outputFilenames[$fullOut] = is_file($fullOut) ? filemtime($fullOut) : false;
                }

                // Compare those times to see if the output file is up to date.
                foreach ($inputFilenames as $iFn => $iTime)
                {
                    foreach ($outputFilenames as $oFn => $oTime)
                    {
                        if (!$oTime || $iTime >= $oTime)
                        {
                            $isUpToDate = false;
                            break;
                        }
                    }
                }
            }
        }
        else
        {
            $isUpToDate = false;
        }

        if (!$isUpToDate)
        {
            try
            {
                $start = microtime(true);
                if ($fileProcessor->process($path, $destinationDir) !== false)
                {
                    $this->bakedFiles[] = $relative;
                    $this->logger->info(PieCrustBaker::formatTimed($start, $relative));
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error processing '" . $relative . "': " . $e->getMessage(), 0, $e);
            }
        }
    }
    
    public static function globToRegex($pattern)
    {
        if (substr($pattern, 0, 1) == "/" and
            substr($pattern, -1) == "/")
        {
            // Already a regex.
            return $pattern;
        }
        
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\\*', '[^\\/\\\\]*', $pattern);
        $pattern = str_replace('\\?', '[^\\/\\\\]', $pattern);
        return '/'.$pattern.'/';
    }

    public static function validatePatterns($patterns, array $defaultPatterns = array())
    {
        if (!is_array($patterns))
        {
            $patterns = array($patterns);
        }
        // Convert glob patterns to regex patterns.
        for ($i = 0; $i < count($patterns); ++$i)
        {
            $patterns[$i] = self::globToRegex($patterns[$i]);
        }
        // Add the default patterns.
        foreach ($defaultPatterns as $p)
        {
            $patterns[] = $p;
        }
        return $patterns;
    }
}
