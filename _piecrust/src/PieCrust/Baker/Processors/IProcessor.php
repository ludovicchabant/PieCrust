<?php

namespace PieCrust\Baker\Processors;

use PieCrust\PieCrust;


/**
 * The base interface for baking processors.
 */
interface IProcessor
{
    /// {{{ Priorities
    const PRIORITY_HIGH = 1;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = -1;
    /// }}}
    
    /**
     * Gets the name of the processor, for enabling/disabling from the app configuration.
     */
    public function getName();
    
    /**
     * Initializes a file processor with the given PieCrust instance.
     */
    public function initialize(PieCrust $pieCrust);
    
    /**
     * Gets the priority of this processor.
     *
     * Processors are asked in order of priority whether they support a file
     * given an extension.
     */
    public function getPriority();
    
    /**
     * Returns whether this processor should process a file with the given
     * extension.
     */
    public function supportsExtension($extension);
    
    /**
     * Gets one or more output file names for the given input file.
     */
    public function getOutputFilenames($filename);
    
    /**
     * Processes the given input file. The resulting, processed file should be
     * placed in the given output directory.
     */
    public function process($inputPath, $outputDir);
}
