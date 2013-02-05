<?php

namespace PieCrust\Runner;

use PieCrust\IPieCrust;
use PieCrust\PieCrustCacheInfo;
use PieCrust\PieCrustException;
use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\Util\HttpHeaderHelper;
use PieCrust\Util\PageHelper;
use PieCrust\Util\ServerHelper;


/*
 * A class that runs web-requests on a PieCrust website.
 */
class PieCrustRunner
{
    protected $pieCrust;

    public function getApp()
    {
        return $this->pieCrust;
    }

    /**
     * Creates a new instance of PieCrustRunner.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }

    /**
     * Runs PieCrust on the given URI.
     */
    public function run($uri = null, array $server = null)
    {
        try
        {
            $this->runUnsafe($uri, $server);
        }
        catch (\Exception $e)
        {
            $handler = new PieCrustErrorHandler($this->pieCrust);
            $handler->handleError($e);
        }
    }
    
    /**
     * Runs PieCrust on the given URI with the given extra page rendering data,
     * but without any error handling.
     */
    public function runUnsafe($uri = null, array $server = null, $extraPageData = null, array &$headers = null)
    {
        // Create an execution context.
        $executionContext = $this->pieCrust->getEnvironment()->getExecutionContext(true);
        
        // Check the cache validity, and clean it automatically.
        if ($this->pieCrust->isCachingEnabled())
        {
            $cacheInfo = new PieCrustCacheInfo($this->pieCrust);
            $cacheValidity = $cacheInfo->getValidity(true);
            $executionContext->isCacheValid = $cacheValidity['is_valid'];
            $executionContext->wasCacheCleaned = $cacheValidity['was_cleaned'];
        }

        // Get the resource URI and corresponding physical path.
        if ($server == null)
        {
            $server = $_SERVER;
        }
        if ($uri == null)
        {
            $uri = ServerHelper::getRequestUri($server, $this->pieCrust->getConfig()->getValueUnchecked('site/pretty_urls'));
        }

        // Do the heavy lifting.
        $page = Page::createFromUri($this->pieCrust, $uri);
        $executionContext->pushPage($page);
        if ($extraPageData != null)
        {
            $page->setExtraPageData($extraPageData);
        }
        $pageRenderer = new PageRenderer($page);
        $output = $pageRenderer->get();
        
        // Set or return the HTML headers.
        HttpHeaderHelper::setOrAddHeaders(
            PageRenderer::getHeaders(
                $page->getConfig()->getValue('content_type'), 
                $server
            ), 
            $headers
        );
        
        // Handle caching.
        if (!$this->pieCrust->isDebuggingEnabled())
        {
            $hash = md5($output);
            HttpHeaderHelper::setOrAddHeader('Etag', '"' . $hash . '"', $headers);
            
            $clientHash = null;
            if (isset($server['HTTP_IF_NONE_MATCH']))
            {
                $clientHash = $server['HTTP_IF_NONE_MATCH'];
            }
            if ($clientHash != null)
            {
                $clientHash = trim($clientHash, '"');
                if ($hash == $clientHash)
                {
                    HttpHeaderHelper::setOrAddHeader(0, 304, $headers);
                    HttpHeaderHelper::setOrAddHeader('Content-Length', '0', $headers);
                    return;
                }
            }
        }
        if ($this->pieCrust->isDebuggingEnabled())
        {
            HttpHeaderHelper::setOrAddHeader('Cache-Control', 'no-cache, must-revalidate', $headers);
        }
        else
        {
            $cacheTime = PageHelper::getConfigValue($page, 'cache_time', 'site');
            if ($cacheTime)
            {
                HttpHeaderHelper::setOrAddHeader('Cache-Control', 'public, max-age=' . $cacheTime, $headers);
            }
        }
    
        // Output with or without GZip compression.
        $gzipEnabled = (($this->pieCrust->getConfig()->getValueUnchecked('site/enable_gzip') === true) and
                        (array_key_exists('HTTP_ACCEPT_ENCODING', $server)) and
                        (strpos($server['HTTP_ACCEPT_ENCODING'], 'gzip') !== false));
        if ($gzipEnabled)
        {
            $zippedOutput = gzencode($output);
            if ($zippedOutput === false)
            {
                HttpHeaderHelper::setOrAddHeader('Content-Length', strlen($output), $headers);
                echo $output;
            }
            else
            {
                HttpHeaderHelper::setOrAddHeader('Content-Encoding', 'gzip', $headers);
                HttpHeaderHelper::setOrAddHeader('Content-Length', strlen($zippedOutput), $headers);
                echo $zippedOutput;
            }
        }
        else
        {
            HttpHeaderHelper::setOrAddHeader('Content-Length', strlen($output), $headers);
            echo $output;
        }
    }

    /**
     * Figures out parameters for a new PieCrust app automatically given
     * the current, or supplied, server environment.
     */
    public static function getPieCrustParameters(array $parameters = array(), array $server = null)
    {
        if ($server == null)
        {
            $server = $_SERVER;
        }
        $get = array();
        if (isset($server['QUERY_STRING']))
        {
            parse_str($server['QUERY_STRING'], $get);
        }
        
        $parameters = array_merge(
            array(
                'root' => null, 
                'debug' => false, 
                'cache' => true
            ),
            $parameters
        );
        if ($parameters['root'] == null)
        {
            // Figure out the default root directory.
            if (!isset($server['SCRIPT_FILENAME']))
                throw new PieCrustException("Can't figure out the default root directory for the website.");
            $parameters['root'] = dirname($server['SCRIPT_FILENAME']);
        }
        $parameters['debug'] = ((bool)$parameters['debug'] or isset($get['!debug']));
        $parameters['cache'] = ((bool)$parameters['cache'] and !isset($get['!nocache']));

        return $parameters;
    }
}

