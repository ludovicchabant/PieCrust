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
    
    public function initialize($config)
    {
        require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
        self::$usePrettyUrls = (isset($config['site']['pretty_urls']) ? ($config['site']['pretty_urls'] == true) : false);
    }
    
    public function renderPage($pieCrustApp, $pageConfig, $pageData)
    {           
        $dwoo = new Dwoo($pieCrustApp->getCompiledTemplatesDir(), $pieCrustApp->getTemplatesCacheDir());
        $dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
        $tpl = new Dwoo_Template_File($pieCrustApp->getTemplatesDir() . $pageConfig['layout'] . '.tpl');
        
        $data = new Dwoo_Data();
        $data->setData($pageData);
        
        return $dwoo->get($tpl, $data);
    }
}
