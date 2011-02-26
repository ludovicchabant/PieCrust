<?php

require_once 'Assetor.class.php';
require_once 'Paginator.class.php';

/**
 * A class that represents a page (article or post) in PieCrust.
 *
 */
class Page
{
	protected $pieCrust;
	protected $cache;
	
	protected $path;
	/**
	 * Gets the file-system path to the page's file.
	 */
	public function getPath()
	{
		return $this->path;
	}
	
	protected $uri;
	/**
	 * Gets the PieCrust URI to the page.
	 */
	public function getUri()
	{
		return $this->uri;
	}
	
	protected $pageNumber;
	/**
	 * Gets the page number (for pages that display a large number of posts).
	 */
	public function getPageNumber()
	{
		return $this->pageNumber;
	}
	
	/**
	 * Sets the page number.
	 */
	public function setPageNumber($pageNumber)
	{
		$this->pageNumber = $pageNumber;
		$this->config = null;
		$this->contents = null;
		$this->data = null;
	}
	
	protected $isPost;
	/**
	 * Gets whether this page is a blog post.
	 */
	public function isPost()
	{
		return $this->isPost;
	}
	
	protected $isCached;
	/**
	 * Gets whether this page's contents have been cached.
	 */
	public function isCached()
	{
		if ($this->isCached === null)
		{
			$cacheTime = $this->getCacheTime();
			if ($cacheTime === false)
			{
				$this->isCached = false;
			}
			else
			{
				$this->isCached = ($cacheTime > filemtime($this->path));
			}
		}
		return $this->isCached;
	}
	
	protected $cacheTime;
	/**
	 * Gets the cache time for this page, or false if it was not cached.
	 */
	public function getCacheTime()
	{
		if ($this->cacheTime === null)
		{
			if ($this->cache == null)
			{
				$this->cacheTime = false;
			}
			else
			{
				$this->cacheTime = $this->cache->getCacheTime($this->uri, 'html');
			}
		}
		return $this->cacheTime;
	}
	
	protected $config;
	/**
	 * Gets the page's configuration from its YAML frontmatter.
	 */
	public function getConfig()
	{
		if ($this->config === null)
		{
			$this->loadConfigAndContents();
		}
		return $this->config;
	}
	
	/**
	 * Convenience method for accessing a configuration value.
	 */
	public function getConfigValue($key)
	{
		$config = $this->getConfig();
		if (!isset($config[$key]))
		{
			return null;
		}
		return $config[$key];
	}
	
	protected $contents;
	/**
	 * Gets the page's formatted contents.
	 */
	public function getContents()
	{
		if ($this->contents === null)
		{
			$this->loadConfigAndContents();
		}
		return $this->contents;
	}
	
	protected $data;
	/**
	 * Gets the page's data for rendering.
	 */
	public function getPageData()
	{
		if ($this->data === null)
		{
			$data = array(
				'page' => $this->getConfig(),
				'asset'=> new Assetor($this->pieCrust, $this),
				'pagination' => new Paginator($this->pieCrust, $this)
			);
			$data['page']['url'] = $this->pieCrust->getUrlBase() . $this->getUri();
			$data['page']['slug'] = $this->getUri();
			
			if ($this->extraData != null)
			{
				$data = array_merge($data, $this->extraData);
			}
			
			$this->data = $data;
		}
		return $this->data;
    }
	
	protected $extraData;
	/**
	 * Adds extra data to the page's data for rendering.
	 */
	public function setExtraPageData($data)
	{
		if ($this->data != null or $this->config != null or $this->contents != null)
			throw new PieCrustException("Extra data on a page must be set before the page's configuration, contents and data are loaded.");
		$this->extraData = $data;
	}
	
	/**
	 * Creates a new Page instance.
	 */
	public function __construct(PieCrust $pieCrust, $uri)
	{
		$this->pieCrust = $pieCrust;
		if ($uri != null)
		{
			$uriInfo = Page::parseUri($pieCrust, $uri);
			
			$this->uri = $uriInfo['uri'];
			$this->pageNumber = $uriInfo['page'];
			$this->isPost = ($uriInfo['isPost'] === true);
			$this->path = $uriInfo['path'];
			
			if (!is_file($this->path))
			{
				throw new PieCrustException('404');
			}
		}
		else
		{
			$this->uri = null;
			$this->path = null;
			$this->pageNumber = 1;
			$this->isPost = false;
		}
		
		$this->cache = null;
		if ($pieCrust->getConfigValue('site', 'enable_cache') === true)
		{
			$this->cache = new Cache($pieCrust->getCacheDir() . 'pages_r');
		}
	}
	
	/**
	 * Creates a new Page instance with pre-determined properties.
	 */
	public static function create(PieCrust $pieCrust, $uri, $path, $isPost = false, $pageNumber = 1)
	{
		$page = new Page($pieCrust, null);
		$page->uri = trim($uri, '/');
		$page->path = $path;
		$page->pageNumber = $pageNumber;
		$page->isPost = $isPost;
		return $page;
	}
	
	/**
	 *	Parse a relative URI and returns information about it.
	 */
	public static function parseUri(PieCrust $pieCrust, $uri)
    {
		if (strpos($uri, '..') !== false)	// Some bad boy's trying to access files outside of our standard folders...
		{
			throw new PieCrustException('404');
		}
		
        $uri = trim($uri, '/');
		$pageNumber = 1;
		$matches = array();
		if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
		{
			// Requesting a page other than the first for this article.
			$uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
			$pageNumber = intval($matches[1]);
		}

		$path = null;
		$isPost = false;
		$matches = array();
		$postsPattern = Paginator::buildPostUrlPattern($pieCrust->getConfigValue('site', 'posts_urls'));
		if (preg_match($postsPattern, $uri, $matches))
		{
			// Requesting a post.
			$baseDir = $pieCrust->getPostsDir();
			$postsFs = $pieCrust->getConfigValue('site', 'posts_fs');
			switch ($postsFs)
			{
			case 'hierarchy':
				$path = $baseDir . $matches['year'] . DIRECTORY_SEPARATOR . $matches['month'] . DIRECTORY_SEPARATOR . $matches['day'] . '_' . $matches['slug'] . '.html';
				break;
			case 'flat':
			default:
				$path = $baseDir . $matches['year'] . '-' . $matches['month'] . '-' . $matches['day'] . '_' . $matches['slug'] . '.html';
				break;
			}
			$isPost = true;
		}
		else
		{
			// Requesting a page.
			$baseDir = $pieCrust->getPagesDir();
			$path = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $uri) . '.html';
			$isPost = false;
		}
		
		return array(
			'uri' => $uri,
			'page' => $pageNumber,
			'isPost' => $isPost,
			'path' => $path
		);
    }
	
	protected function loadConfigAndContents()
	{
		if ($this->isCached())
		{
			// Get the page from the cache.
			$this->contents = $this->cache->read($this->uri, 'html');
			$configText = $this->cache->read($this->uri, 'json');
			$config = json_decode($configText, true);
			$this->config = $this->buildValidatedConfig($config);
        }
        else
        {
			// Re-format/process the page.
			$rawContents = file_get_contents($this->path);
			$this->config = $this->parseConfig($rawContents);
			
			$data = $this->getPageData();
			$data = array_merge($data, $this->pieCrust->getSiteData());
			$templateEngine = $this->pieCrust->getTemplateEngine();
			ob_start();
			$templateEngine->renderString($rawContents, $data);
			$rawContents = ob_get_clean();
		
			$this->contents = $this->pieCrust->formatText($rawContents, $this->config['format']);			
			
			// Do not cache the page if 'volatile' data was accessed (e.g. the page displays
			// the latest posts).
			if ($this->cache != null and $this->wasVolatileDataAccessed($data) == false)
			{
				$this->cache->write($this->uri, 'html', $this->contents);
				$yamlMarkup = json_encode($this->config);
				$this->cache->write($this->uri, 'json', $yamlMarkup);
			}
		}
        if (!isset($this->config) or $this->config == null or 
			!isset($this->contents) or $this->contents == null)
		{
            throw new PieCrustException('An unknown error occured while loading the contents and configuration for page: ' . $this->uri);
		}
	}
	
	protected function wasVolatileDataAccessed($data)
	{
		return $data['pagination']->wasPaginationDataAccessed();
	}
    
    protected function parseConfig(&$rawContents)
    {
        $yamlHeaderMatches = array();
        $hasYamlHeader = preg_match('/^(---\s*\n)((.*\n)*?)^(---\s*\n)/m', $rawContents, $yamlHeaderMatches);
        if ($hasYamlHeader == true)
        {
			// Remove the YAML header from the raw contents string.
            $yamlHeader = substr($rawContents, strlen($yamlHeaderMatches[1]), strlen($yamlHeaderMatches[2]));
            $rawContents = substr($rawContents, strlen($yamlHeaderMatches[0]));
			// Parse the YAML header.
			try
			{
				$yamlParser = new sfYamlParser();
				$config = $yamlParser->parse($yamlHeader);
            }
            catch (Exception $e)
            {
                throw new PieCrustException('An error occured while reading the YAML header for the requested article: ' . $e->getMessage());
            }
        }
        else
        {
            $config = array();
        }
        
        return $this->buildValidatedConfig($config);
    }
    
    protected function buildValidatedConfig($config)
    {
		// Add the default page config values.
		$validatedConfig = array_merge(
			array(
				'layout' => ($this->isPost == true) ? PIECRUST_DEFAULT_POST_TEMPLATE_NAME : PIECRUST_DEFAULT_PAGE_TEMPLATE_NAME,
				'format' => $this->pieCrust->getConfigValue('site', 'default_format'),
				'content_type' => 'html',
				'title' => 'Untitled Page'
			),
			$config);
		return $validatedConfig;
    }
}
