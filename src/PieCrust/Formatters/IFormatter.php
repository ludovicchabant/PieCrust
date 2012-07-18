<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


/**
 * The base interface for PieCrust page formatters.
 *
 */
interface IFormatter
{
    /// {{{ Priorities
    const PRIORITY_HIGH = 1;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = -1;
    /// }}}
    
    /**
     * Initializes the formatter.
     *
     * This function should do minimal processing (ideally just store a reference
     * to the given PieCrust app) because all formatters are initialized regardless
     * of their being actually used. Instead, including library files and creating
     * the actual formatter implementation should be done the first time format()
     * is called.
     */
    public function initialize(IPieCrust $pieCrust);
    
    /**
     * Gets the priority of the formatter, which defines the relative order in which
     * it will be asked for whether it supports a given format (see format()).
     */
    public function getPriority();

    /**
     * Gets whether the formatter is exclusive, i.e. only wants unformatted text.
     *
     * Each page needs to be formatted with exactly one 'exclusive' formatter.
     * Non-'exclusive' formatters can then further modify the text. Those formatters
     * would usually have a 'LOW' priority to come after the main formatters.
     */
    public function isExclusive();
    
    /**
     * Returns whether the formatter supports the given format.
     */
    public function supportsFormat($format);
    
    /**
     * Should return the formatted text.
     */
    public function format($text);
}

