<?php

namespace PieCrust\Baker;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;


/**
 * A class responsible for baking non-PieCrust content.
 */
class DirectoryBaker implements IBaker
{
    protected $pieCrust;
    protected $logger;
    protected $rootDirLength;
    protected $mountDirLengths;
    protected $tmpDir;
    protected $bakeDir;
    protected $parameters;

    protected $bakedFiles;
    /**
     * Gets the files' metadata for the last bake.
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
        $this->ensureProcessors();
        return $this->processors;
    }

    // IBaker members {{{
    public function getBakeDir()
    {
        return $this->bakeDir;
    }
    // }}}
    
    /**
     * Creates a new instance of DirectoryBaker.
     */
    public function __construct(IPieCrust $pieCrust, $bakeDir, array $parameters = array(), $logger = null)
    {
        $this->pieCrust = $pieCrust;
        $this->tmpDir = $this->pieCrust->isCachingEnabled() ? 
            $this->pieCrust->getCacheDir() . 'bake_t/' :
            rtrim(sys_get_temp_dir(), '/\\') . '/piecrust/bake_t/';
        $this->bakeDir = rtrim(str_replace('\\', '/', $bakeDir), '/') . '/';
        $this->parameters = array_merge(
            array(
                'smart' => true,
                'mounts' => array(),
                'skip_patterns' => array(),
                'force_patterns' => array(),
                'processors' => array('copy')
            ),
            $parameters
        );
        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;

        // Add a special mount point for the theme directory, if any.
        if ($this->pieCrust->getThemeDir())
        {
            $this->parameters['mounts']['theme'] = $this->pieCrust->getThemeDir();
        }
        
        // Validate skip patterns.
        $this->parameters['skip_patterns'] = self::validatePatterns(
            $this->parameters['skip_patterns'],
            array('/^_cache/', '/^_content/', '/^_counter/', '/^theme_info\.yml/', '/(\.DS_Store)|(Thumbs.db)|(\.git)|(\.hg)|(\.svn)/')
        );

        // Validate force-bake patterns.
        $this->parameters['force_patterns'] = self::validatePatterns(
            $this->parameters['force_patterns']
        );
        
        // Compute the number of characters we need to remove from file paths
        // to get their relative paths.
        $rootDir = $this->pieCrust->getRootDir();
        $this->rootDirLength = strlen(rtrim($rootDir, '/\\')) + 1;
        $this->mountDirLengths = array();
        foreach ($this->parameters['mounts'] as $name => $dir)
        {
            $this->mountDirLengths[$name] = strlen(rtrim($dir, '/\\')) + 1;
        }

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
        $baker = $this;
        $this->triggerBakeEvent(
            "pre bake process",
            function ($p) use($baker) { $p->onBakeStart($baker); }
        );

        $this->bakedFiles = array();
        if ($path == null)
        {
            // Bake everything (root directory and special mount points).
            $this->logger->debug("Initiating bake on: {$this->pieCrust->getRootDir()}");
            $this->bakeDirectory(
                $this->pieCrust->getRootDir(), 
                0, 
                $this->pieCrust->getRootDir(), 
                $this->rootDirLength
            );
            foreach ($this->parameters['mounts'] as $name => $dir)
            {
                $this->logger->debug("Initiating bake on: {$dir}");
                $this->bakeDirectory($dir, 0, $dir, $this->mountDirLengths[$name]);
            }
        }
        else
        {
            // Find if this path belongs to a special mount point.
            $rootDir = $this->pieCrust->getRootDir();
            $rootDirLength = $this->rootDirLength;
            foreach ($this->parameters['mounts'] as $name => $dir)
            {
                if (substr($path, 0, $this->mountDirLengths[$name]) == $dir)
                {
                    $rootDir = $dir;
                    $rootDirLength = $this->mountDirLengths[$name];
                    break;
                }
            }

            if (is_dir($path))
            {
                $this->logger->debug("Initiating bake on: {$rootDir}");
                $this->bakeDirectory($path, 0, $rootDir, $rootDirLength);
            }
            else if (is_file($path))
            {
                $this->logger->debug("Initiating bake on: {$rootDir}");
                $this->bakeFile($path, $rootDir, $rootDirLength);
            }
        }

        $this->triggerBakeEvent(
            "post bake process",
            function ($p) { $p->onBakeEnd(); }
        );
    }

    protected function bakeDirectory($currentDir, $level, $rootDir, $rootDirLength)
    {
        $it = new \FilesystemIterator($currentDir);
        foreach ($it as $i)
        {
            // Figure out the root-relative path.
            $absolute = str_replace('\\', '/', $i->getPathname());
            $relative = substr($absolute, $rootDirLength);

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
            {
                $this->logger->debug("Skipping '$relative' [skip_patterns]");
                continue;
            }
            
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
                $this->bakeDirectory($absolute, $level + 1, $rootDir, $rootDirLength);
            }
            else if ($i->isFile())
            {
                $this->bakeFile($absolute, $rootDir, $rootDirLength);
            }
        }
    }

    protected function bakeFile($path, $rootDir, $rootDirLength)
    {
        // Start timing this.
        $start = microtime(true);

        // Figure out the root-relative path.
        $relative = substr($path, $rootDirLength);

        // Figure out if a previously baked file has overridden this one.
        // This can happen for example when a theme's file (baked via a
        // special mount point) is overridden in the user's website (baked
        // via the first normal root directory).
        $isOverridden = false;
        foreach ($this->bakedFiles as $bakedFile)
        {
            if ($bakedFile['relative_input'] == $relative)
            {
                $isOverridden = true;
            }
        }
        if ($isOverridden)
        {
            $this->bakedFiles[$path] = array(
                'relative_input' => $relative,
                'was_baked' => false,
                'was_overridden' => true
            );
            $this->logger->info(PieCrustBaker::formatTimed($start, $relative) . ' [not baked, overridden]');
            return;
        }

        // Get the processing tree for that file.
        $builder = new ProcessingTreeBuilder($this->getProcessors(), $this->logger);
        $treeRoot = $builder->build($relative);

        // Add an entry in the baked files' metadata.
        $bakeDir = $this->bakeDir;
        $treeLeaves = $treeRoot->getLeaves();
        $this->bakedFiles[$path] = array(
            'relative_input' => $relative,
            'relative_outputs' => array_map(
                function ($n) {
                    return $n->getPath();
                },
                $treeLeaves
            ),
            'outputs' => array_map(
                function ($n) use ($bakeDir) {
                    return $bakeDir . $n->getPath();
                },
                $treeLeaves
            ),
            'was_baked' => false,
            'was_overridden' => false
        );

        // See if we should force bake the file.
        $forceBake = !$this->parameters['smart'];
        if (!$forceBake)
        {
            foreach ($this->parameters['force_patterns'] as $p)
            {
                if (preg_match($p, $relative))
                {
                    $forceBake = true;
                    break;
                }
            }
        }
        if ($forceBake)
        {
            $treeRoot->setState(ProcessingTreeNode::STATE_DIRTY, true);
        }

        // Bake!
        $runner = new ProcessingTreeRunner(
            $rootDir,
            $this->tmpDir,
            $this->bakeDir,
            $this->logger
        );
        if ($runner->bakeSubTree($treeRoot))
        {
            $this->bakedFiles[$path]['was_baked'] = true;
            $this->logger->info(PieCrustBaker::formatTimed($start, $relative));
        }
    }

    protected function triggerBakeEvent($eventName, $func)
    {
        foreach ($this->getProcessors() as $proc)
        {
            $start = microtime(true);
            $func($proc);
            $end = microtime(true);
            $elapsed = ($end - $start) * 1000.0;
            if ($elapsed > 5)
            {
                $message = "[{$eventName} for {$proc->getName()}]";
                $this->logger->info(PieCrustBaker::formatTimed($start, $message));
            }
        }
    }

    protected function ensureProcessors()
    {
        if ($this->processors !== null)
            return;

        // Load the plugin processors.
        $this->processors = $this->pieCrust->getPluginLoader()->getProcessors();

        // Filter processors to use.
        $processorsToFilter = $this->parameters['processors'];
        if (!is_array($processorsToFilter))
            $processorsToFilter = explode(' ', $processorsToFilter);
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
            $proc->initialize($this->pieCrust, $this->logger);
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
