<?php


/**
 * A class responsible for building the response to a web request.
 */
class StupidHttp_ResponseBuilder
{
    protected $vfs;
    protected $preprocessor;
    protected $handlers;
    protected $log;

    /**
     * Builds a new instance of StupidHttp_ResponseBuilder.
     */
    public function __construct($vfs, $preprocessor, $handlers, $log)
    {
        $this->vfs = $vfs;
        $this->preprocessor = $preprocessor;
        $this->handlers = $handlers;
        $this->log = $log;
    }

    /**
     * Runs the builder, and returns the web response.
     */
    public function run($options, $request)
    {
        // Run the preprocessor, if any.
        if ($this->preprocessor != null)
        {
            $this->log->debug("Preprocessing '{$request->getUri()}'...");
            $func = $this->preprocessor;
            $func($request);
            $this->log->debug("Done preprocessing '{$request->getUri()}'.");
        }

        // See if the request maps to an existing file on our VFS.
        $handled = false;
        $documentPath = $this->vfs->getDocumentPath($request->getUriPath());
        if ($documentPath != null)
        {
            if ($request->getMethod() == 'GET' and is_file($documentPath))
            {
                // Serve existing file...
                $this->log->debug('Serving static file: ' . $documentPath);
                return $this->vfs->serveDocument($request, $documentPath);
            }
            else if ($request->getMethod() == 'GET' and is_dir($documentPath))
            {
                $indexPath = $this->vfs->getIndexDocument($documentPath);
                if ($indexPath != null)
                {
                    // Serve a directory's index file... 
                    $this->log->debug('Serving static file: ' . $indexPath);
                    return $this->vfs->serveDocument($request, $indexPath);
                }
                else if (
                    $options['list_directories'] and
                    (
                        $options['list_root_directory'] or 
                        $request->getUriPath() != '/'
                    )
                )
                {
                    // Serve the directory's contents...
                    $this->log->debug('Serving directory: ' . $documentPath);
                    return $this->vfs->serveDirectory($request, $documentPath);
                }
            }
        }
        
        // No static file/directory matched. Run the registered handlers.
        if (isset($this->handlers[$request->getMethod()]))
        {
            // Run the request handlers.
            foreach ($this->handlers[$request->getMethod()] as $handler)
            {
                if ($handler->_isMatch($request->getUri()))
                {
                    $response = new StupidHttp_WebResponse();
                    $context = new StupidHttp_HandlerContext($request, $response, $this->log);
                    $this->log->debug('--- Starting request handler for ' . $request->getUri() . ' ---');
                    $this->log->_startBuffering();
                    ob_start();
                    try
                    {
                        $handler->_run($context);
                    }
                    catch (Exception $e)
                    {
                        ob_end_clean();
                        $this->log->_endBuffering();
                        $this->log->debug('--- Finished request handler ---');
                        $this->log->error("Handler error for URI '" . $request->getUri() . "': " . strval($e));
                        return new StupidHttp_WebResponse(500);
                    }
                    $body = ob_get_clean();
                    $response->setBody($body);
                    $this->log->_endBuffering();
                    $this->log->debug('--- Finished request handler ---');
                    return $response;
                }
            }
        }
        
        if ($request->getMethod() == 'GET')
        {
            return new StupidHttp_WebResponse(404);  // Not found.
        }
        else
        {
            return new StupidHttp_WebResponse(501);  // Method not implemented.
        }
    }
}

