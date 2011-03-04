<?php

class TwigTemplateEngine implements ITemplateEngine
{
	protected $pieCrust;
	protected $twigEnv;
	protected $twigLoader;
	
    public function initialize(PieCrust $pieCrust)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        Twig_Autoloader::register();
		require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/ExtendedFilesystem.php');
		require_once(PIECRUST_APP_DIR . 'libs-plugins/twig/PieCrustExtension.php');
		
		$this->pieCrust = $pieCrust;
		
		$dirs = array(rtrim($this->pieCrust->getTemplatesDir(), DIRECTORY_SEPARATOR));
		$this->twigLoader = new Twig_Loader_ExtendedFilesystem($dirs);
        
		$options = array('cache' => false);
		if ($pieCrust->isCachingEnabled())
		{
			$options['cache'] = $pieCrust->getCacheDir() . 'templates_c';
		}
		if ($pieCrust->isDebuggingEnabled())
		{
			$options['debug'] = true;
		}
        $this->twigEnv = new Twig_Environment($this->twigLoader, $options);
        $this->twigEnv->addExtension(new PieCrustExtension($this->pieCrust));
    }
	
	public function addTemplatesPaths($paths)
	{
		$twigPaths = $this->twigLoader->getPaths();
		if (is_array($paths))
			$twigPaths = array_combine($twigPaths, $paths);
		else
			$twigPaths[] = $paths;
		$this->twigLoader->setPaths($twigPaths);
	}
	
	public function renderString($content, $data)
	{
		$this->twigLoader->setTemplateSource('__string_tpl__', $content);
		$tpl = $this->twigEnv->loadTemplate('__string_tpl__');
		$tpl->display($data);
	}
	
    public function renderFile($templateName, $data)
	{
		$tpl = $this->twigEnv->loadTemplate($templateName);
		$tpl->display($data);
	}
}
