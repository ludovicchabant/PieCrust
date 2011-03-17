<?php

require_once 'StupidHttp_WebException.php';
require_once 'StupidHttp_WebRequestHandler.php';
require_once 'StupidHttp_WebResponse.php';


/**
 *  A very, very stupid HTTP web server.
 */
class StupidHttp_WebServer
{
    protected $sock;
    protected $requestHandlers;
 
    protected $documentRoot;   
    /**
     *
     */
    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }
    
    protected $address;
    /**
     *
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    protected $port;
    /**
     *
     */
    public function getPort()
    {
        return $this->port;
    }
    
    protected $mimeTypes;
    /**
     *
     */
    public function getMimeTypes()
    {
        return $this->mimeTypes;
    }
    
    /**
     *
     */
    public function setMimeTypes($mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
    }
    
    /**
     * Create a new instance of the stupid HTTP web server.
     */
    public function __construct($documentRoot, $port = 8080, $address = 'localhost')
    {
        set_time_limit(0);
        
        $this->address = $address;
        $this->port = $port;
        
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
     *
     */
    public function __destruct()
    {
        if ($this->sock !== null)
        {
            echo "Shutting server down...\n\n";
            socket_close($this->sock);
        }
    }
    
    public function on($method, $uri)
    {
        $uri = '/' . trim($uri, '/');
        $uriPattern = preg_quote($uri, '/');
        return $this->onPattern($method, $uriPattern);
    }
    
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
     *
     */
    public function run()
    {
        $this->setupNetworking();
        do
        {
            if (($msgsock = socket_accept($this->sock)) === false)
            {
                throw new StupidHttp_WebException("Failed accepting connection: " . socket_strerror(socket_last_error($this->sock)));
            }
        
            $emptyCount = 0;
            $request = array();
            do
            {
                if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ)))
                {
                    throw new StupidHttp_WebException("Error while reading request: " . socket_strerror(socket_last_error($msgsock)));
                }
                if (!$buf = trim($buf))
                {
                    $emptyCount++;
                    if ($emptyCount >= 2)
                    {
                        break;
                    }
                }
                else
                {
                    $emptyCount = 0;
                    $request[] = $buf;
                }
            }
            while (true);
    
            $this->processRequest($msgsock, $request);
            
            socket_close($msgsock);
        }
        while (true);
    }
    
    protected function setupNetworking()
    {
        if (($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            throw new StupidHttp_WebException("Can't create socket: " . socket_strerror(socket_last_error()));
        }
        
        if (socket_bind($this->sock, $this->address, $this->port) === false)
        {
            throw new StupidHttp_WebException("Can't bind socket to " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if (socket_listen($this->sock, 5) === false)
        {
            throw new StupidHttp_WebException("Failed listening to socket on " . $this->address . ":" . $this->port . ": " . socket_strerror(socket_last_error($this->sock)));
        }
        
        echo "\n";
        echo "STUPID-HTTP SERVER\n\n";
        echo "Listening on " . $this->address . ":" . $this->port . "...\n\n";
    }
    
    protected function processRequest($sock, array $request)
    {
        echo '> ' . $request[0];
        
        $matches = array();
        if (!preg_match('/([A-Z]+)\s+([^\s]+)\s+HTTP\/1\.\d/', $request[0], $matches))
        {
            echo "Got bad request: " . $request[0] . PHP_EOL;
            $this->returnResponse($sock, '400 Bad Request');
            echo PHP_EOL;
            return;
        }
        
        $method = $matches[1];
        $uri = $matches[2];
        $documentPath = $this->getDocumentPath($uri);
        if (is_file($documentPath))
        {
            // Serve existing file.
            $contents = file_get_contents($documentPath);
            $contentsHash = md5($contents);
            $extension = pathinfo($documentPath, PATHINFO_EXTENSION);
            $headers = array(
                'Cache-Control: public',
                'Content-MD5: ' . base64_encode($contentsHash),
                'Content-Type: ' . (isset($this->mimeTypes[$extension]) ? $this->mimeTypes[$extension] : 'text/plain'),
                'ETag: ' . $contentsHash,
                'Last-Modified: ' . date("D, d M Y H:i:s T", filemtime($documentPath))
            );
            $this->returnResponse($sock, '200 OK', $headers, $contents);
        }
        else if (isset($this->requestHandlers[$method]))
        {
            // Run the request handlers.
            $handled = false;
            foreach ($this->requestHandlers[$method] as $handler)
            {
                if ($handler->_isMatch($uri))
                {
                    $server = $this->buildServerVariables($method, $uri, $request);
                    $response = new StupidHttp_WebResponse($uri, $server);
                    ob_start();
                    $handled = $handler->_run($response);
                    $body = ob_get_clean();
                    if ($handled)
                    {
                        $this->returnResponse(
                            $sock,
                            $response->getStatus(),
                            $response->getHeaders(),
                            $body
                        );
                        $log = $response->getLog();
                        if (!empty($log))
                        {
                            echo PHP_EOL;
                            echo $log;
                        }
                        break;
                    }
                }
            }
            if (!$handled)
            {
                $this->returnResponse($sock, '404 Not Found');
            }
        }
        else
        {
            // Nothing to do for this method.
            $this->returnResponse($sock, '501 Not Implemented');
        }
        
        echo PHP_EOL;
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
        echo '  ->  ' . $code;
        
        $response = "HTTP/1.1 " . $code . PHP_EOL;
        $response .= "Server: PieCrust Chef Server\n";
        $response .= "Connection: close\n";
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
    
    protected function buildServerVariables($method, $uri, $request)
    {
        $server = array();
        
        $server['REQUEST_METHOD'] = $method;
        $server['SERVER_NAME'] = $this->address;
        $server['SERVER_PORT'] = $this->port;
        $server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $server['QUERY_STRING'] = $uri;
        $server['REQUEST_URI'] = $uri;
        $server['REQUEST_TIME'] = time();
        $server['argv'] = array();
        $server['argc'] = 0;
        
        foreach ($request as $entry)
        {
            $matches = array();
            if (preg_match('/^([\w\-]+):\s+(.*)$/', $entry, $matches))
            {
                $key = $matches[1];
                $value = $matches[2];
                $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($key));
                $server[$serverKey] = $value;
            }
        }
        return $server;
    }
}

