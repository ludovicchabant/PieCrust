<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrust.class.php';

/**
 *
 */
class ChefServer
{
    protected $appDir;
    protected $address;
    protected $port;
    protected $mimeTypes;
    protected $sock;
    
    /**
     *
     */
    public function __construct($appDir, $address = 'localhost', $port = 8080)
    {
        set_time_limit(0);
        ob_implicit_flush();
        
        $this->appDir = rtrim(realpath($appDir), '/\\');
        $this->address = $address;
        $this->port = $port;
        
        $mimeTypesPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'mime.types';
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
            if ($hasError) throw new Exception("Unexpected fgets() fail.");
        }
        else
        {
            throw new Exception("Can't find the 'mime.types' file: " . $mimeTypesPath);
        }
        
        if (!is_dir($appDir))
        {
            throw new Exception("The given application directory is not valid: " . $appDir);
        }
        
        if (($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            throw new Exception("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
        }
        
        if (socket_bind($this->sock, $address, $port) === false)
        {
            throw new Exception("socket_bind() failed: reason: " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if (socket_listen($this->sock, 5) === false)
        {
            throw new Exception("socket_listen() failed: reason: " . socket_strerror(socket_last_error($this->sock)));
        }
    }
    
    /**
     *
     */
    public function __destruct()
    {
        echo "Closing server...\n";
        if ($this->sock !== null) socket_close($this->sock);
    }

    /**
     *
     */
    public function run()
    {
        do
        {
            echo "Listening on " . $this->address . ":" . $this->port . "...\n";
            if (($msgsock = socket_accept($this->sock)) === false)
            {
                echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($this->sock)) . "\n";
                break;
            }
        
            $emptyCount = 0;
            $request = array();
            try
            {
                do
                {
                    if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ)))
                    {
                        echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
                        break;
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
            }
            catch (Exception $e)
            {
                echo "Error: " . $e . PHP_EOL;
            }
            
            socket_close($msgsock);
        }
        while (true);
    }
    
    protected function processRequest($sock, array $request)
    {
        $matches = array();
        if (!preg_match('/([A-Z]+)\s+([^\s]+)\s+HTTP\/1\.\d/', $request[0], $matches))
        {
            echo "Got bad request: " . $request[0] . PHP_EOL;
            $this->returnResponse($sock, '400 Bad Request');
            return;
        }
        if ($matches[1] != 'GET')
        {
            echo "Got " . $matches[1] . " request -- not supported.\n";
            $this->returnResponse($sock, '405 Method Not Allowed');
            return;
        }
        
        $path = $this->appDir . str_replace('/', DIRECTORY_SEPARATOR, $matches[2]);
        if (is_file($path))
        {
            // Return existing file.
            echo "Sending file " . $matches[2] . PHP_EOL;
            
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $headers = array(
                'Content-Type: ' . (isset($this->mimeTypes[$extension]) ? $this->mimeTypes[$extension] : 'text/plain')
            );
            $contents = file_get_contents($path);
            $this->returnResponse($sock, '200 OK', $headers, $contents);
        }
        else
        {
            // Setup PieCrust and process the URI.
            echo "Processing PieCrust request " . $matches[2] . PHP_EOL;
            
            $server = $this->buildServerVariable($request);
            $server['REQUEST_METHOD'] = $matches[1];
            $server['SERVER_NAME'] = $this->address;
            $server['SERVER_PORT'] = $this->port;
            $server['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $server['QUERY_STRING'] = $matches[2];
            $server['REQUEST_URI'] = $matches[2];
            $server['REQUEST_TIME'] = time();
            $server['argv'] = array();
            $server['argc'] = 0;
            
            $pieCrust = new PieCrust(array(
                                           'url_base' => 'http://' . $this->address . ':' . $this->port,
                                           'root' => $this->appDir,
                                           'cache' => true,
                                           'debug' => true
                                           )
                                     );
            ob_start();
            $pieCrustError = null;
            try
            {
                $pieCrust->runUnsafe($matches[2], $server);
            }
            catch (Exception $e)
            {
                $pieCrustError = $e->getMessage();
            }
            if (headers_sent())
            {
                $headers = headers_list();
                foreach ($headers as $header)
                {
                    $name = substr($header, 0, strpos($header, ':'));
                    header_remove($name);
                }
            }
            if (empty($headers))
            {
                $headers = array('Content-Type: text/plain');
            }
            $contents = ob_get_clean();
            
            $code = ($pieCrustError == null) ? '200 OK' :
                        (
                            ($pieCrustError == '404') ? '404 Not Found' : '500 Internal Server Error'
                        );
            
            $this->returnResponse($sock, $code, $headers, $contents);
        }
    }
    
    protected function returnResponse($sock, $code, $headers = null, $contents = null)
    {
        $response = "HTTP/1.1 " . $code . PHP_EOL;
        $response .= "Server: PieCrust Chef Server\n";
        $response .= "Connection: close\n";
        if ($headers != null)
        {
            foreach ($headers as $header)
            {
                $response .= $header . PHP_EOL;
            }
        }
        if ($contents != null)
        {
            $response .= 'Content-Length: ' . strlen($contents) . PHP_EOL;
            $response .= PHP_EOL;
            $response .= $contents;
        }
        else
        {
            $response .= PHP_EOL;
        }
        
        socket_write($sock, $response, strlen($response));
    }
    
    protected function buildServerVariable($request)
    {
        $server = array();
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
