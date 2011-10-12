<?php

namespace PieCrust;

use \Exception;
use PieCrust\Formatters\IFormatter;
use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\TemplateEngines\ITemplateEngine;
use PieCrust\Util\HttpHeaderHelper;
use PieCrust\Util\PluginLoader;
use PieCrust\Util\ServerHelper;


/**
 * The main PieCrust application class.
 *
 * This class contains the application's configuration and directory setup information,
 * and handles, among other things, routing and errors.
 */
class PieCrust
{
    /**
     * The current version of PieCrust.
     */
    const VERSION = '0.1.3';
    
    /**
     * The application's source code directory.
     */
    const APP_DIR = __DIR__;
    
    /**
     * Names for special pages.
     */
    const INDEX_PAGE_NAME = '_index';
    const CATEGORY_PAGE_NAME = '_category';
    const TAG_PAGE_NAME = '_tag';
    
    /**
     * Names for special directories and files.
     */
    const CONTENT_DIR = '_content/';
    const CONFIG_PATH = '_content/config.yml';
    const CONTENT_TEMPLATES_DIR = '_content/templates/';
    const CONTENT_PAGES_DIR = '_content/pages/';
    const CONTENT_POSTS_DIR = '_content/posts/';
    const CACHE_DIR = '_cache/';
    const CACHE_INFO_FILENAME = 'cacheinfo';
    
    /**
     * Default values for configuration settings.
     */
    const DEFAULT_BLOG_KEY = 'blog';
    const DEFAULT_FORMAT = 'markdown';
    const DEFAULT_PAGE_TEMPLATE_NAME = 'default';
    const DEFAULT_POST_TEMPLATE_NAME = 'post';
    const DEFAULT_TEMPLATE_ENGINE = 'twig';
    const DEFAULT_POSTS_FS = 'flat';
    const DEFAULT_DATE_FORMAT = 'F j, Y';
    
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
            $this->setTemplatesDirs(self::CONTENT_TEMPLATES_DIR);
            
            // Add custom template directories specified in the configuration.
            $additionalPaths = $this->getConfigValue('site', 'template_dirs');
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
        
        if (!is_array($dir)) $dir = array($dir);
        foreach ($dir as $d)
        {
            $absolute = $d;
            if ($d[0] != '/' and $d[1] != ':')
            {
                $absolute = $this->getRootDir() . $d;
            }
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
            $this->setPagesDir($this->rootDir . self::CONTENT_PAGES_DIR);
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
            $this->setPostsDir($this->rootDir . self::CONTENT_POSTS_DIR);
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
    
    protected $cacheDir;
    /**
     * Gets the cache directory ('/_cache' by default).
     */
    public function getCacheDir()
    {
        if ($this->cacheDir === null)
        {
            if ($this->cachingEnabled)
                $this->setCacheDir($this->rootDir . self::CACHE_DIR);
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
    
    protected $config;
    /**
     * Gets the application's configuration.
     *
     * This function lazy-loads the '/_content/config.yml' file unless the configuration was
     * specifically set by setConfig().
     */
    public function getConfig($category = null)
    {
        $this->ensureConfig();
        if ($category != null)
        {
            return $this->config->getSection($category);
        }
        return $this->config;
    }
    
    /**
     * Sets the application's configuration.
     *
     * This is useful for controlled environments like unit-testing.
     */
    public function setConfig($config)
    {
        $this->ensureConfig();
        $this->config->set($config);
    }
    
    /**
     * Helper function for getting a configuration setting, or null if it doesn't exist.
     */
    public function getConfigValue($section, $key)
    {
        $this->ensureConfig();
       return $this->config->getSectionValue($section, $key);
    }
    
    /**
     * Helper function for getting a configuration setting, but without checks
     * for existence or validity.
     */
    public function getConfigValueUnchecked($section, $key)
    {
        $this->ensureConfig();
        return $this->config->getSectionValueUnchecked($section, $key);
    }
    
    /**
     * Sets a configuration setting.
     */
    public function setConfigValue($section, $key, $value)
    {
        $this->ensureConfig();
        $this->config->setSectionValue($section, $key, $value);
    }
    
    protected $formattersLoader;
    /**
     * Gets the PluginLoader for the page formatters.
     */
    public function getFormattersLoader()
    {
        if ($this->formattersLoader === null)
        {
            $this->formattersLoader = new PluginLoader(
                                            'PieCrust\\Formatters\\IFormatter',
                                            self::APP_DIR . '/Formatters',
                                            function ($p1, $p2) { return $p1->getPriority() < $p2->getPriority(); }
                                            );
            foreach ($this->formattersLoader->getPlugins() as $formatter)
            {
                $formatter->initialize($this);
            }
        }
        return $this->formattersLoader;
    }
    
    /**
     * Formats a given text using the registered page formatters.
     */
    public function formatText($text, $format)
    {
        $unformatted = true;
        $formattedText = $text;
        foreach ($this->getFormattersLoader()->getPlugins() as $formatter)
        {
            if ($formatter->supportsFormat($format, $unformatted))
            {
                $formattedText = $formatter->format($formattedText);
                $unformatted = false;
            }
        }
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
            $isBaking = ($this->getConfigValue('baker', 'is_baking') === true);
            $isPretty = ($this->getConfigValueUnchecked('site','pretty_urls') === true);
            $this->pathPrefix = $this->getConfigValueUnchecked('site', 'root') . (($isPretty or $isBaking) ? '' : '?/');
            $this->pathSuffix = ($isBaking and !$isPretty) ? '.html' : '';
            if ($this->debuggingEnabled && !$isBaking)
                $this->pathSuffix .= '?!debug';
        }
        
        return $this->pathPrefix . $uri . $this->pathSuffix;
    }
    
    protected $templateEngines;
    /**
     * Gets the template engine associated with the given extension.
     */
    public function getTemplateEngine($extension = 'html')
    {
        if ($this->templateEngines === null)
        {
            $loader = new PluginLoader(
                                        'PieCrust\\TemplateEngines\\ITemplateEngine',
                                        self::APP_DIR . '/TemplateEngines'
                                        );
            $this->templateEngines = array();
            foreach ($loader->getPlugins() as $engine)
            {
                $engine->initialize($this);
                $this->templateEngines[$engine->getExtension()] = $engine;
            }
        }
        
        if ($extension == 'html')
        {
            $extension = $this->getConfigValueUnchecked('site', 'default_template_engine');
        }
        return $this->templateEngines[$extension];
    }
    
    /**
     * Gets the application's data for page rendering.
     */
    public function getSiteData($wasCurrentPageCached = null)
     {
        $this->ensureConfig();
        $data = array_merge(
            $this->config->get(), 
            array('piecrust' => new PieCrustSiteData($this, $wasCurrentPageCached))
         );
        return $data;
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
    public function __construct(array $parameters = array())
    {
        $parameters = array_merge(
            array(
                'root' => null,
                'cache' => true,
                'debug' => false
            ),
            $parameters
        );
        
        if (!$parameters['root'])
        {
            // Figure out the default root directory.
            if (!isset($_SERVER['SCRIPT_FILENAME']))
                throw new PieCrustException("Can't figure out the default root directory for the website.");
            $parameters['root'] = dirname($_SERVER['SCRIPT_FILENAME']);
        }
        
        $this->rootDir = rtrim($parameters['root'], '/\\') . '/';
        $this->debuggingEnabled = ((bool)$parameters['debug'] or isset($_GET['!debug']));
        $this->cachingEnabled = ((bool)$parameters['cache'] and !isset($_GET['!nocache']));
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
        if ($uri == null) $uri = ServerHelper::getRequestUri($server, $this->getConfigValueUnchecked('site', 'pretty_urls'));
        
        // Do the heavy lifting.
        $page = Page::createFromUri($this, $uri);
        if ($extraPageData != null) $page->setExtraPageData($extraPageData);
        $pageRenderer = new PageRenderer($this);
        $output = $pageRenderer->get($page, $extraPageData);
        
        // Set or return the HTML headers.
        HttpHeaderHelper::setOrAddHeaders(PageRenderer::getHeaders($page->getConfigValue('content_type'), $server), $headers);
        
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
            $cacheTime = $page->getConfigValue('cache_time');
            if ($cacheTime === null) $cacheTime = $this->getConfigValue('site', 'cache_time');
            if ($cacheTime)
            {
                HttpHeaderHelper::setOrAddHeader('Cache-Control', 'public, max-age=' . $cacheTime, $headers);
            }
        }
    
        // Output with or without GZip compression.
        $gzipEnabled = (($this->getConfigValueUnchecked('site', 'enable_gzip') === true) and
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
    
    protected function ensureConfig()
    {
        if ($this->config == null)
        {
            $configParameters = array(
                'cache_dir' => ($this->cachingEnabled ? $this->getCacheDir() : false),
                'cache' => $this->cachingEnabled
            );
            $this->config = new PieCrustConfiguration($configParameters, ($this->rootDir . self::CONFIG_PATH));
        }
    }
    
    /**
     * A utility function that searches an app's templates directories for
     * a template file.
     */
    public static function getTemplatePath(PieCrust $pieCrust, $templateName)
    {
        foreach ($pieCrust->getTemplatesDirs() as $dir)
        {
            $path = $dir . '/' . $templateName;
            if (is_file($path))
            {
                return $path;
            }
        }
        throw new PieCrustException("Couldn't find template: " . $templateName);
    }
}
