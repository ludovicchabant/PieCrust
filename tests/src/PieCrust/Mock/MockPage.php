<?php

namespace PieCrust\Mock;

use PieCrust\IPage;
use PieCrust\IPageObserver;
use PieCrust\IPieCrust;
use PieCrust\Page\PageConfiguration;


class MockPage implements IPage
{
    public $pieCrust;
    
    public function getApp()
    {
        return $this->pieCrust;
    }
    
    public $path;
    
    public function getPath()
    {
        return $this->path;
    }
    
    public $uri;
    
    public function getUri()
    {
        return $this->uri;
    }
    
    public $blogKey;
    
    public function getBlogKey()
    {
        return $this->blogKey;
    }
    
    public $pageNumber;
    
    public function getPageNumber()
    {
        return $this->pageNumber;
    }
    
    public function setPageNumber($pageNumber)
    {
        $this->pageNumber = $pageNumber;
    }
    
    public $pageKey;
    
    public function getPageKey()
    {
        return $this->pageKey;
    }

    public function setPageKey($key)
    {
        $this->pageKey = $key;
    }
    
    public $date;
    
    public function getDate($withTime = false)
    {
        return $this->date;
    }
    
    public function setDate($date)
    {
        $this->date = $date;
    }
    
    public $pageType;
    
    public function getPageType()
    {
        return $this->pageType;
    }
    
    public $wasCached;
    
    public function wasCached()
    {
        return $this->wasCached;
    }
    
    public $config;
    
    public function getConfig()
    {
        return $this->config;
    }
    
    public $contents;
    
    public function getContentSegment($segment = 'content')
    {
        return $this->contents[$segment];
    }
    
    public function hasContentSegment($segment)
    {
        return isset($this->contents[$segment]);
    }
    
    public function getContentSegments()
    {
        return $this->contents;
    }
    
    public $data;

    public function getPageData()
    {
        return $this->data;
    }
    
    public $extraData;
    
    public function getExtraPageData()
    {
        return $this->extraData;
    }
    
    public function setExtraPageData(array $data)
    {
        $this->extraData = $data;
    }
    
    public $assetUrlBaseRemap;
    
    public function getAssetUrlBaseRemap()
    {
        return $this->assetUrlBaseRemap;
    }
    
    public function setAssetUrlBaseRemap($remap)
    {
        $this->assetUrlBaseRemap = $remap;
    }
    
    public $paginationDataSource;

    public function getPaginationDataSource()
    {
        return $this->paginationDataSource;
    }

    public function setPaginationDataSource($postInfos)
    {
        $this->paginationDataSource = $postInfos;
    }

    public function unload()
    {
    }

    public function isLoaded()
    {
        return true;
    }

    public function addObserver(IPageObserver $observer)
    {
    }

    public function __construct(IPieCrust $pieCrust = null)
    {
        if (!$pieCrust)
            $pieCrust = new MockPieCrust();
        $this->pieCrust = $pieCrust;
        $this->config = new PageConfiguration($this, array());
        $this->pageNumber = 1;
    }
}
