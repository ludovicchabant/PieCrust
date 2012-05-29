<?php

/**
 * A class that represents an HTTP response.
 */
class StupidHttp_WebResponse
{
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
     * Gets a formatted version of the HTTP headers that should be returned.
     */
    public function getFormattedHeaders()
    {
        $res = array();
        if ($this->headers != null)
        {
            foreach ($this->headers as $header => $value)
            {
                $res[] = $header . ': ' . $value;
            }
        }
        return $res;
    }
    
    /**
     * Gets a specific HTTP header.
     */
    public function getHeader($header)
    {
        if (!isset($this->headers[$header])) return null;
        return $this->headers[$header];
    }
    
    /**
     * Sets an HTTP header to return.
     */
    public function setHeader($header, $value)
    {
        $this->headers[$header] = $value;
    }
    
    protected $body;
    /**
     * Gets the response body.
     */
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     * Sets the response body.
     */
    public function setBody($body)
    {
        $this->body = $body;
    }
    
    /**
     * Creates a new instance of StupidHttp_WebResponse.
     */
    public function __construct($status = 200, array $headers = array(), $body = null)
    {
        if (!is_int($status))
            throw new StupidHttp_WebException('The given HTTP return code was not an integer: ' . $code, 500);

        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }
}

