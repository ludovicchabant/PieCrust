<?php


/**
 * A StupidHttp_Log class that outputs to the console.
 */
class StupidHttp_ConsoleLog extends StupidHttp_Log
{
    /**
     * Creates a new instance of StupidHttp_ConsoleLog.
     */
    public function __construct()
    {
    }
    
    /**
     * Implementation of the StupidHttp_Log::log() function.
     */
    public function log($message, $type = StupidHttp_Log::TYPE_INFO)
    {
        if ($type > StupidHttp_Log::TYPE_INFO)
        {
            echo '[' . StupidHttp_Log::messageTypeToString($type) . '] ' . $message . PHP_EOL;
        }
    }
}

