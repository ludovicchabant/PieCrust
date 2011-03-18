<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrust.class.php';
require_once 'PieCrustException.class.php';

require_once 'StupidHttp/StupidHttp_WebServer.php';
require_once 'StupidHttp/StupidHttp_PearLog.php';


/**
 * The PieCrust chef server.
 */
class ChefServer
{
    protected $server;
    protected $additionalTemplatesDir;
    
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
        $this->server->setLog(StupidHttp_PearLog::fromSingleton('file', 'chef_server_' . basename($appDir) . '.log'));
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
    public function run(array $options = null)
    {
        if ($options != null)
        {
            $this->additionalTemplatesDir = $options['templates_dir'];
        }
        else
        {
            $this->additionalTemplatesDir = null;
        }
        
        $this->server->run($options);
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
        if ($this->additionalTemplatesDir != null)
        {
            $pieCrust->getTemplateEngine()->addTemplatesPaths($this->additionalTemplatesDir);
        }
        
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
        $response->getLog()->logDebug("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        
        return true;
    }
}
