<?php

namespace PieCrust\Environment;

use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\UriBuilder;


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
        if (!is_array($tags))
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
        
        $tags = array_map(
            function ($t) { return UriBuilder::slugify($t); },
            $tags
        );
        $tagCombination = implode('/', $tags);
        if (!in_array($tagCombination, $this->tagCombinations[$blogKey]))
        {
            $this->tagCombinations[$blogKey][] = $tagCombination;
        }
    }
}
