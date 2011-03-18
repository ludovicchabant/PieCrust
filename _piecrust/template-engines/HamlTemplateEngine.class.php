<?php

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
    protected $templateDirs;
    protected $haml;
    
    public function initialize(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getExtension()
    {
        return 'haml';
    }
    
    public function addTemplatesPaths($paths)
    {
        if (is_array($paths))
            $this->templateDirs = array_combine($this->templateDirs, rtrim($paths, DIRECTORY_SEPARATOR));
        else
            $this->templateDirs[] = rtrim($paths, DIRECTORY_SEPARATOR);
    }
    
    public function renderString($content, $data)
    {
        $this->ensureLoaded();
        
        $temp = $this->cacheDir . DIRECTORY_SEPARATOR . '__string_tpl__.haml';
        $out = $this->cacheDir . DIRECTORY_SEPARATOR . '__string_tpl__.php';
        
        @file_put_contents($temp, $content);
        
        $phpMarkup = $this->haml->parse($temp);
        file_put_contents($out, $phpMarkup);
        
        // Declare all top-level data as local-scope variables before including the HAML PHP.
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
        
        $templatePath = null;
        foreach ($this->templateDirs as $dir)
        {
            $path = $dir . DIRECTORY_SEPARATOR . $templateName;
            if (is_file($path))
            {
                $templatePath = $path;
                break;
            }
        }
        if ($templatePath === null) throw new PieCrustException("Can't find any template named: " . $templateName);
        
        $outputPath = $this->haml->parse($templatePath, $this->cacheDir);
        if ($outputPath === false) throw new PieCrustException("An error occured processing template: " . $templateName);
        
        // Declare all top-level data as local-scope variables before including the HAML PHP.
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
            $this->templateDirs = array(rtrim($this->pieCrust->getTemplatesDir(), DIRECTORY_SEPARATOR));
            
            $this->cacheDir = false;
            if ($this->pieCrust->isCachingEnabled())
            {
                $this->cacheDir = $this->pieCrust->getCacheDir() . 'templates_c';
            }
            
            $appConfig = $this->pieCrust->getConfig();
            if (isset($appConfig['haml'])) $hamlOptions = $appConfig['haml'];
            else $hamlOptions = array();
            require_once 'libs/phamlp/haml/HamlParser.php';
            $this->haml = new HamlParser($hamlOptions);
        }
    }
}
