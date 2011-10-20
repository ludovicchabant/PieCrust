<?php

require_once 'StupidHttp_Log.php';
require_once 'StupidHttp_MultiLog.php';
require_once 'StupidHttp_WebRequest.php';
require_once 'StupidHttp_WebResponse.php';
require_once 'StupidHttp_WebException.php';
require_once 'StupidHttp_HandlerContext.php';
require_once 'StupidHttp_WebRequestHandler.php';


/**
 *  A very, very stupid HTTP web server.
 */
class StupidHttp_WebServer
{
    protected $sock;
    protected $mounts;
    protected $requestHandlers;
    protected $preprocessor;
 
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
    
    protected $logs;
    /**
     * Gets the current added logs.
     */
    public function getLogs()
    {
        return $this->logs;
    }
    
    /**
     * Clears the current logs, and adds the given one.
     */
    public function setLog(StupidHttp_Log $log)
    {
        $this->logs = array($log);
    }
    
    /**
     * Adds a log system.
     */
    public function addLog(StupidHttp_Log $log)
    {
        $this->logs[] = $log;
    }
    
    /**
     * Clears all the logs.
     */
    public function clearLogs()
    {
        $this->logs = array();
    }
    
    /**
     * Create a new instance of the stupid HTTP web server.
     */
    public function __construct($documentRoot = null, $port = 8080, $address = 'localhost')
    {
        set_time_limit(0);
        
        $this->address = $address;
        $this->port = $port;
        $this->logs = array();
        
        if ($documentRoot != null and !is_dir($documentRoot))
        {
            throw new StupidHttp_WebException("The given document root is not valid: " . $documentRoot);
        }
        $this->documentRoot = rtrim($documentRoot, '/\\');
        $this->mounts = array();
        
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
        $uriPattern = '^' . preg_quote($uri, '|') . '$';
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
     * Mounts a directory into the document root.
     */
    public function mount($directory, $alias)
    {
        $this->mounts[$alias] = rtrim($directory, '/\\');
    }
    
    /**
     * Sets the preprocessor that is run before each request.
     */
    public function setPreprocess($preprocessor)
    {
        if (!is_callable($preprocessor)) throw new PieCrustException('The preprocessor needs to be a callable object.');
        $this->preprocessor = $preprocessor;
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
            array(
                'list_directories' => true,
                'list_root_directory' => false, 
                'run_browser' => false,
                'keep_alive' => false,
                'timeout' => 4,
                'show_banner' => true
            ),
            $options
        );
        
        $this->setupNetworking($options);
        
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
                
                $timeout = array('sec' => $options['timeout'], 'usec' => 0);
                if (@socket_set_option($msgsock, SOL_SOCKET, SO_RCVTIMEO, $timeout) === false)
                {
                    throw new StupidHttp_WebException("Failed setting timeout value: " . socket_strerror(socket_last_error($msgsock)));
                }
            }
        
            $emptyCount = 0;
            $rawRequest = array();
            $processRequest = false;
            $profilingInfo = array();
            $msgsockReceiveBufferSize = @socket_get_option($this->sock, SOL_SOCKET, SO_RCVBUF);
            do
            {
                if (false === ($buf = @socket_read($msgsock, $msgsockReceiveBufferSize, PHP_NORMAL_READ)))
                {
                    if (socket_last_error($msgsock) === SOCKET_ETIMEDOUT)
                    {
                        // Kept-alive connection probably timed out. Just close it.
                        $processRequest = false;
                        if (empty($rawRequest))
                        {
                            $this->logDebug("    : Timed out... ending conversation.");
                        }
                        else
                        {
                            $this->logError("Timed out while receiving request.");
                        }
                        break;
                    }
                    else
                    {
                        $this->logError("Error reading request from connection: " . socket_strerror(socket_last_error($msgsock)));
                        $processRequest = false;
                        break;
                    }
                }
                if (empty($rawRequest)) $profilingInfo['receive.start'] = microtime(true);
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
                    $emptyCount = 0;
                    $rawRequest[] = $buf;
                }
            }
            while (true);
            $profilingInfo['receive.end'] = microtime(true);
            
            $closeSocket = true;
            if ($processRequest)
            {
                $profilingInfo['process.start'] = microtime(true);
                $request = new StupidHttp_WebRequest($this, $rawRequest);
                
                // Process the request, get the response.
                try
                {
                    if ($this->preprocessor != null)
                    {
                        $this->logInfo('... preprocessing ' . $request->getUri() . ' ...');
                        $func = $this->preprocessor;
                        $func($request);
                    }
                    $response = $this->processRequest($options, $request);
                }
                catch (StupidHttp_WebException $e)
                {
                    $this->logError('Error processing request:');
                    $this->logError($e->getCode() . ': ' . $e->getMessage());
                    if ($e->getCode() != 0)
                    {
                        $response = $this->createResponse($e->getCode());
                    }
                    else
                    {
                        $response = $this->createResponse(500);
                    }
                }
                catch (Exception $e)
                {
                    $this->logError('Error processing request:');
                    $this->logError($e->getCode() . ': ' . $e->getMessage());
                    $response = $this->createResponse(500);
                }
                
                // Figure out whether to close the connection with the client.
                if ($options['keep_alive'])
                {
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
                else
                {
                    $closeSocket = true;
                }
                
                // Adjust the headers and send the response.
                if ($closeSocket) $response->setHeader('Connection', 'close');
                else $response->setHeader('Connection', 'keep-alive');
                if ($response->getHeader('Content-Length') == null)
                {
                    if ($response->getBody() != null) $response->setHeader('Content-Length', strlen($response->getBody()));
                    else $response->setHeader('Content-Length', 0);
                }
                
                $profilingInfo['process.end'] = microtime(true);
                $profilingInfo['send.start'] = microtime(true);
                try
                {
                    $this->sendResponse($msgsock, $response);
                }
                catch (Exception $e)
                {
                    $this->logError('Error sending response:');
                    $this->logError($e->getCode() . ': ' . $e->getMessage());
                }
                $profilingInfo['send.end'] = microtime(true);
                
                $this->logInfo('> ' . $request->getMethod() . ' ' . $request->getUri() . '  -->  ' . self::getHttpStatusHeader($response->getStatus()));
            }
            
            $this->logProfilingInfo($profilingInfo);
            
            if ($closeSocket or !$processRequest)
            {
                $this->logDebug("Closing connection.");
                usleep(100);  // Weird, this seems to fix some networking problems...
                @socket_shutdown($msgsock);
                @socket_close($msgsock);
                $msgsock = false;
                gc_collect_cycles();
            }
        }
        while (true);
    }
    
    protected function setupNetworking(array $options)
    {
        if (($this->sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            throw new StupidHttp_WebException("Can't create socket: " . socket_strerror(socket_last_error()));
        }
        
        if (@socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1) === false)
        {
            throw new StupidHttp_WebException("Can't set options on the socket: " . socket_strerror(socket_last_error()));
        }
        
        if (@socket_bind($this->sock, $this->address, $this->port) === false)
        {
            throw new StupidHttp_WebException("Can't bind socket to " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if (@socket_listen($this->sock) === false)
        {
            throw new StupidHttp_WebException("Failed listening to socket on " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if ($options['show_banner'])
        {
            $this->logInfo("");
            $this->logInfo("STUPID-HTTP SERVER");
            $this->logInfo("");
            $this->logInfo("Listening on " . $this->address . ":" . $this->port . "...");
            $this->logInfo("");
        }
        else
        {
            $this->logDebug("Started server on " . $this->address . ":" . $this->port);
        }
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
    
    protected function processRequest(array $options, StupidHttp_WebRequest $request)
    {
        $this->logDebug('> ' . $request->getMethod() . ' ' . $request->getUri());
        
        $handled = false;
        $documentPath = $this->getDocumentPath($request->getUri());
        if ($request->getMethod() == 'GET' and is_file($documentPath))
        {
            // Serve existing file...
            return $this->serveDocument($request, $documentPath);
        }
        else if ($request->getMethod() == 'GET' and is_dir($documentPath))
        {
            $indexPath = $this->getIndexDocument($documentPath);
            if ($indexPath != null)
            {
                // Serve a directory's index file... 
                return $this->serveDocument($request, $indexPath);
            }
            else if ($options['list_directories'] and
                     ($options['list_root_directory'] or $request->getUri() != '/'))
            {
                // Serve the directory's contents...
                return $this->serveDirectory($request, $documentPath);
            }
        }
        
        if (isset($this->requestHandlers[$request->getMethod()]))
        {
            // Run the request handlers.
            foreach ($this->requestHandlers[$request->getMethod()] as $handler)
            {
                if ($handler->_isMatch($request->getUri()))
                {
                    $response = new StupidHttp_WebResponse();
                    $multiLog = new StupidHttp_MultiLog($this->logs);
                    $context = new StupidHttp_HandlerContext($request, $response, $multiLog);
                    $multiLog->_startBuffering();
                    ob_start();
                    try
                    {
                        $handler->_run($context);
                    }
                    catch (Exception $e)
                    {
                        ob_end_clean();
                        $multiLog->_endBuffering();
                        $this->logError("Handler error for URI '" . $request->getUri() . "': " . strval($e));
                        return $this->createResponse(500);
                    }
                    $body = ob_get_clean();
                    $response->setBody($body);
                    $multiLog->_endBuffering();
                    return $response;
                }
            }
        }
        
        if ($request->getMethod() == 'GET')
        {
            return $this->createResponse(404);  // Not found.
        }
        else
        {
            return $this->createResponse(501);  // Method not implemented.
        }
    }
    
    protected function serveDocument(StupidHttp_WebRequest $request, $documentPath)
    {
        // First, check for timestamp if possible.
        $serverTimestamp = filemtime($documentPath);
        $ifModifiedSince = $request->getHeader('If-Modified-Since');
        if ($ifModifiedSince != null)
        {
            $clientTimestamp = strtotime($ifModifiedSince);
            if ($clientTimestamp > $serverTimestamp)
            {
                return $this->createResponse(304);
            }
        }
        
        // ...otherwise, check for similar checksum.
        $documentSize = filesize($documentPath);
        if ($documentSize == 0)
        {
            return $this->createResponse(200);
        }
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
                return $this->createResponse(304);
            }
        }
        
        // ...ok, let's send the file.
        $extension = pathinfo($documentPath, PATHINFO_EXTENSION);
        $headers = array(
            'Content-Length' => $documentSize,
            'Content-MD5' => base64_encode($contentsHash),
            'Content-Type' => (isset($this->mimeTypes[$extension]) ? $this->mimeTypes[$extension] : 'text/plain'),
            'ETag' => $contentsHash,
            'Last-Modified' => date("D, d M Y H:i:s T", filemtime($documentPath))
        );
        return $this->createResponse(200, $headers, $contents);
    }
    
    protected function serveDirectory(StupidHttp_WebRequest $request, $documentPath)
    {
        $headers = array();
        
        $contents = '<ul>' . PHP_EOL;
        foreach (new DirectoryIterator($documentPath) as $entry)
        {
            $contents .= '<li>' . $entry->getFilename() . '</li>' . PHP_EOL;
        }
        $contents .= '</ul>' . PHP_EOL;
        
        $replacements = array(
            '%path%' => $documentPath,
            '%contents%' => $contents
        );
        $body = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'directory-listing.html');
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        return $this->createResponse(200, $headers, $body);
    }
    
    protected function getDocumentPath($uri)
    {
        $root = $this->getDocumentRoot();
        $secondSlash = strpos($uri, '/', 1);
        if ($secondSlash !== false)
        {
            $firstDir = substr($uri, 1, $secondSlash - 1);
            if (isset($this->mounts[$firstDir]))
            {
                $root = $this->mounts[$firstDir];
                $uri = substr($uri, $secondSlash);
            }
        }
        if ($root === false) return false;
        return $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);
    }
    
    protected function getIndexDocument($path)
    {
        static $indexDocuments = array(
            'index.htm',
            'index.html'
        );
        $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
        foreach ($indexDocuments as $doc)
        {
            if (is_file($path . $doc))
            {
                return $path . $doc;
            }
        }
        return null;
    }
    
    protected function createResponse($code, array $headers = array(), $contents = null)
    {
        if (!is_int($code)) throw new StupidHttp_WebException('The given HTTP return code was not an integer: ' . $code, 500);
        
        return new StupidHttp_WebResponse($code, $headers, $contents);
    }
    
    protected function sendResponse($sock, StupidHttp_WebResponse $response)
    {
        $this->logDebug('    ->  ' . self::getHttpStatusHeader($response->getStatus()));
        $this->logDebug('    : ' . memory_get_usage() / (1024.0 * 1024.0) . 'Mb');
        
        $responseStr = "HTTP/1.1 " . self::getHttpStatusHeader($response->getStatus()) . PHP_EOL;
        $responseStr .= "Server: PieCrust Chef Server".PHP_EOL;
        $responseStr .= "Date: " . date("D, d M Y H:i:s T") . PHP_EOL;
        foreach ($response->getFormattedHeaders() as $header)
        {
            $responseStr .= $header . PHP_EOL;
        }
        $responseStr .= PHP_EOL;
        $headerLength = strlen($responseStr);
        
        if ($response->getBody() != null)
        {
            $responseStr .= $response->getBody();
        }
        
        $transmitted = 0;
        $responseLength = strlen($responseStr);
        $sockSendBufferSize = @socket_get_option($this->sock, SOL_SOCKET, SO_SNDBUF);
        while ($transmitted < $responseLength)
        {
            $socketWriteLength = min($responseLength - $transmitted, $sockSendBufferSize);
            $transmittedThisTime = @socket_write($sock, $responseStr, $socketWriteLength);
            if (false === $transmittedThisTime)
            {
                throw new StupidHttp_WebException("Couldn't write response to socket: " . socket_strerror(socket_last_error($sock)));
            }
            $transmitted += $transmittedThisTime;
            $responseStr = substr($responseStr, $transmittedThisTime);
            $this->logDebug('    : transmitted ' . $transmittedThisTime . ' bytes, ' . ($responseLength - $transmitted) . ' left to go.');
        }
        $declaredLength = intval($response->getHeader('Content-Length'));
        $this->logDebug('    : total transmitted was ' . $transmitted . ' bytes, declared ' . ($declaredLength + $headerLength));
        if (($declaredLength + $headerLength) != $transmitted)
        {
            $this->logError("Discrepancy of " . ($transmitted - $declaredLength - $headerLength) . " bytes between transmitted byte count and declared byte count.");
        }
        if ($declaredLength != strlen($response->getBody()))
        {
            $this->logError("Declared body length was " . $declaredLength . " but should have been " . strlen($response->getBody()));
        }
    }
    
    protected function logProfilingInfo(array $profilingInfo)
    {
        if (isset($profilingInfo['receive.start']) and isset($profilingInfo['receive.end']))
        {
            $this->logDebug('    : received request in ' . ($profilingInfo['receive.end'] - $profilingInfo['receive.start'])*1000.0 . ' ms.');
        }
        if (isset($profilingInfo['process.start']) and isset($profilingInfo['process.end']))
        {
            $this->logDebug('    : processed in ' . ($profilingInfo['process.end'] - $profilingInfo['process.start'])*1000.0 . ' ms.');
        }
        if (isset($profilingInfo['send.start']) and isset($profilingInfo['send.end']))
        {
            $this->logDebug('    : sent request in ' . ($profilingInfo['send.end'] - $profilingInfo['send.start'])*1000.0 . ' ms.');
        }
    }
    
    protected function log($message, $type)
    {
        foreach ($this->logs as $log)
        {
            $log->log($message, $type);
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
unset($shady_functions);
