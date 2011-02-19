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
	protected $isPost;
	
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
	
	/**
	 * Gets the page's data for rendering.
	 */
	public function getPageData()
	{
		$config = $this->getConfig();
		$assetor = new Assetor($this->pieCrust, $this);
		$paginator = new Paginator($this->pieCrust, $this);
        $data = array(
			'page' => array(
				'title' => $config['title'],
				'url' => $this->getUri()
			),
			'asset'=> $assetor,
			'pagination' => $paginator
        );
		return $data;
    }
	
	/**
	 * Creates a new Page instance.
	 */
	public function __construct(PieCrust $pieCrust, $uri)
	{
		$this->pieCrust = $pieCrust;
		$this->parseUri($uri);
		
		$this->cache = null;
		if ($pieCrust->getConfigValue('site', 'enable_cache') === true)
		{
			$this->cache = new Cache($pieCrust->getCacheDir() . 'pages_r');
		}
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
			
			if ($this->cache != null)
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
    
    protected function parseUri($uri)
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
		$this->uri = $uri;
		$this->pageNumber = $pageNumber;

		$matches = array();
		if (preg_match('/^((\d+)\/(\d+)\/(\d+))\/(.*)$/', $uri, $matches))
		{
			// Requesting a post.
			$baseDir = $this->pieCrust->getPostsDir();
			$this->path = $baseDir . $matches[2] . '-' . $matches[3] . '-' . $matches[4] . '_' . $matches[5] . '.html';
			$this->isPost = true;
		}
		else
		{
			// Requesting a page.
			$baseDir = $this->pieCrust->getPagesDir();
			$this->path = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $uri) . '.html';
			$this->isPost = false;
		}
		
		if (!is_file($this->path))
		{
			throw new PieCrustException('404');
		}
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
		$validatedConfig = array_merge(array(
				'layout' => ($this->isPost == true) ? PIECRUST_DEFAULT_POST_TEMPLATE_NAME : PIECRUST_DEFAULT_PAGE_TEMPLATE_NAME,
				'format' => $this->pieCrust->getConfigValue('site', 'default_format'),
				'content_type' => 'html',
				'title' => 'Untitled Page'
			), $config);
		return $validatedConfig;
    }
}
