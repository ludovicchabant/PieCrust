<?php

class DwooTemplateEngine implements ITemplateEngine
{
    protected static $usePrettyUrls;
    
    public static function usePrettyUrls()
    {
        return DwooTemplateEngine::$usePrettyUrls;
    }
    
    public static function getPathPrefix()
    {
        return (DwooTemplateEngine::$usePrettyUrls ? '/' : '/?/');
    }
    
    public function initialize($config)
    {
        require_once(PIECRUST_APP_DIR . 'libs/dwoo/dwooAutoload.php');
        DwooTemplateEngine::$usePrettyUrls = ($config['site']['pretty_urls'] == true);
    }
    
    public function renderPage($pieCrustApp, $pageConfig, $pageData)
    {
        $layoutName = 'default';
        if (array_key_exists('layout', $pageConfig))
            $layoutName = $pageConfig['layout'];
            
        $dwoo = new Dwoo($pieCrustApp->getCompiledTemplatesDir(), $pieCrustApp->getTemplatesCacheDir());
        $dwoo->getLoader()->addDirectory(PIECRUST_APP_DIR . 'libs-plugins/dwoo/');
        $tpl = new Dwoo_Template_File($pieCrustApp->getTemplatesDir() . $layoutName . '.tpl');
        
        $data = new Dwoo_Data();
        $data->setData($pageData);
        
        $dwoo->output($tpl, $data);
    }
}
