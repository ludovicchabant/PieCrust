<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $usePrettyUrls;
    
    public static function usePrettyUrls()
    {
        return self::$usePrettyUrls;
    }
    
    public static function getPathPrefix()
    {
        return (self::$usePrettyUrls ? '/' : '/?/');
    }
    
    protected $dwoo;
    protected $templatesDir;
    
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
        $config = $pieCrust->getConfig();
        self::$usePrettyUrls = (isset($config['site']['pretty_urls']) ? ($config['site']['pretty_urls'] == true) : false);
        
        $useCacheAsTemplates = ($config['site']['use_cache_as_templates'] == true);
        if ($useCacheAsTemplates)
            throw new PieCrustException('The "use_cache_as_templates" setting is not implemented with the Dwoo template engine yet.');
        
        $this->dwoo = new Dwoo($pieCrust->getCompiledTemplatesDir(), $pieCrust->getTemplatesCacheDir());
        $this->dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
        $this->templatesDir = $pieCrust->getTemplatesDir();
    }
    
    public function renderPage($pageConfig, $pageData)
    {
        $tpl = new Dwoo_Template_File($this->templatesDir . $pageConfig['layout'] . '.tpl');
        
        $data = new Dwoo_Data();
        $data->setData($pageData);
        
        return $this->dwoo->get($tpl, $data);
    }
    
    public function isCacheValid($templateName, $time)
    {
        return false;
    }
}
