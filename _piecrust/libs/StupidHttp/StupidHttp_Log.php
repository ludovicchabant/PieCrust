<?php


/**
 * The base class for a logger for the StupidHttp_WebServer.
 */
class StupidHttp_Log
{
    // {{{ Message types
    const TYPE_CRITICAL = 0;
    const TYPE_ERROR = 1;
    const TYPE_WARNING = 2;
    const TYPE_INFO = 3;
    const TYPE_DEBUG = 4;
    // }}}
    
    /**
     * Logs a message (abstract implementation).
     */
    public function log($message, $type)
    {
        return false;
    }
    
    /**
     * Logs a critical message.
     */
    public function logCritical($message)
    {
        $this->log($message, self::TYPE_CRITICAL);
    }
    
    /**
     * Logs an error message.
     */
    public function logError($message)
    {
        $this->log($message, self::TYPE_ERROR);
    }
    
    /**
     * Logs a warning message.
     */
    public function logWarning($message)
    {
        $this->log($message, self::TYPE_WARNING);
    }
    
    /**
     * Logs an informational message.
     */
    public function logInfo($message)
    {
        $this->log($message, self::TYPE_INFO);
    }
    
    /**
     * Logs a debug message.
     */
    public function logDebug($message)
    {
        $this->log($message, self::TYPE_DEBUG);
    }
    
    /**
     * For internal use.
     */
    public function _startBuffering() {}
    public function _endBuffering() {}
    
    /**
     * Gets the string representation of a message type.
     */
    public static function messageTypeToString($type)
    {
        switch ($type)
        {
        case self::TYPE_CRITICAL:
            return "CRITICAL";
        case self::TYPE_ERROR:
            return "ERROR";
        case self::TYPE_WARNING:
            return "WARNING";
        case self::TYPE_INFO:
            return "INFO";
        case self::TYPE_DEBUG:
            return "DEBUG";
        }
    }
}
