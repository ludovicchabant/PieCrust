<?php

namespace PieCrust\Formatters;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


class HamlFormatter implements IFormatter
{
    protected $pieCrust;
    protected $haml;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }

    public function isExclusive()
    {
        return true;
    }
    
    public function supportsFormat($format)
    {
        return $format == 'haml';
    }
    
    public function format($text)
    {
        $this->ensureLoaded();
        
        $temp = $this->pieCrust->getCacheDir() . '__format__.haml';
        $out = $this->pieCrust->getCacheDir() . '__format__.php';
        
        if (@file_put_contents($temp, $text) === false)
            throw new PieCrustException("Can't write input Haml template to: " . $temp);
        
        $phpMarkup = $this->haml->parse($temp);
        if (@file_put_contents($out, $phpMarkup) === false)
            throw new PieCrustException("Can't write output Haml template to: " . $out);
        
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
                                       array('filterDir' => PieCrustDefaults::APP_DIR . '/Plugins/Haml'),
                                       $hamlOptions
                                       );
            require_once 'PhamlP/haml/HamlParser.php';
            $this->haml = new \HamlParser($hamlOptions);
        }
    }
}
