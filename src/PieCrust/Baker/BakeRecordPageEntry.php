<?php

namespace PieCrust\Baker;

use PieCrust\IPage;
use PieCrust\Util\JsonSerializable;
use PieCrust\Util\JsonSerializer;


class BakeRecordPageEntry implements JsonSerializable
{
    public $path;
    public $pageType;
    public $blogKey;
    public $pageKey;
    public $taxonomy;
    public $usedTaxonomyCombinations;
    public $usedPages;
    public $usedPosts;
    public $outputs;

    public function initialize(IPage $page, $baker)
    {
        $this->path = $page->getPath();
        $this->pageType = $page->getPageType();
        $this->blogKey = $page->getBlogKey();
        $this->pageKey = $page->getPageKey();

        $this->taxonomy = array();
        $this->usedTaxonomyCombinations = array();
        $this->usedPages = false;
        $this->usedPosts = array();
        $this->outputs = array();

        if ($baker)
        {
            $tags = $page->getConfig()->getValue('tags');
            if ($tags)
                $this->taxonomy['tags'] = $tags;

            $category = $page->getConfig()->getValue('category');
            if ($category)
                $this->taxonomy['category'] = $category;

            $collector = $page->getApp()->getEnvironment()->getLinkCollector();
            if ($collector)
            {
                $tagCombinations = $collector->getAllTagCombinations();
                if ($tagCombinations)
                    $this->usedTaxonomyCombinations['tags'] = $tagCombinations;
                $collector->clearAllTagCombinations();
            }

            // TODO: remember posts used by blog.
            $this->usedPosts = $baker->wasPaginationDataAccessed();

            $this->outputs = $baker->getBakedFiles();
        }
    }

    public function wasBaked()
    {
        return (bool)$this->outputs;
    }

    public function getTerms($taxonomy)
    {
        if (isset($this->taxonomy[$taxonomy]))
            return $this->taxonomy[$taxonomy];
        return null;
    }

    public function jsonSerialize()
    {
        return array(
            'path' => $this->path,
            'pageType' => $this->pageType,
            'blogKey' => $this->blogKey,
            'pageKey' => $this->pageKey,
            'taxonomy' => $this->taxonomy,
            'usedTaxonomyCombinations' => $this->usedTaxonomyCombinations,
            'usedPages' => $this->usedPages,
            'usedPosts' => $this->usedPosts,
            'outputs' => $this->outputs
        );
    }

    public function jsonDeserialize($data)
    {
        $this->path = $data['path'];
        $this->pageType = $data['pageType'];
        $this->blogKey = $data['blogKey'];
        $this->pageKey = $data['pageKey'];
        $this->taxonomy = $data['taxonomy'];
        $this->usedTaxonomyCombinations = $data['usedTaxonomyCombinations'];
        $this->usedPages = $data['usedPages'];
        $this->usedPosts = $data['usedPosts'];
        $this->outputs = $data['outputs'];
    }
}

