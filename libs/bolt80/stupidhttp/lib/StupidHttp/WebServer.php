<?php


/**
 *  A very, very stupid HTTP web server.
 */
class StupidHttp_WebServer
{
    // Server Properties {{{
    protected $driver;
    /**
     * Gets the driver.
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Gets the root directory for the served documents.
     */
    public function getDocumentRoot()
    {
        return $this->driver->getVfs()->getDocumentRoot();
    }
    
    protected $address;
    /**
     * Gets the IP address (or host name) of the server.
     */
    public function getAddress()
    {
        return $this->driver->getNetworkHandler()->getAddress();
    }
    
    protected $port;
    /**
     * Gets the port of the server.
     */
    public function getPort()
    {
        return $this->driver->getNetworkHandler()->getPort();
    }
    
    /**
     * Gets the mime types used by the server.
     *
     * This is an associative array where file extensions are keys and
     * HTTP mime types are values.
     */
    public function getMimeTypes()
    {
        return $this->driver->getVfs()->getMimeTypes();
    }
    
    /**
     * Sets the mime types to be used by the server.
     */
    public function setMimeTypes($mimeTypes)
    {
        $this->driver->getVfs()->setMimeTypes($mimeTypes);
    }
    
    /**
     * Sets a specific mime type for a given file extension.
     */
    public function setMimeType($extension, $mimeType)
    {
        $this->driver->getVfs()->setMimeType($extension, $mimeType);
    }
    
    /**
     * Gets the logger.
     */
    public function getLog()
    {
        return $this->driver->getLog();
    }
    
    /**
     * Clears the current logs, and adds the given one.
     */
    public function setLog($log)
    {
        if ($log == null)
            $log = new StupidHttp_Log();
        $this->driver->setLog($log);
    }
    // }}}
    
    // Construction and Destruction {{{
    /**
     * Create a new instance of the stupid HTTP web server.
     */
    public function __construct($documentRoot = null, $port = 8080, $address = 'localhost')
    {
        $log = new StupidHttp_ConsoleLog();
        $vfs = new StupidHttp_VirtualFileSystem($documentRoot);
        $handler = new StupidHttp_SocketNetworkHandler($address, $port);
        $this->driver = new StupidHttp_Driver($this, $vfs, $handler, $log);
    }
    
    /**
     * Destructor for the StupidHttp_WebServer.
     */
    public function __destruct()
    {
        if ($this->driver != null)
        {
            $this->getLog()->info("Shutting server down...");
            $this->driver->unregister();
        }
    }
    // }}}
    
    // Routes, Preprocessors and Mounted Directories {{{
    /**
     * Adds a route to match requests against, and returns the handler.
     */
    public function on($method, $uri)
    {
        $uri = '/' . trim($uri, '/');
        $uriPattern = '^' . preg_quote($uri, '|') . '$';
        return $this->onPattern($method, $uriPattern);
    }
    
    /**
     * Adds a route pattern to match requests against, and returns the handler.
     */
    public function onPattern($method, $uriPattern)
    {
        $handler = new StupidHttp_WebRequestHandler($this, $uriPattern);
        $this->driver->addRequestHandler($method, $handler);
        return $handler;
    }
    
    /**
     * Mounts a directory into the document root.
     */
    public function mount($directory, $alias)
    {
        $this->driver->getVfs()->addMountPoint($directory, $alias);
    }
    
    /**
     * Sets the preprocessor that is run before each request.
     */
    public function setPreprocessor($preprocessor)
    {
        $this->driver->setPreprocessor($preprocessor);
    }
    // }}}

    // Main Server Methods {{{
    /**
     * Runs the server.
     */
    public function run(array $options = array())
    {
        try
        {
            $this->runUnsafe($options);
        }
        catch (Exception $e)
        {
            $this->getLog()->critical($e->getMessage());
            $this->getLog()->critical("The server will now shut down!");
        }
    }
    
    protected function runUnsafe(array $options = array())
    {
        // Validate options.
        $options = array_merge(
            array(
                'list_directories' => true,
                'list_root_directory' => false, 
                'run_browser' => false,
                'keep_alive' => false,
                'timeout' => 4,
                'poll_interval' => 1,
                'show_banner' => true,
                'name' => null
            ),
            $options
        );
        
        // Setup the server.
        $this->driver->register();
        if ($options['show_banner'])
        {
            if ($options['name'] != null)
                $this->getLog()->info(">> " . $options['name']);
            $this->getLog()->info(">> Stupid-Http server");
            $this->getLog()->info(">> Listening on {$this->getAddress()}:{$this->getPort()}...");
            $this->getLog()->info(">> (use CTRL+C to stop)");
        }
        else
        {
            $this->getLog()->debug(">> Started server on {$this->getAddress()}:{$this->getPort()}.");
        }
        
        // Optionally run the user's default browser on the server's root URL.
        if ($options['run_browser'])
        {
            $this->runBrowser();
        }
        
        // Start the main networking loop.
        $this->driver->run($options);
    }
    // }}}
    
    // Miscellaneous {{{
    protected function runBrowser()
    {
        switch (PHP_OS)
        {
            case 'Windows':
            case 'WINNT':
            case 'WIN32':
                exec('start http://' . $this->getAddress() . ':' . $this->getPort());
                break;
            default:
                exec('open http://' . $this->getAddress() . ':' . $this->getPort());
                break;
        }
    }
    
    /**
     * Gets the full HTTP header for a given status code.
     */
    public static function getHttpStatusHeader($code)
    {
        static $headers = array(100 => "100 Continue",
                                200 => "200 OK",
                                201 => "201 Created",
                                204 => "204 No Content",
                                206 => "206 Partial Content",
                                300 => "300 Multiple Choices",
                                301 => "301 Moved Permanently",
                                302 => "302 Found",
                                303 => "303 See Other",
                                304 => "304 Not Modified",
                                307 => "307 Temporary Redirect",
                                400 => "400 Bad Request",
                                401 => "401 Unauthorized",
                                403 => "403 Forbidden",
                                404 => "404 Not Found",
                                405 => "405 Method Not Allowed",
                                406 => "406 Not Acceptable",
                                408 => "408 Request Timeout",
                                410 => "410 Gone",
                                413 => "413 Request Entity Too Large",
                                414 => "414 Request URI Too Long",
                                415 => "415 Unsupported Media Type",
                                416 => "416 Requested Range Not Satisfiable",
                                417 => "417 Expectation Failed",
                                500 => "500 Internal Server Error",
                                501 => "501 Method Not Implemented",
                                503 => "503 Service Unavailable",
                                506 => "506 Variant Also Negotiates");
        return $headers[$code];
    }
    // }}}
}



// Global stuff.

// Test compatibility of current system.
$shady_functions = array("socket_create");
foreach ($shady_functions as $name)
{
    if (!is_callable($name))
    {
        die("StupidHttp: Function '" . $name. "' is not available on your system.");
    }
}
unset($shady_functions);
