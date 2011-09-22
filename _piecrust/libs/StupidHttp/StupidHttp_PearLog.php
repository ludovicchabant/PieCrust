<?php

require_once 'Log/Log.php';


/**
 * A StupidHttp_Log class that wraps a PEAR logger.
 */
class StupidHttp_PearLog extends StupidHttp_Log
{
    /**
     * Helper method that creates a StupidHttp_PearLog out of a PEAR logger singleton.
     */
    public static function fromSingleton($handler, $path, $ident = '', $conf = array(), $level = PEAR_LOG_DEBUG)
    {
        $logger = Log::singleton($handler, $path, $ident, $conf, $level);
        return new StupidHttp_PearLog($logger);
    }

    protected $log;
    /**
     * Creates a new instance of StupidHttp_PearLog wrapped around the given PEAR logger.
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }
    
    /**
     * Implementation of the StupidHttp_Log::log() function.
     */
    public function log($message, $type = StupidHttp_Log::TYPE_INFO)
    {
        $pearType = PEAR_LOG_INFO;
        switch ($type)
        {
        case StupidHttp_Log::TYPE_CRITICAL:
            $pearType = PEAR_LOG_EMERG;
            break;
        case StupidHttp_Log::TYPE_ERROR:
            $pearType = PEAR_LOG_ERR;
            break;
        case StupidHttp_Log::TYPE_WARNING:
            $pearType = PEAR_LOG_WARNING;
            break;
        case StupidHttp_Log::TYPE_INFO:
            $pearType = PEAR_LOG_INFO;
            break;
        case StupidHttp_Log::TYPE_DEBUG:
            $pearType = PEAR_LOG_DEBUG;
            break;
        }
        $this->log->log($message, $pearType);
    }
}

