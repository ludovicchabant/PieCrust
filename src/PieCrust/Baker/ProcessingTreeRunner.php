<?php

namespace PieCrust\Baker;

use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


/**
 * A class that can run a processing tree.
 */
class ProcessingTreeRunner
{
    protected $rootDir;
    protected $tmpDir;
    protected $outDir;
    protected $logger;

    /**
     * Creates a new instance of the runner.
     */
    public function __construct($rootDir, $tmpDir, $outDir, $logger = null)
    {
        $this->rootDir = $rootDir;
        $this->tmpDir = $tmpDir;
        $this->outDir = $outDir;

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
    }

    /**
     * Runs a sub-tree, starting at the given processing node.
     */
    public function bakeSubTree($root)
    {
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
            else
            {
                // If the node is clean, let's still look at the
                // the rest of the tree, in case the user tempered
                // with the cache or output folders.
                $this->printProcessingTreeNode($curNode, "[clean]", false);
                foreach ($curNode->getOutputs() as $out)
                {
                    if (!$out->isLeaf())
                        array_push($walkStack, $out);
                }
            }
        }
        return $didBake;
    }
    
    protected function computeNodeDirtyness($node)
    {
        $processor = $node->getProcessor();
        if (
            $processor->isDelegatingDependencyCheck() && 
            !$processor->isBypassingStructuredProcessing()
        )
        {
            // Start profiling.
            $start = microtime(true);

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
                    if (!$oTime || $iTime > $oTime)
                    {
                        $node->setState(ProcessingTreeNode::STATE_DIRTY, true);

                        if (!$oTime)
                            $message = "Output file '{$oFn}' doesn't exist. Re-processing sub-tree.";
                        else
                            $message = "Input file is newer than '{$oFn}'. Re-processing sub-tree.";
                        $this->printProcessingTreeNode($node, $message);
                        break 2;
                    }
                }
            }

            // Print profiling info.
            $end = microtime(true);
            $elapsedTime = sprintf('%.1f ms', ($end - $start)*1000.0);
            $this->printProcessingTreeNode($node, "Computed node dirtiness [{$elapsedTime}]");
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

        // If the processor for this node wants to bypass structured
        // processing altogether, run it now and exit.
        $processor = $node->getProcessor();
        if ($processor->isBypassingStructuredProcessing())
        {
            try
            {
                $start = microtime(true);
                $processor->process($path, $this->outDir);
                $end = microtime(true);
                $processTime = sprintf('%.1f ms', ($end - $start)*1000.0);
                $this->printProcessingTreeNode($node, " (bypassing structured processing) [{$processTime}].");
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error processing '{$node->getPath()}'.", 0, $e);
            }
            return true;
        }

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
        $relativeOutputDir = PathHelper::getRelativePath($this->rootDir, $outputDir);
        PathHelper::ensureDirectory($outputDir, true);

        // Now process the node!
        $didBake = false;
        try
        {
            $start = microtime(true);
            $processResult = $processor->process($path, $outputDir);
            $end = microtime(true);
            $processTime = sprintf('%.1f ms', ($end - $start)*1000.0);
            if ($processResult !== false)
            {
                $this->printProcessingTreeNode($node, "-> '{$relativeOutputDir}' [{$processTime}].");
                $didBake = true;
            }
            else
            {
                $this->printProcessingTreeNode($node, "-> '{$relativeOutputDir}' [clean, {$processTime}].");
            }
        }
        catch (Exception $e)
        {
            throw new PieCrustException("Error processing '{$node->getPath()}'.", 0, $e);
        }
        return $didBake;
    }

    protected function printProcessingTreeNode($node, $message = null, $recursive = false)
    {
        $indent = str_repeat('  ', $node->getLevel());
        $processor = $node->getProcessor() ? $node->getProcessor()->getName() : 'n/a';
        $path = PathHelper::getRelativePath(
            $this->rootDir, 
            $this->getNodeRootDir($node) . $node->getPath()
        );
        if (!$message)
            $message = '';

        $this->logger->debug("{$indent}{$path} [{$processor}] {$message}");

        if ($recursive)
        {
            foreach ($node->getOutputs() as $out)
            {
                $this->printProcessingTreeNode($out, null, true);
            }
        }
    }

    protected function getNodeRootDir($node)
    {
        $dir = $this->tmpDir . $node->getLevel() . '/';
        if ($node->getLevel() == 0)
            $dir = $this->rootDir;
        else if ($node->isLeaf())
            $dir = $this->outDir;
        return $dir;
    }
}

