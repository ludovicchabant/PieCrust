<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;
use PieCrust\Util\PathHelper;


class PassThroughTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getExtension()
    {
        return 'none';
    }
    
    public function renderString($content, $data)
    {
        echo $content;
    }
    
    public function renderFile($templateName, $data)
    {
        $templatePath = PathHelper::getTemplatePath($this->pieCrust, $templateName);
        $content = file_get_contents($templatePath);
        echo $content;
    }
    
    public function clearInternalCache()
    {
    }
}
