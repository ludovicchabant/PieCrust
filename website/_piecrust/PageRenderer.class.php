<?php

class PageRenderer
{
	protected $pieCrust;
	protected $cache;
	
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
		
		$this->cache = null;
		if ($pieCrust->getConfigValue('site', 'enable_cache') === true)
		{
			$this->cache = new Cache($pieCrust->getCacheDir() . 'html');
		}
	}
	
	public function render(Page $page, $extraData = null)
	{
		$pageConfig = $page->getConfig();
		$templateName = $pageConfig['layout'] . '.html';
		
		// Get the template engine and figure out if we need to re-render the page.
		$fetchFromHtmlCache = false;
		if ($this->cache != null and
			$page->isCached())
		{
			$templateFilename = $this->pieCrust->getTemplatesDir() . $templateName;
			$templateTime = filemtime($templateFilename);
			$pageCacheTime = $page->getCacheTime();
			$maxTime = max($pageCacheTime, $templateTime);
			if ($this->cache->isValid($page->getUri(), 'html', $maxTime))
			{
				$fetchFromHtmlCache = true;
			}
		}
		
        if ($fetchFromHtmlCache)
        {
            echo $this->cache->read($page->getUri(), 'html');
        }
        else
        {
			$templateEngine = $this->pieCrust->getTemplateEngine();
            $data = array('content' => $page->getContents());
			$data = array_merge($data, $page->getPageData(), $this->pieCrust->getSiteData());
            if ($extraData != null)
            {
                if (is_array($extraData))
                {
					$data = array_merge($data, $extraData);
                }
                else
                {
                    $data['extra'] = $extraData;
                }
            }
            $output = $templateEngine->renderFile($templateName, $data);
            if ($this->cache != null)
			{
                $this->cache->write($page->getUri(), 'html', $output);
			}
            echo "<!-- PieCrust " . PieCrust::VERSION . " - " . ($page->isCached() ? "baked this morning!" : "baked just now!") . " -->\n";
            echo $output;
        }
	}
}

