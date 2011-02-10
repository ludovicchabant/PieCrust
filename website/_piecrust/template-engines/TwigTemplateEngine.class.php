<?php

class TwigTemplateEngine implements ITemplateEngine
{
	protected static $pathPrefix;
    
    public static function getPathPrefix()
    {
        return self::$pathPrefix;
    }
	
	protected $twigLoader;
	protected $twigEnv;
	
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/Functions.php');
        Twig_Autoloader::register();
		
		$usePrettyUrls = ($pieCrust->getConfigValue('site','pretty_urls') === true);		
		$isCacheEnabled = ($pieCrust->getConfigValue('site', 'enable_cache') === true);
		$useCacheAsTemplates = ($pieCrust->getConfigValue('site', 'use_cache_as_templates') === true);
		$disableAutoReload = ($pieCrust->getConfigValue('twig', 'auto_reload') === false);
		
		self::$pathPrefix = ($pieCrust->getUrlBase() . ($usePrettyUrls ? '/' : '/?/'));
		
		$dirs = array(rtrim($pieCrust->getTemplatesDir(), DIRECTORY_SEPARATOR));
		if ($isCacheEnabled and $useCacheAsTemplates)
			array_push($dirs, rtrim($pieCrust->getFormattedCacheDir(), DIRECTORY_SEPARATOR));
        
		$this->twigLoader = new Twig_Loader_Filesystem($dirs);
		$options = array(
							'cache' => ($isCacheEnabled ? rtrim($pieCrust->getCompiledTemplatesDir(), DIRECTORY_SEPARATOR) : false),
							'auto_reload' => !$disableAutoReload
						);
        $this->twigEnv = new Twig_Environment($this->twigLoader, $options);
        $this->twigEnv->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
    }
    
    public function renderPage($pageConfig, $pageData)
    {
        $template = $this->twigEnv->loadTemplate($pageConfig['layout'] . '.html');
        return $template->render($pageData);
    }
	
	public function isCacheValid($templateName)
	{
		$fullTemplateName = $templateName . '.html';
		$cacheTime = $this->getCacheTime($templateName);
		if ($cacheTime === false)
			return false;
		return ($this->twigEnv->isAutoReload() and $this->twigLoader->isFresh($fullTemplateName, $cacheTime));
	}
	
	public function getCacheTime($templateName)
	{
		$fullTemplateName = $templateName . '.html';
		$cacheFilename = $this->twigEnv->getCacheFilename($fullTemplateName);
		if ($cacheFilename === false)
			return false;
		if (!file_exists($cacheFilename))
			return false;
		return filemtime($cacheFilename);
	}
}
