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
    
    /**
     * Creates a new instance of DirectoryBaker.
     */
    public function __construct(IPieCrust $pieCrust, $bakeDir, array $parameters = array(), $logger = null)
    {
        $this->pieCrust = $pieCrust;
        $this->tmpDir = $this->pieCrust->isCachingEnabled() ? 
            $this->pieCrust->getCacheDir() . 'bake_tmp/' :
            rtrim(sys_get_temp_dir(), '/\\') . '/piecrust/bake_tmp/';
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

        // Get the processing tree for that file.
        $builder = new ProcessingTreeBuilder(
            $this->pieCrust->getRootDir(),
            $this->tmpDir,
            $this->bakeDir,
            $this->getProcessors());
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
            'was_baked' => false
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

        $start = microtime(true);
        if ($this->bakeSubTree($treeRoot))
        {
            $this->bakedFiles[$path]['was_baked'] = true;
            $this->logger->info(PieCrustBaker::formatTimed($start, $relative));
        }
    }

    protected function bakeSubTree($root)
    {
        // Give some breathing room in verbose mode.
        $this->logger->debug('');

        $didBake = false;
        $walkStack = array($root);
        while (count($walkStack) > 0)
        {
            $curNode = array_pop($walkStack);

            // Make sure we have a processor.
            $processor = $curNode->getProcessor();
            if (!$processor)
            {
                $this->logger->err("Can't find processor for: {$curNode->getPath()}");
                continue;
            }

            // Make sure we have a valid clean/dirty state.
            if ($curNode->getState() == ProcessingTreeNode::STATE_UNKNOWN)
                $this->computeNodeDirtyness($curNode);

            // If the node is dirty, re-process it.
            if ($curNode->getState() == ProcessingTreeNode::STATE_DIRTY)
            {
                $didBakeThisNode = $this->processNode($curNode);
                $didBake |= $didBakeThisNode;

                // If we really re-processed it, push its output
                // nodes on the stack (unless they're leaves in the tree,
                // which means they don't themselves have anything to
                // produce).
                if ($didBakeThisNode)
                {
                    foreach ($curNode->getOutputs() as $out)
                    {
                        if (!$out->isLeaf())
                            array_push($walkStack, $out);
                    }
                }
            }
        }
        return $didBake;
    }
    
    protected function computeNodeDirtyness($node)
    {
        $processor = $node->getProcessor();
        if ($processor->isDelegatingDependencyCheck())
        {
            // Get the paths and last modification times for the input file and
            // all its dependencies, if any.
            $nodeRootDir = $this->getNodeRootDir($node);
            $path = $nodeRootDir . $node->getPath();
            $pathMTime = filemtime($path);
            $inputTimes = array($path => $pathMTime);
            try
            {
                $dependencies = $processor->getDependencies($path);
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
                $this->logger->warning($e->getMessage() . 
                    " -- Will force-bake {$node->getPath()}");
                $node->setState(ProcessingTreeNode::STATE_DIRTY, true);
                return;
            }

            // Get the paths and last modification times for the output files.
            $outputTimes = array();
            foreach ($node->getOutputs() as $out)
            {
                $outputRootDir = $this->getNodeRootDir($out);
                $fullOut = $outputRootDir . $out->getPath();
                $outputTimes[$fullOut] = is_file($fullOut) ? filemtime($fullOut) : false;
            }

            // Compare those times to see if the output file is up to date.
            foreach ($inputTimes as $iFn => $iTime)
            {
                foreach ($outputTimes as $oFn => $oTime)
                {
                    if (!$oTime || $iTime >= $oTime)
                    {
                        $node->setState(ProcessingTreeNode::STATE_DIRTY, true);

                        if (!$oTime)
                            $message = "Output file '{$oFn}' doesn't exist. Re-processing sub-tree.";
                        else
                            $message = "Input file is newer than '{$oFn}'. Re-processing sub-tree.";
                        $this->printProcessingTreeNode($node, $message);
                        break;
                    }
                }
            }
        }
        else
        {
            // The processor wants to handle dependencies himself.
            // We'll have to start rebaking from there.
            // However, we don't set the state recursively -- instead,
            // child nodes of this one will re-evaluate their dirtyness
            // after this one has run, in case the processor figures
            // it's clean after all.
            $node->setState(ProcessingTreeNode::STATE_DIRTY, false);
            $this->printProcessingTreeNode($node, "Handles dependencies itself, set locally to 'dirty'.");
        }
    }

    protected function processNode($node)
    {
        // Get the input path.
        $nodeRootDir = $this->getNodeRootDir($node);
        $path = $nodeRootDir . $node->getPath();

        // Get the output directory.
        // (all outputs of a node go to the same directory, so we
        //  can just get the directory of the first output node).
        $nodeOutputs = $node->getOutputs();
        $outputRootDir = $this->getNodeRootDir($nodeOutputs[0]);
        $outputChildDir = dirname($node->getPath());
        if ($outputChildDir == '.')
            $outputChildDir = '';
        $outputDir = $outputRootDir . $outputChildDir;
        if ($outputChildDir != '')
            $outputDir .= '/';
        $relativeOutputDir = PathHelper::getRelativePath($this->pieCrust, $outputDir);
        PathHelper::ensureDirectory($outputDir, true);

        $this->printProcessingTreeNode($node, "Processing into '{$relativeOutputDir}'.");

        // If we need to, re-process the node!
        $didBake = false;
        try
        {
            $start = microtime(true);
            $processor = $node->getProcessor();
            if ($processor->process($path, $outputDir) !== false)
            {
                $indent = str_repeat('  ', $node->getLevel() + 1);
                $message = $node->getPath() . ' ' .
                    '[' . $processor->getName() . '] -> ' .
                    PathHelper::getRelativePath($this->pieCrust, $outputDir);
                $this->logger->debug($indent . PieCrustBaker::formatTimed($start, $message));
                $this->logger->debug('');

                $didBake = true;
            }
        }
        catch (Exception $e)
        {
            throw new PieCrustException("Error processing '{$node->getPath()}': {$e->getMessage()}", 0, $e);
        }
        return $didBake;
    }

    protected function printProcessingTreeNode($node, $message = null, $recursive = false)
    {
        $indent = str_repeat('  ', $node->getLevel() + 1);
        $processor = $node->getProcessor() ? $node->getProcessor()->getName() : 'n/a';
        $path = PathHelper::getRelativePath(
            $this->pieCrust, 
            $this->getNodeRootDir($node) . $node->getPath());
        if (!$message)
            $message = '';

        $this->logger->debug("{$indent}{$path} [{$processor}] {$message}");

        if ($recursive)
        {
            foreach ($node->getOutputs() as $out)
            {
                $this->printProcessingTreeNode($out, true);
            }
        }
    }

    protected function getNodeRootDir($node)
    {
        $dir = $this->tmpDir . $node->getLevel() . '/';
        if ($node->getLevel() == 0)
            $dir = $this->pieCrust->getRootDir();
        else if ($node->isLeaf())
            $dir = $this->bakeDir;
        return $dir;
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
            $proc->initialize($this->pieCrust);
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
