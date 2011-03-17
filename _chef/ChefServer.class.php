<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrust.class.php';
require_once 'PieCrustException.class.php';

require_once 'StupidHttp/StupidHttp_WebServer.php';

/**
 *
 */
class ChefServer
{
    protected $server;
    
    /**
     *
     */
    public function __construct($appDir, $port = 8080)
    {
        set_time_limit(0);
        error_reporting(E_ALL);
        date_default_timezone_set('America/Los_Angeles');
        
        $self = $this; // Workaround for $this not being capturable in closures.
        $appDir = rtrim(realpath($appDir), '/\\');
        $this->server = new StupidHttp_WebServer($appDir, $port);
        $this->server->onPattern('GET', '.*')
                     ->call(function($response) use ($self)
                            {
                                return $self->runPieCrustRequest($response);
                            });
    }

    /**
     *
     */
    public function run()
    {
        $this->server->run();
    }
    
    public function runPieCrustRequest(StupidHttp_WebResponse $response)
    {
        $startTime = microtime(true);
        $pieCrust = new PieCrust(array(
                                        'url_base' => 'http://' . $this->server->getAddress() . ':' . $this->server->getPort(),
                                        'root' => $this->server->getDocumentRoot(),
                                        'cache' => true,
                                        'debug' => true
                                        )
                                  );
        $pieCrust->setConfigValue('site', 'pretty_urls', true);
        
        $pieCrustError = null;
        $pieCrustHeaders = array();
        try
        {
            $pieCrust->runUnsafe($response->getUri(), $response->getServerVariables(), null, $pieCrustHeaders);
        }
        catch (Exception $e)
        {
            $pieCrustError = $e->getMessage();
        }
        
        $code = ($pieCrustError == null) ? '200 OK' :
                    (
                        ($pieCrustError == '404') ? '404 Not Found' : '500 Internal Server Error'
                    );
                    
        $response->setStatus($code);
        foreach ($pieCrustHeaders as $h)
        {
            $response->addHeader($h);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $response->addLog("    : Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        
        return true;
    }
}
