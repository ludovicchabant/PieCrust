<?php

/**
 * A class that represents an HTTP response.
 */
class StupidHttp_WebResponse
{
    protected $uri;
    /**
     * Gets the requested URI.
     */
    public function getUri()
    {
        return $this->uri;
    }
    
    protected $serverVariables;
    /**
     * Gets the server variables (emulated $_SERVER) for the request.
     */
    public function getServerVariables()
    {
        return $this->serverVariables;
    }
    
    /**
     * Creates a new instance of StupidHttp_WebResponse.
     */
    public function __construct($uri, $serverVariables, $log)
    {
        $this->uri = $uri;
        $this->serverVariables = $serverVariables;
        $this->log = $log;
    }
    
    protected $status;
    /**
     * Gets the HTTP status code that should be returned.
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Sets the HTTP status code that should be returned.
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }
    
    protected $headers;
    /**
     * Gets the HTTP headers that should be returned.
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    
    /**
     * Gets a specific HTTP header.
     */
    public function getHeader($header)
    {
        return $this->headers[$header];
    }
    
    /**
     * Adds an HTTP header to return.
     */
    public function addHeader($header)
    {
        $this->headers[] = $header;
    }
    
    protected $log;
    /**
     * Gets the text that will be logged by the StupidHttp_WebServer.
     */
    public function getLog()
    {
        return $this->log;
    }
}

