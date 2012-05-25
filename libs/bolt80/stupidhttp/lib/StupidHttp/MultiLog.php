<?php


/**
 * A StupidHttp_Log class that aggregates multiple other logs.
 */
class StupidHttp_MultiLog extends StupidHttp_Log
{
    protected $logs;
    
    /**
     * Creates a new instance of StupidHttp_MultiLog.
     */
    public function __construct(array $logs)
    {
        $this->logs = $logs;
    }
    
    /**
     * Implementation of the StupidHttp_Log::log() function.
     */
    public function log($message, $type = StupidHttp_Log::TYPE_INFO)
    {
        foreach ($this->logs as $log)
        {
            $log->log($message, $type);
        }
    }
    
    /**
     * For internal use.
     */
    public function _startBuffering()
    {
        foreach ($this->logs as $log)
        {
            $log->_startBuffering();
        }
    }
    
    /**
     * For internal use.
     */
    public function _endBuffering()
    {
        foreach ($this->logs as $log)
        {
            $log->_endBuffering();
        }
    }
}

