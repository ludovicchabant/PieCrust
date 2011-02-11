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
	
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/Functions.php');
        Twig_Autoloader::register();
		
		$usePrettyUrls = ($pieCrust->getConfigValue('site','pretty_urls') === true);		
		
		$this->pieCrust = $pieCrust;
		self::$pathPrefix = ($pieCrust->getUrlBase() . ($usePrettyUrls ? '' : '?/'));
        
		$options = array('cache' => false);
        $this->twigEnv = new Twig_Environment(null, $options);
        $this->twigEnv->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
    }
	
	public function renderString($content, $data)
	{
		$templates = array('tpl' => $content);
		$this->twigEnv->setLoader(new Twig_Loader_Array($templates));
		$tpl = $this->twigEnv->loadTemplate('tpl');
		return $tpl->render($data);
	}
	
    public function renderFile($templateName, $data)
	{
		$dirs = array(rtrim($this->pieCrust->getTemplatesDir(), DIRECTORY_SEPARATOR));
		$this->twigEnv->setLoader(new Twig_Loader_Filesystem($dirs));
		$tpl = $this->twigEnv->loadTemplate($templateName);
		return $tpl->render($data);
	}
}
