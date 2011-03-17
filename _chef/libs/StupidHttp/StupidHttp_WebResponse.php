<?php

/**
 *
 */
class StupidHttp_WebResponse
{
    protected $uri;
    
    public function getUri()
    {
        return $this->uri;
    }
    
    protected $serverVariables;
    
    public function getServerVariables()
    {
        return $this->serverVariables;
    }
    
    public function __construct($uri, $serverVariables)
    {
        $this->uri = $uri;
        $this->serverVariables = $serverVariables;
    }
    
    protected $status;
    
    public function getStatus()
    {
        return $this->status;
    }
    
    public function setStatus($status)
    {
        $this->status = $status;
    }
    
    protected $headers;
    
    public function getHeaders()
    {
        return $this->headers;
    }
    
    public function addHeader($header)
    {
        $this->headers[] = $header;
    }
    
    protected $log;
    
    public function getLog()
    {
        return $this->log;
    }
    
    public function addLog($log)
    {
        $this->log .= $log;
    }
}

