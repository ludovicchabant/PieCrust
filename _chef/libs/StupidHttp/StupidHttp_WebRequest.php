<?php


/**
 * A class that represents the HTTP request.
 */
class StupidHttp_WebRequest
{
    protected $method;
    protected $uri;
    protected $version;
    protected $headers;
    
    /**
     * Gets the HTTP method for the request (GET, POST, etc.).
     */
    public function getMethod()
    {
        return $this->method;
    }
    
    /**
     * Gets the URI being requested.
     */
    public function getUri()
    {
        return $this->uri;
    }
    
    /**
     * Gets the HTTP version used ('HTTP/1.0' or 'HTTP/1.1').
     */
    public function getVersion()
    {
        return $this->version;
    }
    
    /**
     * Gets the headers for the request.
     *
     * This is an associative array with the header names as keys
     * and the header values as, well, values.
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    
    /**
     * Gets a specific HTTP header, if it exists.
     */
    public function getHeader($headerName)
    {
        if (isset($this->headers[$headerName])) return $this->headers[$headerName];
        return null;
    }
    
    /**
     * Creates a new instance of StupidHttp_WebRequest.
     */
    public function __construct($rawLines)
    {
        if (count($rawLines) < 1) throw new StupidHttp_WebException('The raw request must contain at least one line.', 0, '400 Bad Request');
        
        $matches = array();
        if (!preg_match('/([A-Z]+)\s+([^\s]+)\s+(HTTP\/1\.\d)/', $rawLines[0], $matches))
        {
            throw new StupidHttp_WebException('Unexpected request header format.', 0, '400 Bad Request');
        }
        $this->method = $matches[1];
        $this->uri = $matches[2];
        $this->version = $matches[3];
        
        $this->headers = array();
        for ($i = 1; $i < count($rawLines); ++$i)
        {
            $header = explode(':', $rawLines[$i]);
            $this->headers[trim($header[0])] = trim($header[1]);
        }
    }
}
