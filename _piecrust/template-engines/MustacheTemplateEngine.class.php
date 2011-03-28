<?php

class MustacheTemplateEngine implements ITemplateEngine
{
	protected $pieCrust;
	protected $mustache;
	
	public function initialize(PieCrust $pieCrust)
    {
		$this->pieCrust = $pieCrust;
    }
	
	public function getExtension()
	{
		return 'mustache';
	}
	
	public function renderString($content, $data)
	{
		$this->ensureLoaded();
		echo $this->mustache->render($content, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$this->ensureLoaded();
		$templatePath = PieCrust::getTemplatePath($this->pieCrust, $templateName);
		$content = file_get_contents($templatePath);
		$this->renderString($content, $data);
	}
	
	public function clearInternalCache()
	{
	}
	
	protected function ensureLoaded()
	{
		if ($this->mustache === null)
		{
			require_once 'libs/mustache/Mustache.php';
			$this->mustache = new Mustache();
		}
	}
}
