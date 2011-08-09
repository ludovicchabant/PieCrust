<?php

/**
 *
 */
class BakeRecord
{
    protected $lastBakeInfo;
    
    protected $postInfos;
    protected $postTags;
    protected $postCategories;
    protected $tagsToBake;
    protected $categoriesToBake;
    protected $wasAnyPostBaked;
    
    /**
     *
     */
    public function __construct(PieCrust $pieCrust, $lastBakeInfoPath)
    {
        $this->postInfos = array();
        $this->postTags = array();
        $this->postCategories = array();
        $this->tagsToBake = array();
        $this->categoriesToBake = array();
        $this->wasAnyPostBaked = false;
        
        foreach ($pieCrust->getConfigValueUnchecked('site', 'blogs') as $blogKey)
        {
            $this->postTags[$blogKey] = array();
            $this->tagsToBake[$blogKey] = array();
            $this->postCategories[$blogKey] = array();
            $this->categoriesToBake[$blogKey] = array();
        }
        
        $this->loadLastBakeInfo($lastBakeInfoPath);
    }
    
    /**
     *
     */
    public function addPostInfo(array $postInfo, $wasBaked)
    {
        $postIndex = count($this->postInfos);
        $this->postInfos[] = $postInfo;
        
        $blogKey = $postInfo['blogKey'];
        
        $tags = $postInfo['tags'];
        if ($tags != null)
        {
            foreach ($tags as $tag)
            {
                if (!isset($this->postTags[$blogKey][$tag])) $this->postTags[$blogKey][$tag] = array();
                $this->postTags[$blogKey][$tag][] = $postIndex;
                
                if ($wasBaked) $this->tagsToBake[$blogKey][$tag] = true;
            }
        }
        
        $category = $postInfo['category'];
        if ($category != null)
        {
            if (!isset($this->postCategories[$blogKey][$category])) $this->postCategories[$blogKey][$category] = array();
            $this->postCategories[$blogKey][$category][] = $postIndex;
            
            if ($wasBaked) $this->categoriesToBake[$blogKey][$category] = true;
        }
        
        $this->wasAnyPostBaked = ($this->wasAnyPostBaked or $wasBaked);
    }

    /**
     *
     */
    public function addPageUsingPosts($relativePath)
    {
        $this->lastBakeInfo['pagesUsingPosts'][$relativePath] = true;
    }
    
    /**
     *
     */
    public function wasAnyPostBaked()
    {
        return $this->wasAnyPostBaked;
    }
    
    /**
     *
     */
    public function isPageUsingPosts($relativePath)
    {
        if (!isset($this->lastBakeInfo['pagesUsingPosts'][$relativePath])) return false;
        return ($this->lastBakeInfo['pagesUsingPosts'][$relativePath] === true);
    }
    
    /**
     *
     */
    public function getTagsToBake($blogKey)
    {
        return array_keys($this->tagsToBake[$blogKey]);
    }
    
    /**
     *
     */
    public function getPostsTagged($blogKey, $tag)
    {
        $postInfos = array();
        $postIndices = $this->postTags[$blogKey][$tag];
        foreach ($postIndices as $i)
        {
            $postInfos[] = $this->postInfos[$i];
        }
        return $postInfos;
    }
    
    /**
     *
     */
    public function getCategoriesToBake($blogKey)
    {
        return array_keys($this->categoriesToBake[$blogKey]);
    }
    
    /**
     *
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
     *
     */
    public function getLastBakeTime()
    {
        return $this->lastBakeInfo['time'];
    }
    
    /**
     *
     */
    public function getLast($what)
    {
        return $this->lastBakeInfo[$what];
    }

    /**
     *
     */
    public function saveBakeInfo($bakeInfoPath, array $infos = array())
    {
        $infos = array_merge(
            array('time' => time(), 'url_base' => '/'),
            $infos
        );
        
        $this->lastBakeInfo['time'] = $infos['time'];
        $this->lastBakeInfo['url_base'] = $infos['url_base'];
        
        $jsonMarkup = json_encode($this->lastBakeInfo);
        file_put_contents($bakeInfoPath, $jsonMarkup);
    }
    
    protected function loadLastBakeInfo($bakeInfoPath)
    {
        $bakeInfo = array(
            'time' => false,
            'url_base' => '/',
            'pagesUsingPosts' => array()
        );
    
        if (is_file($bakeInfoPath))
        {
            $loadedBakeInfo = json_decode(file_get_contents($bakeInfoPath), true);
            $bakeInfo = array_merge($bakeInfo, $loadedBakeInfo);
        }
        $this->lastBakeInfo = $bakeInfo;
    }
}

