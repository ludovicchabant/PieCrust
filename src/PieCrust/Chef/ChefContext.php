<?php

namespace PieCrust\Chef;

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

    protected $debuggingEnabled;
    /**
     * Returns whether the command should print debug messages.
     */
    public function isDebuggingEnabled()
    {
        return $this->debuggingEnabled;
    }

    public function __construct(IPieCrust $pieCrust, Console_CommandLine_Result $result, Log $log)
    {
        $this->app = $pieCrust;
        $this->result = $result;
        $this->log = $log;
        $this->debuggingEnabled = false;
    }

    public function setVerbosity($verbosity)
    {
        switch ($verbosity)
        {
        case 'debug':
            $this->log->setChildMask(ChefLog::LOG_CONSOLE, PEAR_LOG_ALL);
            $this->debuggingEnabled = true;
            break;
        case 'quiet':
            $this->log->setChildMask(ChefLog::LOG_CONSOLE, Log::MAX(PEAR_LOG_WARNING));
            break;
        default:
            $this->log->setChildMask(ChefLog::LOG_CONSOLE, Log::MAX(PEAR_LOG_INFO));
            break;
        }
    }
}

