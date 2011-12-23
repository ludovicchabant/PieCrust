<?php

namespace PieCrust;

use \Exception;
use PieCrust\Formatters\IFormatter;
use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Util\HttpHeaderHelper;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;
use PieCrust\Util\ServerHelper;


/**
 * The main PieCrust application class.
 *
 * This class contains the application's configuration and directory setup information,
 * and handles, among other things, routing and errors.
 */
class PieCrust implements IPieCrust
{
    protected $rootDir;
    /**
     * The root directory of the website.
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }
    
    protected $cachingEnabled;
    /**
     * Gets whether caching is enabled.
     */
    public function isCachingEnabled()
    {
        return $this->cachingEnabled;
    }
    
    protected $debuggingEnabled;
    /**
     * Gets whether debugging is enabled.
     */
    public function isDebuggingEnabled()
    {
        return $this->debuggingEnabled;
    }
    
    protected $templatesDirs;
    /**
     * Gets the directories that contain templates and layouts ('/_content/templates' by default).
     */
    public function getTemplatesDirs()
    {
        if ($this->templatesDirs === null)
        {
            try
            {
                $this->setTemplatesDirs(PieCrustDefaults::CONTENT_TEMPLATES_DIR);
            }
            catch (PieCrustException $e)
            {
                // The default template directory doesn't exist... whatever. Just don't use it.
                $this->templatesDirs = array();
            }
            
            // Add custom template directories specified in the configuration.
            $additionalPaths = $this->getConfig()->getValue('site/templates_dirs');
            if ($additionalPaths)
            {
                $this->addTemplatesDir($additionalPaths);
            }
        }
        return $this->templatesDirs;
    }
    
    /**
     * Sets the directories that contain templates and layouts. Directories can be
     * relative to the site's root directory.
     */
    public function setTemplatesDirs($dir)
    {
        $this->templatesDirs = array();
        $this->addTemplatesDir($dir);
    }
    
    /**
     * Adds a templates directory. It can be relative to the site's root directory.
     */
    public function addTemplatesDir($dir)
    {
        $this->getTemplatesDirs();  // Ensure defaults are created.
        
        if (!is_array($dir)) 
            $dir = array($dir);

        foreach ($dir as $d)
        {
            $absolute = PathHelper::getAbsolutePath($d, $this->getRootDir());
            if (is_dir($absolute) === false)
            {
                throw new PieCrustException("The specified templates directory doesn't exist: " . $absolute);
            }
            $this->templatesDirs[] = rtrim($absolute, '/\\') . '/';
        }
    }
    
    protected $pagesDir;
    /**
     * Gets the directory that contains the pages and their assets ('/_content/pages' by default).
     */
    public function getPagesDir()
    {
        if ($this->pagesDir === null)
        {
            try
            {
                $this->setPagesDir($this->rootDir . PieCrustDefaults::CONTENT_PAGES_DIR);
            }
            catch (PieCrustException $e)
            {
                $this->pagesDir = false;
            }
        }
        return $this->pagesDir;
    }
    
    /**
     * Sets the directory that contains the pages and their assets.
     */
    public function setPagesDir($dir)
    {
        $this->pagesDir = rtrim($dir, '/\\') . '/';
        if (is_dir($this->pagesDir) === false)
        {
            throw new PieCrustException('The pages directory doesn\'t exist: ' . $this->pagesDir);
        }
    }
    
    protected $postsDir;
    /**
     * Gets the directory that contains the posts and their assets ('/_content/posts' by default).
     */
    public function getPostsDir()
    {
        if ($this->postsDir === null)
        {
            try
            {
                $this->setPostsDir($this->rootDir . PieCrustDefaults::CONTENT_POSTS_DIR);
            }
            catch (PieCrustException $e)
            {
                $this->postsDir = false;
            }
        }
        return $this->postsDir;
    }
    
    /**
     * Sets the directory that contains the posts and their assets.
     */
    public function setPostsDir($dir)
    {
        $this->postsDir = rtrim($dir, '/\\') . '/';
        if (is_dir($this->postsDir) === false)
        {
            throw new PieCrustException('The posts directory doesn\'t exist: ' . $this->postsDir);
        }
    }

    protected $pluginsDirs;
    /**
     * Gets the directories that contain the user plugins.
     */
    public function getPluginsDirs()
    {
        if ($this->pluginsDirs === null)
        {
            try
            {
                $this->setPluginsDirs($this->rootDir . PieCrustDefaults::CONTENT_PLUGINS_DIR);
            }
            catch (PieCrustException $e)
            {
                // The default plugins directory doesn't exist... whatever, just don't use it.
                $this->pluginsDirs = array();
            }

            // Add custom plugin directories specified in the configuration.
            $additionalPaths = $this->getConfig()->getValue('site/plugins_dirs');
            if ($additionalPaths)
            {
                $this->addPluginsDir($additionalPaths);
            }
        }
        return $this->pluginsDirs;
    }
    
    /**
     * Sets the directories that contain the user plugins.
     */
    public function setPluginsDirs($dir)
    {
        $this->pluginsDirs = array();
        $this->addPluginsDir($dir);
    }

    /**
     * Adds a directory that contains some user plugins.
     */
    public function addPluginsDir($dir)
    {
        $this->getPluginsDirs();  // Ensure defaults are created.
        
        if (!is_array($dir)) 
            $dir = array($dir);

        foreach ($dir as $d)
        {
            $absolute = PathHelper::getAbsolutePath($d, $this->getRootDir());
            if (is_dir($absolute) === false)
            {
                throw new PieCrustException("The specified plugins directory doesn't exist: " . $absolute);
            }
            $this->pluginsDirs[] = rtrim($absolute, '/\\') . '/';
        }
    }

    protected $cacheDir;
    /**
     * Gets the cache directory ('/_cache' by default).
     */
    public function getCacheDir()
    {
        if ($this->cacheDir === null)
        {
            if ($this->cachingEnabled)
                $this->setCacheDir($this->rootDir . PieCrustDefaults::CACHE_DIR);
            else
                $this->cacheDir = false;
        }
        return $this->cacheDir;
    }
    
    /**
     * Sets the cache directory ('/_cache' by default).
     */
    public function setCacheDir($dir)
    {
        $this->cacheDir = rtrim($dir, '/\\') . '/';
        if (is_writable($this->cacheDir) === false)
        {
            try
            {
                if (!is_dir($this->cacheDir))
                {
                    mkdir($dir, 0777, true);
                }
                else
                {
                    chmod($this->cacheDir, 0777);
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException('The cache directory must exist and be writable, and we can\'t create it or change the permissions ourselves: ' . $this->cacheDir);
            }
        }
    }
    
    protected $pluginLoader;
    /**
     * Gets the plugin loader for this app.
     */
    public function getPluginLoader()
    {
        return $this->pluginLoader;
    }

    protected $config;
    /**
     * Gets the application's configuration.
     */
    public function getConfig()
    {
        $this->ensureConfig();
        return $this->config;
    }
    
    /**
     * Formats a given text using the registered page formatters.
     * 
     * Throws an exception if no 'exclusive' formatter was found.
     */
    public function formatText($text, $format = null)
    {
        if (!$format)
            $format = $this->getConfig()->getValueUnchecked('site/default_format');
        
        $unformatted = true;
        $formattedText = $text;
        $gotExclusiveFormatter = false;
        foreach ($this->getPluginLoader()->getFormatters() as $formatter)
        {
            $isExclusive = $formatter->isExclusive();
            if ((!$isExclusive || $unformatted) && 
                $formatter->supportsFormat($format))
            {
                $formattedText = $formatter->format($formattedText);
                $unformatted = false;
                if ($isExclusive)
                    $gotExclusiveFormatter = true;
            }
        }
        if (!$gotExclusiveFormatter)
            throw new PieCrustException("Unknown page format: " . $format);
        return $formattedText;
    }
    
    protected $pathPrefix;
    protected $pathSuffix;
    /**
     * Gets a formatted page URL.
     */
    public function formatUri($uri)
    {
        if ($this->pathPrefix === null or $this->pathSuffix === null)
        {
            $isBaking = ($this->getConfig()->getValue('baker/is_baking') === true);
            $isPretty = ($this->getConfig()->getValueUnchecked('site/pretty_urls') === true);
            $this->pathPrefix = $this->getConfig()->getValueUnchecked('site/root') . (($isPretty or $isBaking) ? '' : '?/');
            $this->pathSuffix = ($isBaking and !$isPretty) ? '.html' : '';
            if ($this->debuggingEnabled && !$isBaking)
            {
                if ($isPretty)
                    $this->pathSuffix .= '?!debug';
                else if (strpos($this->pathPrefix, '?') === false)
                    $this->pathSuffix .= '?!debug';
                else
                    $this->pathSuffix .= '&!debug';
            }
        }
        
        return $this->pathPrefix . $uri . $this->pathSuffix;
    }
    
    /**
     * Gets the template engine associated with the given extension.
     */
    public function getTemplateEngine($extension = 'html')
    {
        if ($extension == 'html')
        {
            $extension = $this->getConfig()->getValueUnchecked('site/default_template_engine');
        }

        foreach ($this->getPluginLoader()->getTemplateEngines() as $templateEngine)
        {
            if ($templateEngine->getExtension() == $extension)
            {
                return $templateEngine;
            }
        }
    }
    
    protected $lastRunInfo = null;
    /**
     * Gets the information about the last execution (call to run() or runUnsafe()).
     */
    public function getLastRunInfo()
    {
        return $this->lastRunInfo;
    }
    
    /**
     * Creates a new PieCrust instance with the given base URL.
     */
    public function __construct(array $parameters = array(), array $server = null)
    {
        $parameters = array_merge(
            array(
                'root' => null,
                'cache' => true,
                'debug' => false
            ),
            $parameters
        );
        if ($server == null)
        {
            $server = $_SERVER;
        }
        $get = array();
        if (isset($server['QUERY_STRING']))
        {
            parse_str($server['QUERY_STRING'], $get);
        }
        
        if (!$parameters['root'])
        {
            // Figure out the default root directory.
            if (!isset($server['SCRIPT_FILENAME']))
                throw new PieCrustException("Can't figure out the default root directory for the website.");
            $parameters['root'] = dirname($server['SCRIPT_FILENAME']);
        }
        
        $this->rootDir = rtrim($parameters['root'], '/\\') . '/';
        $this->debuggingEnabled = ((bool)$parameters['debug'] or isset($get['!debug']));
        $this->cachingEnabled = ((bool)$parameters['cache'] and !isset($get['!nocache']));
        $this->pluginLoader = new PluginLoader($this);
    }

    /**
     * Runs PieCrust on the given URI.
     */
    public function run($uri = null, $server = null)
    {
        try
        {
            $this->runUnsafe($uri, $server);
        }
        catch (Exception $e)
        {
            $handler = new PieCrustErrorHandler($this);
            $handler->handleError($e);
        }
    }
    
    /**
     * Runs PieCrust on the given URI with the given extra page rendering data,
     * but without any error handling.
     */
    public function runUnsafe($uri = null, $server = null, $extraPageData = null, array &$headers = null)
    {
        // Remember the time.
        $this->lastRunInfo = array('start_time' => microtime(true));
        
        // Check the cache validity, and clean it automatically.
        if ($this->cachingEnabled)
        {
            $cacheInfo = new PieCrustCacheInfo($this);
            $cacheValidity = $cacheInfo->getValidity(true);
            $this->lastRunInfo['cache_validity'] = $cacheValidity;
        }
        else
        {
            $this->lastRunInfo['cache_validity'] = null;
        }

        // Get the resource URI and corresponding physical path.
        if ($server == null) $server = $_SERVER;
        if ($uri == null)
        {
            $uri = ServerHelper::getRequestUri($server, $this->getConfig()->getValueUnchecked('site/pretty_urls'));
        }

        // Do the heavy lifting.
        $page = Page::createFromUri($this, $uri);
        if ($extraPageData != null)
        {
            $page->setExtraPageData($extraPageData);
        }
        $pageRenderer = new PageRenderer($page);
        $output = $pageRenderer->get();
        
        // Set or return the HTML headers.
        HttpHeaderHelper::setOrAddHeaders(PageRenderer::getHeaders($page->getConfig()->getValue('content_type'), $server), $headers);
        
        // Handle caching.
        if (!$this->isDebuggingEnabled())
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
        if ($this->isDebuggingEnabled())
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
        $gzipEnabled = (($this->getConfig()->getValueUnchecked('site/enable_gzip') === true) and
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
     * Ensures the configuration has been loaded.
     */
    protected function ensureConfig()
    {
        if ($this->config == null)
        {
            $configCache = $this->cachingEnabled ? $this->getCacheDir() : false;
            $this->config = new PieCrustConfiguration($this->rootDir . PieCrustDefaults::CONFIG_PATH, $configCache);
        }
    }
}
