<?php

namespace PieCrust\Mock;

use PieCrust\IPieCrust;
use PieCrust\Baker\IBaker;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\PieCrustConfiguration;


class MockProcessor implements IProcessor
{
    public $name;
    public $priority;
    public $extensions;
    public $callback;

    public function __construct($name, $extensions, $callback = null, $priority = IProcessor::PRIORITY_DEFAULT)
    {
        $this->name = $name;
        $this->priority = $priority;
        $this->callback = $callback;

        if (!is_array($extensions))
            $extensions = array($extensions => $extensions);
        $this->extensions = $extensions;
    }

    public function getName()
    {
        return $this->name;
    }

    public function initialize(IPieCrust $pieCrust, $logger = null)
    {
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function onBakeStart(IBaker $baker)
    {
    }

    public function supportsExtension($extension)
    {
        return isset($this->extensions[$extension]);
    }

    public function isBypassingStructuredProcessing()
    {
        return false;
    }
    
    public function isDelegatingDependencyCheck()
    {
        return true;
    }

    public function getDependencies($path)
    {
        return null;
    }

    public function getOutputFilenames($filename)
    {
        $inputExtension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!isset($this->extensions[$inputExtension]))
            throw new \Exception("Extension '{$inputExtension}' wasn't mapped to any output extension.");

        $outputExtension = $this->extensions[$inputExtension];
        $outputFilename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $outputExtension;

        return $outputFilename;
    }

    public function process($inputPath, $outputDir)
    {
        $contents = file_get_contents($inputPath);
        if ($this->callback)
        {
            $callback = $this->callback;
            $contents = $callback($contents);
        }

        $outputFilename = $this->getOutputFilenames($inputPath);
        $outputPath = $outputDir . $outputFilename;
        file_put_contents($outputPath, $contents);
    }

    public function onBakeEnd()
    {
    }
}

