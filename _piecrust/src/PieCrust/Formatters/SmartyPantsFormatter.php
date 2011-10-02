<?php

namespace PieCrust\Formatters;

use PieCrust\PieCrust;


class SmartyPantsFormatter implements IFormatter
{
    protected $enabled;
    protected $smartypantsLibDir;
    
    public function initialize(PieCrust $pieCrust)
    {
        $config = $pieCrust->getConfig();
        $this->smartypantsLibDir = 'Smartypants';
        if (isset($config['smartypants']))
        {
            $smartypantsConfig = $config['smartypants'];
            $this->enabled = ($smartypantsConfig['enable'] == true);
            if (isset($smartypantsConfig['use_smartypants_typographer']) and
                $smartypantsConfig['use_smartypants_typographer'] == true)
            {
                $this->smartypantsLibDir = 'SmartypantsTypographer';
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
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $format != 'none' and $this->enabled;
    }
    
    public function format($text)
    {
        require_once ($this->smartypantsLibDir . '/smartypants.php');
        return SmartyPants($text);
    }
}

