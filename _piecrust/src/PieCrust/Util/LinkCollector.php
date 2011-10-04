<?php

namespace PieCrust\Util;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * A singleton class that collects all declared links
 * while the application is running.
 */
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
    
    public function getTagCombinations($blogKey)
    {
        if (!isset($this->tagCombinations[$blogKey]))
            return null;
        return array_values($this->tagCombinations[$blogKey]);
    }
    
    public function getAllTagCombinations()
    {
        return $this->tagCombinations;
    }
    
    public function clearTagCombinations($blogKey)
    {
        $this->tagCombinations[$blogKey] = array();
    }
    
    public function clearAllTagCombinations()
    {
        $this->tagCombinations = array();
    }
    
    public function __construct()
    {
        $this->tagCombinations = array();
    }
    
    public function registerTagCombination($blogKey, $tags)
    {
        if (strpos($tags, '/') === false)
        {
            return;
        }
        if ($blogKey == null)
        {
            $blogKey = PieCrust::DEFAULT_BLOG_KEY;
        }
        if (!array_key_exists($blogKey, $this->tagCombinations))
        {
            $this->tagCombinations[$blogKey] = array();
        }
        
        $tags = strtolower($tags);
        if (!in_array($tags, $this->tagCombinations[$blogKey]))
        {
            $this->tagCombinations[$blogKey][] = $tags;
        }
    }
}
