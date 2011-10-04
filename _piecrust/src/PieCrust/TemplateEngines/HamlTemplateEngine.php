<?php

namespace PieCrust\TemplateEngines;

use PieCrust\PieCrust;


class ArrayWrapper
{
    protected $array;
    
    public function __construct(array $array)
    {
        $this->array = $array;
    }
    
    public function __isset($name)
    {
        return isset($this->array[$name]);
    }
    
    public function __get($name)
    {
        return $this->array[$name];
    }
}

class HamlTemplateEngine implements ITemplateEngine
{
    protected $pieCrust;
    protected $cacheDir;
    protected $haml;
    
    public function initialize(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getExtension()
    {
        return 'haml';
    }
    
    public function renderString($content, $data)
    {
        $this->ensureLoaded();
        
        $dir = $this->cacheDir;
        if (!$dir)
            $dir = rtrim(sys_get_temp_dir(), '/\\') . '/';
        $temp = $dir . '__haml_string_tpl__.haml';
        $out = $dir . '__haml_string_tpl__.php';
        
        @file_put_contents($temp, $content);
        
        $phpMarkup = $this->haml->parse($temp);
        file_put_contents($out, $phpMarkup);
        
        // Declare all top-level data as local-scope variables before including the HAML PHP.
        $_PIECRUST_APP = $this->pieCrust;
        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                $$key = new ArrayWrapper($value);
            }
            else
            {
                $$key = $value;
            }
        }
        require $out;
    }
    
    public function renderFile($templateName, $data)
    {
        $this->ensureLoaded();
        
        $templatePath = PieCrust::getTemplatePath($this->pieCrust, $templateName);
        $outputPath = $this->haml->parse($templatePath, $this->cacheDir);
        if ($outputPath === false) throw new PieCrustException("An error occured processing template: " . $templateName);
        
        // Declare all top-level data as local-scope variables before including the HAML PHP.
        $_PIECRUST_APP = $this->pieCrust;
        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                $$key = new ArrayWrapper($value);
            }
            else
            {
                $$key = $value;
            }
        }
        require $outputPath;
    }
    
    public function clearInternalCache()
    {
    }
    
    protected function ensureLoaded()
    {
        if ($this->haml === null)
        {
            $this->cacheDir = false;
            if ($this->pieCrust->isCachingEnabled())
            {
                $this->cacheDir = $this->pieCrust->getCacheDir() . 'templates_c';
            }
            
            $appConfig = $this->pieCrust->getConfig();
            if (isset($appConfig['haml'])) $hamlOptions = $appConfig['haml'];
            else $hamlOptions = array('ugly' => false, 'style' => 'nested');
            $hamlOptions = array_merge(
                                       array('filterDir' => PieCrust::APP_DIR . '/Plugins/Haml'),
                                       $hamlOptions
                                       );
            require_once 'PhamlP/haml/HamlParser.php';
            $this->haml = new \HamlParser($hamlOptions);
        }
    }
}
