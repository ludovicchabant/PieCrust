<?php

namespace PieCrust\Baker\Processors;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * A convenient base class for file processors who only
 * process one input file into one output file.
 *
 * Extend this class and pass the input and output extensions
 * to its constructor. Then, implement the 'doProcess()' method.
 *
 * Look at the LessProcessor or SassProcessor classes for examples
 * of how to use this class.
 */
class SimpleFileProcessor implements IProcessor
{
    protected $pieCrust;
    protected $name;
    protected $priority;
    protected $inputExtensions;
    protected $outputExtensions;
    
    public function __construct($name, $inputExtensions, $outputExtensions, $priority = IProcessor::PRIORITY_DEFAULT)
    {
        if (is_array($inputExtensions))
        {
            $this->inputExtensions = $inputExtensions;
            
            if (is_array($outputExtensions))
            {
                if (count($inputExtensions) != count($outputExtensions)) throw new PieCrustException('The input and output extensions arrays must have the same length');
                $this->outputExtensions = $outputExtensions;
            }
            else
            {
                $this->outputExtensions = array_fill(0, count($inputExtensions), $outputExtensions);
            }
        }
        else
        {
            if (is_array($outputExtensions)) throw new PieCrustException('The output extensions parameter can only be an array if the input extensions parameter is an array of the same length.');
            $this->inputExtensions = array($inputExtensions);
            $this->outputExtensions = array($outputExtensions);
        }
        
        $this->name = $name;
        $this->priority = $priority;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    public function initialize(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getPriority()
    {
        return $this->priority;
    }
    
    public function supportsExtension($extension)
    {
        if ($extension == null or $extension == '') return false;
        return in_array($extension, $this->inputExtensions);
    }
    
    public function getOutputFilenames($filename)
    {
        $pathinfo = pathinfo($filename);
        if (!isset($pathinfo['extension']))
        {
            throw new PieCrustException("The filename doesn't have an extension -- " .
                                        "it should have been declared as unsupported by 'supportsExtension()'.");
        }
        
        $key = array_search($pathinfo['extension'], $this->inputExtensions);
        if ($key === false)
        {
            throw new PieCrustException("Extension '" . $pathinfo['extension'] . "' is not supported.");
        }
        
        return $pathinfo['filename'] . '.' . $this->outputExtensions[$key];
    }
    
    public function process($inputPath, $outputDir)
    {
        $outputPath = $outputDir . $this->getOutputFilenames(pathinfo($inputPath, PATHINFO_BASENAME));
        $this->doProcess($inputPath, $outputPath);
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
    }
}
