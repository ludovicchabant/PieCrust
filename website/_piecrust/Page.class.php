<?php

class Page
{
	protected $pieCrust;
	protected $cache;
	protected $assetsDir;
	
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
        $data = array(
			'page' => array(
				'title' => $config['title']
			),
			'asset'=> $this->getAssetData()
        );
		if (isset($config['need_posts']) and $config['need_posts'] == true)
		{
			$paginationData = $this->getPaginationData();
			$data['pagination'] = $paginationData;
		}
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
				$assetUrlBase = $this->pieCrust->getUrlBase() . PIECRUST_CONTENT_PAGES_DIR . $this->getUri();
			
				$pathPattern = $this->assetsDir . DIRECTORY_SEPARATOR . '*';
				$paths = glob($pathPattern, GLOB_NOSORT|GLOB_ERR);
				if ($paths === false)
					throw new PieCrustException('An error occured while reading the requested page\'s assets directory.');
				
				if (count($paths) > 0)
				{			
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
	
	protected $paginationData;
	
	protected function getPaginationData()
	{
		if ($this->paginationData === null)
		{
			$postsData = array();
			$nextPageIndex = null;
			$previousPageIndex = ($this->pageNumber > 2) ? $this->pageNumber - 1 : '';
			
			$pathPattern = $this->pieCrust->getPostsDir() . '*.html';
			$paths = glob($pathPattern, GLOB_ERR);
			if ($paths === false)
				throw new PieCrustException('An error occured while reading the posts directory.');

			if (count($paths) > 0)
			{
				rsort($paths);
				$postsUri = $this->pieCrust->getConfigValue('site', 'posts_url');
				$postsPerPage = $this->pieCrust->getConfigValue('site', 'posts_per_page');
				$postsDateFormat = $this->pieCrust->getConfigValue('site', 'posts_date_format');
				
				$offset = ($this->pageNumber - 1) * $postsPerPage;
				for ($i = $offset; $i < $offset + $postsPerPage and $i < count($paths); ++$i)
				{
					$matches = array();
					$filename = pathinfo($paths[$i], PATHINFO_FILENAME);
					if (preg_match('/^(\d+-\d+-\d+)_(.*)$/', $filename, $matches) == false)
						continue;

					$post = new Page($this->pieCrust, '/' . $postsUri . '/' . $filename);
					$postConfig = $post->getConfig();
					$postDateTime = strtotime($matches[1]);
					
					array_push($postsData, array(
						'title' => $postConfig['title'],
						'date' => date($postsDateFormat, $postDateTime),
						'content' => $post->getContents()
					));
				}
				
				if ($offset + $postsPerPage < count($paths))
				{
					$nextPageIndex = $this->pageNumber + 1;
				}
			}
			
			$this->paginationData = array(
										  'posts' => $postsData,
										  'prev_page' => ($this->uri == '_index' && $previousPageIndex == null) ?
															'' : $this->uri . '/' . $previousPageIndex,
										  'this_page' => $this->uri . '/' . $this->pageNumber,
										  'next_page' => $this->uri . '/' . $nextPageIndex
										  );
		}
		return $this->paginationData;
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
        $uri = ltrim($uri, '/');
		$pageNumber = 1;
		$matches = array();
		if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
		{
			$uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
			$pageNumber = intval($matches[1]);
		}
		$this->uri = $uri;
		$this->pageNumber = $pageNumber;
		
		$postsUrl = $this->pieCrust->getConfigValue('site', 'posts_url');
		$baseDir = $this->pieCrust->getPagesDir();
		if (substr($uri, 0, strlen($postsUrl)) == $postsUrl)
		{
			$baseDir = $this->pieCrust->getPostsDir();
			$uri = substr($uri, strlen($postsUrl));
		}
		$this->path = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $uri) . '.html';
		
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
				'layout' => PIECRUST_DEFAULT_TEMPLATE_NAME,
				'format' => $this->pieCrust->getConfigValue('site', 'default_format'),
				'title' => 'Untitled Page'
			), $config);
		return $validatedConfig;
    }
}
