<?php

use PieCrust\IPieCrust;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\PieCrustConfiguration;


class MockProcessor implements IProcessor
{
    public $name;
    public $priority;
    public $extensions;

    public function __construct($name, $extensions, $priority = IFormatter::PRIORITY_DEFAULT)
    {
        $this->name = $name;
        $this->priority = $priority;

        if (!is_array($extensions))
            $formats = array($extensions);
        $this->extensions = $extensions;
    }

    public function getName()
    {
        return $this->name;
    }

    public function initialize(IPieCrust $pieCrust)
    {
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function supportsExtension($extension)
    {
        return in_array($extension, $this->extensions);
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
        return $filename;
    }

    public function process($inputPath, $outputDir)
    {
    }
}

