<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


class MustacheTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
    /**
     * @var \Mustache_Engine
     */
    protected $mustache;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getExtension()
    {
        return 'mustache';
    }
    
    public function renderString($content, $data)
    {
        $this->ensureLoaded();

        echo $this->mustache->render($content, $data);
    }
    
    public function renderFile($templateNames, $data)
    {
        $this->ensureLoaded();

        $templatePath = null;

        foreach ($templateNames as $templateName)
        {
            echo $this->mustache->render($templateName,$data);
        }

    }
    
    public function clearInternalCache()
    {
    }
    
    protected function ensureLoaded()
    {
        if ($this->mustache === null)
        {
            $dirs = $this->pieCrust->getTemplatesDirs();

            $loaders = array();
            foreach($dirs as $dir){
                $loaders[]= new \Mustache_Loader_FilesystemLoader(realpath($dir));
            }

            $loaders[]=new \Mustache_Loader_StringLoader();
            $loader =new \Mustache_Loader_CascadingLoader($loaders);
            $options  = array(
                'loader'          => $loader,
                'partials_loader' => $loader,
                'debug'=>false,
                'cache'=>null
            );
            if ($this->pieCrust->isDebuggingEnabled() or
                $this->pieCrust->getConfig()->getValue('mustache/debug') === true)
            {
                $options['debug'] = true;
            }
            if ($this->pieCrust->isCachingEnabled())
            {
                $options['cache'] = $this->pieCrust->getCacheDir() . 'mustache_c';
            }

            $this->mustache = new \Mustache_Engine($options);

        }
    }
}
