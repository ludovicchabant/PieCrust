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
    protected $rootDir;
    protected $tmpDir;
    protected $outDir;

    protected $processors;

    public function __construct($rootDir, $tmpDir, $outDir, array $processors)
    {
        $this->rootDir = $rootDir;
        $this->tmpDir = $tmpDir;
        $this->outDir = $outDir;
        $this->processors = $processors;
    }

    public function build($relativeInputFile)
    {
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

        return $treeRoot;
    }
}

