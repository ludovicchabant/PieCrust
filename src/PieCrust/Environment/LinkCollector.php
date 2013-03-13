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
            // Temporary warning for a change in how multi-tags
            // are specified.
            if (isset($GLOBALS['__CHEF_LOG']) && strpos($tags, '/') !== false)
            {
                $log = $GLOBALS['__CHEF_LOG'];
                $log->warning(
                    "A link to tag {$tags} was specified in this page. ".
                    "If this is a tag that contains a slash character ('/') then ignore this warning. ".
                    "However, if this was intended to be a multi-tags link, you'll need to ".
                    "now pass an array of tags like so: `{{pctagurl(['tag1', 'tag2'])}}`. ".
                    "Your current link won't work!");
            }
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
