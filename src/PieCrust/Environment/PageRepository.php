<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Page\Page;


/**
 * A class that stores already processed pages for recycling.
 */
class PageRepository
{
    protected $pieCrust;
    protected $enabled;
    protected $pages;
    protected $assetUrlBaseRemap;

    public function __construct(IPieCrust $pieCrust, $enabled = true)
    {
        $this->pieCrust = $pieCrust;
        $this->enabled = $enabled;
        $this->pages = array();
        $this->assetUrlBaseRemap = null;
    }
    
    public function isEnabled()
    {
        return $this->enabled;
    }
    
    public function enable($onOff = true)
    {
        $this->enabled = $onOff;
    }
    
    public function clearPages()
    {
        $this->pages = array();
    }
    
    public function addPage(IPage $page)
    {
        if (!$this->enabled)
            return;
        $this->pages[$page->getUri()] = $page;
    }

    public function getPages()
    {
        return $this->pages;
    }
    
    public function getPage($uri)
    {
        if (!$this->enabled)
            return null;
        if (!isset($this->pages[$uri]))
            return null;
        return $this->pages[$uri];
    }
    
    public function getOrCreatePage($uri, $path, $pageType = IPage::TYPE_REGULAR, $blogKey = null)
    {
        $page = $this->getPage($uri);
        if ($page == null)
        {
            $page = new Page($this->pieCrust, $uri, $path, $pageType, $blogKey);
            if ($this->assetUrlBaseRemap != null)
                $page->setAssetUrlBaseRemap($this->assetUrlBaseRemap);
            $this->addPage($page);
        }
        else
        {
            $page->setPageNumber(1);
        }
        return $page;
    }

    public function setAssetUrlBaseRemap($remap)
    {
        $this->assetUrlBaseRemap = $remap;
    }
}
