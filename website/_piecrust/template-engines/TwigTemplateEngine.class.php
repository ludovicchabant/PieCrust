<?php

class TwigTemplateEngine implements ITemplateEngine
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
	
	protected $twigLoader;
	protected $twigEnv;
	
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/Functions.php');
        Twig_Autoloader::register();
		
		$config = $pieCrust->getConfig();
		self::$usePrettyUrls = ($config['site']['pretty_urls'] == true);
		
		$isCacheEnabled = ($config['site']['enable_cache'] == true);
		$useCacheAsTemplates = ($config['site']['use_cache_as_templates'] == true);
		$autoReload = (isset($config['twig']) and $config['twig']['auto_reload'] == true);
		
		$dirs = array($pieCrust->getTemplatesDir());
		if ($this->isCacheEnabled and $this->useCacheAsTemplates)
			array_push($dirs, $pieCrust->getFormattedCacheDir());
        
		$this->twigLoader = new Twig_Loader_Filesystem($dirs);
		$options = array(
							'cache' => ($isCacheEnabled ? $pieCrust->getCompiledTemplatesDir() : false),
							'auto_reload' => $autoReload
						);
        $this->twigEnv = new Twig_Environment($this->twigLoader, $options);
        $this->twigEnv->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
    }
    
    public function renderPage($pageConfig, $pageData)
    {
        $template = $this->twigEnv->loadTemplate($pageConfig['layout'] . '.html');
        return $template->render($pageData);
    }
	
	public function isCacheValid($templateName, $time)
	{
		return $this->twigLoader->isFresh($templateName . '.html', $time);
	}
}
