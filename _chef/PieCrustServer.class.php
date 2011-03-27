<?php

require_once 'ChefEnvironment.inc.php';
require_once 'PieCrust.class.php';
require_once 'PieCrustException.class.php';
require_once 'PieCrustBaker.class.php';

require_once 'StupidHttp/StupidHttp_WebServer.php';
require_once 'StupidHttp/StupidHttp_PearLog.php';


/**
 * The PieCrust chef server.
 */
class PieCrustServer
{
    protected $server;
    protected $rootDir;
    protected $additionalTemplatesDir;
    protected $autobake;
    protected $fullFirstBake;
    
    /**
     * Creates a new chef server.
     */
    public function __construct($appDir, array $options = array())
    {
        set_time_limit(0);
        error_reporting(E_ALL);
        date_default_timezone_set('America/Los_Angeles');
        
        $options = array_merge(
            array(
                  'port' => 8080,
                  'templates_dir' => null,
                  'autobake' => false,
                  'mime_types' => array('less' => 'text/css')
                  ),
            $options
        );
        $this->additionalTemplatesDir = $options['templates_dir'];
        $this->autobake = (is_string($options['autobake']) ? $options['autobake'] : false);
        $this->fullFirstBake = $options['full_first_bake'];
        $this->rootDir = rtrim(realpath($appDir), '/\\');
        
        $documentRoot = ($this->autobake === false) ? $this->rootDir : $this->autobake;
        $port = $options['port'];
        $this->server = new StupidHttp_WebServer($documentRoot, $port);
        $this->server->setLog(StupidHttp_PearLog::fromSingleton('file', 'chef_server_' . basename($appDir) . '.log'));
        foreach ($options['mime_types'] as $ext => $mime)
        {
            $this->server->setMimeType($ext, $mime);
        }
        $self = $this; // Workaround for $this not being capturable in closures.
        if ($this->autobake === false)
        {
            $this->server->onPattern('GET', '.*')
                         ->call(function($response) use ($self)
                                {
                                    $self->_runPieCrustRequest($response);
                                });
        }
        else
        {
            $this->server->setPreprocess(function($response) use ($self)
                                         {
                                            $self->_bake(true);
                                         });
        }
    }

    /**
     * Runs the chef server.
     */
    public function run(array $options = array())
    {
        if ($this->autobake !== false and $this->fullFirstBake)
        {
            $this->_bake(false, true);
        }
        
        $this->server->run($options);
    }
    
    protected function createPieCrustApp()
    {
        $pieCrust = new PieCrust(array(
                                        'url_base' => 'http://' . $this->server->getAddress() . ':' . $this->server->getPort(),
                                        'root' => $this->rootDir,
                                        'cache' => true
                                      )
                                );
        if ($this->additionalTemplatesDir != null)
        {
            $pieCrust->getTemplateEngine()->addTemplatesPaths($this->additionalTemplatesDir);
        }
        return $pieCrust;
    }
    
    /**
     * For internal use only.
     */
    public function _bake($smart, $showBanner = false)
    {
        $pieCrust = $this->createPieCrustApp();
        $baker = new PieCrustBaker($pieCrust, array('smart' => $smart, 'show_banner' => $showBanner));
        $baker->setBakeDir($this->autobake);
        $baker->bake();
    }
    
    /**
     * For internal use only.
     */
    public function _runPieCrustRequest(StupidHttp_HandlerContext $context)
    {
        $startTime = microtime(true);
        
        // Run PieCrust dynamically.
        $pieCrust = $this->createPieCrustApp();
        $pieCrust->setConfigValue('site', 'cache_time', false);
        $pieCrust->setConfigValue('site', 'pretty_urls', true);
        $pieCrust->setConfigValue('server', 'is_hosting', true);
        
        $headers = array();
        $pieCrustError = null;
        try
        {
            $pieCrust->runUnsafe(
                                 $context->getRequest()->getUri(),
                                 $context->getRequest()->getServerVariables(),
                                 null,
                                 $headers
                                 );
        }
        catch (Exception $e)
        {
            $pieCrustError = $e->getMessage();
        }
        
        $code = 500;
        if (isset($headers[0]))
        {
            // The HTTP return code is set by PieCrust in the '0'-th header most of the time.
            $code = $headers[0];
            unset($headers[0]); // Unset so we can iterate on headers more easily later.
        }
        else
        {
            $code = ($pieCrustError == null) ? 200 :
                        (
                            ($pieCrustError == '404') ? 404 : 500
                        );
        }
        
        $context->getResponse()->setStatus($code);
        
        foreach ($pieCrustHeaders as $h => $v)
        {
            $context->getResponse()->setHeader($h, $v);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $context->getLog()->logDebug("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        if ($pieCrustError != null) $context->getLog()->logError("    PieCrust error: " . $pieCrustError);
    }
}
