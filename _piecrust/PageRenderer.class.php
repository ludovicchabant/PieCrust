<?php

class PageRenderer
{
    protected $pieCrust;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function render(Page $page, $extraData = null, $outputHeaders = true)
    {
        $pageConfig = $page->getConfig();
        
        // Set the HTML header.
        if ($outputHeaders === true)
        {
            PageRenderer::setHeaders($pageConfig['content_type']);
        }
		
		// Get the template name.
		$templateName = $pageConfig['layout'];
		if ($templateName == null or $templateName == '' or $templateName == 'none')
		{
			$templateName = false;
		}
		else
		{
			if (!preg_match('/\.[a-zA-Z0-9]+$/', $templateName))
			{
				$templateName .= '.html';
			}
		}
        
		if ($templateName !== false)
		{
			// Get the template engine and the page data.
			$extension = pathinfo($templateName, PATHINFO_EXTENSION);
			$templateEngine = $this->pieCrust->getTemplateEngine($extension);
			$data = $page->getContentSegments();
			$data = array_merge($this->pieCrust->getSiteData(), $page->getPageData(), $data);
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
			
			// Render the page.
			$templateEngine->renderFile($templateName, $data);
		}
		else
		{
			echo $page->getContentSegment();
		}
        
        if ($this->pieCrust->isDebuggingEnabled())
        {
            // Add a footer with version, caching and timing information.
            $this->renderStatsFooter($page);
        }
    }
    
    public function get(Page $page, $extraData = null, $outputHeaders = true)
    {
        ob_start();
        $this->render($page, $extraData, $outputHeaders);
        return ob_get_clean();
    }
    
    public function renderStatsFooter(Page $page)
    {
        global $PIECRUST_START_TIME;
        $timeSpan = microtime(true) - $PIECRUST_START_TIME;
        echo "<!-- PieCrust " . PieCrust::VERSION . " - " .
             ($page->isCached() ? "baked this morning" : "baked just now") .
             ", in " . $timeSpan * 1000 . " milliseconds. -->";
    }
    
    public static function setHeaders($contentType)
    {
        switch ($contentType)
        {
            case 'html':
            default:
                header("Content-type: text/html; charset=utf-8");
                break;
            case 'xml':
                header("Content-type: text/xml; charset=utf-8");
                break;
            case 'txt':
            case 'text':
                header("Content-type: text/plain; charset=utf-8");
                break;
            case 'css':
                header("Content-type: text/css; charset=utf-8");
                break;
            case 'atom':
                header("Content-type: application/atom+xml; charset=utf-8");
                break;
            case 'rss':
                header("Content-type: application/rss+xml; charset=utf-8");
                break;
            case 'json':
                header("Content-type: application/json; charset=utf-8");
                break;
        }
    }
}

