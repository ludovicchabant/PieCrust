<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
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
    
    public function renderFile($templateNames, $data)
    {
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
        echo $content;
    }
    
    public function clearInternalCache()
    {
    }
}
