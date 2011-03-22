<?php

/**
 * The context for a web request handler.
 */
class StupidHttp_HandlerContext
{
    protected $request;
    /**
     * Gets the web request.
     */
    public function getRequest()
    {
        return $this->request;
    }
    
    protected $response;
    /**
     * Gets the web response.
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    protected $log;
    /**
     * Gets the server log.
     */
    public function getLog()
    {
        return $this->log;
    }
    
    /**
     * Creates a new instance of StupidHttp_HandlerContext.
     */
    public function __construct(StupidHttp_WebRequest $request, StupidHttp_WebResponse $response, StupidHttp_Log $log)
    {
        $this->request = $request;
        $this->response = $response;
        $this->log = $log;
    }
}
