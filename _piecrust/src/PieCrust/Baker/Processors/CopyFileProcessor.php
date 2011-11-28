<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class CopyFileProcessor implements IProcessor
{
    protected $rootDirLength;
    
    public function getName()
    {
        return "copy";
    }
    
    public function __construct()
    {
    }
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->rootDirLength = strlen($pieCrust->getRootDir());
    }
    
    public function getPriority()
    {
        return IProcessor::PRIORITY_LOW;
    }
    
    public function supportsExtension($extension)
    {
        return true;
    }
    
    public function getDependencies($path)
    {
        return null;
    }

    public function getOutputFilenames($filename)
    {
        return basename($filename);
    }
    
    public function process($inputPath, $outputDir)
    {
        $outputPath = $outputDir . basename($inputPath);
        if (@copy($inputPath, $outputPath) == false)
            throw new PieCrustException("Can't copy '".$inputPath."' to '".$outputPath."'.");
    }
}
