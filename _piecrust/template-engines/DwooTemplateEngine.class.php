<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $currentApp;
    
    public static function formatUri($uri)
    {
        return self::$currentApp->formatUri($uri);
    }
	
	public static function getTagUrlFormat()
	{
		return self::$currentApp->getConfigValueUnchecked('site', 'tag_url');
	}
	
	public static function getCategoryUrlFormat()
	{
        return self::$currentApp->getConfigValueUnchecked('site', 'category_url');
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
	
	public function renderString($content, $data)
	{
		$this->ensureLoaded();
		$tpl = new Dwoo_Template_String($content);
		$this->dwoo->output($tpl, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$this->ensureLoaded();
		$templatePath = PieCrust::getTemplatePath($this->pieCrust, $templateName);
		$tpl = new Dwoo_Template_File($templatePath);
		$this->dwoo->output($tpl, $data);
	}
	
	public function clearInternalCache()
	{
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
		
			require_once 'libs/dwoo/dwooAutoload.php';
			$this->dwoo = new Dwoo($compileDir, $cacheDir);
			$this->dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
		}
	}
}
