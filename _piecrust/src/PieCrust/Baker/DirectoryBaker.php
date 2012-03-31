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

        $this->bakedFiles = array();
        
        if (!is_dir($this->bakeDir) or !is_writable($this->bakeDir))
        {
            throw new PieCrustException('The bake directory is not writable, or does not exist: ' . $this->bakeDir);
        }
    }
    
    /**
     * Bakes the app's root directory and all its files and sub-directories,
     * or just bakes the given file, if any.
     */
    public function bake($path = null)
    {
        $this->bakedFiles = array();
        if ($path == null)
            $this->bakeDirectory($this->pieCrust->getRootDir(), 0);
        else if (is_file($path))
            $this->bakeFile($path);
        else if (is_dir($path))
            $this->bakeDirectory($path, 0);
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
            $absolute = str_replace('\\', '/', $i->getPathname());
            $relative = substr($absolute, $this->rootDirLength);

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
                $normalizedAbsolute = rtrim($absolute, '/') . '/';
                if ($normalizedAbsolute == $this->bakeDir)
                {
                    continue;
                }

                $destination = $this->bakeDir . $relative;
                if (!is_dir($destination))
                {
                    if (@mkdir($destination, 0777, true) === false)
                        throw new PieCrustException("Can't create directory: " . $destination);
                }
                $this->bakeDirectory($absolute, $level + 1);
            }
            else if ($i->isFile())
            {
                $this->bakeFile($absolute);
            }
        }
    }

    protected function bakeFile($path)
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

        // Get the destination directories.
        $relativeDir = dirname($relative);
        $relativeDestinationDir = $relativeDir == '.' ? '' : ($relativeDir . '/');
        $destinationDir = $this->bakeDir . ($relativeDir == '.' ? '' : ($relativeDir . '/'));

        // Get the output files.
        $filename = pathinfo($path, PATHINFO_BASENAME);
        $outputFilenames = $fileProcessor->getOutputFilenames($filename);
        if (!is_array($outputFilenames))
            $outputFilenames = array($outputFilenames);

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
            $inputTimes = array($path => $pathMTime);
            try
            {
                $dependencies = $fileProcessor->getDependencies($path);
                if ($dependencies)
                {
                    foreach ($dependencies as $dep)
                    {
                        $inputTimes[$dep] = filemtime($dep);
                    }
                }
            }
            catch(Exception $e)
            {
                $this->logger->log($e->getMessage() . " -- Will force-bake {$relative}");
                $isUpToDate = false;
            }

            if ($isUpToDate)
            {
                // Get the paths and last modification times for the output files.
                $outputTimes = array();
                foreach ($outputFilenames as $out)
                {
                    $fullOut = $destinationDir . $out;
                    $outputTimes[$fullOut] = is_file($fullOut) ? filemtime($fullOut) : false;
                }

                // Compare those times to see if the output file is up to date.
                foreach ($inputTimes as $iFn => $iTime)
                {
                    foreach ($outputTimes as $oFn => $oTime)
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
                    $this->bakedFiles[$path] = array(
                        'relativeInput' => $relative,
                        'relativeOutputs' => array_map(
                            function ($p) use ($relativeDestinationDir) { return $relativeDestinationDir . $p; },
                            $outputFilenames
                        ),
                        'outputs' => array_map(
                            function ($p) use ($destinationDir) { return $destinationDir . $p; },
                            $outputFilenames
                        )
                    );
                    $this->logger->info(PieCrustBaker::formatTimed($start, $relative));
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error processing '" . $relative . "': " . $e->getMessage(), 0, $e);
            }
        }
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
            $patterns[$i] = PathHelper::globToRegex($patterns[$i]);
        }
        // Add the default patterns.
        foreach ($defaultPatterns as $p)
        {
            $patterns[] = $p;
        }
        return $patterns;
    }
}
