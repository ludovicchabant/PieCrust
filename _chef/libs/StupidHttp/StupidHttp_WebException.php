<?php

/**
 * The exception class for the StupidHttp library.
 */
class StupidHttp_WebException extends Exception
{
    /**
     * Creates a new instance of StupidHttp_WebException.
     */
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
