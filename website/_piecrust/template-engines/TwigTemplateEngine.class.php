<?php

class TwigTemplateEngine implements ITemplateEngine
{
	protected static $pathPrefix;
    
    public static function getPathPrefix()
    {
        return self::$pathPrefix;
    }
	
	protected $pieCrust;
	protected $twigEnv;
	protected $twigLoader;
	
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/Functions.php');
        Twig_Autoloader::register();
		require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/ExtendedFilesystem.php');
		
		$usePrettyUrls = ($pieCrust->getConfigValue('site','pretty_urls') === true);		
		
		$this->pieCrust = $pieCrust;
		self::$pathPrefix = ($pieCrust->getUrlBase() . ($usePrettyUrls ? '' : '?/'));
		
		$dirs = array(rtrim($this->pieCrust->getTemplatesDir(), DIRECTORY_SEPARATOR));
		$this->twigLoader = new Twig_Loader_ExtendedFilesystem($dirs);
        
		$options = array('cache' => false);
		if ($pieCrust->getConfigValue('site', 'enable_cache') == true)
		{
			$options['cache'] = $pieCrust->getCacheDir() . 'templates_c';
			$options['auto_reload'] = true;
		}
        $this->twigEnv = new Twig_Environment($this->twigLoader, $options);
        $this->twigEnv->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
    }
	
	public function renderString($content, $data)
	{
		$this->twigLoader->setTemplateSource('__string_tpl__', $content);
		$tpl = $this->twigEnv->loadTemplate('__string_tpl__');
		return $tpl->render($data);
	}
	
    public function renderFile($templateName, $data)
	{
		$tpl = $this->twigEnv->loadTemplate($templateName);
		return $tpl->render($data);
	}
}
