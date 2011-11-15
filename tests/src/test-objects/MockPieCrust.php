<?php

use PieCrust\IPieCrust;
use PieCrust\PieCrustConfiguration;


class MockPieCrust implements IPieCrust
{
    public $rootDir;
    
    public function getRootDir()
    {
        return $this->rootDir;
    }
    
    public $isCachingEnabled;
    
    public function isCachingEnabled()
    {
        return $this->isCachingEnabled;
    }
    
    public $isDebuggingEnabled;
    
    public function isDebuggingEnabled()
    {
        return $this->isDebuggingEnabled;
    }
    
    public $templateDirs;
    
    public function getTemplatesDirs()
    {
        return $this->templateDirs;
    }
    
    public function setTemplatesDirs($dir)
    {
        $this->templateDirs = $dir;
    }
    
    public function addTemplatesDir($dir)
    {
        $templateDirs[] = $dir;
    }
    
    public $pagesDir;
    
    public function getPagesDir()
    {
        return $this->pagesDir;
    }
    
    public function setPagesDir($dir)
    {
        $this->pagesDir = $dir;
    }
    
    public $postsDir;
    
    public function getPostsDir()
    {
        return $this->postsDir;
    }
    
    public function setPostsDir($dir)
    {
        $this->postsDir = $dir;
    }
    
    public $cacheDir;
    
    public function getCacheDir()
    {
        return $this->cacheDir;
    }
    
    public function setCacheDir($dir)
    {
        $this->cacheDir = $dir;
    }
    
    public $config;
    
    public function getConfig()
    {
        return $this->config;
    }
    
    public function formatText($text, $format = null)
    {
        return $text;
    }
    
    public function formatUri($uri)
    {
        return $uri;
    }
    
    public $templateEngines;
    
    public function getTemplateEngine($extension = 'html')
    {
        if (!$this->templateEngines or !isset($this->templateEngines[$extension]))
            return null;
        return $this->templateEngines[$extension];
    }
    
    public $lastRunInfo;
    
    public function getLastRunInfo()
    {
        return $this->lastRunInfo;
    }
    
    public function run($uri = null, $server = null)
    {
        return null;
    }
    
    public function runUnsafe($uri = null, $server = null, $extraPageData = null, array &$headers = null)
    {
        return null;
    }
    
    public function __construct()
    {
        $this->config = new PieCrustConfiguration();
        $engine = new PieCrust\TemplateEngines\PassThroughTemplateEngine();
        $engine->initialize($this);
        $this->templateEngines = array('none' => $engine);
    }
}
