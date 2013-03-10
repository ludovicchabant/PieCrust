<?php

namespace PieCrust\Baker;

use \Exception;
use \StdClass;
use PieCrust\PieCrustException;


/**
 * The builder class for a processing tree.
 */
class ProcessingTreeBuilder
{
    protected $processors;
    protected $logger;

    public function __construct(array $processors, $logger = null)
    {
        $this->processors = $processors;

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
    }

    public function build($relativeInputFile)
    {
        // Start profiling.
        $start = microtime(true);

        // Create the root of the baking tree.
        $treeRoot = new ProcessingTreeNode($relativeInputFile, $this->processors);

        // Create the walking stack.
        $walkStack = array($treeRoot);

        // Start walking!
        while (count($walkStack) > 0)
        {
            $curNode = array_pop($walkStack);

            // Get the node's processor.
            $processor = $curNode->getProcessor();
            if (!$processor)
                continue;

            // If this processor wants to bypass this while tree-thing,
            // so be it, but we only accept it on the root node.
            if ($processor->isBypassingStructuredProcessing())
            {
                if ($curNode != $treeRoot)
                    throw new PieCrustException("Only root processors can bypass structured processing.");
                break;
            }

            // Get the destination directories.
            $relativeDir = dirname($curNode->getPath());
            $relativeDestinationDir = $relativeDir == '.' ? '' : ($relativeDir . '/');

            // Get the output files.
            $filename = pathinfo($curNode->getPath(), PATHINFO_BASENAME);
            $outputFilenames = $processor->getOutputFilenames($filename);
            if (!is_array($outputFilenames))
                $outputFilenames = array($outputFilenames);

            // Create the children nodes and push them on the stack.
            foreach ($outputFilenames as $f)
            {
                $outputNode = new ProcessingTreeNode(
                    $relativeDestinationDir . $f,
                    $curNode->getAvailableProcessors(),
                    $curNode->getLevel() + 1);
                $curNode->addOutput($outputNode);

                if ($processor->getName() != 'copy')
                    array_push($walkStack, $outputNode);
            }
        }

        // Print profiling info.
        $end = microtime(true);
        $buildTime = sprintf('%.1f ms', ($end - $start)*1000.0);
        $this->logger->debug("Built processing tree for '{$relativeInputFile}' [{$buildTime}]");

        return $treeRoot;
    }
}

