<?php

require_once('Paginator.class.php');

class Page
{
	protected $pieCrust;
	protected $cache;
	protected $assetsDir;
	protected $isPost;
	
	protected $path;
	
	public function getPath()
	{
		return $this->path;
	}
	
	protected $uri;
	
	public function getUri()
	{
		return $this->uri;
	}
	
	protected $pageNumber;
	
	public function getPageNumber()
	{
		return $this->pageNumber;
	}
	
	protected $isCached;
		
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
	
	public function getConfig()
	{
		if ($this->config === null)
		{
			$this->loadConfigAndContents();
		}
		return $this->config;
	}
	
	protected $contents;
	
	public function getContents()
	{
		if ($this->contents === null)
		{
			$this->loadConfigAndContents();
		}
		return $this->contents;
	}
	
	public function getPageData()
	{
		$config = $this->getConfig();
		$paginator = new Paginator($this->pieCrust, $this->getUri(), $this->getPageNumber());
        $data = array(
			'page' => array(
				'title' => $config['title'],
				'url' => $this->getUri()
			),
			'asset'=> $this->getAssetData(),
			'pagination' => $paginator
        );
		return $data;
    }
	
	protected $assetData;
	
	protected function getAssetData()
	{
		if ($this->assetData === null)
		{
			$this->assetData = array();
			
			if (is_dir($this->assetsDir))
			{
				$pathPattern = $this->assetsDir . DIRECTORY_SEPARATOR . '*';
				$paths = glob($pathPattern, GLOB_NOSORT|GLOB_ERR);
				if ($paths === false)
					throw new PieCrustException('An error occured while reading the requested page\'s assets directory.');
				
				if (count($paths) > 0)
				{
					$assetUrlBase = $this->pieCrust->getUrlBase() . PIECRUST_CONTENT_PAGES_DIR . $this->getUri();
					foreach ($paths as $p)
					{
						$name = basename($p);
						$key = str_replace('.', '_', $name);
						$this->assetData[$key] = $assetUrlBase . '/' . $name;
					}
				}
			}
		}
		return $this->assetData;
	}
	
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
			throw new PieCrustException('404');
		
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
			throw new PieCrustException('404');		
		$pathParts = pathinfo($this->path);
		$this->assetsDir = $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
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
