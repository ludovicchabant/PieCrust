<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrust.class.php';
require_once 'PieCrustException.class.php';

require_once 'StupidHttp/StupidHttp_WebServer.php';

/**
 * The PieCrust chef server.
 */
class ChefServer
{
    protected $server;
    
    /**
     * Creates a new chef server.
     */
    public function __construct($appDir, $port = 8080)
    {
        set_time_limit(0);
        error_reporting(E_ALL);
        date_default_timezone_set('America/Los_Angeles');
        
        $self = $this; // Workaround for $this not being capturable in closures.
        $appDir = rtrim(realpath($appDir), '/\\');
        $this->server = new StupidHttp_WebServer($appDir, $port);
        $this->server->setMimeType('less', 'text/css');
        $this->server->onPattern('GET', '.*')
                     ->call(function($response) use ($self)
                            {
                                return $self->runPieCrustRequest($response);
                            });
    }

    /**
     * Runs the chef server.
     */
    public function run()
    {
        $this->server->run(array('run_browser' => true));
    }
    
    /**
     * For internal use only.
     */
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
        $pieCrust->setConfigValue('server', 'is_hosting', true);
        
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
        
        $code = ($pieCrustError == null) ? 200 :
                    (
                        ($pieCrustError == '404') ? 404 : 500
                    );
                    
        $response->setStatus($code);
        foreach ($pieCrustHeaders as $h)
        {
            $response->addHeader($h);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $response->addLog("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        
        return true;
    }
}
