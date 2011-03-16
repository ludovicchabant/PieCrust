<?php


class StupidHttp_WebRequestHandler
{
    protected $server;
    protected $uriPattern;
    protected $uriPatternMatches;
    protected $callback;
    
    public function __construct(StupidHttp_WebServer $server, $uriPattern)
    {
        $this->server = $server;
        $this->uriPattern = '/' . $uriPattern . '/';
        $this->uriPatternMatches = array();
    }
    
    public function call($callback)
    {
        if (!is_callable($callback))
        {
            throw new StupidHttp_WebException('The given callback is not a callable PHP object.');
        }
        $this->callback = $callback;
        return $this;
    }
    
    public function _isMatch($uri)
    {
        return preg_match($this->uriPattern, $uri, $this->uriPatternMatches);
    }
    
    public function _run($response)
    {
        $callback = $this->callback;
        
        $matches = array();
        if (count($this->uriPatternMatches) > 1)
        {
            return $callback($response, $matches);
        }
        return $callback($response);
    }
}
