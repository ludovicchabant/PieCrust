<?php


/**
 * A StupidHttp_Log class that outputs to the console.
 */
class StupidHttp_ConsoleLog extends StupidHttp_Log
{
    protected $level;
    protected $isBuffering;
    protected $buffer;
    
    /**
     * Creates a new instance of StupidHttp_ConsoleLog.
     */
    public function __construct($level = StupidHttp_Log::TYPE_INFO)
    {
        $this->level = $level;
        $this->isBuffering = false;
        $this->buffer = '';
    }
    
    /**
     * Implementation of the StupidHttp_Log::log() function.
     */
    public function log($message, $type = StupidHttp_Log::TYPE_INFO)
    {
        if ($type <= $this->level)
        {
            $formattedMessage = '[' . StupidHttp_Log::messageTypeToString($type) . '] ' . $message . PHP_EOL;
            if ($this->isBuffering)
            {
                $this->buffer .= $formattedMessage;
            }
            else
            {
                echo $formattedMessage;
            }
        }
    }
    
    /**
     * For internal use.
     */
    public function _startBuffering()
    {
        $this->isBuffering = true;
    }
    
    /**
     * For internal use.
     */
    public function _endBuffering()
    {
        $this->isBuffering = false;
        if ($this->buffer)
        {
            echo $this->buffer;
        }
        $this->buffer = '';
    }
}

