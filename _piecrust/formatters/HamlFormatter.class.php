<?php

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
        require $out;
        return ob_get_clean();
    }
    
    protected function ensureLoaded()
    {
        if ($this->haml === null)
        {
            $appConfig = $this->pieCrust->getConfig();
            if (isset($appConfig['haml'])) $hamlOptions = $appConfig['haml'];
            else $hamlOptions = array();
            $hamlOptions = array_merge(
                                       array('filterDir' => PIECRUST_APP_DIR . 'libs-plugins/haml/filters'),
                                       $hamlOptions
                                       );
            require_once 'libs/phamlp/haml/HamlParser.php';
            $this->haml = new HamlParser($hamlOptions);
        }
    }
}
