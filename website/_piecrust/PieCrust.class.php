<?php

/**
 *  The main PieCrust app.
 *
 */

 
/**
 * The application directory, where this file lives.
 */
define('PIECRUST_APP_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);

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
    define('PIECRUST_ROOT_DIR', dirname(PIECRUST_APP_DIR) . DIRECTORY_SEPARATOR);
}

/**
 * Some default values for various PieCrust things.
 */
define('PIECRUST_INDEX_PAGE_NAME', '_index');
define('PIECRUST_CATEGORY_PAGE_NAME', '_category');
define('PIECRUST_TAG_PAGE_NAME', '_tag');
define('PIECRUST_CONTENT_DIR', '_content/');
define('PIECRUST_CONFIG_PATH', PIECRUST_CONTENT_DIR . 'config.yml');
define('PIECRUST_CONTENT_TEMPLATES_DIR', PIECRUST_CONTENT_DIR . 'templates/');
define('PIECRUST_CONTENT_PAGES_DIR', PIECRUST_CONTENT_DIR . 'pages/');
define('PIECRUST_CONTENT_POSTS_DIR', PIECRUST_CONTENT_DIR . 'posts/');
define('PIECRUST_CACHE_DIR', '_cache/');

define('PIECRUST_DEFAULT_FORMAT', 'markdown');
define('PIECRUST_DEFAULT_PAGE_TEMPLATE_NAME', 'default');
define('PIECRUST_DEFAULT_POST_TEMPLATE_NAME', 'post');
define('PIECRUST_DEFAULT_TEMPLATE_ENGINE', 'Twig');

/**
 * Include all the classes we need.
 */
require_once 'IFormatter.class.php';
require_once 'ITemplateEngine.class.php';
require_once 'Page.class.php';
require_once 'PageRenderer.class.php';
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
    const VERSION = '0.0.2';
	
	protected $rootDir;
	/**
	 * The root directory of the website.
	 */
	public function getRootDir()
	{
		return $this->rootDir();
	}
    
    protected $host;
    /**
     * The host of the application.
     */
    public function getHost()
    {
        return $this->host;
    }
    
    protected $urlBase;
	/**
	 * The base URL of the application ('/' most of the time).
	 */
	public function getUrlBase()
	{
		return $this->urlBase;
	}
    
    protected $templatesDir;
    /**
	 * Gets the directory that contains templates and layouts ('/_content/templates' by default).
	 */
    public function getTemplatesDir()
    {
        if ($this->templatesDir === null)
		{
            $this->setTemplatesDir($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
		}
        return $this->templatesDir;
    }
    
	/**
	 * Sets the directory that contains templates and layouts.
	 */
    public function setTemplatesDir($dir)
    {
		$this->templatesDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        if (is_dir($this->templatesDir) === false)
		{
            throw new PieCrustException('The templates directory doesn\'t exist: ' . $this->templatesDir);
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
			throw new PieCrustException('The cache directory must be writable: ' . $this->cacheDir);
		}
    }
    
    protected $config;
    /**
	 * Gets the application's configuration.
	 *
	 * This function lazy-loads the '/_content/config.yml' file unless the configuration was
	 * specifically set by setConfig().
	 */
    public function getConfig()
    {
        if ($this->config === null)
        {
            $configPath = $this->rootDir . PIECRUST_CONFIG_PATH;
            
            // Always cache a JSON version of the configuration for faster
            // boot-up time (this saves a couple milliseconds).
            $cache = new Cache($this->getCacheDir());
            if ($cache->isValid('config', 'json', filemtime($configPath)))
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
                $cache->write('config', 'json', $yamlMarkup);
            }
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
        $this->config = $this->validateConfig($config);
    }
    
    protected function validateConfig($config)
    {
        // Add the default values.
        if (!isset($config['site']))
		{
            $config['site'] = array();
		}
            
        $config['site'] = array_merge(array(
                        'title' => 'PieCrust Untitled Website',
						'root' => ($this->host . $this->urlBase),
                        'default_format' => PIECRUST_DEFAULT_FORMAT,
                        'enable_cache' => false,
						'enable_gzip' => false,
						'pretty_urls' => false,
						'posts_urls' => '%year%/%month%/%day%/%slug%',
                        'posts_per_page' => 5,
                        'posts_date_format' => 'F j, Y',
						'posts_fs' => 'flat',
                        'tags_urls' => 'tag/%tag%',
                        'categories_urls' => '%category%',
                        'debug' => false
                    ),
                    $config['site']);
        return $config;
    }
	
	/**
	 * Helper function for getting a configuration setting, or null if it doesn't exist.
	 */
	public function getConfigValue($category, $key)
	{
		$config = $this->getConfig();
		if (!isset($config[$category]))
		{
			return null;
		}
		if (!isset($config[$category][$key]))
		{
			return null;
		}
		return $config[$category][$key];
	}
    
    /**
     * Sets a configuration setting.
     */
    public function setConfigValue($category, $key, $value)
    {
        $this->getConfig();
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
                                            create_function('$p1, $p2', 'return $p1->getPriority() < $p2->getPriority();'));
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
            $formatter->initialize($this);
            if ($formatter->supportsFormat($format, $unformatted))
            {
                $formattedText = $formatter->format($formattedText);
                $unformatted = false;
            }
        }
        return $formattedText;
    }
	
	protected $templateEngine;
    /**
	 * Gets the template engine.
	 */
    public function getTemplateEngine()
    {
		if ($this->templateEngine === null)
		{		
			$templateEngineName = $this->getConfigValue('site', 'template_engine');
			if ($templateEngineName == null)
			{
				$templateEngineName = PIECRUST_DEFAULT_TEMPLATE_ENGINE;
			}
				
			$templateEngineClass = $templateEngineName . 'TemplateEngine';
			require_once(PIECRUST_APP_DIR . 'template-engines' . DIRECTORY_SEPARATOR . $templateEngineClass . '.class.php');
			
			$reflector = new ReflectionClass($templateEngineClass);
			$this->templateEngine = $reflector->newInstance();
			$this->templateEngine->initialize($this);
		}
        return $this->templateEngine;
    }
	
	/**
	 * Gets the application's data for page rendering.
	 */
	public function getSiteData()
	{
		$data = $this->getConfig();
		$data = array_merge(
			$data, 
			array(
				'piecrust' => array(
					'version' => self::VERSION,
					'branding' => 'Baked with <em><a href="http://piecrustphp.com">PieCrust</a> ' . self::VERSION . '</em>.'
				)
			)
		);
		return $data;
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
				'root' => PIECRUST_ROOT_DIR,
                'host' => null,
				'url_base' => null
			),
			$parameters
		);
		
		$this->rootDir = rtrim($parameters['root'], '/\\') . DIRECTORY_SEPARATOR;
		
        if ($parameters['host'] === null)
        {
            $this->host = ((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        }
        else
        {
            $this->host = rtrim($parameters['host'], '/');
        }
        
        if ($parameters['url_base'] === null)
        {
			$this->urlBase = dirname($_SERVER['PHP_SELF']) . '/';
        }
        else
        {
            $this->urlBase = '/' . trim($parameters['url_base'], '/') . '/';
        }
    }
    
	/**
	 * Runs PieCrust on the given URI.
	 */
    public function run($uri = null)
    {
		try
		{
			$this->runUnsafe($uri);
		}
		catch (Exception $e)
		{
			// Something wrong happened... check that we're not running
			// some completely brand new and un-configured website.
			if ($this->isEmptySetup())
			{
				$this->showSystemMessage('welcome');
				exit();
			}
			
			// If debugging is enabled, just display the error and exit.
			if ($this->getConfigValue('site', 'debug') === true)
			{
				include 'FatalError.inc.php';
				piecrust_fatal_error(array($e));
				exit();
			}
			
			// Get the URI to the custom error page.
			$errorPageUri = '_error';
			if ($e->getMessage() == '404')
			{
                header('HTTP/1.0 404 Not Found');
				$errorPageUri = '_404';
			}
			$errorPageUriInfo = Page::parseUri($this, $errorPageUri);
			if (is_file($errorPageUriInfo['path']))
			{
				// We have a custom error page. Show it, or display
				// the "fatal error" page if even this doesn't work.
				try
				{
					$this->runUnsafe($errorPageUri, array(
							'error' => array(
								'code' => $e->getCode(),
								'message' => $e->getMessage(),
								'file' => $e->getFile(),
								'line' => $e->getLine(),
								'trace' => $e->getTraceAsString(),
								'debug' => (ini_get('display_errors') == true)
							)
						));
				}
				catch (Exception $inner)
				{
					include 'FatalError.inc.php';
					piecrust_fatal_error(array($e, $inner));
					exit();
				}
			}
			else
			{
				// We don't have a custom error page. Just show a generic
				// error page and exit.
				$this->showSystemMessage(substr($errorPageUri, 1));
				exit();
			}
		}
    }
	
	/**
	 * Runs PieCrust on the given URI with the given extra page rendering data,
	 * but without any error handling.
	 */
	public function runUnsafe($uri = null, $extraPageData = null)
	{
		// Get the resource URI and corresponding physical path.
		if ($uri == null)
		{
			$uri = $this->getRequestUri($_SERVER);
		}
		
		if ($this->getConfigValue('site', 'baked') === true)
		{
			// We're serving a baked website.
			$bakedPath = $this->getCacheDir() . 'baked' . $uri;
			if (is_dir($bakedPath))
			{
				PageRenderer::setHeaders('html');
				$output = file_get_contents($bakedPath . DIRECTORY_SEPARATOR . 'index.html');
			}
			else if (is_file($bakedPath))
			{
				PageRenderer::setHeaders(pathinfo($bakedPath, PATHINFO_EXTENSION));
				$output = file_get_contents($bakedPath);
			}
			else
			{
				throw new PieCrustException('404');
			}
		}
		else
		{
			// We're baking this website on demand.
			$page = new Page($this, $uri);
			$pageRenderer = new PageRenderer($this);
			$output = $pageRenderer->get($page, $extraPageData);
		}
	
		// Output with or without GZip compression.
		$gzipEnabled = (($this->getConfigValue('site', 'enable_gzip') === true) and
						(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false));
		if ($gzipEnabled)
		{
			$zippedOutput = gzencode($output);
			if ($zippedOutput === false)
			{
				echo $output;
			}
			else
			{
				header('Content-Encoding: gzip');
				echo $zippedOutput;
			}
		}
		else
		{
			echo $output;
		}
	}
    
	/**
	 * Gets the requested PieCrust URI based on given server variables.
	 */
    public function getRequestUri($server)
    {
		$requestUri = null;
        if ($this->getConfigValue('site', 'pretty_urls') !== true)
        {
            // Using standard query (no pretty URLs / URL rewriting)
            $requestUri = $server['QUERY_STRING'];
			if ($requestUri == null or $requestUri == '')
			{
				$requestUri = '/';
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
				if (strlen($this->urlBase) > 1)
				{
                    if (strlen($requestUri) < strlen($this->urlBase))
                    {
                        throw new PieCrustException("You're trying to access a resource that's not within the directory served by PieCrust.");
                    }
					$requestUri = substr($requestUri, strlen($this->urlBase) - 1);
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
		if ($requestUri == '/')
		{
			$requestUri = '/' . PIECRUST_INDEX_PAGE_NAME;
		}
        return $requestUri;
    }
	
	protected function isEmptySetup()
	{
		if (!is_dir($this->rootDir . PIECRUST_CONTENT_DIR))
			return true;
		if (!is_file($this->rootDir . PIECRUST_CONFIG_PATH))
			return true;
		$templatesDir = ($this->templatesDir != null) ? $this->templatesDir : ($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
		if (!is_dir($templatesDir))
			return true;
		$pagesDir = ($this->pagesDir != null) ? $this->pagesDir : ($this->rootDir . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
		if (!is_dir($pagesDir))
			return true;
			
		return false;
	}
	
	protected function showSystemMessage($message)
	{
		echo file_get_contents(PIECRUST_APP_DIR . 'messages/' . $message . '.html');
	}
    
    /**
     * Sets up basic things like the global error handler or the timezone.
     */
    public static function setup($profile = 'web')
    {
        date_default_timezone_set('America/Los_Angeles');
		if ($profile == 'web')
		{
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
}

// Get the time this was included so we can display the baking time on the requested page.
$PIECRUST_START_TIME = microtime(true);
