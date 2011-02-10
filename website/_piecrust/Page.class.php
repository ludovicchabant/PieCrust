<?php



class Page
{
	protected $uri;
	protected $path;
	
	protected $config;
	protected $contents;
	
	protected $cache;
	protected $cacheTime;
	
	public function getUri()
	{
		return $this->uri;
	}
	
	public function getCacheTime()
	{
		return $this->cacheTime;
	}
	
	public function isFresh()
	{
		return ($this->cacheTime == null);
	}
	
	public function getConfig()
	{
		return $this->config;
	}
	
	public function getContents()
	{
		return $this->contents;
	}
	
	public function __construct(PieCrust $pieCrust, $uri)
	{
		$this->uri = $uri;
		$this->path = $this->findPath($pieCrust, $uri);
		
		$this->cache = null;
		if ($pieCrust->getConfigValue('site', 'enable_cache') === true)
		{
			$this->cache = new Cache($pieCrust->getFormattedCacheDir());
		}
		
		$this->initialize($pieCrust);
	}
	
	protected function initialize(PieCrust $pieCrust)
	{
        $this->cacheTime = null;
		if ($this->cache != null)
        {
            $this->cacheTime = $this->cache->getCacheTime($this->uri, 'html');
        }
		
		if ($this->cacheTime != null and ($this->cacheTime > filemtime($this->path)))
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
			$this->contents = $this->formatContents($pieCrust, $rawContents, $extension);
			
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
            throw new PieCrustException('An unknown error occured while loading the contents and configuration for page: ' . $this->uri);
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
    
    protected function formatContents($pieCrust, $contents, $extension)
    {
        $unFormatted = true;
        $formattedContents = $contents;
        foreach ($pieCrust->getFormattersLoader()->getPlugins() as $formatter)
        {
            $formatter->initialize($pieCrust);
            if ($formatter->supportsExtension($extension, $unFormatted))
            {
                $formattedContents = $formatter->format($formattedContents);
                $unFormatted = false;
            }
        }
        return $formattedContents;
    }
}
