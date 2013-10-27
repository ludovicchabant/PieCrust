<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
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
    
    public function renderFile($templateNames, $data)
    {
        $this->ensureLoaded();

        $templatePath = null;
        foreach ($templateNames as $templateName)
        {
            $templatePath = PathHelper::getTemplatePath($this->pieCrust, $templateName);
            if ($templatePath)
                break;
        }
        if (!$templatePath)
        {
            throw new PieCrustException(
                sprintf(
                    "Couldn't find template(s) '%s' in: %s",
                    implode(', ', $templateNames),
                    implode(', ', $this->pieCrust->getTemplatesDirs())
                )
            );
        }

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
