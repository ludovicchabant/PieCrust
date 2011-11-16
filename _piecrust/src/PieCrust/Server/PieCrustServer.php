<?php

namespace PieCrust\Server;

require_once 'StupidHttp/StupidHttp_WebServer.php';
require_once 'StupidHttp/StupidHttp_PearLog.php';
require_once 'StupidHttp/StupidHttp_ConsoleLog.php';

use \Exception;
use \StupidHttp_Log;
use \StupidHttp_PearLog;
use \StupidHttp_ConsoleLog;
use \StupidHttp_WebServer;
use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\PieCrustErrorHandler;


/**
 * The PieCrust chef server.
 */
class PieCrustServer
{
    protected $server;
    protected $rootDir;
    protected $additionalTemplatesDir;
    
    /**
     * Creates a new chef server.
     */
    public function __construct($appDir, array $options = array())
    {
        $options = array_merge(
            array(
                  'port' => 8080,
                  'templates_dir' => null,
                  'mime_types' => array('less' => 'text/css'),
                  'log_file' => null,
                  'log_console' => false
                  ),
            $options
        );
        $this->rootDir = rtrim($appDir, '/\\');
        $this->additionalTemplatesDir = $options['templates_dir'];
        
        // Set-up the stupid web server.
        $port = $options['port'];
        $this->server = new StupidHttp_WebServer($this->rootDir, $port);
        
        if ($options['log_file'])
        {
            $this->server->addLog(StupidHttp_PearLog::fromSingleton('file', $options['log_file']));
        }
        
        if ($options['log_console'])
        {
            $this->server->addLog(new StupidHttp_ConsoleLog(StupidHttp_Log::TYPE_DEBUG));
        }
        else
        {
            $this->server->addLog(new StupidHttp_ConsoleLog(StupidHttp_Log::TYPE_INFO));
        }
        
        foreach ($options['mime_types'] as $ext => $mime)
        {
            $this->server->setMimeType($ext, $mime);
        }
        
        $self = $this; // Workaround for $this not being capturable in closures.
        $this->server->onPattern('GET', '.*')
                     ->call(function($context) use ($self)
                            {
                                $self->_runPieCrustRequest($context);
                            });
    }

    /**
     * Runs the chef server.
     */
    public function run(array $options = array())
    {
        $this->server->run($options);
    }
    
    /**
     * For internal use only.
     */
    public function _runPieCrustRequest(\StupidHttp_HandlerContext $context)
    {
        $startTime = microtime(true);
        
        // Run PieCrust dynamically.
        $pieCrust = new PieCrust(array(
                                        'root' => $this->rootDir,
                                        'cache' => true
                                      ),
                                 $context->getRequest()->getServerVariables()
                                );
        $pieCrust->getConfig()->setValue('server/is_hosting', true);
        $pieCrust->getConfig()->setValue('site/cache_time', false);
        $pieCrust->getConfig()->setValue('site/pretty_urls', true);
        $pieCrust->getConfig()->setValue('site/root', '/');
        if ($this->additionalTemplatesDir != null)
        {
            $pieCrust->addTemplatesDir($this->additionalTemplatesDir);
        }
        
        $headers = array();
        $pieCrustException = null;
        try
        {
            $pieCrust->runUnsafe(
                                 null,
                                 $context->getRequest()->getServerVariables(),
                                 null,
                                 $headers
                                 );
        }
        catch (Exception $e)
        {
            $pieCrustException = $e;
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
            $code = ($pieCrustException == null) ? 200 :
                        (
                            ($pieCrustException->getMessage() == '404') ? 404 : 500
                        );
        }
        
        $context->getResponse()->setStatus($code);
        
        foreach ($headers as $h => $v)
        {
            $context->getResponse()->setHeader($h, $v);
        }
        
        if ($pieCrustException)
        {
            $handler = new PieCrustErrorHandler($pieCrust);
            $handler->handleError($pieCrustException);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $context->getLog()->logDebug("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        if ($pieCrustException != null)
        {
            $context->getLog()->logError("    PieCrust error: " . $pieCrustException->getMessage());
        }
    }
}
