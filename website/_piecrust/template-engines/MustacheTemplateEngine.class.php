<?php

class MustacheTemplateEngine implements ITemplateEngine
{
	protected $mustache;
	protected $templatesDir;
	
	public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/mustache/Mustache.php');
		
        $this->mustache = new Mustache();
		$this->templatesDir = $pieCrust->getTemplatesDir();
    }
	
	public function renderString($content, $data)
	{
		return $this->mustache->render($content, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$content = file_get_contents($this->templatesDir . $templateName);
		return $this->renderString($content, $data);
	}
}
