<?php

namespace PieCrust\Environment;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\UriBuilder;
use PieCrust\Util\PieCrustHelper;


/**
 * A singleton class that collects all declared links
 * while the application is running.
 */
class LinkCollector
{
    protected $pieCrust;
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
    
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->tagCombinations = array();
    }
    
    public function registerTagCombination($blogKey, $tags)
    {
        if (!is_array($tags))
        {
            // Temporary warning for a change in how multi-tags
            // are specified.
            $log = $this->pieCrust->getEnvironment()->getLog();
            if (strpos($tags, '/') !== false)
            {
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
        
        $pieCrust = $this->pieCrust;
        $tags = array_map(
            function ($t) use ($pieCrust) { return PieCrustHelper::slugify($pieCrust, 'tags', $t); },
            $tags
        );
        $tagCombination = implode('/', $tags);
        if (!in_array($tagCombination, $this->tagCombinations[$blogKey]))
        {
            $this->tagCombinations[$blogKey][] = $tagCombination;
        }
    }
}
