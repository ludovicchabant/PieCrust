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
use PieCrust\Baker\DirectoryBaker;
use PieCrust\IO\FileSystem;
use PieCrust\Page\PageRepository;


/**
 * The PieCrust chef server.
 */
class PieCrustServer
{
    protected $server;
    protected $rootDir;
    protected $logger;
    protected $debugMode;

    protected $bakeCacheDir;
    protected $bakeCacheFiles;
    
    /**
     * Creates a new chef server.
     */
    public function __construct($appDir, array $options = array(), $logger = null)
    {
        $this->rootDir = rtrim($appDir, '/\\');

        $pieCrust = new PieCrust(array(
                'root' => $this->rootDir,
                'cache' => true
            )
        );
        $this->bakeCacheDir = $pieCrust->getCacheDir() . 'server_cache';
        FileSystem::ensureDirectory($this->bakeCacheDir);

        $options = array_merge(
            array(
                  'port' => 8080,
                  'mime_types' => array('less' => 'text/css'),
                  'log_file' => null,
                  'debug' => false
                  ),
            $options
        );
        $this->debugMode = $options['debug'];

        if ($logger == null)
        {
            require_once 'Log.php';
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
        
        // Set-up the stupid web server.
        $port = $options['port'];
        $this->server = new StupidHttp_WebServer($this->bakeCacheDir, $port);
        if ($logger != null)
        {
            $this->server->addLog(new StupidHttp_PearLog($logger));
        }
        else
        {
            $this->server->addLog(new StupidHttp_ConsoleLog(StupidHttp_Log::TYPE_INFO));
        }
        if ($options['log_file'])
        {
            $this->server->addLog(StupidHttp_PearLog::fromSingleton('file', $options['log_file']));
        }
        
        foreach ($options['mime_types'] as $ext => $mime)
        {
            $this->server->setMimeType($ext, $mime);
        }

        // Mount the `_content` directory so that we can see page assets.
        $this->server->mount($this->rootDir . DIRECTORY_SEPARATOR . '_content', '_content');
        
        $self = $this; // Workaround for $this not being capturable in closures.
        $this->server->onPattern('GET', '.*')
                     ->call(function($context) use ($self)
                            {
                                $self->_runPieCrustRequest($context);
                            });
        $this->server->setPreprocess(function($req) use ($self) { $self->_preprocessRequest($req); });
    }

    /**
     * Runs the chef server.
     */
    public function run(array $options = array())
    {
        $bakedFiles = $this->prebake();

        // Build a reverse-index of what file creates each output file.
        $this->bakeCacheFiles = array();
        foreach ($bakedFiles as $f => $info)
        {
            foreach ($info['outputs'] as $out)
            {
                $this->bakeCacheFiles[$out] = $f;
            }
        }

        $this->server->run($options);
    }

    /**
     * For internal use only.
     */
    public function _preprocessRequest(\StupidHttp_WebRequest $request)
    {
        $documentPath = $this->bakeCacheDir . $request->getUri();
        if (is_file($documentPath) && isset($this->bakeCacheFiles[$documentPath]))
        {
            // Make sure this file is up-to-date.
            $this->prebake(
                $request->getServerVariables(),
                $this->bakeCacheFiles[$documentPath],
                true
            );
        }
        else
        {
            // Perhaps a new file? Re-bake and update our index.
            $bakedFiles = $this->prebake(
                $request->getServerVariables(),
                null,
                true
            );
            foreach ($bakedFiles as $f => $info)
            {
                foreach ($info['outputs'] as $out)
                {
                    $this->bakeCacheFiles[$out] = $f;
                }
            }
        }
    }
    
    /**
     * For internal use only.
     */
    public function _runPieCrustRequest(\StupidHttp_HandlerContext $context)
    {
        $startTime = microtime(true);
        
        // Run PieCrust dynamically.
        $pieCrustException = null;
        try
        {
            // We need to clear pages between requests, otherwise the user
            // could have modified some of the posts and we would keep using
            // the cached versions until the actual posts' pages are requested.
            PageRepository::clearPages();

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
            if ($this->debugMode)
            {
                $pieCrust->getConfig()->setValue('site/display_errors', true);
            }
        }
        catch (Exception $e)
        {
            // Error while setting up PieCrust.
            $pieCrustException = $e;
        }
        
        $headers = array();
        if ($pieCrustException == null)
        {
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
                // Error while running the request.
                $pieCrustException = $e;
            }
        }
        
        $code = 500;
        if (isset($headers[0]))
        {
            // The HTTP return code is set by PieCrust in the '0'-th header (if
            // set at all).
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
            $headers = array();
            $handler = new PieCrustErrorHandler($pieCrust);
            $handler->handleError($pieCrustException, null, $headers);
        }
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $context->getLog()->logDebug("Ran PieCrust request (" . $timeSpan * 1000 . "ms)");
        if ($pieCrustException != null)
        {
            $context->getLog()->logError("    PieCrust error: " . $pieCrustException->getMessage());
        }
    }

    protected function prebake($server = null, $path = null, $smart = false)
    {
        $pieCrust = new PieCrust(array(
                'root' => $this->rootDir,
                'cache' => true
            ),
            $server
        );

        $parameters = $pieCrust->getConfig()->getValue('baker');
        if ($parameters == null)
            $parameters = array();
        $parameters = array_merge(array(
                'smart' => true,
                'processors' => '*',
                'skip_patterns' => array(),
                'force_patterns' => array()
            ),
            $parameters
        );

        $dirBaker = new DirectoryBaker($pieCrust,
            $this->bakeCacheDir,
            array(
                'smart' => $smart,
                'skip_patterns' => $parameters['skip_patterns'],
                'force_patterns' => $parameters['force_patterns'],
                'processors' => $parameters['processors']
            ),
            $this->logger
        );
        $dirBaker->bake($path);

        return $dirBaker->getBakedFiles();
    }
}
