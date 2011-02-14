<?php

class PageRenderer
{
	protected $pieCrust;
	
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
	}
	
	public function get(Page $page, $extraData = null)
	{
		$pageConfig = $page->getConfig();
		$templateName = $pageConfig['layout'] . '.html';
		
		// Get the template engine and render the page.
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
		return "<!-- PieCrust " . PieCrust::VERSION . " - " .
				($page->isCached() ? "baked this morning!" : "baked just now!") . " -->\n" .
				$output;
	}
	
	public function render(Page $page, $extraData = null)
	{
		echo get($page, $extraData);
	}
}

