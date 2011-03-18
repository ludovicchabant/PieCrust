<?php

require_once 'StupidHttp_Log.php';
require_once 'StupidHttp_WebRequest.php';
require_once 'StupidHttp_WebResponse.php';
require_once 'StupidHttp_WebException.php';
require_once 'StupidHttp_WebRequestHandler.php';


/**
 *  A very, very stupid HTTP web server.
 */
class StupidHttp_WebServer
{
    protected $sock;
    protected $requestHandlers;
 
    protected $documentRoot;   
    /**
     * Gets the root directory for the served documents.
     */
    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }
    
    protected $address;
    /**
     * Gets the IP address (or host name) of the server.
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    protected $port;
    /**
     * Gets the port of the server.
     */
    public function getPort()
    {
        return $this->port;
    }
    
    protected $mimeTypes;
    /**
     * Gets the mime types used by the server.
     *
     * This is an associative array where file extensions are keys and
     * HTTP mime types are values.
     */
    public function getMimeTypes()
    {
        return $this->mimeTypes;
    }
    
    /**
     * Sets the mime types to be used by the server.
     */
    public function setMimeTypes($mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
    }
    
    /**
     * Sets a specific mime type for a given file extension.
     */
    public function setMimeType($extension, $mimeType)
    {
        $this->mimeTypes[$extension] = $mimeType;
    }
    
    protected $log;
    /**
     * Gets the current log system.
     */
    public function getLog()
    {
        return $this->log;
    }
    
    /**
     * Sets the log system.
     */
    public function setLog(StupidHttp_Log $log)
    {
        $this->log = $log;
    }
    
    /**
     * Create a new instance of the stupid HTTP web server.
     */
    public function __construct($documentRoot, $port = 8080, $address = 'localhost')
    {
        set_time_limit(0);
        
        $this->address = $address;
        $this->port = $port;
        $this->log = null;
        
        if (!is_dir($documentRoot))
        {
            throw new StupidHttp_WebException("The given document root is not valid: " . $documentRoot);
        }
        $this->documentRoot = $documentRoot;
        
        $mimeTypesPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime.types';
        $handle = @fopen($mimeTypesPath, "r");
        if ($handle)
        {
            $hasError = false;
            $this->mimeTypes = array();
            while (($buffer = fgets($handle, 4096)) !== false)
            {
                $tokens = preg_split('/\s+/', $buffer, -1, PREG_SPLIT_NO_EMPTY);
                if (count($tokens) > 1)
                {
                    for ($i = 1; $i < count($tokens); $i++)
                    {
                        $this->mimeTypes[$tokens[$i]] = $tokens[0];
                    }
                }
            }
            if (!feof($handle)) $hasError = true;
            fclose($handle);
            if ($hasError) throw new StupidHttp_WebException("An error occured while reading the mime.types file: " . $mimeTypesPath);
        }
        else
        {
            throw new StupidHttp_WebException("Can't find the 'mime.types' file: " . $mimeTypesPath);
        }
        
        $this->requestHandlers = array();
    }
    
    /**
     * Destructor for the StupidHttp_WebServer.
     */
    public function __destruct()
    {
        if ($this->sock !== null)
        {
            $this->logInfo("Shutting server down...");
            $this->logInfo("");
            socket_close($this->sock);
        }
    }
    
    /**
     * Adds a route to match requests against, and returns the handler.
     */
    public function on($method, $uri)
    {
        $uri = '/' . trim($uri, '/');
        $uriPattern = preg_quote($uri, '/');
        return $this->onPattern($method, $uriPattern);
    }
    
    /**
     * Adds a route pattern to match requests against, and returns the handler.
     */
    public function onPattern($method, $uriPattern)
    {
        $method = strtoupper($method);
        if (!isset($this->requestHandlers[$method]))
        {
            $this->requestHandlers[$method] = array();
        }
        
        $handler = new StupidHttp_WebRequestHandler($this, $uriPattern);
        $this->requestHandlers[$method][] = $handler;
        return $handler;
    }

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
            $this->logCritical($e->getMessage());
            $this->logCritical("Shutting down server...");
        }
    }
    
    protected function runUnsafe(array $options = array())
    {
        $options = array_merge(
            array('run_browser' => false),
            $options
        );
        
        $this->setupNetworking();
        
        if ($options['run_browser'] === true)
        {
            $this->runBrowser();
        }
        
        $msgsock = false;
        do
        {
            if ($msgsock === false)
            {
                $this->logDebug("Opening connection...");
                if (($msgsock = @socket_accept($this->sock)) === false)
                {
                    throw new StupidHttp_WebException("Failed accepting connection: " . socket_strerror(socket_last_error($this->sock)));
                }
                
                $timeout = array('sec' => 5, 'usec' => 0);
                if (@socket_set_option($msgsock, SOL_SOCKET, SO_RCVTIMEO, $timeout) === false)
                {
                    throw new StupidHttp_WebException("Failed setting timeout value: " . socket_strerror(socket_last_error($msgsock)));
                }
            }
        
            $emptyCount = 0;
            $rawRequest = array();
            $closeSocket = true;
            $processRequest = false;
            do
            {
                if (false === ($buf = @socket_read($msgsock, 2048, PHP_NORMAL_READ)))
                {
                    if (socket_last_error($msgsock) === SOCKET_ETIMEDOUT)
                    {
                        // Kept-alive connection probably timed out. Just close it.
                        $closeSocket = true;
                        $processRequest = false;
                        if (!empty($rawRequest))
                        {
                            $this->logError("Timed out while receiving request.");
                        }
                        break;
                    }
                    else
                    {
                        $this->logError("Error reading request from connection: " . socket_strerror(socket_last_error($msgsock)));
                        $closeSocket = true;
                        $processRequest = false;
                        break;
                    }
                }
                if (!$buf = trim($buf))
                {
                    $emptyCount++;
                    if ($emptyCount >= 2)
                    {
                        $processRequest = true;
                        break;
                    }
                }
                else
                {
                    $this->logDebug("Got: " . $buf);
                    $emptyCount = 0;
                    $rawRequest[] = $buf;
                }
            }
            while (true);
            
            if ($processRequest)
            {
                try
                {
                    $request = new StupidHttp_WebRequest($rawRequest);
                    $this->processRequest($msgsock, $request);
                    switch ($request->getVersion())
                    {
                    case 'HTTP/1.0':
                    default:
                        // Always close, unless asked to keep alive.
                        $closeSocket = ($request->getHeader('Connection') != 'keep-alive');
                        break;
                    case 'HTTP/1.1':
                        // Always keep alive, unless asked to close.
                        $closeSocket = ($request->getHeader('Connection') == 'close');
                        break;
                    }
                }
                catch (StupidHttp_WebException $e)
                {
                    $this->logError($e->getCode() . ': ' . $e->getMessage());
                    if ($e->getCode() != 0)
                    {
                        $this->returnResponse($msgsock, $e->getCode());
                    }
                    else
                    {
                        $this->returnResponse($msgsock, 500);
                    }
                }
                catch (Exception $e)
                {
                    $this->logError($e->getCode() . ': ' . $e->getMessage());
                    $this->returnResponse($msgsock, 500);
                }
            }
            
            if ($closeSocket)
            {
                $this->logDebug("Closing connection.");
                socket_close($msgsock);
                $msgsock = false;
                gc_collect_cycles();
            }
        }
        while (true);
    }
    
    protected function setupNetworking()
    {
        if (($this->sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            throw new StupidHttp_WebException("Can't create socket: " . socket_strerror(socket_last_error()));
        }
        
        if (@socket_bind($this->sock, $this->address, $this->port) === false)
        {
            throw new StupidHttp_WebException("Can't bind socket to " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if (@socket_listen($this->sock) === false)
        {
            throw new StupidHttp_WebException("Failed listening to socket on " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        $this->logInfo("");
        $this->logInfo("STUPID-HTTP SERVER");
        $this->logInfo("");
        $this->logInfo("Listening on " . $this->address . ":" . $this->port . "...");
        $this->logInfo("");
    }
    
    protected function runBrowser()
    {
        switch (PHP_OS)
        {
            case 'Windows':
            case 'WINNT':
            case 'WIN32':
                exec('start http://' . $this->address . ':' . $this->port);
                break;
            default:
                exec('open http://' . $this->address . ':' . $this->port);
                break;
        }
    }
    
    protected function processRequest($sock, StupidHttp_WebRequest $request)
    {
        $this->logInfo('> ' . $request->getMethod() . ' ' . $request->getUri());
        
        $documentPath = $this->getDocumentPath($request->getUri());
        if (is_file($documentPath))
        {
            // Serve existing file...
            // ...but check for timestamp first if possible.
            $serverTimestamp = filemtime($documentPath);
            $ifModifiedSince = $request->getHeader('If-Modified-Since');
            if ($ifModifiedSince != null)
            {
                $clientTimestamp = strtotime($ifModifiedSince);
                if ($clientTimestamp > $serverTimestamp)
                {
                    $this->returnResponse($sock, 304);
                    return;
                }
            }
            
            // ...otherwise, check for similar checksum.
            $documentSize = filesize($documentPath);
            $documentHandle = fopen($documentPath, "rb");
            $contents = fread($documentHandle, $documentSize);
            fclose($documentHandle);
            if ($contents === false)
            {
                throw new StupidHttp_WebException('Error reading file: ' . $documentPath, 500);
            }
            $contentsHash = md5($contents);
            $ifNoneMatch = $request->getHeader('If-None-Match');
            if ($ifNoneMatch != null)
            {
                if ($ifNoneMatch == $contentsHash)
                {
                    $this->returnResponse($sock, 304);
                    return;
                }
            }
            
            // ...ok, let's send the file.
            $extension = pathinfo($documentPath, PATHINFO_EXTENSION);
            $headers = array(
                'Content-Length: ' . $documentSize,
                'Content-MD5: ' . base64_encode($contentsHash),
                'Content-Type: ' . (isset($this->mimeTypes[$extension]) ? $this->mimeTypes[$extension] : 'text/plain'),
                'ETag: ' . $contentsHash,
                'Last-Modified: ' . date("D, d M Y H:i:s T", filemtime($documentPath))
            );
            $this->returnResponse($sock, 200, $headers, $contents);
        }
        else if (isset($this->requestHandlers[$request->getMethod()]))
        {
            // Run the request handlers.
            $handled = false;
            foreach ($this->requestHandlers[$request->getMethod()] as $handler)
            {
                if ($handler->_isMatch($request->getUri()))
                {
                    $server = $this->buildServerVariables($request);
                    $response = new StupidHttp_WebResponse($request->getUri(), $server, $this->log);
                    ob_start();
                    $handled = $handler->_run($response);
                    $body = ob_get_clean();
                    if ($handled)
                    {
                        $this->returnResponse(
                            $sock,
                            $response->getStatus(),
                            $response->getFormattedHeaders(),
                            $body
                        );
                        break;
                    }
                }
            }
            if (!$handled)
            {
                $this->returnResponse($sock, 404);
            }
        }
        else
        {
            // Nothing to do for this method.
            $this->returnResponse($sock, 501);
        }
    }
    
    protected function getDocumentPath($uri)
    {
        return $this->getDocumentRoot() . str_replace('/', DIRECTORY_SEPARATOR, $uri);
    }
    
    protected function getIndexDocument($path)
    {
        static $indexDocuments = array(
            'index.htm',
            'index.html',
            'index.php'
        );
        $path = rtrim('/\\', $path) . DIRECTORY_SEPARATOR;
        foreach ($indexDocuments as $doc)
        {
            if (is_file($path . $doc))
            {
                return $path . $doc;
            }
        }
        return null;
    }
    
    protected function returnResponse($sock, $code, $headers = null, $contents = null)
    {
        if (!is_int($code)) throw new StupidHttp_WebException('The given HTTP return code was not an integer: ' . $code, 500);
        
        $this->logInfo('    ->  ' . self::getHttpStatusHeader($code));
        $this->logDebug('    : ' . memory_get_usage() / (1024.0 * 1024.0) . 'Mb');
        
        $response = "HTTP/1.1 " . self::getHttpStatusHeader($code) . PHP_EOL;
        $response .= "Server: PieCrust Chef Server\n";
        $response .= "Date: " . date("D, d M Y H:i:s T") . PHP_EOL;
        if ($headers != null)
        {
            foreach ($headers as $header)
            {
                $response .= $header . PHP_EOL;
            }
        }
        
        if ($contents != null)
        {
            $response .= PHP_EOL;
            $response .= $contents;
        }
        else
        {
            $response .= PHP_EOL;
        }
        
        socket_write($sock, $response, strlen($response));
    }
    
    protected function buildServerVariables(StupidHttp_WebRequest $request)
    {
        $server = array();
        
        $server['REQUEST_METHOD'] = $request->getMethod();
        $server['SERVER_NAME'] = $this->address;
        $server['SERVER_PORT'] = $this->port;
        $server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $server['QUERY_STRING'] = $request->getUri();
        $server['REQUEST_URI'] = $request->getUri();
        $server['REQUEST_TIME'] = time();
        $server['argv'] = array();
        $server['argc'] = 0;
        
        $headers = $request->getHeaders();
        foreach ($headers as $key => $value)
        {
            $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($key));
            $server[$serverKey] = $value;
        }
        
        return $server;
    }
    
    protected function log($message, $type)
    {
        if ($this->log != null)
        {
            $this->log->log($message, $type);
        }
        if ($type <= StupidHttp_Log::TYPE_INFO)
        {
            echo StupidHttp_Log::messageTypeToString($type) . ': ' . $message . PHP_EOL;
        }
    }
    
    protected function logCritical($message)
    {
        $this->log($message, StupidHttp_Log::TYPE_CRITICAL);
    }
    
    protected function logError($message)
    {
        $this->log($message, StupidHttp_Log::TYPE_ERROR);
    }
    
    protected function logWarning($message)
    {
        $this->log($message, StupidHttp_Log::TYPE_WARNING);
    }
    
    protected function logInfo($message)
    {
        $this->log($message, StupidHttp_Log::TYPE_INFO);
    }
    
    protected function logDebug($message)
    {
        $this->log($message, StupidHttp_Log::TYPE_DEBUG);
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
}



// Global stuff.

// Test compatibility of current system.
$shady_functions = array("socket_create");
foreach ($shady_functions as $name)
{
    if (!is_callable($name))
    {
        errexit("StupidHttp: Function '" . $name. "' is not available on your system.");
    }
}
