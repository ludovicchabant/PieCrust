<?php

namespace PieCrust\Formatters;

use PieCrust\PieCrust;


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
    public function initialize(PieCrust $pieCrust);
    
    /**
     * Gets the priority of the formatter, which defines the relative order in which
     * it will be asked for whether it supports a given format (see format()).
     */
    public function getPriority();
    
    /**
     * Should return whether the formatter supports the given format.
     * @param bool $isUnformatted Whether the text being formatted has already been
     *                            formatted by another formatter with higher priority.
     */
    public function supportsFormat($format, $isUnformatted);
    
    /**
     * Should return the formatted text.
     */
    public function format($text);
}

