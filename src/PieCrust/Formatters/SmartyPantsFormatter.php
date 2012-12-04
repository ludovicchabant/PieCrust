<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


class SmartyPantsFormatter implements IFormatter
{
    protected $enabled;
    protected $smartypantsLibDir;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->smartypantsLibDir = 'smartypants';
        $smartypantsConfig = $pieCrust->getConfig()->getValue('smartypants');
        if ($smartypantsConfig)
        {
            $this->enabled = (
                $pieCrust->getConfig()->getValue('smartypants/enable') or
                $pieCrust->getConfig()->getValue('smartypants/enabled')
            );
            if ($pieCrust->getConfig()->getValue('smartypants/use_smartypants_typographer'))
            {
                $this->smartypantsLibDir = 'smartypants-typographer';
            }
        }
        else
        {
            $this->enabled = false;
        }
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_LOW;
    }

    public function isExclusive()
    {
        return false;
    }
    
    public function supportsFormat($format)
    {
        return $format != 'none' and $this->enabled;
    }
    
    public function format($text)
    {
        require_once ('smartypants/' . $this->smartypantsLibDir . '/smartypants.php');
        return SmartyPants($text);
    }
}

