<?php

namespace PieCrust\Baker\Processors;

use PieCrust\PieCrust;


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
    
    public function initialize(PieCrust $pieCrust)
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
    
    public function getOutputFilenames($filename)
    {
        return basename($filename);
    }
    
    public function process($inputPath, $outputDir)
    {
        @copy($inputPath, $outputDir . basename($inputPath));
    }
}
