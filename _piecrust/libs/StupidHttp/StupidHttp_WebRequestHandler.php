<?php


/**
 * A class that handles a set of specific requests given
 * a URI pattern and a callback.
 */
class StupidHttp_WebRequestHandler
{
    protected $server;
    protected $uriPattern;
    protected $uriPatternMatches;
    protected $callback;
    
    /**
     * Creates a new StupidHttp_WebRequestHandler.
     */
    public function __construct(StupidHttp_WebServer $server, $uriPattern)
    {
        $this->server = $server;
        $this->uriPattern = $uriPattern;
        $this->uriPatternMatches = array();
    }
    
    /**
     * Specifies the callback to use if a matching HTTP request comes up.
     */
    public function call($callback)
    {
        if (!is_callable($callback))
        {
            throw new StupidHttp_WebException('The given callback is not a callable PHP object.');
        }
        $this->callback = $callback;
        return $this;
    }
    
    /**
     * Internal use only.
     */
    public function _isMatch($uri)
    {
        if ($this->callback == null) return false;
        return preg_match('|' . $this->uriPattern . '|i', $uri, $this->uriPatternMatches);
    }
    
    /**
     * Internal use only.
     */
    public function _run($context)
    {
        $callback = $this->callback;
        
        if (count($this->uriPatternMatches) > 1)
        {
            $callback($context, $this->uriPatternMatches);
        }
        else
        {
            $callback($context);
        }
    }
}
