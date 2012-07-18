<?php

namespace PieCrust\Environment;

use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * A singleton class that collects all declared links
 * while the application is running.
 */
class LinkCollector
{
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
            $blogKey = PieCrustDefaults::DEFAULT_BLOG_KEY;
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
