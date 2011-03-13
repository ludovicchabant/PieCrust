<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $currentApp;
    
    public static function formatUri($uri)
    {
        return self::$currentApp->formatUri($uri);
    }
    
	protected $pieCrust;
    protected $dwoo;
    
    public function initialize(PieCrust $pieCrust)
    {
		$this->pieCrust = $pieCrust;
    }
	
	public function getExtension()
	{
		return 'dwoo';
	}
    
    public function addTemplatesPaths($paths)
    {
        throw new PieCrustException('Not implemented yet.');
    }
	
	public function renderString($content, $data)
	{
		$this->ensureLoaded();
		$tpl = new Dwoo_Template_String($content);
		$this->dwoo->output($tpl, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$this->ensureLoaded();
		$templatesDir = $this->pieCrust->getTemplatesDir();
		$tpl = new Dwoo_Template_File($templatesDir . $templateName);
		$this->dwoo->output($tpl, $data);
	}
	
	protected function ensureLoaded()
	{
		if ($this->dwoo === null)
		{
			self::$currentApp = $this->pieCrust;
			
			$compileDir = $this->pieCrust->getCacheDir() . 'templates_c';
			if (!is_dir($compileDir)) mkdir($compileDir, 0777, true);
			$cacheDir = $this->pieCrust->getCacheDir() . 'templates';
			if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
		
			require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
			$this->dwoo = new Dwoo($compileDir, $cacheDir);
			$this->dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
		}
	}
}
