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
	
	protected $cacheTemplates;
	
    public function initialize($config)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/Functions.php');
        Twig_Autoloader::register();
		self::$usePrettyUrls = ($config['site']['pretty_urls'] == true);
		$this->cacheTemplates = ($config['site']['cache_templates'] == true);
    }
    
    public function renderPage($pieCrustApp, $pageConfig, $pageData)
    {
        $loader = new Twig_Loader_Filesystem($pieCrustApp->getTemplatesDir());
        $twig = new Twig_Environment($loader,
                                     array(
                                        'cache' => $this->cacheTemplates ? $pieCrustApp->getCompiledTemplatesDir() : false
                                    ));
        $twig->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
        
        $template = $twig->loadTemplate($pageConfig['layout'] . '.html');
        return $template->render($pageData);
    }
}
