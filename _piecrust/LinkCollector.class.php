<?php

class LinkCollector
{
    protected static $instance;
    
    public static function instance()
    {
        return self::$instance;
    }
    
    public static function isEnabled()
    {
        return self::$instance != null;
    }
    
    public static function enable()
    {
        if (self::$instance != null)
            throw new PieCrustException("The LinkCollector is already enabled.");
        
        self::$instance = new LinkCollector();
    }
    
    public static function disable()
    {
        if (self::$instance == null)
            throw new PieCrustException("The LinkCollector has not been enabled.");
        
        self::$instance = null;
    }
    
    protected $tagCombinations;
    
    public function getTagCombinations()
    {
        return array_values($this->tagCombinations);
    }
    
    public function __construct()
    {
        $this->tagCombinations = array();
    }
    
    public function registerTagCombination($tags)
    {
        if (is_array($tags))
        {
            $combinationKey = implode('/', $tags);
        }
        else
        {
            $combinationKey = $tags;
            $tags = explode('/', $tags);
        }
        if (!array_key_exists($combinationKey, $this->tagCombinations))
        {
            $this->tagCombinations[$combinationKey] = $tags;
        }
    }
}
