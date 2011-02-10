<?php

class Page
{
	protected $pieCrust;
	protected $cache;
	
	protected $path;
	protected $assetsDir;
	
	protected $uri;
	
	public function getUri()
	{
		return $this->uri;
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
        return array(
			'page' => array(
				'title' => $config['title']
			),
			'asset'=> $this->getAssetData()
        );
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
	
	public function __construct(PieCrust $pieCrust, $uri)
	{
		$this->pieCrust = $pieCrust;
		$this->uri = ltrim($uri, '/');
		$this->path = $this->findPath($pieCrust, $uri);
		$pathParts = pathinfo($this->path);
		$this->assetsDir = $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
		
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
			$configText = $this->cache->read($this->uri, 'yml');
			$yamlParser = new sfYamlParser();
			$config = $yamlParser->parse($configText);
			$this->config = $this->buildValidatedConfig($config);
        }
        else
        {
			// Re-format/process the page.
			$this->cacheTime = null;		// Unset cacheTime to say we're fresh.
			
			$rawContents = file_get_contents($this->path);
			$this->config = $this->parseConfig($rawContents);
		
			$extension = pathinfo($this->path, PATHINFO_EXTENSION);
			$formattedContents = $this->pieCrust->formatText($rawContents, $extension);
			
			$data = $this->getPageData();
			$data = array_merge($data, $this->pieCrust->getSiteData());
			$templateEngine = $this->pieCrust->getTemplateEngine();
			$this->contents = $templateEngine->renderString($formattedContents, $data);
			
			if ($this->cache != null)
			{
				$this->cache->write($this->uri, 'html', $this->contents);
				$yamlDumper = new sfYamlDumper();
				$yamlMarkup = $yamlDumper->dump($this->config, 1);
				$this->cache->write($this->uri, 'yml', $yamlMarkup);
			}
		}
        if (!isset($this->config) or $this->config == null or 
			!isset($this->contents) or $this->contents == null)
		{
            throw new PieCrustException('An unknown error occured while loading the contents and configuration for page: ' . $this->uri);
		}
	}
    
    protected function findPath(PieCrust $pieCrust, $uri)
    {
        $uri = ltrim($uri, '/');
        $pathPattern = $pieCrust->getPagesDir() . str_replace('/', DIRECTORY_SEPARATOR, $uri) . '.*';
        $paths = glob($pathPattern, GLOB_NOSORT|GLOB_ERR);
        if ($paths === false)
            throw new PieCrustException('404');
        $pathCount = count($paths);
        if ($pathCount == 0)
            throw new PieCrustException('404');
        if ($pathCount > 1)
            throw new PieCrustException('More than one article was found for the requested URL (' . $uri . '). Tell the writers to make up their minds.');
        
        return $paths[0];
    }
    
    protected function parseConfig(&$rawContents)
    {
        $yamlHeaderMatches = array();
        $hasYamlHeader = preg_match('/^(---\s*\n.*?\n?)^(---\s*$\n?)/m', $rawContents, $yamlHeaderMatches);
        if ($hasYamlHeader == true)
        {
			// Remove the YAML header from the raw contents string.
            $yamlHeader = substr($rawContents, 0, strlen($yamlHeaderMatches[1]));
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
				'title' => 'Untitled Page'
			), $config);
		return $validatedConfig;
    }
}
