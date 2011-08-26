<?php

/**
 * The main PieCrust app.
 *
 */
if (!defined('PHP_VERSION_ID') or PHP_VERSION_ID < 50300)
{
    die("You need PHP 5.3+ to use PieCrust.");
}


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
 * Set the include path
 */
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));

/**
 * Include all the classes we need.
 */
require_once 'IFormatter.class.php';
require_once 'ITemplateEngine.class.php';
require_once 'Page.class.php';
require_once 'PageRenderer.class.php';
require_once 'PageRepository.class.php';
require_once 'Cache.class.php';
require_once 'PluginLoader.class.php';
require_once 'PieCrustException.class.php';

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
        if ($category == null) return $this->config;
        if (isset($this->config[$category])) return $this->config[$category];
        return null;
    }
    
    /**
     * Sets the application's configuration.
     *
     * This is useful for controlled environments like unit-testing.
     */
    public function setConfig($config)
    {
        $this->config = $this->validateConfig($config);
    }
    
    protected function ensureConfig()
    {
        if ($this->config === null)
        {
            $configPath = $this->rootDir . PIECRUST_CONFIG_PATH;
            
            // Cache a validated JSON version of the configuration for faster
            // boot-up time (this saves a couple milliseconds).
            $cache = $this->cachingEnabled ? new Cache($this->getCacheDir()) : null;
            $configOrCodeTime = max(filemtime($configPath), filemtime(__FILE__));
            if ($cache != null and $cache->isValid('config', 'json', $configOrCodeTime))
            {
                $configText = $cache->read('config', 'json');
                $this->config = json_decode($configText, true);
            }
            else
            {
                try
                {
                    $yamlParser = new sfYamlParser();
                    $config = $yamlParser->parse(file_get_contents($configPath));
                    $this->config = $this->validateConfig($config);
                }
                catch (Exception $e)
                {
                    throw new PieCrustException('An error was found in the PieCrust configuration file: ' . $e->getMessage());
                }
                
                $yamlMarkup = json_encode($this->config);
                if ($cache != null) $cache->write('config', 'json', $yamlMarkup);
            }
        }
    }
    
    protected function validateConfig($config)
    {
        // Add the default values for the site configuration.
        if (!isset($config['site']))
        {
            $config['site'] = array();
        }
        $config['site'] = array_merge(array(
                        'title' => 'PieCrust Untitled Website',
                        'root' => $this->urlBase,
                        'default_format' => PIECRUST_DEFAULT_FORMAT,
                        'default_template_engine' => PIECRUST_DEFAULT_TEMPLATE_ENGINE,
                        'enable_gzip' => false,
                        'pretty_urls' => false,
                        'posts_fs' => 'flat',
                        'blogs' => array(PIECRUST_DEFAULT_BLOG_KEY),
                        'cache_time' => 28800,
                        'check_cache_validity' => true
                    ),
                    $config['site']);
        if (in_array(PIECRUST_DEFAULT_BLOG_KEY, $config['site']['blogs']) and count($config['site']['blogs']) > 1)
            throw new PieCrustException("'".PIECRUST_DEFAULT_BLOG_KEY."' cannot be specified as a blog key for multi-blog configurations. Please pick custom keys.");
        
        // Add default values for the blogs configurations, or use values
        // defined at the site level for easy site-wide configuration of multiple blogs
        // and backwards compatibility.
        $defaultValues = array(
            'post_url' => '%year%/%month%/%day%/%slug%',
            'tag_url' => 'tag/%tag%',
            'category_url' => '%category%',
            'posts_per_page' => 5,
            'date_format' => 'F j, Y'
        );
        foreach (array_keys($defaultValues) as $key)
        {
            if (isset($config['site'][$key]))
                $defaultValues[$key] = $config['site'][$key];
        }
        foreach ($config['site']['blogs'] as $blogKey)
        {
            $prefix = '';
            if ($blogKey != PIECRUST_DEFAULT_BLOG_KEY)
            {
                $prefix = $blogKey . '/';
            }
            if (!isset($config[$blogKey]))
            {
                $config[$blogKey] = array();
            }
            $config[$blogKey] = array_merge(array(
                            'post_url' => $prefix . $defaultValues['post_url'],
                            'tag_url' => $prefix . $defaultValues['tag_url'],
                            'category_url' => $prefix . $defaultValues['category_url'],
                            'posts_per_page' => $defaultValues['posts_per_page'],
                            'date_format' => $defaultValues['date_format']
                        ),
                        $config[$blogKey]);
        }
        
        return $config;
    }
    
    /**
     * Helper function for getting a configuration setting, or null if it doesn't exist.
     */
    public function getConfigValue($category, $key)
    {
        $this->ensureConfig();
        if (!isset($this->config[$category]))
        {
            return null;
        }
        if (!isset($this->config[$category][$key]))
        {
            return null;
        }
        return $this->config[$category][$key];
    }
    
    /**
     * Helper function for getting a configuration setting, but without checks
     * for existence or validity.
     */
    public function getConfigValueUnchecked($category, $key)
    {
        $this->ensureConfig();
        return $this->config[$category][$key];
    }
    
    /**
     * Sets a configuration setting.
     */
    public function setConfigValue($category, $key, $value)
    {
        $this->ensureConfig();
        if (!isset($this->config[$category]))
        {
            $this->config[$category] = array($key => $value);
        }
        else
        {
            $this->config[$category][$key] = $value;
        }
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
    public function getSiteData()
    {
        $this->ensureConfig();
        $data = array_merge(
            $this->config, 
            array(
                'piecrust' => array(
                    'version' => self::VERSION,
                    'branding' => 'Baked with <em><a href="http://bolt80.com/piecrust/">PieCrust</a> ' . self::VERSION . '</em>.'
                )
            )
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
    public function __construct(array $parameters = null)
    {
        if ($parameters == null)
        {
            $parameters = array();
        }
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
            $this->handleError($e);
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
        if ($this->cachingEnabled && $this->getConfigValueUnchecked('site', 'check_cache_validity'))
        {
            $cacheValidity = $this->checkCacheValidity(true);
            $this->lastRunInfo['cache_validity'] = $cacheValidity;
        }
        else
        {
            $this->lastRunInfo['cache_validity'] = null;
        }
        
        // Get the resource URI and corresponding physical path.
        if ($server == null) $server = $_SERVER;
        if ($uri == null) $uri = $this->getRequestUri($server);
        
        // Do the heavy lifting.
        $page = Page::createFromUri($this, $uri);
        if ($extraPageData != null) $page->setExtraPageData($extraPageData);
        $pageRenderer = new PageRenderer($this);
        $output = $pageRenderer->get($page, $extraPageData);
        
        // Set or return the HTML headers.
        self::setOrAddHeaders(PageRenderer::getHeaders($page->getConfigValue('content_type'), $server), $headers);
        
        // Handle caching.
        if (!$this->isDebuggingEnabled())
        {
            $hash = md5($output);
            self::setOrAddHeader('Etag', '"' . $hash . '"', $headers);
            
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
                    self::setOrAddHeader(0, 304, $headers);
                    self::setOrAddHeader('Content-Length', '0', $headers);
                    return;
                }
            }
        }
        if ($this->isDebuggingEnabled())
        {
            self::setOrAddHeader('Cache-Control', 'no-cache, must-revalidate', $headers);
        }
        else
        {
            $cacheTime = $page->getConfigValue('cache_time');
            if ($cacheTime === null) $cacheTime = $this->getConfigValue('site', 'cache_time');
            if ($cacheTime)
            {
                self::setOrAddHeader('Cache-Control', 'public, max-age=' . $cacheTime, $headers);
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
                self::setOrAddHeader('Content-Length', strlen($output), $headers);
                echo $output;
            }
            else
            {
                self::setOrAddHeader('Content-Encoding', 'gzip', $headers);
                self::setOrAddHeader('Content-Length', strlen($zippedOutput), $headers);
                echo $zippedOutput;
            }
        }
        else
        {
            self::setOrAddHeader('Content-Length', strlen($output), $headers);
            echo $output;
        }
    }
    
    /**
     * Handles an exception by showing an appropriate
     * error page.
     */
    public function handleError(Exception $e)
    {
        $displayErrors = ((bool)ini_get('display_errors') or $this->isDebuggingEnabled());
        
        // If debugging is enabled, just display the error and exit.
        if ($displayErrors)
        {
            $errorMessage = piecrust_format_errors(array($e), true);
            $this->showSystemMessage('error', $errorMessage);
            return;
        }
        
        // First of all, check that we're not running
        // some completely brand new and un-configured website.
        if ($this->isEmptySetup())
        {
            $this->showSystemMessage('welcome');
            return;
        }
        
        // Get the URI to the custom error page.
        $errorPageUri = '_error';
        if ($e->getMessage() == '404')
        {
            header('HTTP/1.0 404 Not Found');
            $errorPageUri = '_404';
        }
        $errorPageUriInfo = UriParser::parseUri($this, $errorPageUri);
        $errorMessage = "<p>We're very sorry but something wrong happened. We'll try to do better next time.</p>";
        if ($errorPageUriInfo != null and is_file($errorPageUriInfo['path']))
        {
            // We have a custom error page. Show it, or display
            // the "fatal error" page if even this doesn't work.
            try
            {
                $this->runUnsafe($errorPageUri);
            }
            catch (Exception $inner)
            {
                // Well there's really something wrong.
                $this->showSystemMessage('error', $errorMessage);
            }
        }
        else
        {
            // We don't have a custom error page. Just show a generic
            // error page and exit.
            $this->showSystemMessage(substr($errorPageUri, 1), $errorMessage);
        }
    }
    
    /**
     * Gets the requested PieCrust URI based on given server variables.
     */
    public function getRequestUri($server)
    {
        $requestUri = null;
        if ($this->getConfigValueUnchecked('site', 'pretty_urls') !== true)
        {
            // Using standard query (no pretty URLs / URL rewriting)
            $requestUri = $server['QUERY_STRING'];
            if ($requestUri == null or $requestUri == '')
            {
                $requestUri = '/';
            }
            else if (strpos($requestUri, '&') !== false)
            {
                $requestUri = strstr($requestUri, '&', true);
            }
        }
        else
        {
            // Get the requested URI via URL rewriting.
            if (isset($server['IIS_WasUrlRewritten']) &&
                $server['IIS_WasUrlRewritten'] == '1' &&
                isset($server['UNENCODED_URL']) &&
                $server['UNENCODED_URL'] != '')
            {
                // IIS7 rewriting module.
                $requestUri = $server['UNENCODED_URL'];
            }
            elseif (isset($server['REQUEST_URI']))
            {
                // Apache mod_rewrite.
                $requestUri = $server['REQUEST_URI'];
            }
            
            if ($requestUri != null)
            {
                // Clean up by removing the base URL of the application, and the trailing
                // query string that we should ignore because we're using 'pretty URLs'.
                $rootDirectory = rtrim(dirname($server['PHP_SELF']), '/') . '/';
                if (strlen($rootDirectory) > 1)
                {
                    if (strlen($requestUri) < strlen($rootDirectory))
                    {
                        throw new PieCrustException("You're trying to access a resource that's not within the directory served by PieCrust.");
                    }
                    $requestUri = substr($requestUri, strlen($rootDirectory) - 1);
                }
                $questionMark = strpos($requestUri, '?');
                if ($questionMark !== false)
                {
                    $requestUri = substr($requestUri, 0, $questionMark);
                }
            }
        }
        if ($requestUri == null)
        {
            throw new PieCrustException("PieCrust can't figure out the request URI. " .
                                        "It may be because you're running a non supported web server (PieCrust currently supports IIS 7.0+ and Apache), " .
                                        "or just because my code sucks.");
        }
        return $requestUri;
    }
    
    /**
     * Get the validity information for the cache.
     *
     * If $cleanCache is true and the cache is not valid, it will be wiped.
     */
    public function checkCacheValidity($cleanCache)
    {
        // Things that could make the cache invalid:
        // - changing the version of PieCrust
        // - changing the pretty_urls setting
        // - being in/out of bake mode
        // - changing the base URL
        $prettyUrls = ($this->getConfigValueUnchecked('site', 'pretty_urls') ? "true" : "false");
        $isBaking = ($this->getConfigValue('baker', 'is_baking') ? "true" : "false");
        $cacheInfo = "version=". PieCrust::VERSION .
                     "&url_base=" . $this->urlBase .
                     "&pretty_urls=" . $prettyUrls .
                     "&is_baking=" . $isBaking;
        $cacheInfo = hash('sha1', $cacheInfo);
        
        $isCacheValid = false;
        $cacheInfoFileName = $this->getCacheDir() . PIECRUST_CACHE_INFO_FILENAME;
        if (file_exists($cacheInfoFileName))
        {
            $previousCacheInfo = file_get_contents($cacheInfoFileName);
            $isCacheValid = ($previousCacheInfo == $cacheInfo);
        }
        $cacheValidity = array(
            'is_valid' => $isCacheValid,
            'path' => $cacheInfoFileName,
            'hash' => $cacheInfo,
            'was_cleaned' => false
        );
        if ($cleanCache && !$isCacheValid)
        {
            // Clean the cache!
            FileSystem::deleteDirectoryContents($this->getCacheDir());
            file_put_contents($cacheInfoFileName, $cacheInfo);
            $cacheValidity['is_valid'] = true;
            $cacheValidity['was_cleaned'] = true;
        }
        return $cacheValidity;
    }
    
    protected function isEmptySetup()
    {
        if (!is_dir($this->rootDir . PIECRUST_CONTENT_DIR))
            return true;
        if (!is_file($this->rootDir . PIECRUST_CONFIG_PATH))
            return true;
        $templatesDir = ($this->templatesDirs != null) ? $this->templatesDirs[0] : ($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
        if (!is_dir($templatesDir))
            return true;
        $pagesDir = ($this->pagesDir != null) ? $this->pagesDir : ($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
        if (!is_dir($pagesDir))
            return true;
        if (!is_file($pagesDir . PIECRUST_INDEX_PAGE_NAME . '.html'))
            return true;
            
        return false;
    }
    
    protected function showSystemMessage($message, $details = null)
    {
        $contents = file_get_contents(PIECRUST_APP_DIR . 'messages/' . $message . '.html');
        if ($details != null)
        {
            $contents = str_replace('{{ details }}', $details, $contents);
        }
        echo $contents;
    }
    
    protected static function setOrAddHeader($header, $value, &$headers)
    {
        if ($headers === null)
        {
            if ($header === 0)
            {
                header("HTTP/1.1 " . self::getHttpStatusHeader($value));
            }
            else
            {
                header($header . ': ' . $value);
            }
        }
        else
        {
            $headers[$header] = $value;
        }
    }
    
    protected static function setOrAddHeaders($headersAndValues, &$headers)
    {
        foreach ($headersAndValues as $h => $v)
        {
            self::setOrAddHeader($h, $v, $headers);
        }
    }
    
    protected static function getHttpStatusHeader($code)
    {
        static $headers = array(100 => "100 Continue",
                                200 => "200 OK",
                                201 => "201 Created",
                                204 => "204 No Content",
                                206 => "206 Partial Content",
                                300 => "300 Multiple Choices",
                                301 => "301 Moved Permanently",
                                302 => "302 Found",
                                303 => "303 See Other",
                                304 => "304 Not Modified",
                                307 => "307 Temporary Redirect",
                                400 => "400 Bad Request",
                                401 => "401 Unauthorized",
                                403 => "403 Forbidden",
                                404 => "404 Not Found",
                                405 => "405 Method Not Allowed",
                                406 => "406 Not Acceptable",
                                408 => "408 Request Timeout",
                                410 => "410 Gone",
                                413 => "413 Request Entity Too Large",
                                414 => "414 Request URI Too Long",
                                415 => "415 Unsupported Media Type",
                                416 => "416 Requested Range Not Satisfiable",
                                417 => "417 Expectation Failed",
                                500 => "500 Internal Server Error",
                                501 => "501 Method Not Implemented",
                                503 => "503 Service Unavailable",
                                506 => "506 Variant Also Negotiates");
        return $headers[$code];
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
