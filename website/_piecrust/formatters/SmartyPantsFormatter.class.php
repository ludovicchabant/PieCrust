<?php

class SmartyPantsFormatter implements IFormatter
{
    protected $enabled;
    protected $smartypantsLibDir;
    
    public function initialize($config)
    {
        $this->smartypantsLibDir = 'smartypants';
        $smartypantsConfig = $config['smartypants'];
        if ($smartypantsConfig != null)
        {
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
    
    public function supportsExtension($extension, $isUnformatted)
    {
        return $this->enabled;
    }
    
    public function format($text)
    {
        require_once(dirname(__FILE__) . '/../libs/' . $this->smartypantsLibDir . '/smartypants.php');
        return SmartyPants($text);
    }
}

