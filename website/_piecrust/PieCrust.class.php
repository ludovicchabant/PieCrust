<?php

/**
 *  The main PieCrust app.
 *
 */
 
/**
 * The application directory, where this file lives. There should be very little
 * reason to change this.
 */
if (!defined(PIECRUST_APP_DIR))
{
    define('PIECRUST_APP_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}

/**
 * The website directory, where the _cache and _content directories live.
 * You can change this if the PieCrust application directory is in a different
 * location than the website (e.g. you have several websites using the same
 * PieCrust application).
 */
if (!defined(PIECRUST_ROOT_DIR))
{
    define('PIECRUST_ROOT_DIR', dirname(PIECRUST_APP_DIR) . DIRECTORY_SEPARATOR);
}

/**
 * Some default values for various PieCrust things.
 */
define('PIECRUST_INDEX_PAGE_NAME', '_index');
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
            $this->setTemplatesDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
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
            $this->setPagesDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
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
            $this->setPostsDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_POSTS_DIR));
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
            $this->setCacheDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CACHE_DIR));
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
            try
            {
				$yamlParser = new sfYamlParser();
				$config = $yamlParser->parse(file_get_contents(PIECRUST_ROOT_DIR . PIECRUST_CONFIG_PATH));
				$this->config = $this->validateConfig($config);			
            }
            catch (Exception $e)
            {
                throw new PieCrustException('An error was found in the PieCrust configuration file: ' . $e->getMessage());
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
                        'default_format' => PIECRUST_DEFAULT_FORMAT,
                        'enable_cache' => false,
						'enable_gzip' => false,
						'pretty_urls' => false,
						'posts_prefix' => '',
                        'posts_per_page' => 5,
                        'posts_date_format' => 'F j, Y',
						'posts_fs' => 'flat',
                        'debug' => false
                    ),
                    $config['site']);
        $config['url_base'] = $this->urlBase;
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
		$config = $this->getConfig();
		$data = array(
			'site' => array(
				'root' => $this->urlBase,
				'title' => $config['site']['title']
			),
			'piecrust' => array(
				'version' => self::VERSION,
                'branding' => 'Baked with <em><a href="http://piecrustphp.com">PieCrust</a> ' . self::VERSION . '</em>.'
			)
		);
		return $data;
	}
    
	/**
	 * Creates a new PieCrust instance with the given base URL.
	 */
    public function __construct($urlBase = null)
    {
        if ($urlBase === null)
        {
            $this->urlBase = '/';
        }
        else
        {
            $this->urlBase = rtrim($urlBase, '/') . '/';
        }
        
        date_default_timezone_set('America/Los_Angeles');
		set_error_handler('piecrust_error_handler');
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
			if ($this->isEmptySetup())
			{
				$this->showWelcomePage();
				exit();
			}
			
			if ($this->getConfigValue('site', 'debug') === true)
			{
				include 'FatalError.inc.php';
				piecrust_fatal_error(array($e));
				exit();
			}
			
			$errorPageUri = '_error';
			if ($e->getMessage() == '404')
			{
                header('HTTP/1.0 404 Not Found');
				$errorPageUri = '_404';
			}
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
	
		$gzipEnabled = (($this->getConfigValue('site', 'enable_gzip') === true) and
						(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false));
		
		// Get the requested page and render it.
		$page = new Page($this, $uri);
		$pageRenderer = new PageRenderer($this);
		
		if ($gzipEnabled)
		{
			ob_start();
		}
		$pageRenderer->render($page, $extraPageData);
		if ($gzipEnabled)
		{
			$output = ob_get_clean();
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
            die ("PieCrust can't figure out the request URI. It may be because you're running a non supported web server (PieCrust currently supports IIS 7.0+ and Apache).");
        }
		if ($requestUri == '/')
		{
			$requestUri = '/' . PIECRUST_INDEX_PAGE_NAME;
		}
        return $requestUri;
    }
	
	protected function isEmptySetup()
	{
		if (!is_dir(PIECRUST_ROOT_DIR . PIECRUST_CONTENT_DIR))
			return true;
		if (!is_file(PIECRUST_ROOT_DIR . PIECRUST_CONFIG_PATH))
			return true;
		$templatesDir = ($this->templatesDir != null) ? $this->templatesDir : (PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
		if (!is_dir($templatesDir))
			return true;
		$pagesDir = ($this->pagesDir != null) ? $this->pagesDir : (PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
		if (!is_dir($pagesDir))
			return true;
			
		return false;
	}
	
	protected function showWelcomePage()
	{
		echo file_get_contents(PIECRUST_APP_DIR . 'messages/welcome.html');
	}
}
