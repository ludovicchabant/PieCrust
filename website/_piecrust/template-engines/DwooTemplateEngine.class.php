<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $pathPrefix;
    
    public static function getPathPrefix()
    {
        return self::$pathPrefix;
    }
    
    protected $dwoo;
    protected $templatesDir;
	protected $autoRecompile;
    
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
		
        $usePrettyUrls = ($pieCrust->getConfigValue('site', 'pretty_urls') === true); 		
        $useCacheAsTemplates = ($pieCrust->getConfigValue('site', 'use_cache_as_templates') === true);
        if ($useCacheAsTemplates)
            throw new PieCrustException('The "use_cache_as_templates" setting is not implemented with the Dwoo template engine yet.');
		
		self::$pathPrefix = ($pieCrust->getUrlBase() . ($usePrettyUrls ? '/' : '/?/');
		
		$this->autoRecompile = ($pieCrust->getConfigValue('dwoo', 'auto_recompile') === true);
        
        $this->dwoo = new Dwoo($pieCrust->getCompiledTemplatesDir(), $pieCrust->getTemplatesCacheDir());
        $this->dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
        $this->templatesDir = $pieCrust->getTemplatesDir();
    }
    
    public function renderPage($pageConfig, $pageData)
    {
        $tpl = new Dwoo_Template_File($this->templatesDir . $pageConfig['layout'] . '.tpl');
		if ($this->autoRecompile)
			$tpl->forceCompilation();
        
        $data = new Dwoo_Data();
        $data->setData($pageData);
        
        return $this->dwoo->get($tpl, $data);
    }
    
    public function isCacheValid($templateName)
    {
        return false;
    }
	
	public function getCacheTime($templateName)
	{
		return false;
	}
}
