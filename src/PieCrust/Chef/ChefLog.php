<?php

namespace PieCrust\Chef;

use \Log;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * A PEAR log with pretty colors, at least on Mac/Linux.
 */
class ChefLog extends \Log_composite
{
    const LOG_CONSOLE = 0;
    const LOG_FILE = 1;

    protected $consoleLog;
    protected $fileLog;

    public function __construct($name, $ident = '', $conf = array(), $level = PEAR_LOG_DEBUG)
    {
        parent::__construct($name, $ident, $conf, $level);
        
        $this->consoleLog = new ChefLogConsole('ChefConsole');
        $this->addChild($this->consoleLog);
    }

    public function setChildMask($which, $mask)
    {
        switch ($which)
        {
        case self::LOG_CONSOLE:
            $this->consoleLog->setMask($mask);
            break;
        case self::LOG_FILE:
            $this->logFile->setMask($mask);
            break;
        default:
            throw new PieCrustException("No such child for the Chef log: {$which}");
        }
    }

    public function addFileLog($path)
    {
        $this->fileLog = Log::factory('file', $path, 'ChefFile');
        $this->addChild($this->fileLog);
    }

    public function convertColors($str)
    {
        return $this->consoleLog->convertColors($str);
    }    

    public function exception($e, $debugMode = false)
    {
        $log = $this;
        if ($debugMode)
        {
            $log->emerg($e->getMessage());
            $log->debug($e->getTraceAsString());
            $e = $e->getPrevious();
            while ($e)
            {
                $log->err("-----------------");
                $log->err($e->getMessage());
                $log->debug($e->getTraceAsString());
                $e = $e->getPrevious();
            }
            $log->err("-----------------");
        }
        else
        {
            $log->emerg($e->getMessage());
            $e = $e->getPrevious();
            while ($e)
            {
                $log->err($e->getMessage());
                $e = $e->getPrevious();
            }
        }
    }
}
