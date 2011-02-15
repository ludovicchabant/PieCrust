<?php

/**
 *  The main PieCrust app class.
 *
 */

if (!defined(PIECRUST_APP_DIR))
{
    define('PIECRUST_APP_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}
if (!defined(PIECRUST_ROOT_DIR))
{
    define('PIECRUST_ROOT_DIR', dirname(PIECRUST_APP_DIR) . DIRECTORY_SEPARATOR);
}

define('PIECRUST_INDEX_PAGE_NAME', '_index');
define('PIECRUST_CONTENT_DIR', '_content/');
define('PIECRUST_CONFIG_PATH', PIECRUST_CONTENT_DIR . 'config.yml');
define('PIECRUST_CONTENT_TEMPLATES_DIR', PIECRUST_CONTENT_DIR . 'templates/');
define('PIECRUST_CONTENT_PAGES_DIR', PIECRUST_CONTENT_DIR . 'pages/');
define('PIECRUST_CONTENT_POSTS_DIR', PIECRUST_CONTENT_DIR . 'posts/');
define('PIECRUST_CACHE_DIR', '_cache/');

define('PIECRUST_DEFAULT_FORMAT', 'markdown');
define('PIECRUST_DEFAULT_TEMPLATE_NAME', 'default');
define('PIECRUST_DEFAULT_TEMPLATE_ENGINE', 'Twig');

require_once('IFormatter.class.php');
require_once('ITemplateEngine.class.php');
require_once('Page.class.php');
require_once('PageRenderer.class.php');
require_once('Cache.class.php');
require_once('PluginLoader.class.php');
require_once('PieCrustException.class.php');

require_once('libs/sfyaml/lib/sfYamlParser.php');
require_once('libs/sfyaml/lib/sfYamlDumper.php');


class PieCrust
{
    const VERSION = '0.0.2';
    
    protected $urlBase;
	
	public function getUrlBase()
	{
		return $this->urlBase;
	}
    
    protected $templatesDir;
    
    public function getTemplatesDir()
    {
        if ($this->templatesDir === null)
            $this->setTemplatesDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_TEMPLATES_DIR));
        return $this->templatesDir;
    }
    
    public function setTemplatesDir($dir)
    {
		$this->templatesDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        if (is_dir($this->templatesDir) === false)
            throw new PieCrustException('The templates directory doesn\'t exist: ' . $this->templatesDir);
    }
    
    protected $pagesDir;
    
    public function getPagesDir()
    {
        if ($this->pagesDir === null)
            $this->setPagesDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_PAGES_DIR));
        return $this->pagesDir;
    }
    
    public function setPagesDir($dir)
    {
        $this->pagesDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        if (is_dir($this->pagesDir) === false)
            throw new PieCrustException('The pages directory doesn\'t exist: ' . $this->pagesDir);
    }
    
    protected $postsDir;
    
    public function getPostsDir()
    {
        if ($this->postsDir === null)
            $this->setPostsDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CONTENT_POSTS_DIR));
        return $this->postsDir;
    }
    
    public function setPostsDir($dir)
    {
        $this->postsDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        if (is_dir($this->postsDir) === false)
            throw new PieCrustException('The posts directory doesn\'t exist: ' . $this->postsDir);
    }
	
	protected $cacheDir;
	
	public function getCacheDir()
    {
        if ($this->cacheDir === null)
            $this->setCacheDir(PIECRUST_ROOT_DIR . str_replace('/', DIRECTORY_SEPARATOR, PIECRUST_CACHE_DIR));
        return $this->cacheDir;
    }
    
    public function setCacheDir($dir)
    {
		$this->cacheDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
		if (is_writable($this->cacheDir) === false)
			throw new PieCrustException('The cache directory must be writable: ' . $this->cacheDir);
    }
    
    protected $config;
    
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
    
    public function setConfig($config)
    {
        $this->config = $this->validateConfig($config);
    }
    
    protected function validateConfig($config)
    {
        // Add the default values.
        if (!isset($config['site']))
            $config['site'] = array();
            
        $config['site'] = array_merge(array(
                        'title' => 'PieCrust Untitled Website',
                        'default_format' => PIECRUST_DEFAULT_FORMAT,
                        'enable_cache' => false,
						'enable_gzip' => false,
                        'posts_per_page' => 5,
                        'posts_url' => 'Y/m/%s',
                        'posts_date_format' => 'F j, Y',
                        'debug' => 'false'
                    ),
                    $config['site']);
        $config['url_base'] = $this->urlBase;
        return $config;
    }
	
	public function getConfigValue($category, $key)
	{
		$config = $this->getConfig();
		if (!isset($config[$category]))
			return null;
		if (!isset($config[$category][$key]))
			return null;
		return $config[$category][$key];
	}
    
    protected $formattersLoader;
    
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
    
    public function formatText($text, $extension)
    {
        $unformatted = true;
        $formattedText = $text;
        foreach ($this->getFormattersLoader()->getPlugins() as $formatter)
        {
            $formatter->initialize($this);
            if ($formatter->supportsExtension($extension, $unformatted))
            {
                $formattedText = $formatter->format($formattedText);
                $unformatted = false;
            }
        }
        return $formattedText;
    }
	
	protected $templateEngine;
    
    public function getTemplateEngine()
    {
		if ($this->templateEngine === null)
		{		
			$templateEngineName = $this->getConfigValue('site', 'template_engine');
			if ($templateEngineName == null)
				$templateEngineName = PIECRUST_DEFAULT_TEMPLATE_ENGINE;
				
			$templateEngineClass = $templateEngineName . 'TemplateEngine';
			require_once(PIECRUST_APP_DIR . 'template-engines/' . $templateEngineClass . '.class.php');
			
			$reflector = new ReflectionClass($templateEngineClass);
			$this->templateEngine = $reflector->newInstance();
			$this->templateEngine->initialize($this);
		}
        return $this->templateEngine;
    }
	
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
    }
    
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
		
			if ($this->getConfigValue('site', 'debug') == true)
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
	
	protected function runUnsafe($uri = null, $extraPageData = null)
	{
		// Get the resource URI and corresponding physical path.
		if ($uri == null)
		{
			$uri = $this->getRequestUri();
		}
	
		$gzipEnabled = (($this->getConfigValue('site', 'enable_gzip') == true) and
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
    
    protected function getRequestUri()
    {
		$requestUri = null;
        if ($this->getConfigValue('site', 'pretty_urls') != true)
        {
            // Using standard query (no pretty URLs / URL rewriting)
            $requestUri = $_SERVER['QUERY_STRING'];
			if ($requestUri == null or $requestUri == '')
				$requestUri = '/';
        }
		else
		{
			// Get the requested URI via URL rewriting.
			if (isset($_SERVER['IIS_WasUrlRewritten']) &&
				$_SERVER['IIS_WasUrlRewritten'] == '1' &&
				isset($_SERVER['UNENCODED_URL']) &&
				$_SERVER['UNENCODED_URL'] != '')
			{
				// IIS7 rewriting module.
				$requestUri = $_SERVER['UNENCODED_URL'];
				if (strlen($this->urlBase) > 1)
					$requestUri = substr($requestUri, strlen($this->urlBase) - 1);
			}
			elseif (isset($_SERVER['REQUEST_URI']))
			{
				// Apache mod_rewrite.
				$requestUri = $_SERVER['REQUEST_URI'];
				if (strlen($this->urlBase) > 1)
					$requestUri = substr($requestUri, strlen($this->urlBase) - 1);
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
