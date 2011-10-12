<?php

namespace PieCrust\TemplateEngines;

use \Exception;
use PieCrust\PieCrust;


class TwigTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
    protected $twigEnv;
    protected $twigLoader;
    
    public function initialize(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getExtension()
    {
        return 'twig';
    }
    
    public function renderString($content, $data)
    {
        $this->ensureLoaded();
        
        // Temporarily disable caching in Twig to prevent the _cache folder from
        // becoming enormous.
        $cache = $this->twigEnv->getCache();
        $this->twigEnv->setCache(false);
        {
            $this->twigLoader->setTemplateSource('__string_tpl__', $content);
            $tpl = $this->twigEnv->loadTemplate('__string_tpl__');
            try
            {
                $tpl->display($data);
            }
            catch (Exception $e)
            {
                $this->twigEnv->setCache($cache);
                throw $e;
            }
        }
        $this->twigEnv->setCache($cache);
    }
    
    public function renderFile($templateName, $data)
    {
        $this->ensureLoaded();
        
        $tpl = $this->twigEnv->loadTemplate($templateName);
        $tpl->display($data);
    }
    
    public function clearInternalCache()
    {
        if ($this->twigEnv != null)
        {
            $this->twigEnv->clearTemplateCache();
        }
    }
    
    protected function ensureLoaded()
    {
        if ($this->twigEnv === null or $this->twigLoader === null)
        {
            require_once 'Twig/lib/Twig/Autoloader.php';
            \Twig_Autoloader::register();
            require_once 'PieCrust/Plugins/Twig/ExtendedFilesystem.php';
            require_once 'PieCrust/Plugins/Twig/GeshiExtension.php';
            require_once 'PieCrust/Plugins/Twig/PieCrustExtension.php';
            
            $isHosted = ($this->pieCrust->getConfigValue('server', 'is_hosting') === true);
            $isBaking = ($this->pieCrust->getConfigValue('baker', 'is_baking') === true);
            
            $dirs = $this->pieCrust->getTemplatesDirs();
            $this->twigLoader = new \ExtendedFilesystem($dirs, $isHosted); // If we're in a long running process (hosted), the templates
                                                                           // will be defined in memory and when the file changes, Twig
                                                                           // won't see it needs to recompile it if the template class
                                                                           // name is the same, so we add the time-stamp in the cache key.
            
            $options = array('cache' => false);
            if ($this->pieCrust->isCachingEnabled())
            {
                $options['cache'] = $this->pieCrust->getCacheDir() . 'templates_c';
            }
            if ($isHosted or ($this->pieCrust->getConfigValue('twig', 'auto_reload') !== false and !$isBaking))
            {
                $options['auto_reload'] = true;
            }
            $this->twigEnv = new \Twig_Environment($this->twigLoader, $options);
            $this->twigEnv->addExtension(new \PieCrustExtension($this->pieCrust));
            $this->twigEnv->addExtension(new \GeshiExtension());
        }
    }
}
