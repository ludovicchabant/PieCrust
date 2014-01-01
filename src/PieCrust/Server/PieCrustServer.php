<?php

namespace PieCrust\Server;

use \Exception;
use \StupidHttp_Log;
use \StupidHttp_PearLog;
use \StupidHttp_ConsoleLog;
use \StupidHttp_WebServer;
use PieCrust\PieCrust;
use PieCrust\PieCrustCacheInfo;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Baker\DirectoryBaker;
use PieCrust\Runner\PieCrustErrorHandler;
use PieCrust\Runner\PieCrustRunner;
use PieCrust\Util\PathHelper;


/**
 * The PieCrust chef server.
 */
class PieCrustServer
{
    protected $rootDir;
    protected $options;
    protected $server;
    protected $logger;

    protected $bakeCacheDir;
    protected $bakeCacheFiles;
    protected $bakeError;
    
    /**
     * Creates a new chef server.
     */
    public function __construct($appDir, array $options = array(), $logger = null)
    {
        // The website's root.
        $this->rootDir = rtrim($appDir, '/\\');

        // Validate the options.
        $this->options = array_merge(
            array(
                'port' => 8080,
                'address' => 'localhost',
                'mime_types' => array('less' => 'text/css'),
                'log_file' => null,
                'debug_server' => false,
                'keep_alive' => false,
                'debug' => false,
                'config_variant' => null,
                'cache' => true,
                'theme_site' => false
            ),
            $options
        );

        // Get a valid logger.
        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;

        // Get the server cache directory.
        if ($this->options['cache'])
        {
            $pieCrust = new PieCrust(array(
                'root' => $this->rootDir,
                'cache' => true,
                'theme_site' => $this->options['theme_site']
            ));
            $this->bakeCacheDir = $pieCrust->getCacheDir() . 'server_cache';
        }
        else
        {
            $this->bakeCacheDir = rtrim(sys_get_temp_dir(), '/\\') . 'piecrust/server_cache';
        }

        $this->server = null;
    }


    /**
     * Runs the chef server.
     */
    public function run(array $options = array())
    {
        // Initialize the web server and other stuff.
        $this->ensureWebServer();
        $this->bakeError = null;
        $this->bakeCacheFiles = array();

        // Run!
        $this->server->run($options);
    }

    /**
     * For internal use only.
     */
    public function _preprocessRequest(\StupidHttp_WebRequest $request)
    {
        try
        {
            $documentPath = $this->bakeCacheDir . $request->getUriPath();
            if (is_file($documentPath) && isset($this->bakeCacheFiles[$documentPath]))
            {
                // Make sure this file is up-to-date.
                $this->prebake(
                    $request->getServerVariables(),
                    $this->bakeCacheFiles[$documentPath]
                );
            }
            else
            {
                // Perhaps a new file? Re-bake and update our index.
                $bakedFiles = $this->prebake(
                    $request->getServerVariables()
                );
                foreach ($bakedFiles as $f => $info)
                {
                    if ($info['was_baked'])
                    {
                        foreach ($info['outputs'] as $out)
                        {
                            $this->bakeCacheFiles[$out] = $f;
                        }
                    }
                }
            }
        }
        catch (Exception $e)
        {
            $this->bakeError = $e;
            $this->logger->crit("Error while pre-processing request '{$request->getUri()}':");
            while ($e != null)
            {
                $this->logger->err('  ' . $e->getMessage());
                $e = $e->getPrevious();
            }
        }
    }
    
    /**
     * For internal use only.
     */
    public function _runPieCrustRequest(\StupidHttp_HandlerContext $context)
    {
        $startTime = microtime(true);
        
        // Things like the plugin loader will add paths to the PHP include path.
        // Let's save it and restore it later.
        $includePath = get_include_path();

        // Run PieCrust dynamically.
        $pieCrustException = null;
        try
        {
            $params = PieCrustRunner::getPieCrustParameters(
                array(
                    'root' => $this->rootDir,
                    'debug' => $this->options['debug'],
                    'cache' => $this->options['cache'],
                    'theme_site' => $this->options['theme_site']
                ),
                $context->getRequest()->getServerVariables()
            );
            $pieCrust = new PieCrust($params);
            $pieCrust->getConfig()->setValue('site/root', '/');
            $pieCrust->getConfig()->setValue('site/pretty_urls', true);
            $pieCrust->getConfig()->setValue('site/cache_time', false);
            $pieCrust->getConfig()->setValue('server/is_hosting', true);

            // New way: apply the `server` variant.
            // Old way: apply the specified variant, or the default one. Warn about deprecation.
            $variantName = $this->options['config_variant'];
            if ($variantName)
            {
                $this->logger->warning("The `--config` parameter has been moved to a global parameter (specified before the command).");
                $pieCrust->getConfig()->applyVariant("server/config_variants/{$variantName}");
                $this->logger->warning("Variant '{$variantName}' has been applied, but will need to be moved to the new `variants` section of the site configuration.");
            }
            else
            {
                if ($pieCrust->getConfig()->hasValue("server/config_variants/default"))
                {
                    $pieCrust->getConfig()->applyVariant("server/config_variants/default");
                    $this->logger->warning("The default server configuration variant has been applied, but will need to be moved into the new `variants/server` section of the site configuration.");
                }
                else
                {
                    $pieCrust->getConfig()->applyVariant("variants/server", false);
                }
            }
        }
        catch (Exception $e)
        {
            // Error while setting up PieCrust.
            $pieCrustException = $e;
        }

        // If there was no error setting up the application, see if there was
        // an error in the last pre-bake.
        if ($pieCrustException == null)
        {
            $pieCrustException = $this->bakeError;
            $this->bakeError = null;
        }
        
        // If there was no error so far, run the current request.
        $headers = array();
        if ($pieCrustException == null)
        {
            try
            {
                $runner = new PieCrustRunner($pieCrust);
                $runner->runUnsafe(
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
        
        // Set the return HTTP status code.
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
        
        // Set the headers.
        foreach ($headers as $h => $v)
        {
            $context->getResponse()->setHeader($h, $v);
        }
        
        // Show an error message, if needed.
        if ($pieCrustException)
        {
            $headers = array();
            $handler = new PieCrustErrorHandler($pieCrust);
            $handler->handleError($pieCrustException, null, $headers);
        }

        // Restore the include path.
        set_include_path($includePath);
        
        $endTime = microtime(true);
        $timeSpan = microtime(true) - $startTime;
        $context->getLog()->debug("Ran PieCrust request in " . $timeSpan * 1000 . "ms.");
        if ($pieCrustException != null)
        {
            $this->logger->exception($pieCrustException, true);
        }
    }

    protected function ensureWebServer()
    {
        if ($this->server != null)
            return;

        PathHelper::ensureDirectory($this->bakeCacheDir);

        // Set-up the stupid web server.
        $this->server = new StupidHttp_WebServer($this->bakeCacheDir, $this->options['port'], $this->options['address']);
        if ($this->options['log_file'])
        {
            $this->server->setLog(StupidHttp_PearLog::fromSingleton('file', $this->options['log_file']));
        }
        elseif (!$this->options['debug_server'] && $this->logger != null && !($this->logger instanceof \Log_null))
        {
            $this->server->setLog(new StupidHttp_PearLog($this->logger));
        }
        else
        {
            $level = StupidHttp_Log::TYPE_INFO;
            if ($this->options['debug_server'])
                $level = StupidHttp_Log::TYPE_DEBUG;
            $this->server->setLog(new StupidHttp_ConsoleLog($level));
        }

        // Use colorized output on Mac/Linux.
        if (!PieCrustDefaults::IS_WINDOWS())
        {
            $color = new \Console_Color2();
            $requestFormat = $color->convert("[%%date%%] %m%%client_ip%%%n --> %g%%method%%%n %%path%% --> %c%%status_name%%%n [%%time%%ms]");
            $this->server->getLog()->setRequestFormat($requestFormat);
        }
        
        foreach ($this->options['mime_types'] as $ext => $mime)
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
        $this->server->setPreprocessor(function($req) use ($self) { $self->_preprocessRequest($req); });
    }

    protected function prebake($server = null, $path = null)
    {
        // Things like the plugin loader will add paths to the PHP include path.
        // Let's save it and restore it later.
        $includePath = get_include_path();

        $pieCrust = new PieCrust(
            array(
                'root' => $this->rootDir,
                'cache' => $this->options['cache'],
                'theme_site' => $this->options['theme_site']
            ),
            $server
        );

        $parameters = $pieCrust->getConfig()->getValue('baker');
        if ($parameters == null)
            $parameters = array();
        $parameters = array_merge(array(
                'smart' => true,
                'mounts' => array(),
                'processors' => '*',
                'skip_patterns' => array(),
                'force_patterns' => array()
            ),
            $parameters
        );

        $dirBaker = new DirectoryBaker($pieCrust,
            $this->bakeCacheDir,
            array(
                'smart' => $parameters['smart'],
                'mounts' => $parameters['mounts'],
                'processors' => $parameters['processors'],
                'skip_patterns' => $parameters['skip_patterns'],
                'force_patterns' => $parameters['force_patterns']
            ),
            $this->logger
        );
        $dirBaker->bake($path);

        // Restore the include path.
        set_include_path($includePath);

        return $dirBaker->getBakedFiles();
    }
}
