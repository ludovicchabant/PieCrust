<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;
use PieCrust\Util\PathHelper;


class MustacheTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
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
    
    public function renderFile($templateName, $data)
    {
        $this->ensureLoaded();
        $templatePath = PathHelper::getTemplatePath($this->pieCrust, $templateName);
        $content = file_get_contents($templatePath);
        $this->renderString($content, $data);
    }
    
    public function clearInternalCache()
    {
    }
    
    protected function ensureLoaded()
    {
        if ($this->mustache === null)
        {
            $this->mustache = new \Mustache_Engine();
        }
    }
}
