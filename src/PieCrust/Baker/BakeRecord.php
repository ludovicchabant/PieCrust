<?php

namespace PieCrust\Baker;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Environment\LinkCollector;


/**
 * A class that keeps track of posts being baked
 * by the PieCrustBaker.
 */
class BakeRecord
{
    const VERSION = 1;

    protected $bakeInfo;
    
    protected $shouldDoFullBake;
    protected $postInfos;
    protected $postTags;
    protected $postCategories;
    protected $tagsToBake;
    protected $categoriesToBake;
    protected $wasAnyPostBaked;
    
    /**
     * Creates a new instance of the BakeRecord.
     */
    public function __construct(array $blogKeys, $lastBakeInfoPath = false)
    {
        $this->postInfos = array();
        $this->postTags = array();
        $this->postCategories = array();
        $this->tagsToBake = array();
        $this->categoriesToBake = array();
        $this->wasAnyPostBaked = false;
        
        foreach ($blogKeys as $blogKey)
        {
            $this->postTags[$blogKey] = array();
            $this->tagsToBake[$blogKey] = array();
            $this->postCategories[$blogKey] = array();
            $this->categoriesToBake[$blogKey] = array();
        }
        
        $this->loadBakeInfo($lastBakeInfoPath);
    }
    
    /**
     * Gets whether the next bake should be a full bake.
     */
    public function shouldDoFullBake()
    {
        return $this->shouldDoFullBake;
    }
    
    /**
     * Adds a post's information to the bake record.
     *
     * The information must contain the blog that the post belongs to,
     * its tags, and its category.
     */
    public function addPostInfo(array $postInfo)
    {
        $postIndex = count($this->postInfos);
        $this->postInfos[] = $postInfo;
        
        $blogKey = $postInfo['blogKey'];
        $wasBaked = (isset($postInfo['wasBaked']) and $postInfo['wasBaked']);
        
        $tags = $postInfo['tags'];
        if ($tags != null)
        {
            foreach ($tags as $tag)
            {
                if (!isset($this->postTags[$blogKey][$tag]))
                    $this->postTags[$blogKey][$tag] = array();
                $this->postTags[$blogKey][$tag][] = $postIndex;
                
                if ($wasBaked)
                    $this->tagsToBake[$blogKey][$tag] = true;
            }
        }
        
        $category = $postInfo['category'];
        if ($category != null)
        {
            if (!isset($this->postCategories[$blogKey][$category]))
                $this->postCategories[$blogKey][$category] = array();
            $this->postCategories[$blogKey][$category][] = $postIndex;
            
            if ($wasBaked)
                $this->categoriesToBake[$blogKey][$category] = true;
        }
        
        $this->wasAnyPostBaked = ($this->wasAnyPostBaked or $wasBaked);
    }

    /**
     * Specifies that a given page is listing blog posts, which means
     * it will have to be re-baked if posts have changed.
     */
    public function addPageUsingPosts($relativePath)
    {
        $this->bakeInfo['pagesUsingPosts'][$relativePath] = true;
    }
    
    /**
     * Adds all collected tag combinations to the known tag combinations.
     */
    public function collectTagCombinations(LinkCollector $collector)
    {
        if ($collector == null)
            return;
        
        $allCombinations = $collector->getAllTagCombinations();
        $collector->clearAllTagCombinations();
        
        $knownCombinations = $this->bakeInfo['knownTagCombinations'];
        foreach ($allCombinations as $blogKey => $combinations)
        {
            if (!array_key_exists($blogKey, $knownCombinations))
            {
                $knownCombinations[$blogKey] = $combinations;
            }
            else
            {
                $knownCombinations[$blogKey] = array_unique(array_merge($knownCombinations[$blogKey], $combinations));
            }
        }
        $this->bakeInfo['knownTagCombinations'] = $knownCombinations;
    }
    
    /**
     * Returns whether any posts were baked since the creation of
     * this bake record.
     */
    public function wasAnyPostBaked()
    {
        return $this->wasAnyPostBaked;
    }
    
    /**
     * Returns whether a page is known to list blog posts.
     */
    public function isPageUsingPosts($relativePath)
    {
        if (!isset($this->bakeInfo['pagesUsingPosts'][$relativePath]))
            return false;
        return ($this->bakeInfo['pagesUsingPosts'][$relativePath] === true);
    }
    
    /**
     * Returns what tags have been invalidated since the creation
     * of this bake record.
     */
    public function getTagsToBake($blogKey)
    {
        return array_keys($this->tagsToBake[$blogKey]);
    }
    
    /**
     * Returns the list of posts known to be tagged with the given tag(s).
     */
    public function getPostsTagged($blogKey, $tag)
    {
        $postInfos = array();
        if (is_array($tag))
        {
            $num = count($tag);
            if ($num == 0)
            {
                $postIndices = array();
            }
            else
            {
                $postIndices = $this->postTags[$blogKey][$tag[0]];
                for ($i = 1; $i < $num; ++$i)
                {
                    $postIndices = array_intersect($postIndices, $this->postTags[$blogKey][$tag[$i]]);
                }
            }
        }
        else
        {
            $postIndices = $this->postTags[$blogKey][$tag];
        }
        
        foreach ($postIndices as $i)
        {
            $postInfos[] = $this->postInfos[$i];
        }
        return $postInfos;
    }
    
    /**
     * Gets the list of categories invalidated since the creation
     * of this bake record.
     */
    public function getCategoriesToBake($blogKey)
    {
        return array_keys($this->categoriesToBake[$blogKey]);
    }
    
    /**
     * Returns the list of posts known to be in the given category.
     */
    public function getPostsInCategory($blogKey, $category)
    {
        $postInfos = array();
        $postIndices = $this->postCategories[$blogKey][$category];
        foreach ($postIndices as $i)
        {
            $postInfos[] = $this->postInfos[$i];
        }
        return $postInfos;
    }
    
    /**
     * Get an information from the last bake.
     */
    public function getLast($what)
    {
        return $this->bakeInfo[$what];
    }

    /**
     * Saves the current bake record to disk.
     */
    public function saveBakeInfo($bakeInfoPath)
    {
        $this->bakeInfo['version'] = PieCrustDefaults::VERSION;
        $this->bakeInfo['record_version'] = self::VERSION;
        $this->bakeInfo['time'] = time();
        $jsonMarkup = json_encode($this->bakeInfo);
        file_put_contents($bakeInfoPath, $jsonMarkup);
    }
    
    protected function loadBakeInfo($bakeInfoPath)
    {
        $bakeInfo = array(
            'version' => false,
            'record_version' => 0,
            'time' => false,
            'pagesUsingPosts' => array(),
            'knownTagCombinations' => array()
        );
    
        if (is_string($bakeInfoPath) and is_file($bakeInfoPath))
        {
            $loadedBakeInfo = json_decode(file_get_contents($bakeInfoPath), true);
            $bakeInfo = array_merge($bakeInfo, $loadedBakeInfo);
        }
        else if (is_array($bakeInfoPath))
        {
            $bakeInfo = array_merge($bakeInfo, $bakeInfoPath);
        }
        
        $this->bakeInfo = $bakeInfo;
        $this->shouldDoFullBake = (
            !$bakeInfo['time'] or
            $bakeInfo['version'] != PieCrustDefaults::VERSION or
            $bakeInfo['record_version'] != self::VERSION
        );
    }
}

