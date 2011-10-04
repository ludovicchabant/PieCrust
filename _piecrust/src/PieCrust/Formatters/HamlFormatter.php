<?php

namespace PieCrust\Formatters;

use \Exception;
use PieCrust\PieCrust;


class HamlFormatter implements IFormatter
{
    protected $pieCrust;
    protected $haml;
    
    public function initialize(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $isUnformatted && ($format == 'haml');
    }
    
    public function format($text)
    {
        $this->ensureLoaded();
        
        $temp = $this->pieCrust->getCacheDir() . '__format__.haml';
        $out = $this->pieCrust->getCacheDir() . '__format__.php';
        
        @file_put_contents($temp, $text);
        
        $phpMarkup = $this->haml->parse($temp);
        file_put_contents($out, $phpMarkup);
        
        ob_start();
        try
        {
            $_PIECRUST_APP = $this->pieCrust;
            require $out;
            return ob_get_clean();
        }
        catch (Exception $e)
        {
            ob_end_clean();
            throw $e;
        }
    }
    
    protected function ensureLoaded()
    {
        if ($this->haml === null)
        {
            $appConfig = $this->pieCrust->getConfig();
            if (isset($appConfig['haml'])) $hamlOptions = $appConfig['haml'];
            else $hamlOptions = array('ugly' => false, 'style' => 'nested');
            $hamlOptions = array_merge(
                                       array('filterDir' => PieCrust::APP_DIR . '/Plugins/Haml'),
                                       $hamlOptions
                                       );
            require_once 'Phamlp/haml/HamlParser.php';
            $this->haml = new \HamlParser($hamlOptions);
        }
    }
}
