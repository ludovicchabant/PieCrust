<?php

namespace PieCrust\Server;

use \Exception;
use \StupidHttp_Log;
use \StupidHttp_PearLog;
use \StupidHttp_ConsoleLog;
use \StupidHttp_WebServer;
use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Page\PageRepository;

require_once 'StupidHttp/StupidHttp_WebServer.php';
require_once 'StupidHttp/StupidHttp_PearLog.php';
require_once 'StupidHttp/StupidHttp_ConsoleLog.php';


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
    protected $lastBakeTime;
    protected $autobakeInterval;
    
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
                  'autobake_interval' => 2,
                  'mime_types' => array('less' => 'text/css'),
                  'log_file' => null,
                  'log_console' => false
                  ),
            $options
        );
        $this->additionalTemplatesDir = $options['templates_dir'];
        $this->autobake = (is_string($options['autobake']) ? $options['autobake'] : false);
        $this->fullFirstBake = $options['full_first_bake'];
        $this->rootDir = rtrim($appDir, '/\\');
        $this->lastBakeTime = 0;
        $this->autobakeInterval = $options['autobake_interval'];
        
        // Set-up the stupid web server.
        $documentRoot = ($this->autobake === false) ? $this->rootDir : $this->autobake;
        $port = $options['port'];
        $this->server = new StupidHttp_WebServer($documentRoot, $port);
        
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
        if ($this->autobake === false)
        {
            $this->server->onPattern('GET', '.*')
                         ->call(function($context) use ($self)
                                {
                                    $self->_runPieCrustRequest($context);
                                });
        }
        else
        {
            $this->server->setPreprocess(function($request) use ($self)
                                         {
                                            if ((microtime(true) - $self->_getLastBakeTime()) > $self->_getAutobakeInterval())
                                            {
                                                $self->_bake(true);
                                                $self->_setLastBakeTime();
                                            }
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
                                        'root' => $this->rootDir,
                                        'cache' => true
                                      )
                                );
        $pieCrust->setConfigValue('server', 'is_hosting', true);
        $pieCrust->setConfigValue('site', 'root', '/');
        if ($this->additionalTemplatesDir != null)
        {
            $pieCrust->addTemplatesDir($this->additionalTemplatesDir);
        }
        return $pieCrust;
    }
    
    /**
     * For internal use only.
     */
    public function _getAutobakeInterval()
    {
        return $this->autobakeInterval;
    }
    
    /**
     * For internal use only.
     */
    public function _getLastBakeTime()
    {
        return $this->lastBakeTime;
    }
    
    /**
     * For internal use only.
     */
    public function _setLastBakeTime($time = null)
    {
        if ($time == null) $time = microtime(true);
        $this->lastBakeTime = $time;
    }
    
    /**
     * For internal use only.
     */
    public function _bake($smart, $showBanner = false)
    {
        PageRepository::clearPages();
        
        $baker = new PieCrustBaker(
            array(
                 'root' => $this->rootDir,
                 'cache' => true
            ),
            array(
                  'smart' => $smart,
                  'show_banner' => $showBanner
            )
        );
        $baker->setBakeDir($this->autobake);
        $baker->getApp()->setConfigValue('server', 'is_hosting', true);
        $baker->getApp()->setConfigValue('site', 'root', '/');
        if ($this->additionalTemplatesDir != null)
        {
            $baker->getApp()->addTemplatesDir($this->additionalTemplatesDir);
        }
        $baker->bake();
    }
    
    /**
     * For internal use only.
     */
    public function _runPieCrustRequest(\StupidHttp_HandlerContext $context)
    {
        $startTime = microtime(true);
        
        // Run PieCrust dynamically.
        $pieCrust = $this->createPieCrustApp();
        $pieCrust->setConfigValue('site', 'cache_time', false);
        $pieCrust->setConfigValue('site', 'pretty_urls', true);
        
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
        
        foreach ($headers as $h => $v)
        {
            $context->getResponse()->setHeader($h, $v);
        }
        
        if ($pieCrustError)
        {
            piecrust_show_system_message('error', $pieCrustError);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $context->getLog()->logDebug("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        if ($pieCrustError != null)
        {
            $context->getLog()->logError("    PieCrust error: " . $pieCrustError);
        }
    }
}
