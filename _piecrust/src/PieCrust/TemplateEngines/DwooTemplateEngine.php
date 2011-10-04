<?php

namespace PieCrust\TemplateEngines;

use PieCrust\PieCrust;


class DwooTemplateEngine implements ITemplateEngine
{
    protected static $currentApp;
    
    public static function formatUri($uri)
    {
        return self::$currentApp->formatUri($uri);
    }
    
    public static function getPostUrlFormat($blogKey)
    {
        if ($blogKey == null) $blogKey = PieCrust::DEFAULT_BLOG_KEY;
        return self::$currentApp->getConfigValueUnchecked($blogKey, 'post_url');
    }
    
    public static function getTagUrlFormat($blogKey)
    {
        if ($blogKey == null) $blogKey = PieCrust::DEFAULT_BLOG_KEY;
        return self::$currentApp->getConfigValueUnchecked($blogKey, 'tag_url');
    }
    
    public static function getCategoryUrlFormat($blogKey)
    {
        if ($blogKey == null) $blogKey = PieCrust::DEFAULT_BLOG_KEY;
        return self::$currentApp->getConfigValueUnchecked($blogKey, 'category_url');
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
        $tpl = new \Dwoo_Template_String($content);
        $this->dwoo->output($tpl, $data);
    }
    
    public function renderFile($templateName, $data)
    {
        $this->ensureLoaded();
        $templatePath = PieCrust::getTemplatePath($this->pieCrust, $templateName);
        $tpl = new \Dwoo_Template_File($templatePath);
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
            
            $dir = $this->pieCrust->getCacheDir();
            if (!$dir) $dir = rtrim(sys_get_temp_dir(), '/\\') . '/';
            $compileDir = $dir . 'templates_c';
            if (!is_dir($compileDir)) mkdir($compileDir, 0777, true);
            $cacheDir = $dir . 'templates';
            if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
            
            require_once 'Dwoo/dwooAutoload.php';
            $this->dwoo = new \Dwoo($compileDir, $cacheDir);
            $this->dwoo->getLoader()->addDirectory(PieCrust::APP_DIR . '/Plugins/Dwoo/');
        }
    }
}
