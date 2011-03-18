<?php

class MustacheTemplateEngine implements ITemplateEngine
{
	protected $mustache;
	protected $templatesDir;
	
	public function initialize(PieCrust $pieCrust)
    {
		$this->templatesDir = $pieCrust->getTemplatesDir();
    }
	
	public function getExtension()
	{
		return 'mustache';
	}
	
	public function addTemplatesPaths($paths)
	{
		throw new PieCrustException('Not implemented yet.');
	}
	
	public function renderString($content, $data)
	{
		$this->ensureLoaded();
		echo $this->mustache->render($content, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$this->ensureLoaded();
		$content = file_get_contents($this->templatesDir . $templateName);
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
