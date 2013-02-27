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
    
    // {{{ Default formats
    const DEFAULT_REQUEST_DATE_FORMAT = "Y/m/d H:i:s";
    const DEFAULT_REQUEST_LOG_FORMAT = "[%date%] %client_ip% --> %method% %path% --> %status% %status_name% [%time%ms]";
    // }}}
    
    public function __construct()
    {
        $this->dateFormat = self::DEFAULT_REQUEST_DATE_FORMAT;
        $this->requestFormat = self::DEFAULT_REQUEST_LOG_FORMAT;
    }

    protected $dateFormat;
    /**
     * Gets the date format.
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Sets the date format.
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
    }

    protected $requestFormat;
    /**
     * Returns the request format.
     */
    public function getRequestFormat()
    {
        return $this->requestFormat;
    }

    /**
     * Sets the request format.
     */
    public function setRequestFormat($format)
    {
        $this->requestFormat = $format;
    }
    
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
    public function critical($message)
    {
        $this->log($message, self::TYPE_CRITICAL);
    }
    
    /**
     * Logs an error message.
     */
    public function error($message)
    {
        $this->log($message, self::TYPE_ERROR);
    }
    
    /**
     * Logs a warning message.
     */
    public function warning($message)
    {
        $this->log($message, self::TYPE_WARNING);
    }
    
    /**
     * Logs an informational message.
     */
    public function info($message)
    {
        $this->log($message, self::TYPE_INFO);
    }
    
    /**
     * Logs a debug message.
     */
    public function debug($message)
    {
        $this->log($message, self::TYPE_DEBUG);
    }

    /**
     * Logs a request.
     */
    public function logRequest($request, $requestInfo, $response)
    {
        $statusName = StupidHttp_WebServer::getHttpStatusHeader($response->getStatus());
        $replacements = array(
            '%date%' => date($this->dateFormat),
            '%client_ip%' => $requestInfo['address'],
            '%client_port%' => $requestInfo['port'],
            '%method%' => $request->getMethod(),
            '%uri%' => $request->getUri(),
            '%path%' => $request->getUriPath(),
            '%status%' => $response->getStatus(),
            '%status_name%' => $statusName,
            '%time%' => $requestInfo['time']
        );
        $this->info(
            str_replace(
                array_keys($replacements),
                array_values($replacements),
                $this->requestFormat
            )
        );
    }

    /**
     *
     */
    
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
