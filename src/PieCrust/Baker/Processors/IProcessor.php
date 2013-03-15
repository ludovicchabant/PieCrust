<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\Baker\IBaker;


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
    public function initialize(IPieCrust $pieCrust, $logger = null);
    
    /**
     * Gets the priority of this processor.
     *
     * Processors are asked in order of priority whether they support a file
     * given an extension.
     */
    public function getPriority();

    /**
     * Gets called before baking the site.
     */
    public function onBakeStart(IBaker $baker);
    
    /**
     * Returns whether this processor should process a file with the given
     * extension.
     */
    public function supportsExtension($extension);

    /**
     * Returns `true` if this processor wants to bypass the tree-based file
     * processing and will write output files on its own.
     * Warning: this means this processor can't be chained with other
     * processors, and won't benefit from PieCrust's processing optimizations.
     */
    public function isBypassingStructuredProcessing();

    /**
     * Returns `true` if PieCrust should handle dependency checking with 
     * `getDependencies`, and `false` if the processor handles its own 
     * dependency checks.
     */
    public function isDelegatingDependencyCheck();

    /**
     * Gets zero or more input dependency file names for the given input file.
     */
    public function getDependencies($path);
    
    /**
     * Gets one or more output file names for the given input file.
     */
    public function getOutputFilenames($filename);
    
    /**
     * Processes the given input file. The resulting, processed file should be
     * placed in the given output directory.
     */
    public function process($inputPath, $outputDir);

    /**
     * Gets called after baking the site.
     */
    public function onBakeEnd();
}
