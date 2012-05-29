<?php


/**
 * A class that represents the HTTP request.
 */
class StupidHttp_WebRequest
{
    protected $serverInfo;
    protected $method;
    protected $uri;
    protected $parsedUri;
    protected $version;
    protected $headers;
    protected $body;
    
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
     * Gets the path part of the URI.
     */
    public function getUriPath()
    {
        return $this->parsedUri['path'];
    }

    /**
     * Gets the query part of the URI.
     */
    public function getUriQuery()
    {
        return $this->parsedUri['query'];
    }

    /**
     * Gets the fragment part of the URI.
     */
    public function getUriFragment()
    {
        return $this->parsedUri['fragment'];
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
     * Gets the request's body (e.g. with a POST request).
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Gets the request's body after decoding.
     */
    public function getFormData()
    {
        if ($this->getMethod() != 'POST')
            return null;
        if (!$this->body)
            return null;

        $vars = array();
        parse_str($this->body, $vars);
        return $vars;
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
    public function __construct(array $serverInfo, array $rawLines, $rawBody = null)
    {
        if (count($rawLines) < 1)
        {
            throw new StupidHttp_WebException('The raw request must contain at least one line.', 0, '400 Bad Request');
        }
        
        // Parse the request line.
        $matches = array();
        if (!preg_match('/([A-Z]+)\s+([^\s]+)\s+(HTTP\/1\.\d)/', $rawLines[0], $matches))
        {
            throw new StupidHttp_WebException('Unexpected request header format: ' . $rawLines[0], 0, '400 Bad Request');
        }
        $this->method = $matches[1];
        $this->uri = $matches[2];
        $this->parsedUri = parse_url($this->uri);
        $this->version = $matches[3];
        
        // Parse the header lines.
        $this->headers = array();
        $rawLinesCount = count($rawLines);
        for ($i = 1; $i < $rawLinesCount; ++$i)
        {
            $header = explode(':', $rawLines[$i]);
            $this->headers[trim($header[0])] = trim($header[1]);
        }
        
        // Get the rest.
        $this->body = $rawBody;
        $this->serverInfo = $serverInfo;
    }
    
    protected function buildServerVariables()
    {
        $uri = parse_url($this->getUri());
        $server = $this->serverInfo;
        
        $server['REQUEST_METHOD'] = $this->getMethod();
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
