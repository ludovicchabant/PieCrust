<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $pathPrefix;
    
    public static function getPathPrefix()
    {
        return self::$pathPrefix;
    }
    
	protected $pieCrust;
    protected $dwoo;
    protected $templatesDir;
    
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
		
        $usePrettyUrls = ($pieCrust->getConfigValue('site', 'pretty_urls') === true); 		

		$this->pieCrust = $pieCrust;
		self::$pathPrefix = ($pieCrust->getUrlBase() . ($usePrettyUrls ? '/' : '/?/'));
		
		$compileDir = $pieCrust->getCacheDir() . 'templates_c';
		if (!is_dir($compileDir))
		{
            mkdir($compileDir, 0777, true);
		}
		$cacheDir = $pieCrust->getCacheDir() . 'templates';
		if (!is_dir($cacheDir))
		{
            mkdir($cacheDir, 0777, true);
		}
		
        $this->dwoo = new Dwoo($compileDir, $cacheDir);
        $this->dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
        $this->templatesDir = $pieCrust->getTemplatesDir();
    }
    
    public function addTemplatesPaths($paths)
    {
        throw new PieCrustException('Not implemented yet.');
    }
	
	public function renderString($content, $data)
	{
		$tpl = new Dwoo_Template_String($content);
		$this->dwoo->output($tpl, $data);
	}
	
	public function renderFile($templateName, $data)
	{
		$tpl = new Dwoo_Template_File($this->templatesDir . $templateName);
		$this->dwoo->output($tpl, $data);
	}
}
