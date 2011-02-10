<?php

class PageRenderer
{	
	protected $cache;
	protected $pieCrust;
	
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
		
		$this->cache = null;
		if ($pieCrust->getConfigValue('site', 'enable_cache') === true)
		{
			$this->cache = new Cache($pieCrust->getHtmlCacheDir());
		}
	}
	
	public function render(Page $page, $extraPageData = null)
	{
		$pageConfig = $page->getConfig();
		$templateName = $pageConfig['layout'];
		$pageCacheLocallyDisabled = (isset($pageConfig['enable_cache']) and $pageConfig['enable_cache'] === false);
		
		// Get the template engine and figure out if we need to re-render the page.
		$templateEngine = $this->pieCrust->getTemplateEngine();
		$fetchFromHtmlCache = false;
		if ($this->cache != null and
			$pageCacheLocallyDisabled == false and
			$page->isFresh() == false and
			$templateEngine->isCacheValid($templateName))
		{
			$pageCacheTime = $page->getCacheTime();
			$templateCacheTime = $templateEngine->getCacheTime($templateName);
			$maxTime = max($pageCacheTime, $templateCacheTime);
			if ($this->cache->isValid($page->getUri(), 'html', $maxTime))
			{
				// The page's contents and config were fetched from the cache, the
				// template the page is using has a valid cache too, and we have a
				// final HTML cache that was computed from the combination of the 2,
				// so unless there's some dynamic stuff going on (in which case the 
				// user should disable caching for that page in the YAML header), we
				// can use that HTML cache.
				$fetchFromHtmlCache = true;
			}
		}
		
        if ($fetchFromHtmlCache)
        {
            echo $this->cache->read($page->getUri(), 'html');
        }
        else
        {
            $pageData = array(
                                'content' => $page->getContents(),
                                'page' => $this->getPageData($pageConfig),
								'asset' => $this->getAssetData($page->getAssetsDir()),
                                'site' => $this->getSiteData($this->pieCrust->getConfig()),
                                'piecrust' => $this->getGlobalData()
                             );
            if ($extraPageData != null)
            {
                if (is_array($extraPageData))
                {
					$pageData = array_merge($pageData, $extraPageData);
                }
                else
                {
                    $pageData['extra'] = $extraPageData;
                }
            }
            $output = $templateEngine->renderPage($pageConfig, $pageData);
            if ($this->cache != null)
			{
                $this->cache->write($page->getUri(), 'html', $output);
			}
            echo "<!-- PieCrust " . PieCrust::VERSION . " - " . ($page->isFresh() ? "baked just now!" : "baked this morning!") . " -->\n";
            echo $output;
        }
	}
	
	protected function getPageData($pageConfig)
    {
        return array(
            'title' => $pageConfig['title']
        );
    }
	
	protected function getAssetData($assetsDir)
	{
		$pathPattern = $assetsDir . DIRECTORY_SEPARATOR . '*';
        $paths = glob($pathPattern, GLOB_NOSORT|GLOB_ERR);
		if ($paths === false or count($paths) == 0)
			return array();
		
		$data = array();
		foreach ($paths as $p)
		{
			$keyName = str_replace('.', '_', basename($p));
			$data[$keyName] = $p;
		}
		return $data;
	}
    
    protected function getSiteData($appConfig)
    {
        $siteConfig = $appConfig['site'];
        if (isset($siteConfig))
        {
            return array(
                'title' => $siteConfig['title'],
                'root' => $appConfig['url_base']
            );
        }
        else
        {
            return array();
        }
    }
    
    protected function getGlobalData()
    {
        return array(
            'version' => PieCrust::VERSION
        );
    }
}

