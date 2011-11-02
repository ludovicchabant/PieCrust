<?php


/**
 * A class that represents the HTTP request.
 */
class StupidHttp_WebRequest
{
    protected $server;
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
    
    protected $serverVariables;
    /**
     * Gets the server variables (emulated $_SERVER) for the request.
     */
    public function getServerVariables()
    {
        if ($this->serverVariables === null)
        {
            $this->serverVariables = $this->buildServerVariables();
        }
        return $this->serverVariables;
    }
    
    protected $queryVariables;
    /**
     * Gets the query variables (emulated $_GET) for the request.
     */
    public function getQueryVariables()
    {
        if ($this->queryVariables === null)
        {
            $this->queryVariables = $this->buildQueryVariables();
        }
        return $this->queryVariables;
    }
    
    /**
     * Creates a new instance of StupidHttp_WebRequest.
     */
    public function __construct(StupidHttp_WebServer $server, $rawLines)
    {
        if (count($rawLines) < 1) throw new StupidHttp_WebException('The raw request must contain at least one line.', 0, '400 Bad Request');
        
        $matches = array();
        if (!preg_match('/([A-Z]+)\s+([^\s]+)\s+(HTTP\/1\.\d)/', $rawLines[0], $matches))
        {
            throw new StupidHttp_WebException('Unexpected request header format.', 0, '400 Bad Request');
        }
        $this->method = $matches[1];
        $this->uri = ($matches[2] == '/' ? '/' : rtrim($matches[2], '/'));
        $this->version = $matches[3];
        
        $this->headers = array();
        for ($i = 1; $i < count($rawLines); ++$i)
        {
            $header = explode(':', $rawLines[$i]);
            $this->headers[trim($header[0])] = trim($header[1]);
        }
        
        $this->server = $server;
    }
    
    protected function buildServerVariables()
    {
        $uri = parse_url($this->getUri());
        $server = array();
        
        $server['REQUEST_METHOD'] = $this->getMethod();
        $server['SERVER_NAME'] = $this->server->getAddress();
        $server['SERVER_PORT'] = $this->server->getPort();
        $server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $server['QUERY_STRING'] = isset($uri['query']) ? $uri['query'] : null;
        $server['REQUEST_URI'] = $this->getUri();
        $server['REQUEST_TIME'] = time();
        $server['argv'] = array();
        $server['argc'] = 0;
        
        $headers = $this->getHeaders();
        foreach ($headers as $key => $value)
        {
            $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($key));
            $server[$serverKey] = $value;
        }
        
        return $server;
    }
    
    protected function buildQueryVariables()
    {
        $url = parse_url($this->getUri());
        $get = array();
        if (isset($url['query']))
        {
            parse_str($url['query'], $get);
        }
        return $get;
    }
}
