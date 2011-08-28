<?php

/**
 * The main PieCrust app.
 *
 */
require_once 'PieCrustEnvironment.inc.php';

/**
 * The website directory, where the _cache and _content directories live.
 * You can change this if the PieCrust application directory is in a different
 * location than the website (e.g. you have several websites using the same
 * PieCrust application, or you moved the '_piecrust' directory outside of the
 * website's directory for increased security).
 *
 * Note that this is only the default value. You can specify the root directory
 * by passing it to the PieCrust constructor too.
 */
if (!defined('PIECRUST_ROOT_DIR'))
{
    if (!isset($_SERVER['SCRIPT_FILENAME'])) throw new PieCrustException("Can't figure out the root directory for the website.");
    define('PIECRUST_ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
}

/**
 * Various PieCrust things.
 */
define('PIECRUST_APP_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('PIECRUST_INDEX_PAGE_NAME', '_index');
define('PIECRUST_CATEGORY_PAGE_NAME', '_category');
define('PIECRUST_TAG_PAGE_NAME', '_tag');
define('PIECRUST_CONTENT_DIR', '_content/');
define('PIECRUST_CONFIG_PATH', PIECRUST_CONTENT_DIR . 'config.yml');
define('PIECRUST_CONTENT_TEMPLATES_DIR', PIECRUST_CONTENT_DIR . 'templates/');
define('PIECRUST_CONTENT_PAGES_DIR', PIECRUST_CONTENT_DIR . 'pages/');
define('PIECRUST_CONTENT_POSTS_DIR', PIECRUST_CONTENT_DIR . 'posts/');
define('PIECRUST_CACHE_DIR', '_cache/');
define('PIECRUST_CACHE_INFO_FILENAME', 'cacheinfo');

define('PIECRUST_DEFAULT_BLOG_KEY', 'blog');
define('PIECRUST_DEFAULT_FORMAT', 'markdown');
define('PIECRUST_DEFAULT_PAGE_TEMPLATE_NAME', 'default');
define('PIECRUST_DEFAULT_POST_TEMPLATE_NAME', 'post');
define('PIECRUST_DEFAULT_TEMPLATE_ENGINE', 'twig');


/**
 * Include all the classes we need.
 */
require_once 'Page.class.php';
require_once 'Cache.class.php';
require_once 'IFormatter.class.php';
require_once 'ServerHelper.class.php';
require_once 'PluginLoader.class.php';
require_once 'PageRenderer.class.php';
require_once 'PageRepository.class.php';
require_once 'ITemplateEngine.class.php';
require_once 'HttpHeaderHelper.class.php';
require_once 'PieCrustSiteData.class.php';
require_once 'PieCrustCacheInfo.class.php';
require_once 'PieCrustException.class.php';
require_once 'PieCrustErrorHandler.class.php';
require_once 'PieCrustConfiguration.class.php';

require_once 'libs/sfyaml/lib/sfYamlParser.php';
require_once 'libs/sfyaml/lib/sfYamlDumper.php';


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
    const VERSION = '0.0.4';
    
    protected $rootDir;
    /**
     * The root directory of the website.
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }
    
    protected $urlBase;
    /**
     * The base URL of the application ('/' most of the time).
     */
    public function getUrlBase()
    {
        return $this->urlBase;
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
            $this->setTemplatesDirs($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
        }
        return $this->templatesDirs;
    }
    
    /**
     * Sets the directories that contain templates and layouts.
     */
    public function setTemplatesDirs($dir)
    {
        $this->templatesDirs = array();
        $this->addTemplatesDir($dir);
    }
    
    /**
     * Adds a templates directory.
     */
    public function addTemplatesDir($dir)
    {
        $this->getTemplatesDirs();  // Ensure defaults are created.
        
        if (!is_array($dir)) $dir = array($dir);
        foreach ($dir as $d)
        {
            if (is_dir($d) === false)
            {
                throw new PieCrustException("The templates directory doesn't exist: " . $d);
            }
            $this->templatesDirs[] = rtrim($d, '/\\') . DIRECTORY_SEPARATOR;
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
            $this->setPagesDir($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
        }
        return $this->pagesDir;
    }
    
    /**
     * Sets the directory that contains the pages and their assets.
     */
    public function setPagesDir($dir)
    {
        $this->pagesDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
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
            $this->setPostsDir($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_POSTS_DIR));
        }
        return $this->postsDir;
    }
    
    /**
     * Sets the directory that contains the posts and their assets.
     */
    public function setPostsDir($dir)
    {
        $this->postsDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
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
            $this->setCacheDir($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CACHE_DIR));
        }
        return $this->cacheDir;
    }
    
    /**
     * Sets the cache directory ('/_cache' by default).
     */
    public function setCacheDir($dir)
    {
        $this->cacheDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
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
                                            'IFormatter',
                                            PIECRUST_APP_DIR . 'formatters',
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
            $this->pathPrefix = $this->getUrlBase() . (($isPretty or $isBaking) ? '' : '?/');
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
                                        'ITemplateEngine',
                                        PIECRUST_APP_DIR . 'template-engines'
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
                'url_base' => null,
                'root' => PIECRUST_ROOT_DIR,
                'cache' => true,
                'debug' => false
            ),
            $parameters
        );

        $this->rootDir = rtrim(realpath($parameters['root']), '/\\') . DIRECTORY_SEPARATOR;
        $this->debuggingEnabled = ((bool)$parameters['debug'] or isset($_GET['!debug']));
        $this->cachingEnabled = ((bool)$parameters['cache'] and !isset($_GET['!nocache']));
        
        if ($parameters['url_base'] === null)
        {
            $host = ((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $folder = rtrim(dirname($_SERVER['PHP_SELF']), '/') .'/';
            $this->urlBase = $host . $folder;
        }
        else
        {
            $this->urlBase = rtrim($parameters['url_base'], '/') . '/';
        }
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
                'url_base' => $this->urlBase,
                'cache_dir' => ($this->cachingEnabled ? $this->getCacheDir() : null),
                'cache' => $this->cachingEnabled
            );
            $this->config = new PieCrustConfiguration($configParameters, ($this->rootDir . PIECRUST_CONFIG_PATH));
        }
    }
    
    /**
     * Sets up basic things like the global error handler or the timezone.
     */
    public static function setup($profile = 'web')
    {
        date_default_timezone_set('America/Los_Angeles');
        if ($profile == 'web')
        {
            //ini_set('display_errors', false);
            set_error_handler('piecrust_error_handler');
        }
    }
    
    /**
     * A utility function that setups and runs PieCrust in one call.
     */
    public static function setupAndRun($parameters = null, $uri = null, $profile = 'web')
    {
        PieCrust::setup($profile);
        $pieCrust = new PieCrust($parameters);
        $pieCrust->run($uri);
    }
    
    /**
     * A utility function that searches an app's templates directories for
     * a template file.
     */
    public static function getTemplatePath(PieCrust $pieCrust, $templateName)
    {
        foreach ($pieCrust->getTemplatesDirs() as $dir)
        {
            $path = $dir . DIRECTORY_SEPARATOR . $templateName;
            if (is_file($path))
            {
                return $path;
            }
        }
        throw new PieCrustException("Couldn't find template: " . $templateName);
    }
}
