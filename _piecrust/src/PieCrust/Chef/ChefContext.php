<?php

namespace PieCrust\Chef;

require_once 'Log.php';
require_once 'Console/CommandLine.php';

use \Log;
use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/*
 * The context for a `chef` command.
 */
class ChefContext
{
    protected $app;
    /**
     * Gets the PieCrust app related to the command being run.
     */
    public function getApp()
    {
        return $this->app;
    }

    protected $result;
    /**
     * Returns the parser result.
     */
    public function getResult()
    {
        return $this->result;
    }

    protected $log;
    /**
     * Returns the logger.
     */
    public function getLog()
    {
        return $this->log;
    }

    public function __construct(IPieCrust $pieCrust, Console_CommandLine_Result $result, Log $log)
    {
        $this->app = $pieCrust;
        $this->result = $result;
        $this->log = $log;
    }
}

