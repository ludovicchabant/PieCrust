<?php

namespace PieCrust\TemplateEngines;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Plugins\Twig\ExtendedFilesystem;
use PieCrust\Plugins\Twig\GeshiExtension;
use PieCrust\Plugins\Twig\PieCrustExtension;


class TwigTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
    protected $twigEnv;
    protected $twigLoader;
    
    public function initialize(IPieCrust $pieCrust)
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
        
        // Some of our extensions require access to the current PieCrust app.
        $data['PIECRUST_APP'] = $this->pieCrust;
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
                unset($data['PIECRUST_APP']);
                $this->twigEnv->setCache($cache);
                throw $e;
            }
        }
        unset($data['PIECRUST_APP']);
        $this->twigEnv->setCache($cache);
    }
    
    public function renderFile($templateNames, $data)
    {
        $this->ensureLoaded();

        $tpl = null;
        $errors = array();
        foreach ($templateNames as $templateName)
        {
            try
            {
                $tpl = $this->twigEnv->loadTemplate($templateName);
                break;
            }
            catch (\Twig_Error_Loader $e)
            {
                // This template name wasn't found... keep looking, but
                // remember all error messages so we can display them if
                // we find nothing at all.
                $errors[] = $e->getMessage();
            }
        }
        if ($tpl == null)
        {
            throw new PieCrustException(implode(', ', $errors));
        }
        
        // Some of our extensions require access to the current PieCrust app.
        $data['PIECRUST_APP'] = $this->pieCrust;
        $tpl->display($data);
        unset($data['PIECRUST_APP']);
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
            $isHosted = ($this->pieCrust->getConfig()->getValue('server/is_hosting') === true);
            $isBaking = ($this->pieCrust->getConfig()->getValue('baker/is_baking') === true);
            
            $dirs = $this->pieCrust->getTemplatesDirs();
            // If we're in a long running process (hosted), the templates
            // will be defined in memory and when the file changes, Twig
            // won't see it needs to recompile it if the template class
            // name is the same, so we add the time-stamp in the cache key.
            // We tell the file-system to do this by passing `true` as the
            // second constructor parameter.
            $this->twigLoader = new ExtendedFilesystem($dirs, $isHosted); 
            
            $options = array('cache' => false, 'debug' => false);
            if ($this->pieCrust->isCachingEnabled())
            {
                $options['cache'] = $this->pieCrust->getCacheDir() . 'templates_c';
            }
            if ($this->pieCrust->isDebuggingEnabled() or
                $this->pieCrust->getConfig()->getValue('twig/debug') === true)
            {
                $options['debug'] = true;
            }
            if ($isHosted or 
                (
                    $this->pieCrust->getConfig()->getValue('twig/auto_reload') !== false and 
                    !$isBaking
                ))
            {
                $options['auto_reload'] = true;
            }
            if ($this->pieCrust->getConfig()->getValue('twig/auto_escape') === false)
            {
                $options['autoescape'] = false;
            }
            $this->twigEnv = new \Twig_Environment($this->twigLoader, $options);
            $this->twigEnv->addExtension(new PieCrustExtension($this->pieCrust));
            $this->twigEnv->addExtension(new GeshiExtension());
            foreach ($this->pieCrust->getPluginLoader()->getTwigExtensions() as $ext)
            {
                $this->twigEnv->addExtension($ext);
            }
            if ($options['debug'])
            {
                $this->twigEnv->addExtension(new \Twig_Extension_Debug());
            }
        }
    }
}
