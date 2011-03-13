<?php

class SmartyPantsFormatter implements IFormatter
{
    protected $enabled;
    protected $smartypantsLibDir;
    
    public function initialize(PieCrust $pieCrust)
    {
        $config = $pieCrust->getConfig();
        $this->smartypantsLibDir = 'smartypants';
        if (isset($config['smartypants']))
        {
            $smartypantsConfig = $config['smartypants'];
            $this->enabled = ($smartypantsConfig['enable'] == true);
            if ($smartypantsConfig['use_smartypants_typographer'] == true)
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
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $format != 'none' and $this->enabled;
    }
    
    public function format($text)
    {
        require_once(dirname(__FILE__) . '/../libs/' . $this->smartypantsLibDir . '/smartypants.php');
        return SmartyPants($text);
    }
}

